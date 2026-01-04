<style>
    /* Credentials table improvements */
    .pc-cred-row {
        min-height: 44px;
    }

    .pc-cred-notes {
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }

    .pc-cred-secret {
        min-width: 120px;
        display: flex;
        align-items: center;
        gap: 6px;
    }


    .pc-cred-secret .pc-secret-mask {
        flex: 1 1 auto;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    .pc-cred-secret .pc-secret-revealed {
        flex: 1 1 auto;
        font-family: monospace;
        color: #333;
        background: #f8f9fa;
        border-radius: 3px;
        padding: 2px 4px;
        white-space: pre-wrap;
        word-break: break-all;
        user-select: all;
    }
    }
</style>
<style>
    /* Credentials table improvements */
    .pc-cred-row {
        min-height: 44px;
    }

    .pc-cred-notes {
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }

    .pc-cred-secret {
        min-width: 120px;
    }
</style>
<?php
// 🔒 Require login
require_once 'includes/auth.php';
requireLogin();

require_once 'config/database.php';
require_once 'includes/audit.php';

include 'includes/header.php';
?>
<div class="container py-5 main-content-container">
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-link text-decoration-none me-2"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-4">
            <div class="d-flex align-items-center mb-2">
                <div style="font-size:1.8rem; color:#2CCCD3;" class="me-2"><i class="fas fa-key"></i></div>
                <div>
                    <h3 class="fw-bold mb-0">Procedures & Credentials</h3>
                    <small class="text-muted">Central repository for documents and secure credentials</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" id="pcTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pcProcedures" type="button" role="tab">Procedures</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pcCredentials" type="button" role="tab">Credentials</button>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="pcProcedures" role="tabpanel">
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input id="pcProcSearch" class="form-control" placeholder="Search procedures..." />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select id="pcProcCategory" class="form-select"></select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" id="pcProcRefresh"><i class="fas fa-sync"></i> Refresh</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="pcProceduresTbody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="pcCredentials" role="tabpanel">
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="pcAddCredBtn"><i class="fas fa-plus"></i> Add Credential</button>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input id="pcCredSearch" class="form-control" placeholder="Search credentials..." />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select id="pcCredCategoryFilter" class="form-select"></select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" id="pcCredRefresh"><i class="fas fa-sync"></i> Refresh</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0 table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Name</th>
                                    <th style="width: 10%;">Category</th>
                                    <th style="width: 15%;">User</th>
                                    <th style="width: 15%;">IP</th>
                                    <th style="width: 15%;">Secret</th>
                                    <th style="width: 15%;">Hint</th>
                                    <th style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pcCredentialsTbody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        Loading credentials...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    try {
        $activityLogs = getAuditLog(['days' => 7], 20);
    } catch (Exception $e) {
        $activityLogs = [];
    }
    include 'includes/activity_timeline.php';
    ?>
</div>
<!-- Page Loading Overlay -->
<div id="pcLoadingOverlay" style="display:none;position:fixed;inset:0;z-index:2000;align-items:center;justify-content:center;background:rgba(255,255,255,0.85);">
    <div style="text-align:center;max-width:360px;padding:20px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.12);background:linear-gradient(180deg,#fff,#f7fbfb);">
        <div class="spinner-border text-primary" role="status" style="width:48px;height:48px;margin-bottom:10px;"><span class="visually-hidden">Loading...</span></div>
        <div id="pcLoadingOverlayMsg" style="font-weight:600;color:#0b7285">Loading...</div>
        <div id="pcLoadingOverlaySub" style="font-size:0.9rem;color:#6c757d;margin-top:6px">Please wait…</div>
    </div>
</div>

<!-- Credential Modal -->
<div class="modal fade" id="pcCredModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pcCredModalTitle">Add Credential</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="pcCredForm">
                <div class="modal-body">
                    <input type="hidden" id="pcCredId" />
                    <div class="mb-3"><label class="form-label">Name</label><input id="pcCredName" class="form-control" required /></div>
                    <div class="mb-3"><label class="form-label">Username (optional)</label><input id="pcCredUsername" class="form-control" /></div>
                    <div class="mb-3"><label class="form-label">Category</label>
                        <select id="pcCredCategory" class="form-select">
                            <option value="">(None)</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Device IP (optional)</label><input id="pcCredIp" class="form-control" placeholder="e.g. 192.168.0.10" /></div>
                    <div class="mb-3"><label class="form-label">Secret</label><textarea id="pcCredSecret" class="form-control" rows="3" required></textarea></div>
                    <div class="mb-3 d-flex flex-column">
                        <div class="d-flex gap-2 align-items-start">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="pcGeneratePassword"><i class="fas fa-key"></i> Generate Strong Password</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pcRevealInModalBtn" title="Reveal Secret in Modal"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="pcGeneratedPassword" class="mt-2 p-2 bg-light rounded d-none">
                            <small class="text-muted">Generated: <span id="pcGenPassText"></span> <button class="btn btn-xs btn-link" onclick="pcUseGeneratedPassword()">Use This</button></small>
                        </div>
                        <small id="pcEditSecretNote" class="form-text text-muted d-none">Secret values are not editable here.</small>
                    </div>
                    <div class="mb-3"><label class="form-label">Observations</label><textarea id="pcCredNotes" class="form-control" rows="4" placeholder="Notes, SOP references, or context for this credential"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Access Log Modal -->
