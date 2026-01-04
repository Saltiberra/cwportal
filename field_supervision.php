<?php
// 🔒 Require login
require_once 'includes/auth.php';
requireLogin();
// Database & Audit helpers
require_once 'config/database.php';
require_once 'includes/audit.php';

// Get logged-in user data
$loggedInUser = getCurrentUser();
$userRole = $loggedInUser['role'] ?? 'operador';
$userId = $loggedInUser['id'] ?? 0;

include 'includes/header.php';
?>
<!-- Global Loading Overlay for Field Supervision -->
<div id="global-loading-overlay" class="show">
    <div class="loading-box">
        <div class="loading-spinner" aria-hidden="true"></div>
        <div class="loading-title">A carregar Field Supervision…</div>
        <div class="loading-subtitle">Preparing data... Please wait.</div>
    </div>
</div>
<script>
    // Field Supervision loading overlay controller
    (function() {
        if (typeof window === 'undefined' || typeof document === 'undefined') return;
        var overlay = document.getElementById('global-loading-overlay');
        if (!overlay) return;
        var ready = {
            projects: false,
            projectDetails: false
        };
        var hideScheduled = false;

        function tryHideOverlay(reason) {
            if (!overlay) return;
            if (ready.projects && ready.projectDetails) {
                if (hideScheduled) return;
                hideScheduled = true;
                setTimeout(function() {
                    overlay.classList.add('overlay-hidden');
                    setTimeout(function() {
                        try {
                            overlay.style.display = 'none';
                        } catch (_) {}
                    }, 260);
                }, 150);
            }
        }

        function showOverlay() {
            if (!overlay) return;
            hideScheduled = false;
            try {
                overlay.style.display = 'flex';
            } catch (e) {}
            overlay.classList.remove('overlay-hidden');
            overlay.classList.add('show');
        }
        // Fallback hide after 6 seconds
        setTimeout(function() {
            if (!overlay) return;
            if (hideScheduled) return;
            hideScheduled = true;
            overlay.classList.add('overlay-hidden');
            setTimeout(function() {
                try {
                    overlay.style.display = 'none';
                } catch (_) {}
            }, 260);
        }, 6000);

        document.addEventListener('fsProjectsReady', function() {
            ready.projects = true;
            tryHideOverlay('projectsReady');
        });
        document.addEventListener('fsProjectDetailsReady', function() {
            ready.projectDetails = true;
            tryHideOverlay('projectDetailsReady');
        });
        document.addEventListener('fsResetOverlayFlags', function() {
            // show again and reset details flag so overlay remains visible until details done
            ready.projectDetails = false;
            ready.projects = true; // projects remain loaded
            tryHideOverlay('resetFlags');
        });
        // Support explicit show request
        document.addEventListener('fsShowOverlay', function() {
            showOverlay();
        });
        // Expose helper for debugging
        window.fsOverlay = {
            show: showOverlay,
            hide: function() {
                ready.projects = true;
                ready.projectDetails = true;
                tryHideOverlay('manual-hide');
            }
        };
    })();
</script>
<script>
    window.CWUserRole = '<?php echo htmlspecialchars($userRole ?? ''); ?>';
    window.CWUserId = <?php echo intval($userId); ?>;
    console.log('field_supervision.js loaded - CWUserRole=' + window.CWUserRole + ' CWUserId=' + window.CWUserId);

    // Helper function to get AJAX URL
    window.getAjaxUrl = function(url) {
        // If URL already starts with 'ajax/', return as is
        if (url && url.indexOf('ajax/') === 0) {
            return url;
        }
        // Otherwise, return the URL as provided
        return url;
    };
</script>
<!-- PDF generation libraries - used for exporting timeline -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    // Helper to produce an abbreviated project name
    function fsAbbrevProjectName(name) {
        if (!name) return 'PRJ';
        // remove accents
        try {
            name = name.normalize('NFD').replace(/\p{Diacritic}/gu, '');
        } catch (e) {}
        name = name.replace(/[^A-Za-z0-9 ]+/g, ''); // remove punctuation
        // collapse and use initials if multi-word
        const parts = name.trim().split(/\s+/).filter(Boolean);
        if (parts.length === 1) return parts[0].slice(0, 20);
        const initials = parts.slice(0, 4).map(p => p[0]).join('');
        const short = (initials.length >= 2) ? initials : name.replace(/\s+/g, '').slice(0, 20);
        return short.toUpperCase();
    }

    async function fsDownloadProjectTimelinePdf(projectId, projectTitle) {
        console.debug('fsDownloadProjectTimelinePdf called', projectId, projectTitle);
        if (!projectId) return fsAlert('ID do projecto é obrigatório');
        const timelineEl = document.getElementById('fsTimelineList_' + projectId);
        if (!timelineEl) return fsAlertError('Timeline não carregada');
        const wrapper = document.createElement('div');
        wrapper.style.background = '#FFFFFF';
        wrapper.style.color = '#000';
        wrapper.style.fontFamily = 'Inter, Arial, sans-serif';
        wrapper.style.padding = '20px';
        wrapper.style.width = '900px';

        const title = document.createElement('div');
        title.style.marginBottom = '8px';
        title.innerHTML = `<div style='font-size:20px;font-weight:600;'>Timeline - ${projectTitle}</div><div style='color:#666;font-size:12px;'>Generated: ${new Date().toISOString().split('T')[0]}</div>`;
        wrapper.appendChild(title);

        // Clone timeline content to avoid mutating page
        const clone = timelineEl.cloneNode(true);
        // Remove interactive elements if any
        clone.querySelectorAll('button, .btn, .dropdown, a[data-action]').forEach(el => el.remove());
        // Ensure consistent layout for PDF
        clone.style.width = '100%';
        clone.style.boxSizing = 'border-box';
        wrapper.appendChild(clone);

        // Attach to DOM off-screen
        wrapper.style.position = 'fixed';
        wrapper.style.left = '-10000px';
        wrapper.style.top = '0px';
        document.body.appendChild(wrapper);

        try {
            // Wait for fonts (if any)
            if (document.fonts && document.fonts.ready) await document.fonts.ready;
        } catch (e) {}

        try {
            // Collect anchors before rendering so we can add clickable links to PDF
            // Compute offsets relative to the wrapper (already appended to DOM)
            const wrapperRect = wrapper.getBoundingClientRect();
            const anchors = Array.from(clone.querySelectorAll('a[href]:not([data-action])'))
                .map(a => {
                    const rect = a.getBoundingClientRect();
                    return {
                        href: a.href,
                        rect: rect,
                        offsetLeft: rect.left - wrapperRect.left,
                        offsetTop: rect.top - wrapperRect.top
                    };
                });
            // debug: print anchor count/first anchor info when localStorage debug flag is set
            try {
                if (localStorage && localStorage.getItem && localStorage.getItem('CW_DEBUG_PDF_LINKS') === '1') {
                    console.debug('fsDownloadProjectTimelinePdf: anchors', anchors.length, anchors.slice(0, 5).map(a => ({
                        href: a.href,
                        left: Math.round(a.offsetLeft),
                        top: Math.round(a.offsetTop),
                        w: Math.round(a.rect.width),
                        h: Math.round(a.rect.height)
                    })));
                }
            } catch (e) {}
            const canvas = await html2canvas(wrapper, {
                scale: 2,
                useCORS: true,
                logging: false
            });
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });
            const canvasWidth = canvas.width;
            const canvasHeight = canvas.height;
            const pageWidthMm = pdf.internal.pageSize.getWidth();
            const pageHeightMm = pdf.internal.pageSize.getHeight();
            const marginMm = 12;
            const availableWidthMm = pageWidthMm - marginMm * 2;
            const imgWidthMm = availableWidthMm;
            const imgHeightMm = (canvasHeight * imgWidthMm) / canvasWidth;
            const mmPerPx = imgWidthMm / canvasWidth;
            // If height fits in a single page
            if (imgHeightMm <= (pageHeightMm - marginMm * 2)) {
                const imgData = canvas.toDataURL('image/jpeg', 0.95);
                pdf.addImage(imgData, 'JPEG', marginMm, marginMm, imgWidthMm, imgHeightMm);
                // Add link annotations for anchors
                try {
                    // canvas.width is in device px; wrapperRect.width is CSS px
                    const cssWidth = wrapperRect.width || parseFloat(canvas.style.width || canvas.width);
                    const scale = canvas.width / cssWidth; // device px per CSS px
                    const mmPerCssPx = imgWidthMm / cssWidth; // mm per CSS px
                    anchors.forEach(a => {
                        const xPx = a.offsetLeft; // CSS px
                        const yPx = a.offsetTop; // CSS px
                        const wPx = a.rect.width; // CSS px
                        const hPx = a.rect.height; // CSS px
                        const xMm = xPx * mmPerCssPx;
                        const yMm = yPx * mmPerCssPx;
                        const wMm = wPx * mmPerCssPx;
                        const hMm = hPx * mmPerCssPx;
                        // Add link; convert to PDF coordinates (left+margin, top+margin)
                        try {
                            pdf.link(marginMm + xMm, marginMm + yMm, wMm, hMm, {
                                url: a.href
                            });
                        } catch (e) {
                            console.warn('Could not add PDF link', e);
                        }
                    });
                } catch (e) {
                    console.warn('Could not add anchor links to PDF', e);
                }
            } else {
                // slice vertically
                const a4CssHeight = Math.round((pageHeightMm - marginMm * 2) * (96 / 25.4));
                const cssWidth = wrapperRect.width || parseFloat(canvas.style.width || canvasWidth);
                const scale = canvas.width / cssWidth;
                let sliceStart = 0;
                const a4CanvasHeight = Math.round((pageHeightMm - marginMm * 2) * (canvasWidth / imgWidthMm));
                while (sliceStart < canvasHeight) {
                    const sliceHeight = Math.min(a4CanvasHeight, canvasHeight - sliceStart);
                    const sliceCanvas = document.createElement('canvas');
                    sliceCanvas.width = canvasWidth;
                    sliceCanvas.height = sliceHeight;
                    const sctx = sliceCanvas.getContext('2d');
                    sctx.drawImage(canvas, 0, sliceStart, canvasWidth, sliceHeight, 0, 0, canvasWidth, sliceHeight);
                    const imgDataSlice = sliceCanvas.toDataURL('image/jpeg', 0.95);
                    const imageHeightMm = Math.round(sliceHeight * mmPerPx);
                    if (sliceStart > 0) pdf.addPage();
                    pdf.addImage(imgDataSlice, 'JPEG', marginMm, marginMm, imgWidthMm, imageHeightMm);
                    // Add link annotations for anchors that fall into this slice
                    try {
                        anchors.forEach(a => {
                            const anchorTopPx = a.offsetTop; // CSS px
                            const anchorLeftPx = a.offsetLeft; // CSS px
                            const anchorRightPx = anchorLeftPx + a.rect.width;
                            const anchorBottomPx = anchorTopPx + a.rect.height;
                            // Convert CSS px -> mm using mmPerCssPx
                            const mmPerCssPx = imgWidthMm / cssWidth;
                            const anchorTopMm = anchorTopPx * mmPerCssPx;
                            const anchorLeftMm = anchorLeftPx * mmPerCssPx;
                            const anchorWidthMm = a.rect.width * mmPerCssPx;
                            const anchorHeightMm = a.rect.height * mmPerCssPx;
                            // Determine if the anchor intersects this slice
                            // sliceStart is device px; convert to CSS px before mm
                            const sliceStartCssPx = sliceStart / scale; // CSS px
                            const sliceStartMm = sliceStartCssPx * mmPerCssPx;
                            const sliceEndMm = sliceStartMm + imageHeightMm;
                            // If overlap:
                            if (anchorBottomMm > sliceStartMm && anchorTopMm < sliceEndMm) {
                                const yOnSliceMm = Math.max(0, anchorTopMm - sliceStartMm) + marginMm;
                                const heightOnSliceMm = Math.min(anchorBottomMm, sliceEndMm) - Math.max(anchorTopMm, sliceStartMm);
                                try {
                                    pdf.link(marginMm + anchorLeftMm, yOnSliceMm, anchorWidthMm, heightOnSliceMm, {
                                        url: a.href
                                    });
                                } catch (e) {
                                    /* ignore */
                                }
                            }
                        });
                    } catch (e) {
                        console.warn('Could not add anchor links to PDF slice', e);
                    }
                    sliceStart += sliceHeight;
                }
            }
            // Filename: TML_[abbrev]_[YYYY-MM-DD].pdf
            const dateStr = new Date().toISOString().split('T')[0];
            const abbrev = fsAbbrevProjectName(projectTitle);
            const filename = `TML_${abbrev}_${dateStr}.pdf`;
            pdf.save(filename);
        } catch (err) {
            console.error('timeline PDF error', err);
            fsAlertError('Error creating PDF: ' + (err && err.message ? err.message : 'unknown'));
        } finally {
            // cleanup
            try {
                document.body.removeChild(wrapper);
            } catch (e) {}
        }
    }