<div class="modal fade" id="pcLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Credential Access Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Notes</th>
                                <th>IP</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody id="pcLogTbody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="pcDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="pcDeleteModalMsg" class="mb-0">Are you sure you want to delete this credential? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="pcDeleteConfirmBtn" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<style>
    .pc-cred-row {
        animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .btn-group .btn {
        transition: all 0.2s ease;
    }

    .btn-group .btn:hover {
        transform: scale(1.05);
    }

    .pc-cred-deleted {
        transition: all 0.5s ease;
        opacity: 0.0;
        transform: translateX(-20px) scale(0.98);
        height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden;
    }

    /* Loading overlay matching Field Supervision style */
    #pcLoadingOverlay {
        display: flex;
    }

    #pcLoadingOverlay[hidden] {
        display: none !important;
    }

    @media (max-width:480px) {
        #pcLoadingOverlay div {
            max-width: 92%;
        }
    }
</style>

<script>
    let pcCredModal;

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Small local helper to escape HTML for rendering user-provided strings
    function escapeHtml(str) {
        if (typeof str !== 'string') return str || '';
        return str.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Convert URLs to clickable anchors while keeping text escaped
    function linkify(text) {
        if (!text) return '—';
        let out = escapeHtml(text);
        const urlRegex = /((https?:\/\/)|(www\.))[\w\-@:%_\+.~#?&//=]+/g;
        out = out.replace(urlRegex, (match) => {
            let url = match;
            if (!/^https?:\/\//i.test(url)) url = 'http://' + url;
            return `<a href="${url}" target="_blank" rel="noopener noreferrer">${escapeHtml(match)}</a>`;
        });
        return out;
    }

    // Loading overlay helpers (re-usable)
    function showLoadingOverlay(msg, sub) {
        const ov = document.getElementById('pcLoadingOverlay');
        if (!ov) return;
        const m = document.getElementById('pcLoadingOverlayMsg');
        const s = document.getElementById('pcLoadingOverlaySub');
        if (m && msg) m.textContent = msg;
        if (s && sub !== undefined) s.textContent = sub;
        ov.style.display = 'flex';
    }

    function hideLoadingOverlay() {
        const ov = document.getElementById('pcLoadingOverlay');
        if (!ov) return;
        ov.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        pcCredModal = new bootstrap.Modal(document.getElementById('pcCredModal'));
        // Delete modal instance & confirm hook
        window.pcDeleteModalObj = new bootstrap.Modal(document.getElementById('pcDeleteModal'));
        document.getElementById('pcDeleteConfirmBtn')?.addEventListener('click', async (e) => {
            // Use a stable reference to the button element and guard against it being removed during async operations
            const btnElem = e.currentTarget || document.getElementById('pcDeleteConfirmBtn');
            const id = btnElem?.dataset?.credId || '';
            if (!id) return;
            if (btnElem) btnElem.disabled = true;
            try {
                await pcDeleteCredential(id, btnElem);
            } finally {
                if (btnElem) try {
                    btnElem.disabled = false;
                } catch (e) {
                    /* ignore */
                }
                if (window.pcDeleteModalObj) window.pcDeleteModalObj.hide();
                // Return focus to Add Credential button to avoid aria-hidden focus warning
                try {
                    document.getElementById('pcAddCredBtn')?.focus();
                } catch (e) {
                    /* ignore */
                }
            }
        });


        document.getElementById('pcCredForm')?.addEventListener('submit', e => {
            e.preventDefault();
            console.log('Form submit triggered');
            pcSaveCredential();
        });

        document.getElementById('pcGeneratePassword')?.addEventListener('click', pcGeneratePassword);
        document.getElementById('pcCredRefresh')?.addEventListener('click', () => pcLoadCredentials());
        document.getElementById('pcCredCategoryFilter')?.addEventListener('change', () => pcLoadCredentials());
        document.getElementById('pcCredSearch')?.addEventListener('keyup', debounce(() => pcLoadCredentials(), 250));
        document.getElementById('pcProcRefresh')?.addEventListener('click', () => pcLoadProcedures());

        // Modal lifecycle: ensure reveal button visibility & cleanup
        const pcCredModalEl = document.getElementById('pcCredModal');
        if (pcCredModalEl) {
            pcCredModalEl.addEventListener('show.bs.modal', () => {
                const id = document.getElementById('pcCredId')?.value || '';
                const revealBtn = document.getElementById('pcRevealInModalBtn');
                if (revealBtn) {
                    if (!id) {
                        revealBtn.classList.add('d-none');
                        pcHideSecretInModal();
                    } else {
                        revealBtn.classList.remove('d-none');
                        revealBtn.dataset.revealed = '0';
                        revealBtn.innerHTML = '<i class="fas fa-eye"></i>';
                        pcHideSecretInModal();
                    }
                }
            });
            pcCredModalEl.addEventListener('hidden.bs.modal', () => {
                pcHideSecretInModal();
                const secretEl = document.getElementById('pcCredSecret');
                if (secretEl && !secretEl.required) secretEl.value = '••••••••';

            });
        }
        document.getElementById('pcProcCategory')?.addEventListener('change', () => pcLoadProcedures());
        document.getElementById('pcProcSearch')?.addEventListener('keyup', debounce(() => pcLoadProcedures(), 250));
        pcLoadCategoriesSelect(document.getElementById('pcProcCategory'), true);
        // Populate credential categories select in modal; if none exist, add initial options
        // Populate credential categories select in modal
        pcLoadCategoriesSelect(document.getElementById('pcCredCategory'), true).then(resp => {
            const sel = document.getElementById('pcCredCategory');
            if (sel && (!resp || !resp.data || resp.data.length === 0)) {
                ['Plataformas', 'Meters', 'Loggers', 'Inverters', 'Modems'].forEach((n) => {
                    sel.innerHTML += `<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`;
                });
            }
        }).catch(() => {});
        // Populate credential categories filter (top of list)
        pcLoadCategoriesSelect(document.getElementById('pcCredCategoryFilter'), true).then(resp => {
            // add fallback options if no categories exist
            const sel = document.getElementById('pcCredCategoryFilter');
            if (sel && (!resp || !resp.data || resp.data.length === 0)) {
                ['Plataformas', 'Meters', 'Loggers', 'Inverters', 'Modems'].forEach((n) => {
                    sel.innerHTML += `<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`;
                });
            }
        }).catch(() => {});
        pcLoadProcedures();
        // Expose whether current user can reveal secrets (used to render inline reveal buttons)
        window.pcUserCanReveal = <?php echo json_encode(in_array($_SESSION['role'] ?? 'operador', ['admin', 'manager', 'gestor'])); ?>;
        // load credentials with overlay
        pcLoadCredentials();
        if (location.hash === '#new-cred' || sessionStorage.getItem('pcOpenNewCred') === '1') {
            sessionStorage.removeItem('pcOpenNewCred');
            setTimeout(() => document.getElementById('pcAddCredBtn')?.click(), 150);
        }
    });

    function pcLoadCategoriesSelect(sel, includeEmpty) {
        // return the fetch promise so callers can await it
        return fetch('ajax/manage_procedures_credentials.php?action=list_categories', {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (!sel) return resp;
                sel.innerHTML = '';
                if (includeEmpty) sel.innerHTML = '<option value="">All</option>';
                const data = resp.data || [];
                data.forEach(c => {
                    sel.innerHTML += `<option value="${c.id}">${escapeHtml(c.name)}</option>`;
                });
                return resp;
            });
    }

    function pcLoadProcedures() {
        const cat = document.getElementById('pcProcCategory')?.value || '';
        const q = document.getElementById('pcProcSearch')?.value || '';
        let url = 'ajax/manage_procedures_credentials.php?action=list_procedures';
        if (cat) url += '&category_id=' + encodeURIComponent(cat);
        if (q) url += '&q=' + encodeURIComponent(q);
        showLoadingOverlay('Loading procedures...', 'Please wait');
        fetch(url, {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                hideLoadingOverlay();
                const tb = document.getElementById('pcProceduresTbody');
                if (!tb) return;
                if (!resp.success) {
                    tb.innerHTML = `<tr><td colspan=4 class='text-danger text-center py-3'>${resp.error||'Error'}</td></tr>`;
                    return;
                }
                const list = resp.data || [];
                if (!list.length) {
                    tb.innerHTML = `<tr><td colspan=4 class='text-muted text-center py-3'>No documents</td></tr>`;
                    return;
                }
                tb.innerHTML = list.map(p => `
        <tr>
          <td><strong>${escapeHtml(p.title||'')}</strong></td>
          <td>${escapeHtml(p.category_name||'—')}</td>
          <td>${escapeHtml(p.version||'—')}</td>
          <td>${p.is_active==1?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Disabled</span>'}</td>
        </tr>`).join('');
            })
            .catch(err => {
                hideLoadingOverlay();
                const tb = document.getElementById('pcProceduresTbody');
                if (tb) tb.innerHTML = `<tr><td colspan=4 class='text-danger text-center py-3'>${err.message}</td></tr>`;
            });
    }

    function pcLoadCredentials() {
        const cat = document.getElementById('pcCredCategoryFilter')?.value || '';
        const q = document.getElementById('pcCredSearch')?.value || '';
        let url = 'ajax/manage_procedures_credentials.php?action=list_credentials';
        if (cat) url += '&category_id=' + encodeURIComponent(cat);
        if (q) url += '&q=' + encodeURIComponent(q);
        showLoadingOverlay('Loading credentials...', 'Please wait');
        fetch(url, {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                hideLoadingOverlay();
                const tb = document.getElementById('pcCredentialsTbody');
                if (!tb) return;
                try {
                    if (!resp.success) {
                        tb.innerHTML = `<tr><td colspan=7 class='text-danger text-center py-3'>${resp.error||'Error'}</td></tr>`;
                        return;
                    }
                    const list = resp.data || [];
                    if (!list.length) {
                        tb.innerHTML = `<tr><td colspan=7 class='text-muted text-center py-3'>No credentials</td></tr>`;
                        return;
                    }
                    tb.innerHTML = list.map(c => `
                    <tr class="pc-cred-row" data-cred-id="${c.id}">
                        <td><strong>${escapeHtml(c.name||'')}</strong><div class="text-muted" style="font-size:11px;">ID:${c.id}</div></td>
                        <td>${escapeHtml(c.category_name||'—')}</td>
                        <td>${escapeHtml(c.username||'—')}</td>
                        <td>${escapeHtml(c.device_ip||'—')}</td>
                        <td class="pc-cred-secret">${window.pcUserCanReveal
                            ? `<span class="pc-secret-mask">••••••</span> <button class="btn btn-sm btn-outline-secondary pc-inline-reveal" title="Reveal" onclick="pcRevealInline(${c.id}, this)"><i class="fas fa-eye"></i></button>`
                            : '<span class="text-muted">••••••</span>'}
                        </td>
                        <td class="pc-cred-notes" title="${escapeHtml(c.secret_hint||'—')}">${linkify(c.secret_hint||'—')}</td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-primary" onclick="pcEditCredential(${c.id}, this)" title="Edit Credential" data-bs-toggle="tooltip"><i class="fas fa-pen"></i></button>
                                <button class="btn btn-outline-danger" data-cred-id="${c.id}" data-cred-name="${escapeHtml(c.name||'')}" onclick="pcShowDeleteModal(this)" title="Delete Credential" data-bs-toggle="tooltip"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>`).join('');
                    // Move these functions outside of pcLoadCredentials
                    function pcRevealInline(id, btn) {
                        const row = btn.closest('tr');
                        if (!row) return;
                        const secretCell = row.querySelector('.pc-cred-secret');
                        if (!secretCell) return;
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        fetch('ajax/manage_procedures_credentials.php?action=get_credential&id=' + id, {
                                credentials: 'include'
                            })
                            .then(r => r.json())
                            .then(resp => {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                                if (!resp.success || !resp.data || !resp.data.secret) {
                                    secretCell.innerHTML = '<span class="text-danger">Error</span>';
                                    return;
                                }
                                // Replace mask with revealed secret, keep button
                                secretCell.innerHTML = `<span class="pc-secret-revealed" title="${escapeHtml(resp.data.secret)}">${escapeHtml(resp.data.secret)}</span> <button class="btn btn-sm btn-outline-secondary pc-inline-hide" title="Hide" onclick="pcHideInline(${id}, this)"><i class="fas fa-eye"></i></button> <button class="btn btn-sm btn-outline-secondary pc-copy-secret" title="Copiar" onclick="pcCopySecret(this)"><i class="fas fa-copy"></i></button>`;

                                function pcCopySecret(btn) {
                                    const secretSpan = btn.parentElement.querySelector('.pc-secret-revealed');
                                    if (!secretSpan) return;
                                    const text = secretSpan.textContent;
                                    if (!text) return;
                                    navigator.clipboard.writeText(text).then(() => {
                                        btn.classList.add('btn-success');
                                        btn.classList.remove('btn-outline-secondary');
                                        setTimeout(() => {
                                            btn.classList.remove('btn-success');
                                            btn.classList.add('btn-outline-secondary');
                                        }, 1200);
                                    });
                                }
                            })
                            .catch(() => {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-eye"></i>';
                                secretCell.innerHTML = '<span class="text-danger">Error</span>';
                            });
                    }

                    function pcHideInline(id, btn) {
                        const row = btn.closest('tr');
                        if (!row) return;
                        const secretCell = row.querySelector('.pc-cred-secret');
                        if (!secretCell) return;
                        secretCell.innerHTML = `<span class="pc-secret-mask">••••••</span> <button class="btn btn-sm btn-outline-secondary pc-inline-reveal" title="Reveal" onclick="pcRevealInline(${id}, this)"><i class="fas fa-eye"></i></button>`;
                    }
                    // Initialize tooltips for new buttons
                    const tooltipTriggerList = tb.querySelectorAll('[data-bs-toggle="tooltip"]');
                    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
                } catch (e) {
                    console.error('render credentials error', e);
                    tb.innerHTML = `<tr><td colspan=7 class='text-danger text-center py-3'>${e.message}</td></tr>`;
                }

                // Add click handler to expand long notes in place
                document.querySelectorAll('.pc-cred-notes a').forEach(a => a.addEventListener('click', e => e.stopPropagation()));
                // Show full hint in modal if clicked
                document.querySelectorAll('.pc-cred-notes').forEach(td => {
                    td.addEventListener('click', function(e) {
                        const hint = td.getAttribute('title');
                        if (hint && hint !== '—') {
                            pcShowHintModal(hint);
                        }
                    });
                });
                // Modal for full hint display
                function pcShowHintModal(hint) {
                    let modal = document.getElementById('pcHintModal');
                    if (!modal) {
                        modal = document.createElement('div');
                        modal.id = 'pcHintModal';
                        modal.className = 'modal fade';
                        modal.tabIndex = -1;
                        modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Full Hint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre style="white-space:pre-wrap;word-break:break-word;">${escapeHtml(hint)}</pre>
                </div>
            </div>
        </div>`;
                        document.body.appendChild(modal);
                    } else {
                        modal.querySelector('.modal-body pre').textContent = hint;
                    }
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }

            })
            .catch(err => {
                const tb = document.getElementById('pcCredentialsTbody');
                if (tb) tb.innerHTML = `<tr><td colspan=7 class='text-danger text-center py-3'>${err.message}</td></tr>`;
            });
    }

    function pcSaveCredential() {
        const id = document.getElementById('pcCredId').value.trim();
        const name = document.getElementById('pcCredName').value.trim();
        const username = document.getElementById('pcCredUsername').value.trim();
        const secret = document.getElementById('pcCredSecret').value.trim();
        const notes = document.getElementById('pcCredNotes')?.value.trim();
        if (!name || (!id && !secret)) {
            alert('Name and Secret are required when creating a credential');
            return;
        }
        console.log('Saving credential:', {
            id,
            name,
            username,
            secret, // Mostra sempre o valor real do campo
            notes
        });
        const fd = new FormData();
        fd.append('action', id ? 'update_credential' : 'create_credential');
        if (id) fd.append('id', id);
        fd.append('name', name);
        // include secret on create, or when editing only when non-empty (updates existing secret)
        if (!id) fd.append('secret', secret);
        else if (secret) fd.append('secret', secret);
        if (username) fd.append('username', username);
        if (notes) fd.append('secret_hint', notes);
        const catId = document.getElementById('pcCredCategory')?.value || '';
        if (catId) fd.append('category_id', catId);
        const deviceIp = document.getElementById('pcCredIp')?.value.trim();
        if (deviceIp !== '') {
            if (!isValidIp(deviceIp)) {
                alert('Invalid IP address');
                return;
            }
            fd.append('device_ip', deviceIp);
        } else if (id) {
            // Explicitly send empty value when editing so server can clear the field if desired
            fd.append('device_ip', '');
        }
        // note: no access_role sent anymore
        showLoadingOverlay('Saving credential...', 'Please wait');
        // Debug: log whether secret is included in the FormData
        try {
            const entries = [];
            for (const e of fd.entries()) entries.push(e[0]);
            console.log('FormData keys:', entries);
        } catch (e) {
            /* ignore */
        }
        fetch('ajax/manage_procedures_credentials.php', {
                method: 'POST',
                credentials: 'include',
                body: fd
            })
            .then(r => {
                console.log('Response status:', r.status);
                return r.json();
            })
            .then(resp => {
                console.log('Response:', resp);
                hideLoadingOverlay();
                if (!resp.success) {
                    alert(resp.error || 'Error');
                    return;
                }
                pcCredModal?.hide();
                // ensure focus returns to the Add button so modal focus isn't retained (fixes aria-hidden warning)
                setTimeout(() => document.getElementById('pcAddCredBtn')?.focus(), 250);
                pcLoadCredentials();
            })
            .catch(err => {
                hideLoadingOverlay();
                console.error('save credential error', err);
                alert('Error saving: ' + err.message);
            });
    }

    // Reveal credential secret (fetches decrypted secret when authorised)
    async function pcRevealCredential(id) {
        try {
            const r = await fetch('ajax/manage_procedures_credentials.php?action=get_credential&id=' + encodeURIComponent(id), {
                credentials: 'include'
            });
            const resp = await r.json();
            if (!resp.success) return alert(resp.error || 'Error');
            const data = resp.data;
            if (!data.secret) {
                return alert('You are not authorised to view this secret');
            }
            // Show in prompt with option to copy
            if (confirm('Secret for "' + (data.name || '') + '" will be shown. Click OK to copy to clipboard.')) {
                await navigator.clipboard.writeText(data.secret);
                // record access
                await fetch('ajax/manage_procedures_credentials.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: new URLSearchParams({
                        action: 'record_access',
                        credential_id: id,
                        access_action: 'view',
                        notes: 'revealed and copied via UI'
                    })
                });
                alert('Secret copied to clipboard — will clear in 30s');
                clearClipboardAfter(30);
            }
        } catch (err) {
            console.error(err);
            alert('Error retrieving secret');
        }
    }

    // Copy credential without revealing (backend still records)
    async function pcCopyCredential(id) {
        try {
            const r = await fetch('ajax/manage_procedures_credentials.php?action=get_credential&id=' + encodeURIComponent(id), {
                credentials: 'include'
            });
            const resp = await r.json();
            if (!resp.success) return alert(resp.error || 'Error');
            const data = resp.data;
            if (!data.secret) return alert('You are not authorised to copy this secret');
            await navigator.clipboard.writeText(data.secret);
            await fetch('ajax/manage_procedures_credentials.php', {
                method: 'POST',
                credentials: 'include',
                body: new URLSearchParams({
                    action: 'record_access',
                    credential_id: id,
                    access_action: 'copy',
                    notes: 'copied to clipboard via UI'
                })
            });
            alert('Secret copied to clipboard — will clear in 30s');
            clearClipboardAfter(30);
        } catch (err) {
            console.error(err);
            alert('Error copying secret');
        }
    }

    // Reveal inline in the table (for authorized users only)
    async function pcRevealInline(id, btn) {
        try {
            if (!window.pcUserCanReveal) return alert('Not authorised');
            const currently = btn.dataset.revealed === '1';
            const row = document.querySelector('.pc-cred-row[data-cred-id="' + id + '"]');
            if (currently) {
                // hide
                row.querySelector('.pc-secret-mask').textContent = '••••••';
                btn.dataset.revealed = '0';
                btn.innerHTML = '<i class="fas fa-eye"></i>';
                return;
            }
            btn.disabled = true;
            const r = await fetch('ajax/manage_procedures_credentials.php?action=get_credential&id=' + encodeURIComponent(id), {
                credentials: 'include'
            });
            const resp = await r.json();
            if (!resp.success) return alert(resp.error || 'Error');
            const data = resp.data || {};
            if (!data.secret) return alert('You are not authorised to view this secret');
            // show secret inline
            row.querySelector('.pc-secret-mask').textContent = data.secret;
            btn.dataset.revealed = '1';
            btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            // record access
            await fetch('ajax/manage_procedures_credentials.php', {
                method: 'POST',
                credentials: 'include',
                body: new URLSearchParams({
                    action: 'record_access',
                    credential_id: id,
                    access_action: 'view',
                    notes: 'revealed inline'
                })
            });
            // auto-hide after 30s
            setTimeout(() => {
                if (btn.dataset.revealed === '1') {
                    try {
                        row.querySelector('.pc-secret-mask').textContent = '••••••';
                        btn.dataset.revealed = '0';
                        btn.innerHTML = '<i class="fas fa-eye"></i>';
                    } catch (e) {}
                }
            }, 30000);
        } catch (err) {
            console.error('pcRevealInline error', err);
            alert('Error revealing secret');
        } finally {
            btn.disabled = false;
        }
    }

    function clearClipboardAfter(seconds) {
        try {
            setTimeout(async () => {
                try {
                    await navigator.clipboard.writeText('');
                } catch (e) {
                    /* ignore */
                }
            }, (seconds || 30) * 1000);
        } catch (e) {
            console.warn('Clipboard clear not supported', e);
        }
    }



    function pcShowDeleteModal(el) {
        const id = el?.dataset?.credId || el || '';
        const name = el?.dataset?.credName || '';
        const msg = document.getElementById('pcDeleteModalMsg');
        const btn = document.getElementById('pcDeleteConfirmBtn');
        if (msg) msg.textContent = `Delete "${name}" (ID ${id})? This action cannot be undone.`;
        if (btn) btn.dataset.credId = id;
        if (window.pcDeleteModalObj) window.pcDeleteModalObj.show();
    }

    // Delete credential (server call) - global function so inline onclick works
    async function pcDeleteCredential(id, btn) {
        console.log('pcDeleteCredential called', id);
        try {
            showLoadingOverlay('Deleting credential...', 'Please wait');
            const fd = new FormData();
            fd.append('action', 'delete_credential');
            fd.append('id', id);
            const r = await fetch('ajax/manage_procedures_credentials.php', {
                method: 'POST',
                credentials: 'include',
                body: fd
            });
            const resp = await r.json();
            console.log('delete response', resp);
            hideLoadingOverlay();
            if (!resp.success) return alert(resp.error || 'Error deleting');
            // Visual feedback: find row by data attribute if button not supplied
            const row = btn ? btn.closest('.pc-cred-row') : document.querySelector(`.pc-cred-row[data-cred-id="${id}"]`);
            if (row) {
                row.classList.add('pc-cred-deleted');
                setTimeout(() => row.remove(), 500);
            }
            // record deletion in UI
            pcLoadCredentials();
        } catch (err) {
            hideLoadingOverlay();
            console.error('delete error', err);
            alert('Error deleting: ' + err.message);
        }
    }

    async function pcViewLog(id) {
        try {
            const r = await fetch('ajax/manage_procedures_credentials.php?action=access_log&credential_id=' + encodeURIComponent(id), {
                credentials: 'include'
            });
            const resp = await r.json();
            if (!resp.success) return alert(resp.error || 'Error');
            const logs = resp.data || [];
            const tbody = document.getElementById('pcLogTbody');
            tbody.innerHTML = '';
            if (!logs.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-muted">No access records</td></tr>';
            } else {
                tbody.innerHTML = logs.map(l => `
                    <tr>
                        <td>${escapeHtml(l.accessed_at||'')}</td>
                        <td>${escapeHtml(l.user_id||'')}</td>
                        <td>${escapeHtml(l.action||'')}</td>
                        <td>${escapeHtml(l.notes||'')}</td>
                        <td>${escapeHtml(l.ip_address||'')}</td>
                        <td style="max-width:300px;word-break:break-word;">${escapeHtml(l.user_agent||'')}</td>
                    </tr>
                `).join('');
            }
            const m = new bootstrap.Modal(document.getElementById('pcLogModal'));
            m.show();
        } catch (err) {
            console.error(err);
            alert('Error loading access log');
        }
    }

    function pcGeneratePassword() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 16; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('pcGenPassText').textContent = password;
        document.getElementById('pcGeneratedPassword').classList.remove('d-none');
    }

    // Open credential for editing
    async function pcEditCredential(id, btn) {
        try {
            console.log('pcEditCredential loading id', id);
            showLoadingOverlay('Loading credential...', 'Fetching data');
            const r = await fetch('ajax/manage_procedures_credentials.php?action=get_credential&id=' + encodeURIComponent(id), {
                credentials: 'include'
            });
            console.log('pcEditCredential fetch status', r.status);
            const resp = await r.json();
            hideLoadingOverlay();
            console.log('pcEditCredential response', resp);
            console.log('pcEditCredential secret_hash:', resp.data ? resp.data.secret_hash : null);
            if (!resp.success) return alert(resp.error || 'Error');
            const data = resp.data || {};
            document.getElementById('pcCredForm').reset();
            const idEl = document.getElementById('pcCredId');
            if (idEl) idEl.value = id;
            const nameEl = document.getElementById('pcCredName');
            if (nameEl) nameEl.value = data.name || '';
            const userEl = document.getElementById('pcCredUsername');
            if (userEl) userEl.value = data.username || '';
            const ipEl = document.getElementById('pcCredIp');
            if (ipEl) ipEl.value = data.device_ip || '';
            const catEl = document.getElementById('pcCredCategory');
            if (catEl) catEl.value = data.category_id || '';
            const titleEl = document.getElementById('pcCredModalTitle');
            if (titleEl) titleEl.textContent = 'Edit Credential';
            // set observations
            const notesEl = document.getElementById('pcCredNotes');
            if (notesEl) notesEl.value = data.secret_hint || '';
            // secret is editable here (leave blank to keep existing secret)
            const secretEl = document.getElementById('pcCredSecret');
            if (secretEl) {
                secretEl.removeAttribute('readonly');
                secretEl.required = false;
                // Keep masked placeholder but leave empty so user can input new secret if desired
                secretEl.value = '';
            }
            // inform user that leaving secret blank keeps it unchanged
            const editNote = document.getElementById('pcEditSecretNote');
            if (editNote) {
                editNote.classList.remove('d-none');
                editNote.textContent = 'Leave the secret blank to keep the current value; fill it to update.';
            }
            document.getElementById('pcGeneratePassword')?.classList.add('d-none');

            // ensure secret is masked
            pcHideSecretInModal();
            pcCredModal.show();
        } catch (err) {
            console.error('edit fetch error', err);
            alert('Error loading credential: ' + (err.message || err));
        }
    }

    function pcUseGeneratedPassword() {
        const pass = document.getElementById('pcGenPassText').textContent;
        document.getElementById('pcCredSecret').value = pass;
    }

    function clearSecretInModalAfter(seconds) {
        try {
            if (window._pcSecretClearTimer) clearTimeout(window._pcSecretClearTimer);
            window._pcSecretClearTimer = setTimeout(() => {
                pcHideSecretInModal();
            }, (seconds || 30) * 1000);
        } catch (e) {
            console.warn('clearSecretInModalAfter failed', e);
        }
    }
    // Simple IP (v4/v6) validation
    function isValidIp(ip) {
        if (!ip) return false;
        // IPv4
        const ipv4 = /^(25[0-5]|2[0-4]\d|[01]?\d?\d)(\.(?:25[0-5]|2[0-4]\d|[01]?\d?\d)){3}$/;
        // Basic IPv6 allow hex and colons (rudimentary)
        const ipv6 = /^[0-9a-fA-F:]{2,45}$/;
        return ipv4.test(ip) || ipv6.test(ip);
    }

    function pcHideSecretInModal() {
        const btn = document.getElementById('pcRevealInModalBtn');
        const secretEl = document.getElementById('pcCredSecret');
        if (secretEl) secretEl.value = '••••••••';
        if (btn) {
            btn.dataset.revealed = '0';
            btn.innerHTML = '<i class="fas fa-eye"></i>';
        }
        if (window._pcSecretClearTimer) {
            clearTimeout(window._pcSecretClearTimer);
            window._pcSecretClearTimer = null;
        }
    }
    document.getElementById('pcGeneratedPassword').classList.add('d-none');

    // Add event listener for 'Add Credential' button
    const pcAddCredBtn = document.getElementById('pcAddCredBtn');
    if (pcAddCredBtn) {
        pcAddCredBtn.addEventListener('click', () => {
            // Reset form fields for a new credential
            const form = document.getElementById('pcCredForm');
            if (form) form.reset();
            document.getElementById('pcCredId').value = '';
            document.getElementById('pcCredName').value = '';
            document.getElementById('pcCredUsername').value = '';
            const secretEl = document.getElementById('pcCredSecret');
            if (secretEl) {
                secretEl.value = '••••••••';
                secretEl.removeAttribute('readonly');
                secretEl.required = true;
            }
            document.getElementById('pcCredNotes').value = '';
            document.getElementById('pcCredIp').value = '';
            document.getElementById('pcCredCategory').value = '';
            document.getElementById('pcCredModalTitle').textContent = 'Add Credential';

            document.getElementById('pcEditSecretNote')?.classList.add('d-none');
            document.getElementById('pcGeneratePassword')?.classList.remove('d-none');
            document.getElementById('pcGeneratedPassword')?.classList.add('d-none');
            pcHideSecretInModal();
            (pcCredModal || new bootstrap.Modal(document.getElementById('pcCredModal'))).show();
        });
    }
</script>
<?php include 'includes/footer.php'; ?>