</script>
<div class="container py-5 main-content-container">
    <a href="index.php" class="btn btn-link text-decoration-none me-2"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-4">
        <div class="d-flex align-items-center mb-2">
            <div style="font-size:1.8rem; color:#2CCCD3;" class="me-2"><i class="fas fa-hard-hat"></i></div>
            <div>
                <h3 class="fw-bold mb-0">Field Supervision</h3>
                <small class="text-muted">Site visits • Inspections • Technical & safety audits</small>
            </div>
            <div class="ms-auto">
                <!-- New Entry button removed per user request -->
            </div>
        </div>
        <div class="alert alert-info mb-0">
            <strong>Under Construction</strong> — This is a functional initial version (list and create). Detailed features (notes, attachments, tasks) will be added.
        </div>
    </div>
</div>

<!-- Global Problems table: moved to top for quicker access -->
<div class="table-responsive mt-3 mb-3">
    <div class="d-flex align-items-center mb-2">
        <h5 class="mb-0">Problems (All Projects)</h5>
        <div class="ms-auto small text-muted d-flex align-items-center">
            <small class="text-muted">Click a row to view details</small>
            <button class="btn btn-sm btn-outline-secondary ms-3" id="fsRefreshBtnTop" title="Refresh" aria-label="Refresh"><i class="fas fa-sync"></i></button>
        </div>
    </div>
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Título</th>
                <th>Tipo</th>
                <th>Projeto</th>
                <th>Estado</th>
                <th>Severidade</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody id="fsVisitsTbody">
            <tr>
                <td colspan="7" class="text-center text-muted py-4">Loading...</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 d-flex align-items-center">
                <h5 class="fw-bold mb-0 me-2">Projects</h5>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-primary" id="fsNewProjectBtn"><i class="fas fa-plus"></i> New Project</button>
                </div>
            </div>
            <div class="list-group list-group-flush" id="fsProjectsList" style="max-height:70vh; overflow:auto;">
                <div class="list-group-item text-center text-muted py-4">Loading projects...</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body" id="fsProjectDetailArea">
                <div class="text-center text-muted py-4">Select a project to view details</div>
            </div>
        </div>
    </div>
</div>
<!-- Filter bar removed per user request -->
<!-- Note: Global problems table already displayed above; duplicate removed. -->

<?php
try {
    $activityLogs = getAuditLog(['days' => 7], 20);
} catch (Exception $e) {
    $activityLogs = [];
}
include 'includes/activity_timeline.php';
?>
</div>
</div>
</div>
<!-- Create/Edit Modal -->
<div class="modal fade" id="fsVisitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fsVisitModalTitle">New Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fsVisitForm">
                <div class="modal-body">
                    <input type="hidden" id="fsVisitId" />
                    <input type="hidden" id="fsVisitProjectId" />
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select id="fsType" class="form-select" required>
                            <option value="visit">Visit</option>
                            <option value="inspection">Inspection</option>
                            <option value="technical_audit">Technical Audit</option>
                            <option value="safety_audit">Safety Audit</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input id="fsTitle" class="form-control" required placeholder="Enter visit title (e.g., Module inspection)" />
                    </div>
                    <div class="mb-3 d-none" id="fsVisitProjectRow">
                        <label class="form-label">Project</label>
                        <div id="fsVisitProjectDisplay" class="form-control-plaintext small text-muted"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date & Time</label>
                        <input id="fsStart" type="datetime-local" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity (optional)</label>
                        <select id="fsSeverity" class="form-select">
                            <option value="">— none —</option>
                            <option value="info">Info</option>
                            <option value="minor">Minor</option>
                            <option value="major">Major</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Report</label>
                        <textarea id="fsDesc" class="form-control" rows="4" placeholder="Detailed report of the visit, inspection or audit..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachments</label>
                        <div id="fsVisitAttachmentsList" class="mb-2"></div>
                        <input id="fsVisitFile" type="file" class="form-control" />
                        <div class="form-text">Upload files relevant to the visit (images, reports). Files will be stored on server.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- New Project Modal -->
<div class="modal fade" id="fsProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fsProjectModalTitle">New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>



            </div>
            <form id="fsProjectForm">
                <div class="modal-body">
                    <input type="hidden" id="fsProjectId" />
                    <div class="mb-3">
                        <label class="form-label">Project Title</label>
                        <input id="fsProjectTitle" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input id="fsProjectStart" type="date" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input id="fsProjectEnd" type="date" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phase (optional)</label>
                        <input id="fsProjectPhase" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="fsProjectDesc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Project Plan (URL)</label>
                        <input id="fsProjectPlan" class="form-control" placeholder="https://..." />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PV Solution (URL)</label>
                        <input id="fsProjectPvSolution" class="form-control" placeholder="https://..." />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Single Line Diagram (SLD) (URL)</label>
                        <input id="fsProjectSld" class="form-control" placeholder="https://..." />
                    </div>
                    <div id="fsProjectColumnsNote" class="mb-2 text-muted small"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Timeline is populated via Problems and Visits; Add Timeline modal removed -->

<!-- Problem Details Modal -->
<div class="modal fade" id="fsProblemDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Problem Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="fsProblemDetailsBody">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Problem Modal -->
<div class="modal fade" id="fsProblemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fsProblemModalTitle">Report Problem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fsProblemForm">
                <div class="modal-body">
                    <input type="hidden" id="fsProblemProjectId" />
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input id="fsProblemTitle" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="fsProblemDesc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity</label>
                        <select id="fsProblemSeverity" class="form-select">
                            <option value="minor">Minor</option>
                            <option value="major">Major</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign to</label>
                        <select id="fsProblemResponsible" class="form-select">
                            <option value="">Unassigned</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attach Visit Modal -->
<div class="modal fade" id="fsAttachModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fsAttachModalTitle">Attach Visit to Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fsAttachForm">
                <div class="modal-body">
                    <input type="hidden" id="fsAttachVisitId" />
                    <div class="mb-3">
                        <label class="form-label">Project</label>
                        <select id="fsAttachProjectSelect" class="form-select" required>
                            <option value="">Select project</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Attach</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Add Note Modal -->
<div class="modal fade" id="fsAddNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fsAddNoteModalTitle">Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fsNoteForm">
                <div class="modal-body">
                    <input type="hidden" id="fsNoteProjectId" />
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea id="fsNoteText" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Add/Edit Contact Modal -->
<div class="modal fade" id="fsContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fsContactModalTitle"><i class="fas fa-user-plus me-2"></i>Add Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fsContactForm">
                <div class="modal-body">
                    <input type="hidden" id="fsContactId" />
                    <input type="hidden" id="fsContactProjectId" />
                    <div class="mb-3">
                        <label class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" id="fsContactName" class="form-control" required placeholder="Full name" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cargo</label>
                        <input type="text" id="fsContactRole" class="form-control" placeholder="Ex: Gestor de Projeto, Técnico, etc." />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contacto Telefónico</label>
                        <input type="tel" id="fsContactPhone" class="form-control" placeholder="Ex: +351 912 345 678" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="fsContactSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    let fsVisitModal;
    // Fallback helpers if not defined elsewhere
    if (typeof escapeHtml === 'undefined') {
        function escapeHtml(text) {
            return text ? text.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, "&#039;") : '';
        }
    }

    /**
     * Convert URLs in text to clickable links
     * @param {string} text - The text to process (already HTML escaped)
     * @returns {string} - Text with URLs converted to anchor tags
     */
    function linkifyText(text) {
        if (!text) return '';
        // URL regex pattern - matches http, https, and www URLs
        const urlPattern = /(https?:\/\/[^\s<]+|www\.[^\s<]+)/gi;
        return text.replace(urlPattern, function(url) {
            // Add protocol if missing (for www. URLs)
            const href = url.startsWith('www.') ? 'https://' + url : url;
            // Truncate display text if too long
            const displayUrl = url.length > 60 ? url.substring(0, 57) + '...' : url;
            return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="text-primary text-break">${displayUrl}</a>`;
        });
    }

    function fsChangeProjectStatus(projectId, newStatus) {
        if (!projectId || !newStatus) return;
        const fd = new FormData();
        fd.append('action', 'update_project_status');
        fd.append('id', projectId);
        fd.append('status', newStatus);
        // Optimistic UI: update badge text and class
        const btn = document.getElementById('fsProjectStatusBtn_' + projectId);
        const origText = btn?.textContent || '';
        const origClass = btn?.className || '';
        if (btn) {
            btn.textContent = newStatus;
            // apply color classes
            btn.className = 'btn btn-compact btn-outline-' + (newStatus === 'active' ? 'success' : (newStatus === 'planned' ? 'secondary' : (newStatus === 'on_hold' ? 'warning' : (newStatus === 'completed' ? 'dark' : 'danger')))) + ' dropdown-toggle';
        }
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
            method: 'POST',
            credentials: 'include',
            body: fd
        }).then(r => r.json()).then(resp => {
            if (!resp.success) {
                fsAlertError(resp.error || 'Error updating status');
                // revert UI
                if (btn) {
                    btn.textContent = origText;
                    btn.className = origClass;
                }
                return;
            }
            // success: reload project header and list
            fsLoadProjects();
            if (projectId) {
                try {
                    document.dispatchEvent(new Event('fsResetOverlayFlags'));
                } catch (e) {}
                try {
                    document.dispatchEvent(new Event('fsShowOverlay'));
                } catch (e) {}
                fsOpenProject(projectId);
            }
        }).catch(err => {
            console.error('change status err', err);
            if (btn) {
                btn.textContent = origText;
                btn.className = origClass;
            }
            fsAlertError('Error updating status');
        });
    }
    if (typeof debounce === 'undefined') {
        function debounce(fn, ms) {
            let t;
            return function() {
                const args = arguments;
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), ms || 250);
            };
        }
    }
    if (typeof isValidUrlClient === 'undefined') {
        function isValidUrlClient(value) {
            if (!value) return true;
            try {
                const _ = new URL(value);
                return true;
            } catch (e) {
                return false;
            }
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('fsVisitModal');
        if (el) fsVisitModal = new bootstrap.Modal(el);
        // Project/Problem/Timeline modals
        // Timeline modal removed; timeline populated by Problems and Visits
        const pmEl = document.getElementById('fsProblemModal');
        if (pmEl) fsProblemModal = new bootstrap.Modal(pmEl);
        const atEl = document.getElementById('fsAttachModal');
        if (atEl) fsAttachModal = new bootstrap.Modal(atEl);
        const nmEl = document.getElementById('fsAddNoteModal');
        if (nmEl) fsAddNoteModal = new bootstrap.Modal(nmEl);
        // Contact modal
        const ctEl = document.getElementById('fsContactModal');
        if (ctEl) fsContactModal = new bootstrap.Modal(ctEl);
        // Contact form submit handler
        document.getElementById('fsContactForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            fsSaveContact();
        });
        // If header set a flag to open new modal directly
        if (location.hash === '#new' || sessionStorage.getItem('fsOpenNew') === '1') {
            sessionStorage.removeItem('fsOpenNew');
            setTimeout(() => document.getElementById('fsAddBtn')?.click(), 150);
        }
        document.getElementById('fsAddBtn')?.addEventListener('click', () => {
            document.getElementById('fsVisitForm')?.reset();
            document.getElementById('fsVisitId').value = '';
            document.getElementById('fsVisitProjectId').value = '';
            const projRow = document.getElementById('fsVisitProjectRow');
            if (projRow) projRow.classList.add('d-none');
            const projDisp = document.getElementById('fsVisitProjectDisplay');
            if (projDisp) projDisp.textContent = '';
            document.getElementById('fsVisitModalTitle').textContent = 'New Entry';
            document.getElementById('fsStart').value = new Date().toISOString().slice(0, 16);
            fsVisitModal.show();
        });
        document.getElementById('fsVisitForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            fsSaveVisit();
        });
        document.getElementById('fsNoteForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            fsSubmitNoteForm();
        });
        // Refresh button (top header) for Problems table
        document.getElementById('fsRefreshBtnTop')?.addEventListener('click', () => fsLoadVisits());
        fsLoadVisits();
    });

    // Store loaded problems for details modal
    let fsLoadedProblems = {};

    function fsLoadVisits() {
        // Build problemsUrl: the global dynamic table shows problems only
        let problemsUrl = window.getAjaxUrl('ajax/manage_field_supervision.php?action=list_problems');
        fetch(problemsUrl, {
                credentials: 'include'
            }).then(r => r.json()).then(probResp => {
                const tb = document.getElementById('fsVisitsTbody');
                if (!tb) return;
                if (!probResp.success) {
                    tb.innerHTML = `<tr><td colspan=8 class='text-danger text-center py-3'>${escapeHtml(probResp.error||'Error')}</td></tr>`;
                    return;
                }
                const problems = probResp.data || [];
                // Store problems by id for later access
                fsLoadedProblems = {};
                problems.forEach(p => fsLoadedProblems[p.id] = p);
                // Normalize problems list to timeline shape
                const combined = problems.map(p => ({
                    id: p.id,
                    type: 'problem',
                    title: p.title || '',
                    project_title: p.project_title || '',
                    project_id: p.project_id || 0,
                    status: p.status || '',
                    severity: p.severity || '',
                    start: p.created_at || '',
                    end: '',
                    raw: p
                }));
                if (!combined.length) {
                    tb.innerHTML = `<tr><td colspan=8 class='text-muted text-center py-3'>No entries</td></tr>`;
                    return;
                }
                // sort by start date descending
                combined.sort((a, b) => {
                    const da = a.start ? new Date(a.start) : 0;
                    const db = b.start ? new Date(b.start) : 0;
                    return db - da;
                });
                tb.innerHTML = combined.map(v => `
                <tr class="problem-row" data-problem-id="${v.id}" style="cursor:pointer;" title="Click to view details">
                    <td><strong>${escapeHtml(v.title||'')}</strong></td>
                    <td><span class="badge bg-light text-dark">${escapeHtml(v.type)}</span></td>
                    <td><small>${escapeHtml(v.project_title||'')}</small></td>
                    <td><span class="badge bg-${v.status==='open'?'secondary':(v.status==='in_progress'?'warning':(v.status==='closed'?'success':'dark'))}">${escapeHtml(v.status)}</span></td>
                    <td>${v.severity?`<span class='badge ${fsSeverityClass(v.severity)}'>${escapeHtml(v.severity)}</span>`:'—'}</td>
                    <td><small>${escapeHtml(v.start||'')}</small></td>
                </tr>`).join('');

                // Attach click handlers for problem rows
                [...tb.querySelectorAll('.problem-row')].forEach(row => {
                    row.addEventListener('click', (e) => {
                        const problemId = row.dataset.problemId;
                        fsShowProblemDetails(problemId);
                    });
                });
            })
            .catch(err => {
                const tb = document.getElementById('fsVisitsTbody');
                if (tb) tb.innerHTML = `<tr><td colspan=6 class='text-danger text-center py-3'>${err.message}</td></tr>`;
            });
    }

    function fsSeverityClass(s) {
        return s === 'critical' ? 'bg-danger' : (s === 'major' ? 'bg-warning text-dark' : (s === 'minor' ? 'bg-info text-dark' : 'bg-secondary'));
    }

    // Problem Details Modal
    let fsProblemDetailsModal = null;
    document.addEventListener('DOMContentLoaded', () => {
        const pdEl = document.getElementById('fsProblemDetailsModal');
        if (pdEl) fsProblemDetailsModal = new bootstrap.Modal(pdEl);
    });

    function fsShowProblemDetails(problemId) {
        const problem = fsLoadedProblems[problemId];
        if (!problem) {
            fsAlertError('Problema não encontrado');
            return;
        }

        const statusBadge = {
            'open': 'bg-secondary',
            'in_progress': 'bg-warning text-dark',
            'closed': 'bg-success'
        };

        const body = document.getElementById('fsProblemDetailsBody');
        if (!body) return;

        body.innerHTML = `
            <div class="row">
                <div class="col-12 mb-3">
                    <h4 class="mb-2">${escapeHtml(problem.title || '')}</h4>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge ${statusBadge[problem.status] || 'bg-secondary'}">${escapeHtml(problem.status || 'open')}</span>
                        ${problem.severity ? `<span class="badge ${fsSeverityClass(problem.severity)}">${escapeHtml(problem.severity)}</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small mb-1">Projeto</label>
                    <div class="fw-medium">${escapeHtml(problem.project_title || '—')}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small mb-1">Responsável</label>
                    <div class="fw-medium">${escapeHtml(problem.responsible_name || 'Não atribuído')}</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small mb-1">Criado em</label>
                    <div>${escapeHtml(problem.created_at || '—')}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small mb-1">Última atualização</label>
                    <div>${escapeHtml(problem.updated_at || '—')}</div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small mb-1">Descrição</label>
                <div class="p-3 bg-light rounded" style="white-space: pre-wrap; min-height: 80px;">
                    ${problem.description ? linkifyText(escapeHtml(problem.description)) : '<span class="text-muted">Sem descrição</span>'}
                </div>
            </div>
        `;

        fsProblemDetailsModal?.show();
    }

    function fsSaveVisit() {
        const id = document.getElementById('fsVisitId').value.trim();
        const type = document.getElementById('fsType').value;
        const title = document.getElementById('fsTitle').value.trim();
        const start = document.getElementById('fsStart').value;
        const severity = document.getElementById('fsSeverity').value;
        const desc = document.getElementById('fsDesc').value.trim();
        if (!type || !title || !start) {
            fsAlert('Tipo, Título e Data de início são obrigatórios');
            return;
        }
        const fd = new FormData();
        fd.append('action', id ? 'update_visit' : 'create_visit');
        if (id) fd.append('id', id);
        fd.append('type', type);
        fd.append('title', title);
        fd.append('date_start', start);
        if (severity) fd.append('severity', severity);
        if (desc) fd.append('description', desc);
        // Remove accidental project link append for visit save
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json())
            .then(resp => {
                console.log('fsOpenProject: get_project response', resp);
                if (!resp.success) {
                    fsAlertError(resp.error || 'Erro');
                    return;
                }
                fsVisitModal?.hide();
                // If a file was selected, upload it after the visit is created/updated
                const fileEl = document.getElementById('fsVisitFile');
                const visitId = resp.id || document.getElementById('fsVisitId').value;
                if (fileEl && fileEl.files && fileEl.files[0] && visitId) {
                    const fd2 = new FormData();
                    fd2.append('action', 'upload_visit_attachment');
                    fd2.append('visit_id', visitId);
                    fd2.append('file', fileEl.files[0]);
                    fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                        method: 'POST',
                        credentials: 'include',
                        body: fd2
                    }).then(r2 => r2.json()).then(resp2 => {
                        if (!resp2.success) fsAlertError(resp2.error || 'Falha no upload');
                        else {
                            // refresh attachments if editing
                            if (document.getElementById('fsVisitId').value) {
                                fsEditVisit(document.getElementById('fsVisitId').value);
                            }
                        }
                    }).catch(err => console.error('upload err', err));
                }
                // If project was set, attach the visit
                const projectId = document.getElementById('fsVisitProjectId').value;
                if (projectId && visitId) {
                    const fd3 = new FormData();
                    fd3.append('action', 'attach_visit_to_project');
                    fd3.append('visit_id', visitId);
                    fd3.append('project_id', projectId);
                    fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                        method: 'POST',
                        credentials: 'include',
                        body: fd3
                    }).then(r3 => r3.json()).then(resp3 => {
                        if (!resp3.success) console.error('Attach failed:', resp3.error);
                        else {
                            // refresh visits and timeline
                            fsLoadVisitsForProject(projectId, fsSelectedProjectVisitsListId);
                            fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
                        }
                    }).catch(err => console.error('attach err', err));
                }
                document.getElementById('fsVisitProjectId').value = '';
                fsLoadVisits();
            })
            .catch(err => console.error('save visit error', err));
    }

    function fsSubmitNoteForm() {
        const projectId = document.getElementById('fsNoteProjectId').value;
        const text = document.getElementById('fsNoteText').value.trim();
        if (!projectId || !text) {
            fsAlert('Projecto e texto da nota são obrigatórios');
            return;
        }
        const fd = new FormData();
        fd.append('action', 'add_project_timeline');
        fd.append('project_id', projectId);
        fd.append('entry_type', 'note');
        fd.append('entry_text', text);
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return fsAlertError(resp.error || 'Error adding note');
                fsAddNoteModal?.hide();
                fsLoadProjectNotes(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectNotesListId);
                // If notes are not currently shown in the timeline, enable the toggle to surface the newly added note
                try {
                    const cb = document.getElementById('fsShowNotesInTimeline');
                    if (cb && !cb.checked) {
                        cb.checked = true;
                        try {
                            localStorage.setItem('fsShowNotesInTimeline_' + projectId, '1');
                        } catch (e) {}
                    }
                } catch (err) {}
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, true);
            }).catch(err => console.error('add note err', err));
    }

    function fsEditVisit(id) {
        if (!id) return;
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=get_visit&id=${encodeURIComponent(id)}`), {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(async resp => {
                if (!resp.success) {
                    fsAlertError(resp.error || 'Erro');
                    return;
                }
                const v = resp.data || {};
                document.getElementById('fsVisitId').value = v.id;
                document.getElementById('fsType').value = v.type;
                document.getElementById('fsTitle').value = v.title;
                document.getElementById('fsStart').value = v.date_start ? v.date_start.replace(' ', 'T') : '';
                document.getElementById('fsSeverity').value = v.severity || '';
                document.getElementById('fsDesc').value = v.description || '';
                document.getElementById('fsVisitModalTitle').textContent = 'Edit Entry';
                document.getElementById('fsVisitProjectId').value = v.project_id || '';
                const projRow = document.getElementById('fsVisitProjectRow');
                const projDisp = document.getElementById('fsVisitProjectDisplay');
                if (v.project_id) {
                    if (projRow) projRow.classList.remove('d-none');
                    // fetch project title
                    try {
                        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=get_project&id=${encodeURIComponent(v.project_id)}`), {
                            credentials: 'include'
                        }).then(r => r.json()).then(presp => {
                            if (presp && presp.success) {
                                if (projDisp) projDisp.textContent = presp.data.title || '';
                            }
                        });
                    } catch (err) {
                        console.error('get project err', err);
                    }
                } else {
                    if (projRow) projRow.classList.add('d-none');
                    if (projDisp) projDisp.textContent = '';
                }
                // load attachments
                try {
                    const atResp = await fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_visit_attachments&visit_id=${encodeURIComponent(id)}`), {
                        credentials: 'include'
                    });
                    const atJson = await atResp.json();
                    const listEl = document.getElementById('fsVisitAttachmentsList');
                    if (listEl) {
                        if (!atJson.success) {
                            listEl.innerHTML = `<div class='text-muted'>${escapeHtml(atJson.error||'No attachments')}</div>`;
                        } else {
                            const items = atJson.data || [];
                            listEl.innerHTML = items.length ? items.map(a => `<div class='d-flex align-items-center mb-1'><a href='${window.getAjaxUrl ? window.getAjaxUrl('ajax/manage_field_supervision.php?action=download_visit_attachment&id=' + a.id) : 'ajax/manage_field_supervision.php?action=download_visit_attachment&id=' + a.id}' target='_blank' class='me-2'>${escapeHtml(a.file_name)}</a><button class='btn btn-compact-xs btn-outline-danger btn-delete-attach' data-id='${a.id}' title='Delete attachment' aria-label='Delete attachment'><i class='fas fa-trash'></i></button></div>`).join('') : `<div class='text-muted'>No attachments</div>`;
                            // attach delete handlers
                            [...listEl.querySelectorAll('.btn-delete-attach')].forEach(b => b.addEventListener('click', async (ev) => {
                                const id = b.dataset.id;
                                if (await fsConfirmDelete('Delete this attachment?', 'Attachment')) {
                                    const fd3 = new FormData();
                                    fd3.append('action', 'delete_visit_attachment');
                                    fd3.append('id', id);
                                    fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                                        method: 'POST',
                                        credentials: 'include',
                                        body: fd3
                                    }).then(r => r.json()).then(resp => {
                                        if (resp.success) {
                                            fsEditVisit(v.id);
                                        } else fsAlertError(resp.error || 'Erro')
                                    });
                                }
                            }));
                        }
                    }
                } catch (err) {
                    console.error('attachments load err', err);
                }
                fsVisitModal.show();
            })
            .catch(err => console.error('open visit err', err));
    }

    // Delete a project by id
    async function fsDeleteProject(projectId) {
        if (!projectId) return;
        if (!await fsConfirmDelete('Confirma eliminação do projecto? Esta acção não pode ser desfeita.', 'Projecto')) return;
        const fd = new FormData();
        fd.append('action', 'delete_project');
        fd.append('id', projectId);
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return fsAlertError(resp.error || 'Error deleting project');
                // if current project was deleted, clear detail pane
                if (fsSelectedProjectId == projectId) {
                    fsSelectedProjectId = null;
                    const area = document.getElementById('fsProjectDetailArea');
                    if (area) area.innerHTML = `<div class="text-center text-muted py-4">Select a project to view details</div>`;
                    // clear global quick access
                    const g = document.getElementById('fsGlobalQuickAccess');
                    if (g) g.innerHTML = '';
                }
                fsLoadProjects();
                console.log('fsLoadVisits invoked');
            }).catch(err => console.error('delete project err', err));
    }

    function fsEditProjectForm(projectId) {
        if (!projectId) return;
        // fetch project details and fill form
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=get_project&id=${encodeURIComponent(projectId)}`), {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return fsAlertError(resp.error || 'Erro');
                const p = resp.data || {};
                document.getElementById('fsProjectId').value = p.id;
                document.getElementById('fsProjectTitle').value = p.title || '';
                document.getElementById('fsProjectStart').value = p.start_date ? p.start_date.split(' ')[0] : '';
                document.getElementById('fsProjectEnd').value = p.end_date ? p.end_date.split(' ')[0] : '';
                document.getElementById('fsProjectPhase').value = p.phase || '';
                document.getElementById('fsProjectDesc').value = p.description || '';
                document.getElementById('fsProjectPlan').value = p.project_plan_url || '';
                document.getElementById('fsProjectPvSolution').value = p.pv_solution_url || '';
                document.getElementById('fsProjectSld').value = p.sld_url || '';
                document.getElementById('fsProjectModalTitle').textContent = 'Edit Project';
                // Check if link columns exist and show note
                fsCheckProjectLinkColumns().then(cols => {
                    const note = document.getElementById('fsProjectColumnsNote');
                    if (note) note.textContent = cols.project_plan_url && cols.pv_solution_url && cols.sld_url ? '' : 'Database columns for quick-access project links are missing; links will not persist until the DB migration is run.';
                }).catch(err => {});
                fsProjectModal?.show();
            }).catch(err => console.error('edit project fetch err', err));
    }

    function fsDeleteTimelineEntry(entryId, projectId) {
        if (!entryId || !projectId) return;
        // Accept prefixed ids like 't37' and extract numeric id
        const numericId = ('' + entryId).replace(/^[a-zA-Z]+/, '');
        const fd = new FormData();
        fd.append('action', 'delete_project_timeline');
        fd.append('id', numericId);
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return fsAlertError(resp.error || 'Error deleting timeline entry');
                // reload timeline and notes
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
                fsLoadProjectNotes(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectNotesListId);
            }).catch(err => console.error('delete timeline err', err));
    }

    // ---- Project functions ----
    let fsProjectModal, fsProblemModal, fsAttachModal, fsAddNoteModal, fsContactModal, fsSelectedProjectId, fsSelectedProjectSupervisorId = 0;
    let fsSelectedProjectTimelineListId = null,
        fsSelectedProjectProblemsListId = null,
        fsSelectedProjectVisitsListId = null,
        fsSelectedProjectNotesListId = null,
        fsSelectedProjectContactsListId = null;
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('fsProjectModal');
        if (el) fsProjectModal = new bootstrap.Modal(el);
        document.getElementById('fsNewProjectBtn')?.addEventListener('click', () => {
            document.getElementById('fsProjectForm').reset();
            document.getElementById('fsProjectId').value = '';
            document.getElementById('fsProjectModalTitle').textContent = 'New Project';
            // check if link columns are present and notify user if not
            fsCheckProjectLinkColumns().then(cols => {
                const note = document.getElementById('fsProjectColumnsNote');
                if (note) note.textContent = cols.project_plan_url && cols.pv_solution_url && cols.sld_url ? '' : 'Note: the database does not have columns for quick-access project links. Links added here may not be persisted unless the DB migration is run.';
                fsProjectModal.show();
            }).catch(err => {
                fsProjectModal.show();
            });
        });
        document.getElementById('fsProjectForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            fsSaveProject();
        });
        // Attach project-level form handlers once to avoid duplicates when opening project details
        document.getElementById('fsProblemForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            fsSubmitProblemForm();
        });
        // Timeline add form removed; timeline entries come from Problems & Visits
        document.getElementById('fsAttachForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            fsSubmitAttachForm();
        });
        fsLoadProjects();
        // Event delegation for project list clicks to ensure handlers are invoked reliably
        try {
            const projectsList = document.getElementById('fsProjectsList');
            if (projectsList) {
                projectsList.addEventListener('click', ev => {
                    // prevent clicks on interactive controls (dropdowns, buttons) inside the list item
                    try {
                        if (ev.target.closest && ev.target.closest('.btn-group')) return;
                    } catch (e) {}
                    const a = ev.target.closest ? ev.target.closest('.fs-project-click-area[data-id]') : null;
                    if (a) {
                        ev.preventDefault();
                        const pid = parseInt(a.dataset.id || 0, 10);
                        if (!pid) {
                            console.warn('fsProjectsList delegated: invalid project id', a.dataset.id);
                            return;
                        }
                        console.log('fsProjectsList delegated click for project id', pid);
                        try {
                            // show overlay while project details load
                            try {
                                document.dispatchEvent(new Event('fsResetOverlayFlags'));
                            } catch (e) {}
                            try {
                                document.dispatchEvent(new Event('fsShowOverlay'));
                            } catch (e) {}
                            fsOpenProject(pid);
                        } catch (err) {
                            console.error('fsProjectsList delegated: fsOpenProject error', err);
                        }
                    }
                });
                console.log('fsLoadProjects: delegation handler attached to projects list');
            }
        } catch (err) {
            console.error('fsLoadProjects: delegation attach err', err);
        }
    });

    async function fsCheckProjectLinkColumns() {
        try {
            const r = await fetch(window.getAjaxUrl('ajax/manage_field_supervision.php?action=get_project_link_columns'), {
                credentials: 'include'
            });
            const j = await r.json();
            if (!j.success) return {
                project_plan_url: false,
                pv_solution_url: false,
                sld_url: false
            };
            return j.data || {
                project_plan_url: false,
                pv_solution_url: false,
                sld_url: false
            };
        } catch (err) {
            console.error('failed to check project link columns', err);
            return {
                project_plan_url: false,
                pv_solution_url: false,
                sld_url: false
            };
        }
    }

    function fsLoadProjects() {
        console.log('fsLoadProjects invoked');
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php?action=list_projects'), {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                const list = document.getElementById('fsProjectsList');
                if (!list) return;
                if (!resp.success) {
                    if (resp.error && resp.error.indexOf('not initialised') !== -1) {
                        list.innerHTML = `<div class="p-3 text-center text-muted">Field Supervision is not initialized on this database. To enable Projects and Timeline, run the Field Supervision migration (db_migrate_field_supervision_procedures.sql).</div>`;
                    } else {
                        list.innerHTML = `<div class="list-group-item text-danger">${escapeHtml(resp.error||'Error')}</div>`;
                    }
                    return;
                }
                const projects = resp.data || [];
                // Sort projects alphabetically by title (case-insensitive)
                projects.sort((a, b) => (String(a.title || '').localeCompare(String(b.title || ''), undefined, {
                    sensitivity: 'base'
                })));
                if (!projects.length) {
                    list.innerHTML = `<div class="list-group-item text-muted text-center">No projects</div>`;
                    // No projects to show; also mark projectDetails as ready so overlay can hide
                    try {
                        document.dispatchEvent(new Event('fsProjectsReady'));
                    } catch (e) {}
                    try {
                        document.dispatchEvent(new Event('fsProjectDetailsReady'));
                    } catch (e) {}
                    return;
                }
                list.innerHTML = projects.map(p => `
                    <div class="list-group-item d-flex align-items-start" data-id="${p.id}">
                        <a href="#" class="fs-project-click-area flex-fill text-decoration-none text-reset" data-id="${p.id}">
                            <div>
                                <strong>${escapeHtml(p.title)}</strong><br>
                                <small class="text-muted">${escapeHtml(p.phase||'')}</small>
                            </div>
                        </a>
                        <div class="ms-auto small text-muted d-flex align-items-center">
                            <div class="me-2">${escapeHtml(p.status)}</div>
                            <button class="btn btn-sm btn-outline-danger delete-project" data-id="${p.id}" title="Delete project">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
                // Signal that projects are ready (for overlay hiding)
                try {
                    document.dispatchEvent(new Event('fsProjectsReady'));
                } catch (e) {}
                // Removed duplicate 'tb' bindings here (they belong to the visits table logic). Keep project list bindings below.
                // NOTE: combined/global table mark-treated handlers are attached in fsLoadVisits()
                // to avoid referencing 'tb' within fsLoadProjects() where it is not defined.
                // Combined (global) table handlers: attached in fsLoadVisits() instead of here
                // Combined (global) table handlers: attached in fsLoadVisits() instead of here
                // NOTE: combined table handlers are attached in fsLoadVisits() where 'tb' is defined
                // attach click events
                // no additional inline dropdowns in the project list: removed .btn-group
                const anchors = [...list.querySelectorAll('.fs-project-click-area[data-id]')];
                // click events are handled via event delegation bound to fsProjectsList
                console.log('fsLoadProjects: attached click handlers to', anchors.length, 'project links');
                // delete buttons
                [...list.querySelectorAll('.delete-project')].forEach(b => b.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    const id = b.dataset.id;
                    fsDeleteProject(id);
                }));
                // edit buttons in list
                [...list.querySelectorAll('.edit-project')].forEach(b => b.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    const id = b.dataset.id;
                    fsEditProjectForm(id);
                }));
                // status binding in list
                [...list.querySelectorAll('.set-status')].forEach(b => b.addEventListener('click', async (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    const id = b.dataset.id;
                    const status = b.dataset.status;
                    if (!id || !status) return;
                    if (!await fsConfirm(`Change project ${id} status to ${status}?`, 'Change Status')) return;
                    fsChangeProjectStatus(id, status);
                }));
                // Clear global quick access if no project is selected
                try {
                    const g = document.getElementById('fsGlobalQuickAccess');
                    if (g && !fsSelectedProjectId) g.innerHTML = '';
                } catch (err) {}
            })
            .catch(err => console.error('load projects err', err));
    }

    function fsSaveProject() {
        const id = document.getElementById('fsProjectId').value.trim();
        const title = document.getElementById('fsProjectTitle').value.trim();
        const start = document.getElementById('fsProjectStart').value;
        const end = document.getElementById('fsProjectEnd').value;
        const phase = document.getElementById('fsProjectPhase').value.trim();
        const desc = document.getElementById('fsProjectDesc').value.trim();
        if (!title) {
            fsAlert('Título é obrigatório');
            return;
        }
        const fd = new FormData();
        fd.append('action', id ? 'update_project' : 'create_project');
        if (id) fd.append('id', id);
        fd.append('title', title);
        if (start) fd.append('start_date', start);
        if (end) fd.append('end_date', end);
        if (phase) fd.append('phase', phase);
        if (desc) fd.append('description', desc);
        // Append project quick links if provided
        const plan = document.getElementById('fsProjectPlan')?.value.trim();
        const pvsol = document.getElementById('fsProjectPvSolution')?.value.trim();
        const sld = document.getElementById('fsProjectSld')?.value.trim();
        // Client-side URL validation
        if (plan && !isValidUrlClient(plan)) {
            fsAlert('Project Plan URL seems invalid. Please use a full URL starting with http:// or https://');
            return;
        }
        if (pvsol && !isValidUrlClient(pvsol)) {
            fsAlert('PV Solution URL seems invalid. Please use a full URL starting with http:// or https://');
            return;
        }
        if (sld && !isValidUrlClient(sld)) {
            fsAlert('SLD URL seems invalid. Please use a full URL starting with http:// or https://');
            return;
        }
        if (plan) fd.append('project_plan_url', plan);
        if (pvsol) fd.append('pv_solution_url', pvsol);
        if (sld) fd.append('sld_url', sld);
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) {
                    fsAlertError(resp.error || 'Erro');
                    return;
                }
                fsProjectModal.hide();
                // After saving, reload projects and open saved project to ensure links are visible
                const newId = resp.id || id;
                fsLoadProjects();
                if (newId) {
                    try {
                        document.dispatchEvent(new Event('fsResetOverlayFlags'));
                    } catch (e) {}
                    try {
                        document.dispatchEvent(new Event('fsShowOverlay'));
                    } catch (e) {}
                    fsOpenProject(newId);
                }
                if (resp.migration_needed) {
                    // Inform user that link columns are not present in DB and should be migrated
                    let msg = 'Project saved, but the database columns for quick-link URLs are not present.\nPlease run the migration to enable persistence for these fields.';
                    try {
                        if (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor') {
                            msg += "\n\nExecutar no servidor: php tools/ensure_project_links_migration.php";
                        } else {
                            msg += "\n\nPlease ask the administrator to run: php tools/ensure_project_links_migration.php";
                        }
                    } catch (e) {}
                    fsAlert(msg, 'Migração Necessária');
                }
            }).catch(err => console.error('save project err', err));
    }

    function fsOpenProject(id) {
        console.log('fsOpenProject invoked for id', id);
        if (!id) return;
        fsSelectedProjectId = id;
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=get_project&id=${encodeURIComponent(id)}`), {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) {
                    fsAlertError(resp.error || 'Erro');
                    return;
                }
                const p = resp.data || {};
                console.log('fsOpenProject: got project data', p.id, p.title);
                const area = document.getElementById('fsProjectDetailArea');
                if (!area) return;
                // build detail UI: header + tabs
                try {
                    area.innerHTML = `
                    <div class="d-flex align-items-center mb-3">
                        <div>
                            <h4 class="mb-0">${escapeHtml(p.title || '')}</h4>
                            <small class="text-muted">${escapeHtml(p.phase||'')}</small>
                        </div>
                        <div class="ms-auto d-flex align-items-center">
                            <div class="btn-group ms-2">
                                <button class="btn btn-compact btn-outline-${p.status === 'active' ? 'success' : (p.status === 'planned' ? 'secondary' : (p.status === 'on_hold' ? 'warning' : (p.status === 'completed' ? 'dark' : 'danger')))} dropdown-toggle" id="fsProjectStatusBtn_${p.id}" data-bs-toggle="dropdown" aria-expanded="false" title="Status" aria-label="Status">${escapeHtml(p.status)}</button>
                                <ul class="dropdown-menu dropdown-menu-end" id="fsProjectStatusMenu_${p.id}">
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item set-status' href='#' data-id='${p.id}' data-status='planned'>planned</a></li>` : '' }
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item set-status' href='#' data-id='${p.id}' data-status='active'>active</a></li>` : '' }
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item set-status' href='#' data-id='${p.id}' data-status='on_hold'>on_hold</a></li>` : '' }
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item set-status' href='#' data-id='${p.id}' data-status='completed'>completed</a></li>` : '' }
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item set-status text-danger' href='#' data-id='${p.id}' data-status='cancelled'>cancelled</a></li>` : '' }
                                </ul>
                            </div>
                            <div class='ms-2'>
                                <div class='btn-group'>
                                    <button class='btn btn-compact btn-outline-secondary dropdown-toggle' data-bs-toggle='dropdown' aria-expanded='false' title='Actions' aria-label='Actions'><i class='fas fa-ellipsis-v'></i></button>
                                    <ul class='dropdown-menu dropdown-menu-end'>
                                        ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item' href='#' id='fsEditProjectBtn' data-id='${p.id}'>Edit</a></li>` : '' }
                                        ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor') ? `<li><a class='dropdown-item text-danger' href='#' id='fsDeleteProjectBtn' data-id='${p.id}'>Delete</a></li>` : '' }
                                    </ul>
                                </div>
                            </div>
                            <div class="ms-3 d-flex align-items-center">
                                <div class="form-check form-switch me-3">
                                    <input class="form-check-input" type="checkbox" id="fsShowNotesInTimeline" />
                                    <label class="form-check-label small text-muted" for="fsShowNotesInTimeline">Show notes in timeline</label>
                                </div>
                                <div id="fsQuickAccessBtns" class="d-flex align-items-center"></div>
                                <button class="btn btn-compact btn-outline-primary ms-2" id="fsDownloadTimelineBtn_${p.id}" title="Download Timeline PDF" aria-label="Download Timeline PDF"><i class='fas fa-file-pdf'></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3"><p>${escapeHtml(p.description||'')}</p></div>
                    <ul class="nav nav-tabs" id="fsProjectTabs" role="tablist">
                      <!-- Overview removed per new UX: Timeline consolidates content -->
                      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#fsProjectTimeline">Timeline</a></li>
                      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fsProjectProblems">Problems</a></li>
                      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fsProjectVisits">Visits</a></li>
                      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fsProjectNotes">Notes</a></li>
                      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fsProjectContacts">Contacts</a></li>
                    </ul>
                    <div class="tab-content pt-3">
                        <!-- Overview tab removed: timeline now consolidates problems and visits -->
                                <div class="tab-pane fade show active" id="fsProjectTimeline">
                                    <!-- Add Timeline Note button removed: timeline is now populated by Problems and Visits -->
                                    <div id="fsTimelineList_${p.id}">Loading timeline...</div>
                                </div>
                        <div class="tab-pane fade" id="fsProjectProblems">
                            <div class="d-flex mb-2">
                                <button class="btn btn-sm btn-outline-danger me-2" id="fsAddProblemBtn">Report Problem</button>
                                <div class="ms-auto"><small class="text-muted">Problems for this project</small></div>
                            </div>
                            <div id="fsProblemsList_${p.id}">Loading problems...</div>
                        </div>
                        <div class="tab-pane fade" id="fsProjectVisits">
                            <div class="d-flex mb-2">
                                <button class="btn btn-sm btn-outline-primary me-2" id="fsAddVisitToProjectBtn">Add Visit</button>
                                <div class="ms-auto"><small class="text-muted">Visits for this project</small></div>
                            </div>
                            <div id="fsProjectVisitsList_${p.id}">Loading visits...</div>
                        </div>
                        <div class="tab-pane fade" id="fsProjectNotes">
                            <div class="d-flex mb-2">
                                <button class="btn btn-sm btn-outline-primary me-2" id="fsAddNoteBtn">Add Note</button>
                                <div class="ms-auto"><small class="text-muted">Notes for this project</small></div>
                            </div>
                            <div id="fsNotesList_${p.id}">Loading notes...</div>
                        </div>
                        <div class="tab-pane fade" id="fsProjectContacts">
                            <div class="d-flex mb-2">
                                <button class="btn btn-sm btn-outline-primary me-2" id="fsAddContactBtn"><i class="fas fa-user-plus me-1"></i>Add Contact</button>
                                <div class="ms-auto"><small class="text-muted">Contactos do projecto</small></div>
                            </div>
                            <div id="fsContactsList_${p.id}">A carregar contactos...</div>
                        </div>
                    </div>
                `;
                } catch (err) {
                    console.error('fsOpenProject: error rendering innerHTML', err);
                    area.innerHTML = `<div class="text-muted">Failed to render project details</div>`;
                    return;
                }
                // attach events
                // Add Timeline button removed; timeline is populated from Problems and Visits
                document.getElementById('fsAddProblemBtn')?.addEventListener('click', async () => {
                    // ensure project users loaded in select
                    try {
                        const appRoot = window.APP_ROOT || (window.BASE_URL ? new URL(window.BASE_URL).pathname : (window.location.pathname.includes('cleanwattsportal') ? '/cleanwattsportal/' : (window.location.pathname.includes('comissionamentov2') ? '/ComissionamentoV2/' : '/')));
                        const r = await fetch(`${appRoot}ajax/get_field_supervision_users.php`, {
                            credentials: 'include'
                        });
                        const json = await r.json();
                        const sel = document.getElementById('fsProblemResponsible');
                        if (json && json.success) {
                            sel.innerHTML = '<option value="">Unassigned</option>' + json.data.map(u => `<option value="${u.id}">${escapeHtml(u.full_name || u.username)}</option>`).join('');
                        }
                    } catch (err) {
                        console.error('load users for problem err', err);
                    }
                    fsShowAddProblemModal();
                });
                document.getElementById('fsAddVisitToProjectBtn')?.addEventListener('click', () => {
                    document.getElementById('fsVisitForm')?.reset();
                    document.getElementById('fsVisitId').value = '';
                    document.getElementById('fsVisitProjectId').value = id;
                    const projRow = document.getElementById('fsVisitProjectRow');
                    if (projRow) projRow.classList.remove('d-none');
                    const projDisp = document.getElementById('fsVisitProjectDisplay');
                    if (projDisp) projDisp.textContent = p.title || '';
                    document.getElementById('fsVisitModalTitle').textContent = 'New Visit';
                    document.getElementById('fsStart').value = new Date().toISOString().slice(0, 16);
                    fsVisitModal.show();
                });
                document.getElementById('fsAddNoteBtn')?.addEventListener('click', () => {
                    const noteFormEl = document.getElementById('fsNoteForm');
                    if (noteFormEl) noteFormEl.reset();
                    document.getElementById('fsNoteProjectId').value = id;
                    document.getElementById('fsAddNoteModalTitle').textContent = 'Add Note';
                    fsAddNoteModal.show();
                    setTimeout(() => {
                        document.getElementById('fsNoteText')?.focus();
                    }, 200);
                });
                // Add contact button handler
                document.getElementById('fsAddContactBtn')?.addEventListener('click', () => {
                    fsShowAddContactModal(id);
                });
                // NOTE: Form submit handlers are attached globally to avoid multiple bindings when opening a project
                // delete project from header
                document.getElementById('fsDeleteProjectBtn')?.addEventListener('click', async function() {
                    if (await fsConfirmDelete('Delete project? This will remove the project and its timeline and related issues.', 'Project')) {
                        fsDeleteProject(id);
                    }
                });
                // edit project from header: open project modal with values
                document.getElementById('fsEditProjectBtn')?.addEventListener('click', function() {
                    fsEditProjectForm(id);
                });
                // status change handlers
                [...document.querySelectorAll('#fsProjectStatusMenu_' + p.id + ' .set-status')].forEach(b => b.addEventListener('click', async (ev) => {
                    ev.preventDefault();
                    const status = b.dataset.status;
                    if (!status) return;
                    if (!await fsConfirm('Change project status to "' + status + '"?', 'Change Status')) return;
                    fsChangeProjectStatus(id, status);
                }));
                // attach timeline PDF download handler (in header)
                const dlBtn = document.getElementById('fsDownloadTimelineBtn_' + p.id);
                if (dlBtn) dlBtn.addEventListener('click', ev => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    console.log('fsDownloadTimeline clicked for project', p.id, p.title);
                    fsDownloadProjectTimelinePdf(p.id, p.title || 'Project');
                });
                // Quick access buttons (Project Plan / PV Solution / SLD) - shown in project detail header
                try {
                    const quick = document.getElementById('fsQuickAccessBtns');
                    if (quick) {
                        quick.innerHTML = '';
                        if (p.project_plan_url) quick.innerHTML += `<a class='btn btn-compact btn-outline-primary ms-1' href='${escapeHtml(p.project_plan_url)}' target='_blank' rel='noopener noreferrer' title='Open Project Plan' aria-label='Open Project Plan'><i class='fas fa-file-alt'></i></a>`;
                        if (p.pv_solution_url) quick.innerHTML += `<a class='btn btn-compact btn-outline-primary ms-1' href='${escapeHtml(p.pv_solution_url)}' target='_blank' rel='noopener noreferrer' title='Open PV Solution' aria-label='Open PV Solution'><i class='fas fa-solar-panel'></i></a>`;
                        if (p.sld_url) quick.innerHTML += `<a class='btn btn-compact btn-outline-primary ms-1' href='${escapeHtml(p.sld_url)}' target='_blank' rel='noopener noreferrer' title='Open SLD' aria-label='Open SLD'><i class='fas fa-project-diagram'></i></a>`;
                    }
                } catch (err) {
                    console.error('render quick access err', err);
                }

                // Populate top-level global quick access: show actions dropdown for the selected project
                try {
                    const g = document.getElementById('fsGlobalQuickAccess');
                    if (g) {
                        g.innerHTML = '';
                        if ((window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id))) {
                            g.innerHTML = `
                                <div class='btn-group'>
                                  <button class='btn btn-compact btn-outline-secondary dropdown-toggle' data-bs-toggle='dropdown' aria-expanded='false' title='Actions' aria-label='Actions'><i class='fas fa-ellipsis-v'></i></button>
                                  <ul class='dropdown-menu dropdown-menu-end'>
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item' href='#' id='fsGlobalEditProj' data-id='${p.id}'>Edit</a></li>` : '' }
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor') ? `<li><a class='dropdown-item text-danger' href='#' id='fsGlobalDeleteProj' data-id='${p.id}'>Delete</a></li>` : '' }
                                    <li><hr class='dropdown-divider'></li>
                                    ${ (window.CWUserRole === 'admin' || window.CWUserRole === 'gestor' || (window.CWUserRole === 'supervisor' && window.CWUserId == p.supervisor_user_id)) ? `<li><a class='dropdown-item set-status' href='#' id='fsGlobalStatusPlanned' data-id='${p.id}' data-status='planned'>Set planned</a></li><li><a class='dropdown-item set-status' href='#' id='fsGlobalStatusActive' data-id='${p.id}' data-status='active'>Set active</a></li><li><a class='dropdown-item set-status' href='#' id='fsGlobalStatusOnHold' data-id='${p.id}' data-status='on_hold'>Set on_hold</a></li><li><a class='dropdown-item set-status' href='#' id='fsGlobalStatusCompleted' data-id='${p.id}' data-status='completed'>Set completed</a></li><li><a class='dropdown-item set-status text-danger' href='#' id='fsGlobalStatusCancelled' data-id='${p.id}' data-status='cancelled'>Set cancelled</a></li>` : '' }
                                  </ul>
                                </div>`;
                            // Attach handlers to global dropdown items
                            setTimeout(() => {
                                const gEdit = document.getElementById('fsGlobalEditProj');
                                if (gEdit) gEdit.addEventListener('click', ev => {
                                    ev.preventDefault();
                                    fsEditProjectForm(p.id);
                                });
                                const gDel = document.getElementById('fsGlobalDeleteProj');
                                if (gDel) gDel.addEventListener('click', async (ev) => {
                                    ev.preventDefault();
                                    if (await fsConfirmDelete('Delete project? This will remove the project and its timeline and related issues.', 'Project')) fsDeleteProject(p.id);
                                });
                                // Global set-status handlers
                                [...document.querySelectorAll('#fsGlobalQuickAccess .set-status')].forEach(b => b.addEventListener('click', async (ev) => {
                                    ev.preventDefault();
                                    const id = b.dataset.id;
                                    const status = b.dataset.status;
                                    if (!id || !status) return;
                                    if (!await fsConfirm(`Change project ${id} status to ${status}?`, 'Change Status')) return;
                                    fsChangeProjectStatus(id, status);
                                }));
                            }, 50);
                        }
                    }
                } catch (err) {
                    console.error('render global quick access err', err);
                }
                document.getElementById('fsAddVisitToProjectBtn')?.addEventListener('click', () => {
                    document.getElementById('fsVisitProjectId').value = id;
                    document.getElementById('fsVisitForm')?.reset();
                    document.getElementById('fsVisitId').value = '';
                    document.getElementById('fsVisitModalTitle').textContent = 'New Visit';
                    document.getElementById('fsStart').value = new Date().toISOString().slice(0, 16);
                    fsVisitModal.show();
                });
                fsSelectedProjectTimelineListId = 'fsTimelineList_' + id;
                console.log('fsOpenProject: detail area updated for project', id);
                fsSelectedProjectProblemsListId = 'fsProblemsList_' + id;
                fsSelectedProjectVisitsListId = 'fsProjectVisitsList_' + id;
                fsSelectedProjectNotesListId = 'fsNotesList_' + id;
                fsSelectedProjectContactsListId = 'fsContactsList_' + id;
                // default toggle state: unchecked meaning notes not included in timeline
                const showNotesCheckbox = document.getElementById('fsShowNotesInTimeline');
                if (showNotesCheckbox) {
                    // Restore last state per-project from localStorage if present
                    try {
                        const v = localStorage.getItem('fsShowNotesInTimeline_' + id);
                        if (v !== null) showNotesCheckbox.checked = (v === '1');
                    } catch (err) {
                        // ignore storage errors
                    }
                    showNotesCheckbox.addEventListener('change', function() {
                        try {
                            localStorage.setItem('fsShowNotesInTimeline_' + id, this.checked ? '1' : '0');
                        } catch (err) {}
                        // reload timeline with notes if checked
                        fsLoadProjectTimeline(id, p.supervisor_user_id, fsSelectedProjectTimelineListId, this.checked);
                    });
                }
                fsLoadProjectTimeline(id, p.supervisor_user_id, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
                fsLoadProjectProblems(id, fsSelectedProjectProblemsListId);
                fsLoadVisitsForProject(id, fsSelectedProjectVisitsListId);
                fsLoadProjectNotes(id, p.supervisor_user_id, fsSelectedProjectNotesListId);
                fsLoadProjectContacts(id, p.supervisor_user_id, fsSelectedProjectContactsListId);
                fsLoadProjectProblems(id, fsSelectedProjectProblemsListId);
                fsLoadVisitsForProject(id, fsSelectedProjectVisitsListId);
            })
            .catch(err => console.error('open project err', err));
    }

    function fsAttachVisitToProject(visitId) {
        if (!visitId) return;
        document.getElementById('fsAttachVisitId').value = visitId;
        // populate project list for the select
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php?action=list_projects'), {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                const sel = document.getElementById('fsAttachProjectSelect');
                if (!sel) return;
                if (!resp.success) {
                    sel.innerHTML = `<option value="">No projects available</option>`;
                    fsAttachModal?.show();
                    return;
                }
                const projects = resp.data || [];
                // Sort option list alphabetically by project title
                projects.sort((a, b) => (String(a.title || '').localeCompare(String(b.title || ''), undefined, {
                    sensitivity: 'base'
                })));
                sel.innerHTML = '<option value="">Select project</option>' + projects.map(p => `<option value="${p.id}">${escapeHtml(p.title)}</option>`).join('');
                fsAttachModal?.show();
            }).catch(err => {
                console.error('load projects for attach err', err);
                fsAttachModal?.show();
            });
    }

    function fsSubmitAttachForm() {
        const visitId = document.getElementById('fsAttachVisitId').value;
        const projectId = document.getElementById('fsAttachProjectSelect').value;
        if (!visitId || !projectId) {
            fsAlert('Escolha um projecto');
            return;
        }
        const fd = new FormData();
        fd.append('action', 'attach_visit_to_project');
        fd.append('visit_id', visitId);
        fd.append('project_id', projectId);
        fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return fsAlertError(resp.error || 'Erro');
                fsAttachModal?.hide();
                fsLoadVisits();
                if (fsSelectedProjectId) {
                    fsLoadVisitsForProject(fsSelectedProjectId, fsSelectedProjectVisitsListId);
                    fsLoadProjectTimeline(fsSelectedProjectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
                }
            }).catch(err => console.error('attach visit err', err));
    }

    function fsLoadProjectTimeline(projectId, projectSupervisorId, elementId, includeNotes = false) {
        const url = window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_project_timeline&project_id=${encodeURIComponent(projectId)}`) + (includeNotes ? '&include_notes=1' : '');
        fetch(url, {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                const el = document.getElementById(elementId || 'fsTimelineList');
                if (!el) return;
                if (!resp.success) {
                    el.innerHTML = `<div class='text-danger'>${escapeHtml(resp.error||'Error')}</div>`;
                    const p = resp.data || {};
                    fsSelectedProjectSupervisorId = p.supervisor_user_id || 0;
                    try {
                        document.dispatchEvent(new Event('fsProjectDetailsReady'));
                    } catch (e) {}
                    return;
                }
                const list = resp.data || [];
                if (!list.length) {
                    el.innerHTML = `<div class='text-muted'>No timeline notes.</div>`;
                    try {
                        document.dispatchEvent(new Event('fsProjectDetailsReady'));
                    } catch (e) {}
                    return;
                }
                el.innerHTML = list.map(it => {
                    // Define icon and badge color based on entry type
                    let typeIcon = 'fas fa-clock';
                    let typeBadge = 'bg-secondary';
                    let typeLabel = it.entry_type || 'entry';
                    switch (it.entry_type) {
                        case 'note':
                            typeIcon = 'fas fa-sticky-note';
                            typeBadge = 'bg-info';
                            typeLabel = 'nota';
                            break;
                        case 'contact':
                            typeIcon = 'fas fa-user';
                            typeBadge = 'bg-primary';
                            typeLabel = 'contacto';
                            break;
                        case 'problem':
                            typeIcon = 'fas fa-exclamation-triangle';
                            typeBadge = 'bg-danger';
                            typeLabel = 'problema';
                            break;
                        case 'visit':
                            typeIcon = 'fas fa-hard-hat';
                            typeBadge = 'bg-success';
                            typeLabel = 'visita';
                            break;
                    }
                    return `
                    <div class='card mb-2'>
                        <div class='card-body p-2'>
                            <div class='d-flex'>
                                <div><small class='text-muted'>${escapeHtml(it.created_at)}</small></div>
                                <div class='ms-auto'><span class='badge ${typeBadge}'><i class='${typeIcon} me-1'></i>${escapeHtml(typeLabel)}</span></div>
                            </div>
                            <div class='d-flex align-items-start justify-content-between mt-2'>
                                <div>${linkifyText(escapeHtml(it.entry_text).replace(/\n/g, '<br>'))}</div>
                                <div class='ms-3'></div>
                            </div>
                        </div>
                    </div>
                `;
                }).join('');
                // When timeline content is updated for project details, signal ready for overlay
                try {
                    document.dispatchEvent(new Event('fsProjectDetailsReady'));
                } catch (e) {}
                // Timeline is read-only: deletion should happen in Problems/Visits/Notes tabs
            }).catch(err => console.error('load project timeline err', err));
    }

    function fsShouldShowNotesInTimeline() {
        try {
            const cb = document.getElementById('fsShowNotesInTimeline');
            if (cb) return !!cb.checked;
            // fallback to persisted per-project setting
            const id = fsSelectedProjectId || '';
            try {
                const v = localStorage.getItem('fsShowNotesInTimeline_' + id);
                return v === '1';
            } catch (err) {
                return false;
            }
        } catch (err) {
            return false;
        }
    }

    function fsLoadProjectNotes(projectId, projectSupervisorId, elementId) {
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_project_notes&project_id=${encodeURIComponent(projectId)}`), {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                const el = document.getElementById(elementId || 'fsNotesList');
                if (!el) return;
                if (!resp.success) {
                    el.innerHTML = `<div class='text-danger'>${escapeHtml(resp.error || 'Error')}</div>`;
                    return;
                }
                const list = resp.data || [];
                if (!list.length) {
                    el.innerHTML = `<div class='text-muted'>No notes.</div>`;
                    return;
                }
                el.innerHTML = list.map(it => `
                    <div class='card mb-2'>
                        <div class='card-body p-2'>
                            <div class='d-flex'>
                                <div><small class='text-muted'>${escapeHtml(it.created_at)}</small></div>
                                <div class='ms-auto'><small class='text-muted'>note</small></div>
                            </div>
                            <div class='d-flex align-items-start justify-content-between mt-2'>
                                <div class='note-text' id='note-text-${it.id}'>${linkifyText(escapeHtml(it.entry_text).replace(/\n/g, '<br>'))}</div>
                                <div class='ms-3 d-flex gap-1'>
                                    ${((window.CWUserRole === 'admin' || window.CWUserRole === 'gestor') || (parseInt(window.CWUserId) === parseInt(it.created_by)) || (parseInt(window.CWUserId) === parseInt(projectSupervisorId))) ? `<button class='btn btn-compact btn-outline-secondary btn-edit-note' data-id='${it.id}' data-text='${escapeHtml(it.entry_text).replace(/'/g, "&#39;")}' title='Edit note' aria-label='Edit note'><i class='fas fa-edit'></i></button><button class='btn btn-compact btn-outline-danger btn-delete-note' data-id='${it.id}' title='Delete note' aria-label='Delete note'><i class='fas fa-trash'></i></button>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
                // Attach edit handlers
                [...el.querySelectorAll('.btn-edit-note')].forEach(b => b.addEventListener('click', (ev) => {
                    const id = b.dataset.id;
                    const currentText = b.dataset.text;
                    fsEditNote(id, currentText, projectId, projectSupervisorId, elementId);
                }));
                // Attach delete handlers
                [...el.querySelectorAll('.btn-delete-note')].forEach(b => b.addEventListener('click', async (ev) => {
                    const id = b.dataset.id;
                    if (await fsConfirmDelete('Delete this note?', 'Note')) fsDeleteTimelineEntry(id, projectId);
                }));
            }).catch(err => console.error('load project notes err', err));
    }

    /**
     * Edit a note - shows inline edit form
     */
    function fsEditNote(noteId, currentText, projectId, projectSupervisorId, elementId) {
        const noteTextEl = document.getElementById('note-text-' + noteId);
        if (!noteTextEl) return;

        // Extract numeric id (in case of prefixed ids like 't37')
        const numericId = ('' + noteId).replace(/^[a-zA-Z]+/, '');

        // Store original HTML to restore on cancel
        const originalHtml = noteTextEl.innerHTML;

        // Replace with textarea
        noteTextEl.innerHTML = `
            <div class='w-100'>
                <textarea class='form-control mb-2' id='edit-note-textarea-${noteId}' rows='4' style='min-width: 300px;'>${currentText}</textarea>
                <div class='d-flex gap-2'>
                    <button class='btn btn-sm btn-primary' id='save-note-${noteId}'><i class='fas fa-save me-1'></i>Save</button>
                    <button class='btn btn-sm btn-secondary' id='cancel-note-${noteId}'><i class='fas fa-times me-1'></i>Cancel</button>
                </div>
            </div>
        `;

        // Focus textarea
        const textarea = document.getElementById('edit-note-textarea-' + noteId);
        if (textarea) {
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }

        // Save handler
        document.getElementById('save-note-' + noteId)?.addEventListener('click', async () => {
            const newText = document.getElementById('edit-note-textarea-' + noteId)?.value?.trim();
            if (!newText) {
                fsAlertError('Note text cannot be empty');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'update_project_note');
            fd.append('id', numericId);
            fd.append('entry_text', newText);

            try {
                const resp = await fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                    method: 'POST',
                    credentials: 'include',
                    body: fd
                }).then(r => r.json());

                if (resp.success) {
                    fsAlert('Note updated successfully');
                    // Reload notes list
                    fsLoadProjectNotes(projectId, projectSupervisorId, elementId);
                } else {
                    fsAlertError(resp.error || 'Error updating note');
                    noteTextEl.innerHTML = originalHtml;
                }
            } catch (err) {
                console.error('Error updating note:', err);
                fsAlertError('Error updating note');
                noteTextEl.innerHTML = originalHtml;
            }
        });

        // Cancel handler
        document.getElementById('cancel-note-' + noteId)?.addEventListener('click', () => {
            noteTextEl.innerHTML = originalHtml;
        });
    }

    // ============ CONTACTS FUNCTIONS ============

    /**
     * Load project contacts list
     */
    function fsLoadProjectContacts(projectId, projectSupervisorId, elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.innerHTML = '<div class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>A carregar...</div>';

        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_contacts&project_id=${encodeURIComponent(projectId)}`), {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) {
                    el.innerHTML = `<div class='text-danger'>${escapeHtml(resp.error || 'Error loading contacts')}</div>`;
                    return;
                }
                const contacts = resp.data || [];
                if (!contacts.length) {
                    el.innerHTML = `<div class='text-muted py-3'><i class='fas fa-users me-2'></i>Sem contactos registados.</div>`;
                    return;
                }
                el.innerHTML = contacts.map(c => `
                <div class='card mb-2'>
                    <div class='card-body p-3'>
                        <div class='d-flex align-items-start justify-content-between'>
                            <div>
                                <div class='fw-bold'><i class='fas fa-user me-2 text-primary'></i>${escapeHtml(c.name)}</div>
                                ${c.role ? `<div class='text-muted small'><i class='fas fa-briefcase me-2'></i>${escapeHtml(c.role)}</div>` : ''}
                                ${c.phone ? `<div class='mt-1'><a href='tel:${escapeHtml(c.phone)}' class='text-decoration-none'><i class='fas fa-phone me-2 text-success'></i>${escapeHtml(c.phone)}</a></div>` : ''}
                            </div>
                            <div class='d-flex gap-1'>
                                ${((window.CWUserRole === 'admin' || window.CWUserRole === 'gestor') || (parseInt(window.CWUserId) === parseInt(c.created_by)) || (parseInt(window.CWUserId) === parseInt(projectSupervisorId))) ? `
                                    <button class='btn btn-compact btn-outline-secondary btn-edit-contact' data-id='${c.id}' data-name='${escapeHtml(c.name).replace(/'/g, "&#39;")}' data-role='${escapeHtml(c.role || '').replace(/'/g, "&#39;")}' data-phone='${escapeHtml(c.phone || '').replace(/'/g, "&#39;")}' title='Editar contacto'><i class='fas fa-edit'></i></button>
                                    <button class='btn btn-compact btn-outline-danger btn-delete-contact' data-id='${c.id}' data-name='${escapeHtml(c.name).replace(/' /g, "&#39;")}' title='Delete contact'><i class='fas fa-trash'></i></button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

                // Attach edit handlers
                [...el.querySelectorAll('.btn-edit-contact')].forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = btn.dataset.id;
                        const name = btn.dataset.name;
                        const role = btn.dataset.role;
                        const phone = btn.dataset.phone;
                        fsShowEditContactModal(id, name, role, phone);
                    });
                });

                // Attach delete handlers
                [...el.querySelectorAll('.btn-delete-contact')].forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.id;
                        const name = btn.dataset.name;
                        if (await fsConfirmDelete(`Delete the contact "${name}"?`, 'Contact')) {
                            fsDeleteContact(id, projectId, projectSupervisorId, elementId);
                        }
                    });
                });
            })
            .catch(err => {
                console.error('Error loading contacts:', err);
                el.innerHTML = `<div class='text-danger'>Error loading contacts</div>`;
            });
    }

    /**
     * Show add contact modal
     */
    function fsShowAddContactModal(projectId) {
        document.getElementById('fsContactForm')?.reset();
        document.getElementById('fsContactId').value = '';
        document.getElementById('fsContactProjectId').value = projectId;
        document.getElementById('fsContactModalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Contact';
        document.getElementById('fsContactSubmitBtn').textContent = 'Adicionar';
        fsContactModal?.show();
    }

    /**
     * Show edit contact modal
     */
    function fsShowEditContactModal(contactId, name, role, phone) {
        document.getElementById('fsContactId').value = contactId;
        document.getElementById('fsContactProjectId').value = fsSelectedProjectId;
        document.getElementById('fsContactName').value = name || '';
        document.getElementById('fsContactRole').value = role || '';
        document.getElementById('fsContactPhone').value = phone || '';
        document.getElementById('fsContactModalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i>Editar Contacto';
        document.getElementById('fsContactSubmitBtn').textContent = 'Save';
        fsContactModal?.show();
    }

    /**
     * Save contact (add or update)
     */
    async function fsSaveContact() {
        const id = document.getElementById('fsContactId').value.trim();
        const projectId = document.getElementById('fsContactProjectId').value.trim();
        const name = document.getElementById('fsContactName').value.trim();
        const role = document.getElementById('fsContactRole').value.trim();
        const phone = document.getElementById('fsContactPhone').value.trim();

        if (!name) {
            fsAlertError('O nome é obrigatório');
            return;
        }

        const fd = new FormData();
        fd.append('action', id ? 'update_contact' : 'add_contact');
        if (id) fd.append('id', id);
        fd.append('project_id', projectId);
        fd.append('name', name);
        fd.append('role', role);
        fd.append('phone', phone);

        try {
            const resp = await fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            }).then(r => r.json());

            if (resp.success) {
                fsAlert(id ? 'Contacto atualizado com sucesso' : 'Contacto adicionado com sucesso');
                fsContactModal?.hide();
                // Reload contacts list
                fsLoadProjectContacts(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectContactsListId);
                // Reload timeline to show the new entry
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
            } else {
                fsAlertError(resp.error || 'Error saving contact');
            }
        } catch (err) {
            console.error('Error saving contact:', err);
            fsAlertError('Error saving contact');
        }
    }

    /**
     * Delete contact
     */
    async function fsDeleteContact(contactId, projectId, projectSupervisorId, elementId) {
        const fd = new FormData();
        fd.append('action', 'delete_contact');
        fd.append('id', contactId);

        try {
            const resp = await fetch(window.getAjaxUrl('ajax/manage_field_supervision.php'), {
                method: 'POST',
                credentials: 'include',
                body: fd
            }).then(r => r.json());

            if (resp.success) {
                fsAlert('Contacto eliminado');
                fsLoadProjectContacts(projectId, projectSupervisorId, elementId);
                // Reload timeline
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
            } else {
                fsAlertError(resp.error || 'Error deleting contact');
            }
        } catch (err) {
            console.error('Error deleting contact:', err);
            fsAlertError('Error deleting contact');
        }
    }

    function fsLoadProjectProblems(projectId, elementId) {
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_problems&project_id=${encodeURIComponent(projectId)}`), {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                const el = document.getElementById(elementId || 'fsProblemsList');
                if (!el) return;
                if (!resp.success) {
                    el.innerHTML = `<div class='text-danger'>${escapeHtml(resp.error||'Error')}</div>`;
                    return;
                }
                const list = resp.data || [];
                if (!list.length) {
                    el.innerHTML = `<div class='text-muted'>No problems reported.</div>`;
                    return;
                }
                el.innerHTML = list.map(p => `
                    <div class='card mb-2'>
                      <div class='card-body p-2 d-flex'>
                                                    <div>
                                                    <strong>${escapeHtml(p.title)}</strong><br>
                                                                                                        <small class='text-muted'>${escapeHtml(p.description||'')}</small>
                                                                                                        ${p.responsible_name ? `<div><small class='text-muted'>Assigned to: ${escapeHtml(p.responsible_name)}</small></div>` : ''}
                                                </div>
                                                <div class='ms-auto text-end'>
                                                    <span class='badge ${p.status==='open'?'bg-danger':'bg-secondary'}'>${escapeHtml(p.status)}</span><br>
                                                    <button class='btn btn-compact btn-outline-secondary' onclick='fsOpenProblem(${p.id})' title='Details' aria-label='Details'><i class='fas fa-info-circle'></i></button>
                                                    ${ ((window.CWUserRole === 'admin') || (window.CWUserRole === 'gestor') || (parseInt(window.CWUserId) === parseInt(p.reported_by)) || (parseInt(window.CWUserId) === parseInt(p.responsible_user_id)) ) ? `<button class='btn btn-compact btn-outline-success ms-1 btn-mark-treated' data-id='${p.id}' data-status='${p.status||"open"}' title='${p.status === 'resolved' ? 'Reopen' : 'Mark Treated'}' aria-label='${p.status === 'resolved' ? 'Reopen' : 'Mark Treated'}'>${p.status === 'resolved' ? "<i class='fas fa-undo'></i>" : "<i class='fas fa-check'></i>"}</button>` : '' }
                                                    ${ ((window.CWUserRole === 'admin') || (window.CWUserRole === 'gestor') || (parseInt(window.CWUserId) === parseInt(p.reported_by))) ? `<button class='btn btn-compact btn-outline-danger ms-1 btn-delete-problem' data-id='${p.id}' title='Delete' aria-label='Delete'><i class='fas fa-trash'></i></button>` : '' }
                                                </div>
                      </div>
                    </div>
                `).join('');
                // Attach handlers immediately after DOM is updated
                [...el.querySelectorAll('.btn-delete-problem')].forEach(b => b.addEventListener('click', async (ev) => {
                    const id = b.dataset.id;
                    if (await fsConfirmDelete('Delete this issue?', 'Issue')) {
                        fsDeleteProblem(id, projectId);
                    }
                }));
                [...el.querySelectorAll('.btn-mark-treated')].forEach(b => b.addEventListener('click', async (ev) => {
                    const id = b.dataset.id;
                    const currentStatus = b.dataset.status || 'open';
                    const newStatus = (currentStatus === 'resolved') ? 'open' : 'resolved';
                    const confirmMsg = (newStatus === 'resolved') ? 'Mark this issue as resolved?' : 'Reopen this issue?';
                    if (await fsConfirm(confirmMsg, newStatus === 'resolved' ? 'Mark Resolved' : 'Reopen Issue')) {
                        // optimistically update label/data while request runs
                        b.dataset.status = newStatus;
                        // Update icon and tooltip/title instead of textual label
                        if (newStatus === 'resolved') {
                            b.innerHTML = "<i class='fas fa-undo'></i>";
                            b.setAttribute('title', 'Reopen');
                            b.setAttribute('aria-label', 'Reopen');
                        } else {
                            b.innerHTML = "<i class='fas fa-check'></i>";
                            b.setAttribute('title', 'Mark Resolved');
                            b.setAttribute('aria-label', 'Mark Resolved');
                        }
                        fsUpdateProblemStatus(id, newStatus, projectId);
                    }
                }));
            }).catch(err => console.error('load project problems err', err));
    }

    function fsLoadVisitsForProject(projectId, elementId) {
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_visits&project_id=${encodeURIComponent(projectId)}`), {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                const el = document.getElementById(elementId || 'fsProjectVisitsList');
                if (!el) return;
                if (!resp.success) {
                    el.innerHTML = `<div class='text-danger'>${escapeHtml(resp.error||'Error')}</div>`;
                    return;
                }
                const list = resp.data || [];
                if (!list.length) {
                    el.innerHTML = `<div class='text-muted'>No visits yet for this project.</div>`;
                    return;
                }
                el.innerHTML = `<ul class='list-group'>` + list.map(v => `
                    <li class='list-group-item d-flex align-items-center'>
                        <div>
                            <strong>${escapeHtml(v.title)}</strong><br>
                            <small>${escapeHtml(v.type)} • ${escapeHtml(v.date_start||'')}</small>
                        </div>
                        <div class='ms-auto'>
                            <span class='small text-muted me-2'>${escapeHtml(v.status)}</span>
                            ${ ((window.CWUserRole === 'admin') || (window.CWUserRole === 'gestor') || (parseInt(window.CWUserId) === parseInt(v.supervisor_user_id))) ? `<button class='btn btn-compact btn-outline-danger ms-1 btn-delete-visit' data-id='${v.id}' title='Delete visit' aria-label='Delete visit'><i class='fas fa-trash'></i></button>` : '' }
                        </div>
                    </li>
                `).join('') + `</ul>`;
                // Attach handlers for delete buttons
                [...el.querySelectorAll('.btn-delete-visit')].forEach(b => b.addEventListener('click', async (ev) => {
                    const id = b.dataset.id;
                    if (await fsConfirmDelete('Delete this visit? This action cannot be undone.', 'Visit')) {
                        fsDeleteVisit(id, projectId);
                    }
                }));
            }).catch(err => console.error('load visits for project err', err));
    }

    // show modal for timeline entry
    // Add timeline note functionality removed: timeline entries are automatically created from Problems and Visits

    // show problem modal
    function fsShowAddProblemModal() {
        if (!fsSelectedProjectId) {
            fsAlert('Seleccione um projecto primeiro');
            return;
        }
        document.getElementById('fsProblemProjectId').value = fsSelectedProjectId;
        document.getElementById('fsProblemTitle').value = '';
        document.getElementById('fsProblemDesc').value = '';
        document.getElementById('fsProblemSeverity').value = 'minor';
        // populate user list
        const appRoot = window.APP_ROOT || (window.BASE_URL ? new URL(window.BASE_URL).pathname : (window.location.pathname.includes('cleanwattsportal') ? '/cleanwattsportal/' : (window.location.pathname.includes('comissionamentov2') ? '/ComissionamentoV2/' : '/')));
        fetch(`${appRoot}ajax/get_field_supervision_users.php`, {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                const sel = document.getElementById('fsProblemResponsible');
                if (!sel) return;
                sel.innerHTML = '<option value="">Unassigned</option>' + (resp.data || []).map(u => `<option value="${u.id}">${escapeHtml(u.full_name||u.username)}</option>`).join('');
                fsProblemModal?.show();
            }).catch(err => {
                console.error('users fetch err', err);
                fsProblemModal?.show();
            });
    }

    function fsSubmitProblemForm() {
        const projectId = document.getElementById('fsProblemProjectId').value;
        const title = document.getElementById('fsProblemTitle').value.trim();
        const desc = document.getElementById('fsProblemDesc').value.trim();
        const severity = document.getElementById('fsProblemSeverity').value;
        const responsible = document.getElementById('fsProblemResponsible').value;
        if (!projectId || !title) {
            fsAlert('Projecto e título são obrigatórios');
            return;
        }
        const fd = new FormData();
        fd.append('action', 'add_problem');
        fd.append('project_id', projectId);
        fd.append('title', title);
        if (desc) fd.append('description', desc);
        fd.append('severity', severity);
        if (responsible) fd.append('responsible_user_id', responsible);
        fetch(window.getAjaxUrl ? window.getAjaxUrl('ajax/manage_field_supervision.php') : 'ajax/manage_field_supervision.php', {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return fsAlertError(resp.error || 'Erro');
                fsProblemModal?.hide();
                fsLoadProjectProblems(projectId, fsSelectedProjectProblemsListId);
                // refresh timeline (problems show up in timeline)
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
            })
            .catch(err => console.error('submit problem err', err));
    }

    // open problem details: show notes and option to add note/change status
    function fsOpenProblem(problemId) {
        fetch(window.getAjaxUrl(`ajax/manage_field_supervision.php?action=list_problem_notes&problem_id=${encodeURIComponent(problemId)}`), {
                credentials: 'include'
            })
            .then(r => r.json()).then(resp => {
                if (!resp.success) {
                    fsAlertError(resp.error || 'Erro');
                    return;
                }
                const notes = resp.data || [];
                const txt = notes.map(n => `${n.created_at}: ${n.note_text}`).join('\n\n');
                fsAlert('Notas do problema:\n\n' + (txt || 'Sem notas ainda') + '\n\nPode adicionar uma nota ou alterar o estado na interface.', 'Notas do Problema');
            });
    }

    function fsDeleteProblem(problemId, projectId) {
        if (!problemId) return;
        const fd = new FormData();
        fd.append('action', 'delete_problem');
        fd.append('id', problemId);
        fetch(window.getAjaxUrl ? window.getAjaxUrl('ajax/manage_field_supervision.php') : 'ajax/manage_field_supervision.php', {
            method: 'POST',
            credentials: 'include',
            body: fd
        }).then(r => r.json()).then(resp => {
            if (!resp.success) return fsAlertError(resp.error || 'Erro');
            // refresh problems and timeline for the project if provided
            if (projectId) {
                fsLoadProjectProblems(projectId, fsSelectedProjectProblemsListId);
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
            }
            // refresh the combined Visits/Problems table globally
            fsLoadVisits();
        }).catch(err => console.error('delete problem err', err));
    }

    function fsDeleteVisit(visitId, projectId) {
        if (!visitId) return;
        const fd = new FormData();
        fd.append('action', 'delete_visit');
        fd.append('id', visitId);
        fetch(window.getAjaxUrl ? window.getAjaxUrl('ajax/manage_field_supervision.php') : 'ajax/manage_field_supervision.php', {
            method: 'POST',
            credentials: 'include',
            body: fd
        }).then(r => r.json()).then(resp => {
            if (!resp.success) return fsAlertError(resp.error || 'Erro');
            // refresh the project's visits and timeline
            if (projectId) {
                fsLoadVisitsForProject(projectId, fsSelectedProjectVisitsListId);
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
            }
            // if the global table is visible, refresh it too
            fsLoadVisits();
        }).catch(err => console.error('delete visit err', err));
    }

    function fsUpdateProblemStatus(problemId, status, projectId) {
        if (!problemId || !status) return;
        const fd = new FormData();
        fd.append('action', 'update_problem');
        fd.append('id', problemId);
        fd.append('status', status);
        console.log('fsUpdateProblemStatus: request', problemId, status, projectId);
        fetch(window.getAjaxUrl ? window.getAjaxUrl('ajax/manage_field_supervision.php') : 'ajax/manage_field_supervision.php', {
            method: 'POST',
            credentials: 'include',
            body: fd
        }).then(r => r.json()).then(resp => {
            console.log('fsUpdateProblemStatus: response', resp);
            if (!resp.success) return fsAlert(resp.error || 'Error');
            // refresh problems & timeline
            if (projectId) {
                fsLoadProjectProblems(projectId, fsSelectedProjectProblemsListId);
                fsLoadProjectTimeline(projectId, fsSelectedProjectSupervisorId, fsSelectedProjectTimelineListId, fsShouldShowNotesInTimeline());
            }

            // Change project status - handled by top-level function
            fsLoadVisits();
        }).catch(err => console.error('update problem err', err));
    }

    // ========================================
    // Custom Modal System (replaces native alert/confirm)
    // ========================================

    // Alert Modal - shows message with OK button
    function fsAlert(message, title = 'Aviso') {
        return new Promise((resolve) => {
            const modalId = 'fsAlertModal';
            let modal = document.getElementById(modalId);

            if (!modal) {
                modal = document.createElement('div');
                modal.id = modalId;
                modal.className = 'modal fade';
                modal.tabIndex = -1;
                modal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i><span id="fsAlertTitle">Aviso</span></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p id="fsAlertMessage" class="mb-0"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            document.getElementById('fsAlertTitle').textContent = title;
            document.getElementById('fsAlertMessage').textContent = message;

            const bsModal = new bootstrap.Modal(modal);
            modal.addEventListener('hidden.bs.modal', () => resolve(), {
                once: true
            });
            bsModal.show();
        });
    }

    // Error Alert - red themed
    function fsAlertError(message, title = 'Erro') {
        return new Promise((resolve) => {
            const modalId = 'fsAlertErrorModal';
            let modal = document.getElementById(modalId);

            if (!modal) {
                modal = document.createElement('div');
                modal.id = modalId;
                modal.className = 'modal fade';
                modal.tabIndex = -1;
                modal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i><span id="fsAlertErrorTitle">Erro</span></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p id="fsAlertErrorMessage" class="mb-0"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            document.getElementById('fsAlertErrorTitle').textContent = title;
            document.getElementById('fsAlertErrorMessage').textContent = message;

            const bsModal = new bootstrap.Modal(modal);
            modal.addEventListener('hidden.bs.modal', () => resolve(), {
                once: true
            });
            bsModal.show();
        });
    }

    // Confirm Modal - shows message with Cancel/Confirm buttons
    function fsConfirm(message, title = 'Confirm', confirmText = 'Confirm', cancelText = 'Cancel', isDanger = false) {
        return new Promise((resolve) => {
            const modalId = 'fsConfirmModal';
            let modal = document.getElementById(modalId);

            if (!modal) {
                modal = document.createElement('div');
                modal.id = modalId;
                modal.className = 'modal fade';
                modal.tabIndex = -1;
                modal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header" id="fsConfirmHeader">
                                <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i><span id="fsConfirmTitle">Confirm</span></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p id="fsConfirmMessage" class="mb-0"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="fsConfirmCancel">Cancel</button>
                                <button type="button" class="btn" id="fsConfirmOk">Confirm</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            const header = document.getElementById('fsConfirmHeader');
            const okBtn = document.getElementById('fsConfirmOk');

            header.className = isDanger ? 'modal-header bg-danger text-white' : 'modal-header bg-primary text-white';
            okBtn.className = isDanger ? 'btn btn-danger' : 'btn btn-primary';

            document.getElementById('fsConfirmTitle').textContent = title;
            document.getElementById('fsConfirmMessage').textContent = message;
            document.getElementById('fsConfirmCancel').textContent = cancelText;
            okBtn.textContent = confirmText;

            const bsModal = new bootstrap.Modal(modal);

            const handleConfirm = () => {
                bsModal.hide();
                resolve(true);
            };

            const handleCancel = () => {
                resolve(false);
            };

            okBtn.onclick = handleConfirm;
            modal.addEventListener('hidden.bs.modal', handleCancel, {
                once: true
            });

            bsModal.show();
        });
    }

    // Delete Confirm - red themed specifically for deletions
    function fsConfirmDelete(message, itemName = '') {
        const title = 'Delete' + (itemName ? ' ' + itemName : '');
        return fsConfirm(message, title, 'Delete', 'Cancel', true);
    }
</script>
<?php include 'includes/footer.php'; ?>