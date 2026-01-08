/**
 * PUNCH LIST WIDGET MANAGER
 * 
 * Manages the open punch lists widget on the dashboard
 * Loads data, filters, and close item actions
 */

class PunchListManager {
    constructor() {
        this.punchLists = [];
        this.filteredLists = [];
        // Use IEC 62682 priorities (High / Medium / Low)
        this.severityColors = {
            'High': '#dc3545',   // Red
            'Medium': '#fd7e14', // Orange
            'Low': '#28a745'     // Green
        };
        this.severityPriority = {
            'High': 1,
            'Medium': 2,
            'Low': 3
        };
        this.init();
    }

    /**
     * Initialize the widget
     */
    init() {
        this.loadPunchLists();
        // Refresh every 5 minutes
        setInterval(() => this.loadPunchLists(), 5 * 60 * 1000);
        // Start session keep-alive and expiry detection
        try { this.startSessionKeepAlive(); } catch (e) { console.warn('[PUNCH] keep-alive init failed', e); }
    }

    /**
     * Ping server to keep session alive and detect expiry
     */
    pingSession() {
        // Debounced single fetch
        if (this._pinging) return;
        this._pinging = true;
        fetch((window.BASE_URL || '') + 'ajax/check_session.php', { credentials: 'same-origin' })
            .then(async resp => {
                const txt = await resp.text();
                let j = null;
                try { j = JSON.parse(txt); } catch (_) { /* ignore */ }
                if (!resp.ok || (j && j.success === false)) {
                    console.warn('[PUNCH] Session check failed', resp.status, txt.substring(0, 300));
                    this.handleSessionExpired();
                } else {
                    // refresh successful
                    console.log('[PUNCH] session active');
                }
            })
            .catch(err => {
                console.warn('[PUNCH] session check error', err);
            })
            .finally(() => { setTimeout(() => { this._pinging = false; }, 2000); });
    }

    /**
     * Start listeners to keep session alive on activity and periodic checks
     */
    startSessionKeepAlive() {
        // Debounce ping on interaction (max once per minute)
        let lastPing = 0;
        const maybePing = () => {
            const now = Date.now();
            if (now - lastPing > 60 * 1000) {
                lastPing = now;
                this.pingSession();
            }
        };

        ['mousemove', 'keydown', 'click', 'touchstart'].forEach(evt => {
            window.addEventListener(evt, maybePing, { passive: true });
        });

        // Periodic background ping every 4 minutes
        setInterval(() => this.pingSession(), 4 * 60 * 1000);
    }

    /**
     * Handle a detected expired session: notify user and redirect to login
     */
    handleSessionExpired() {
        try {
            // Show a friendly modal if present, otherwise alert and redirect
            const modalEl = document.getElementById('sessionExpiredModal');
            if (modalEl) {
                const okBtn = modalEl.querySelector('.btn-primary');
                if (okBtn) {
                    okBtn.addEventListener('click', () => { window.location.href = 'login.php'; }, { once: true });
                }
                const instance = (typeof bootstrap !== 'undefined') ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
                if (instance) instance.show();
                else alert('Sessão expirada — será redirecionado(a) para o login.');
            } else {
                alert('Sessão expirada — será redirecionado(a) para o login.');
            }
        } catch (e) {
            try { alert('Sessão expirada — será redirecionado(a) para o login.'); } catch (_) { }
        }
        // Redirect after short delay to allow user to read message
        setTimeout(() => { window.location.href = 'login.php'; }, 1200);
    }

    /**
     * Load all open punch lists
     */
    loadPunchLists() {
        // Use relative path so it works on localhost and online deployments
        const apiUrl = 'ajax/get_open_punch_lists.php';

        this.logInfo(`Fetching from: ${apiUrl}`);

        fetch(apiUrl)
            .then(async (response) => {
                this.logInfo(`Response status: ${response.status} ${response.statusText}`);
                const rawText = await response.text();
                if (!response.ok) {
                    let details = '';
                    try {
                        const errJson = JSON.parse(rawText);
                        if (errJson && errJson.error) details = ' - ' + errJson.error;
                    } catch (_) {
                        if (rawText) details = ' - ' + rawText.substring(0, 300);
                    }
                    throw new Error(`HTTP ${response.status}: ${response.statusText}${details}`);
                }
                // Parse JSON from text to be robust against BOM or whitespace
                const data = JSON.parse(rawText || '{}');
                return data;
            })
            .then(data => {
                this.logInfo(`Received data:`, data);
                if (data.success) {
                    this.punchLists = data.data || [];
                    this.filteredLists = [...this.punchLists];
                    this.render();
                    this.logInfo(`✅ Loaded ${data.count} open punch lists`);
                } else {
                    this.logError('Error in response: ' + (data.error || 'Unknown error'));
                    this.renderError(`Error loading: ${data.error || 'Unknown error'}`);
                }
            })
            .catch(error => {
                this.logError('❌ Request error: ' + error.message);
                this.renderError(`Error: ${error.message}`);
            });
    }

    /**
     * Render an error message inside the container
     */
    renderError(message) {
        const container = document.getElementById('punch-list-container');
        if (!container) return;

        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Error loading punch lists.</strong>
                <div class="mt-2"><small>${message}</small></div>
            </div>
        `;
    }

    /**
     * Render the punch list widget
     */
    render() {
        const container = document.getElementById('punch-list-container');
        if (!container) return;

        // Always render the header with filters (even if no items)
        let html = `
            <div class="punch-list-header mb-4">
                <h5 class="mb-3">
                    <i class="fas fa-tasks"></i>
                    Open Punch Lists
                    <span class="badge bg-danger">${this.filteredLists.length}</span>
                </h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="punch-search" class="form-control form-control-sm" 
                               placeholder="Search by project or company...">
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 align-items-center">
                            <select id="punch-project-filter" class="form-control form-control-sm">
                                <option value="">Filter by Project</option>
                                <option value="">✅ All</option>
                                <!-- Project options populated dynamically -->
                            </select>

                            <select id="punch-severity-filter" class="form-control form-control-sm">
                                <option value="">Filter by Priority</option>
                                <option value="">✅ All</option>
                                <option value="High">🔴 High — Immediate action</option>
                                <option value="Medium">🟠 Medium — Prompt action</option>
                                <option value="Low">🟢 Low — Action can be delayed</option>
                            </select>
                            
                            <button id="punch-download-pdf" class="btn btn-sm btn-outline-secondary" title="Download current view as PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        // Show "no results" message if filtered list is empty
        if (this.filteredLists.length === 0) {
            html += `
                <div class="alert alert-success text-center py-4">
                    <i class="fas fa-check-circle"></i>
                    <h5>Excellent!</h5>
                    <p>No open punch lists at the moment</p>
                </div>
            `;
            container.innerHTML = html;
            // Still attach event listeners to filters so user can change filter
            this.addEventListeners();
            return;
        }

        html += `
            <div class="punch-list-scrollable">
                <div class="table-responsive">
                    <table class="table table-sm table-hover punch-list-table">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 15%;">Project</th>
                            <th style="width: 15%;">EPC Company</th>
                            <th style="width: 25%;">Description</th>
                            <th style="width: 10%;">Priority</th>
                            <th style="width: 15%;">Assigned To</th>
                            <th style="width: 10%;">Date</th>
                            <th style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredLists.forEach(item => {
            const severityBadge = this.getSeverityBadge(item.severity_level);
            const dateFormatted = item.created_at ? new Date(item.created_at).toLocaleDateString('en-GB') : '-';
            const assignedToDisplay = item.assigned_to === 'Unassigned'
                ? '<span class="badge bg-secondary">Not assigned</span>'
                : `<span class="badge bg-info">${item.assigned_to}</span>`;
            const closeDisabled = item.closable === false;
            const closeBtnAttrs = closeDisabled
                ? 'data-legacy="1" title="Close this item in the report (legacy source)"'
                : 'title="Mark as completed"';

            html += `
                <tr class="punch-item" data-punch-id="${item.id}" data-report-id="${item.report_id}" data-closable="${item.closable !== false}">
                    <td>
                        <strong>${this.truncate(item.project_name, 25)}</strong>
                        <br><small class="text-muted">ID: #${item.report_id}</small>
                    </td>
                    <td>${this.truncate(item.epc_company, 25)}</td>
                    <td>
                        <small>${this.truncate(item.description, 50)}</small>
                    </td>
                    <td class="text-center">${severityBadge}</td>
                    <td>${assignedToDisplay}</td>
                    <td><small class="text-muted">${dateFormatted}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-sm btn-outline-primary punch-view" 
                                    title="View report">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success punch-close" ${closeBtnAttrs}>
                                <i class="fas fa-check"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
                </div>
            </div>

            <div class="punch-list-stats mt-3">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <h6 class="text-danger">${this.countBySeverity('High')}</h6>
                            <small>HIGH</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <h6 class="text-warning">${this.countBySeverity('Medium')}</h6>
                            <small>MEDIUM</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <h6 class="text-success">${this.countBySeverity('Low')}</h6>
                            <small>LOW</small>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;

        // Restore filter values if they were set
        if (this.currentSearch) {
            const s = document.getElementById('punch-search');
            if (s) s.value = this.currentSearch;
        }
        if (this.currentSeverity) {
            const sev = document.getElementById('punch-severity-filter');
            if (sev) sev.value = this.currentSeverity;
        }

        // Populate project filter options from loaded data
        try {
            const projectSelect = document.getElementById('punch-project-filter');
            if (projectSelect) {
                const projects = Array.from(new Set((this.punchLists || []).map(p => (p.project_name || '').trim()).filter(Boolean))).sort((a, b) => a.localeCompare(b));
                projects.forEach(pj => {
                    const opt = document.createElement('option');
                    opt.value = pj;
                    opt.textContent = pj.length > 45 ? pj.substring(0, 45) + '...' : pj;
                    projectSelect.appendChild(opt);
                });

                // Restore current project selection if it exists in the newly populated list
                if (this.currentProject) {
                    projectSelect.value = this.currentProject;
                }

                // Restore saved project selection from server preference (fallback to localStorage)
                try {
                    // Only attempt server fetch once per page load to avoid repeated 400/401 noise
                    if (!this._prefLoaded && !this.currentProject) {
                        this._prefLoaded = true;

                        fetch((window.BASE_URL || '') + 'ajax/get_user_preference.php?key=punch_project_filter')
                            .then(resp => {
                                if (!resp.ok) {
                                    return resp.text().then(txt => {
                                        console.warn(`[PUNCH] Preference fetch failed: ${resp.status} ${resp.statusText} - ${txt.substring(0, 300)}`);
                                        // Fall back to localStorage below
                                        return null;
                                    }).catch(() => null);
                                }
                                return resp.json().catch(() => null);
                            })
                            .then(j => {
                                if (j && j.success && j.value) {
                                    const exists = Array.from(projectSelect.options).some(o => o.value === j.value);
                                    if (exists) {
                                        projectSelect.value = j.value;
                                        try { this.applyFilters(); } catch (e) { /* ignore */ }
                                        return;
                                    }
                                }

                                // Fallback to localStorage for backward compatibility
                                try {
                                    if (typeof localStorage !== 'undefined') {
                                        const saved = localStorage.getItem('punch_project_filter');
                                        if (saved) {
                                            const exists2 = Array.from(projectSelect.options).some(o => o.value === saved);
                                            if (exists2) {
                                                projectSelect.value = saved;
                                                try { this.applyFilters(); } catch (e) { /* ignore */ }
                                            }
                                        }
                                    }
                                } catch (e) { /* ignore localStorage errors */ }
                            })
                            .catch(err => {
                                console.warn('[PUNCH] Could not fetch preference:', err);
                                try {
                                    if (typeof localStorage !== 'undefined') {
                                        const saved = localStorage.getItem('punch_project_filter');
                                        if (saved) {
                                            const exists2 = Array.from(projectSelect.options).some(o => o.value === saved);
                                            if (exists2) {
                                                projectSelect.value = saved;
                                                try { this.applyFilters(); } catch (e) { /* ignore */ }
                                            }
                                        }
                                    }
                                } catch (e) { /* ignore */ }
                            });
                    }
                } catch (e) { console.warn('[PUNCH] Could not populate project filter', e); }
            }
        } catch (e) { console.warn('[PUNCH] Could not populate project filter', e); }

        // Add event listeners
        this.addEventListeners();
    }

    /**
     * Add event listeners to buttons and filters
     */
    addEventListeners() {
        // Search filter
        const searchInput = document.getElementById('punch-search');
        if (searchInput) {
            searchInput.addEventListener('keyup', () => this.applyFilters());
            // Debounce for faster typing UX
            let _timer = null;
            searchInput.addEventListener('input', () => {
                if (_timer) clearTimeout(_timer);
                _timer = setTimeout(() => this.applyFilters(), 220);
            });
        }

        // Severity filter
        const severityFilter = document.getElementById('punch-severity-filter');
        if (severityFilter) {
            severityFilter.addEventListener('change', () => this.applyFilters());
        }

        // Project filter
        const projectFilter = document.getElementById('punch-project-filter');
        if (projectFilter) {
            projectFilter.addEventListener('change', () => {
                // Persist selection to server-side preference (and fall back to localStorage)
                try {
                    fetch((window.BASE_URL || '') + 'ajax/save_user_preference.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ key: 'punch_project_filter', value: projectFilter.value })
                    }).then(r => r.json()).then(j => {
                        if (!j || !j.success) console.warn('[PUNCH] Could not save preference to server', j);
                    }).catch(e => console.warn('[PUNCH] Save preference failed', e));
                } catch (e) {
                    console.warn('[PUNCH] Could not persist preference', e);
                }
                try { if (typeof localStorage !== 'undefined') localStorage.setItem('punch_project_filter', projectFilter.value); } catch (e) { /* ignore */ }
                this.applyFilters();
            });
        }

        // PDF download button
        const pdfBtn = document.getElementById('punch-download-pdf');
        if (pdfBtn) {
            pdfBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const project = encodeURIComponent(document.getElementById('punch-project-filter')?.value || '');
                const severity = encodeURIComponent(document.getElementById('punch-severity-filter')?.value || '');
                const search = encodeURIComponent(document.getElementById('punch-search')?.value || '');
                const url = `server_generate_punch_list_pdf.php?project=${project}&severity=${severity}&search=${search}`;
                // Open in new tab to trigger download
                window.open(url, '_blank');
            });
        }

        // Close/redirect button (green check)
        document.querySelectorAll('.punch-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const row = btn.closest('.punch-item');
                const reportId = row.dataset.reportId;
                // Always handle inside the report per requirement
                this.showOpenReportConfirm(reportId);
            });
        });

        // View punch item buttons (eye icon) - opens report and auto-navigates to punch list tab
        document.querySelectorAll('.punch-view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const row = btn.closest('.punch-item');
                const reportId = row.querySelector('small').textContent.match(/\d+/)[0];
                // Open report with tab=punch-list parameter to auto-navigate to Punch List tab
                window.location.href = `comissionamento.php?report_id=${reportId}&tab=punch-list`;
            });
        });
    }

    /**
     * Apply filters based on search and severity
     */
    applyFilters() {
        const searchEl = document.getElementById('punch-search');
        const severityEl = document.getElementById('punch-severity-filter');
        const projectEl = document.getElementById('punch-project-filter');

        const searchTerm = searchEl?.value.toLowerCase() || '';
        const severityFilter = severityEl?.value || '';
        const projectFilter = projectEl?.value || '';

        // Persist current filter state to the instance so render() can restore it
        this.currentSearch = searchEl?.value || '';
        this.currentSeverity = severityFilter;
        this.currentProject = projectFilter;

        this.filteredLists = this.punchLists.filter(item => {
            const matchesSearch =
                (item.project_name || '').toLowerCase().includes(searchTerm) ||
                (item.epc_company || '').toLowerCase().includes(searchTerm) ||
                (item.description || '').toLowerCase().includes(searchTerm);

            const matchesSeverity = !severityFilter || (item.severity_level === severityFilter);
            const matchesProject = !projectFilter || (item.project_name === projectFilter);

            return matchesSearch && matchesSeverity && matchesProject;
        });

        this.render();
    }

    /**
     * Close a punch item
     */
    closePunchItem(punchId) {
        if (!confirm('Are you sure you want to mark this item as completed?')) {
            return;
        }

        // Use relative path to support different base URLs (localhost folder vs production root)
        const apiUrl = 'ajax/close_punch_item.php';

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: punchId })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.logSuccess('✓ Item marked as completed');
                    this.loadPunchLists();
                } else {
                    this.logError('Error: ' + (data.error || 'Unknown error'));
                    alert('Error closing item: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                this.logError('Request error: ' + error.message);
                alert('Request error: ' + error.message);
            });
    }

    /**
     * Get severity badge HTML
     */
    getSeverityBadge(severity) {
        // Normalize legacy labels to IEC-style High/Medium/Low
        const s = (severity || '').toLowerCase();
        let label = 'Low';
        if (['high', 'severe', 'critical'].includes(s)) label = 'High';
        else if (['medium', 'major'].includes(s)) label = 'Medium';

        const map = {
            'High': { color: 'danger', icon: '🔴', text: 'High' },
            'Medium': { color: 'warning', icon: '🟠', text: 'Medium' },
            'Low': { color: 'success', icon: '🟢', text: 'Low' }
        };
        const m = map[label];
        return `<span class="badge bg-${m.color}">${m.icon} ${m.text}</span>`;
    }

    /**
     * Count items by severity
     */
    countBySeverity(severity) {
        const want = (severity || '').toLowerCase();
        return this.filteredLists.filter(item => {
            const s = (item.severity_level || '').toLowerCase();
            if (want === 'high') return ['high', 'severe', 'critical'].includes(s);
            if (want === 'medium') return ['medium', 'major'].includes(s);
            if (want === 'low') return ['low', 'minor', ''].includes(s);
            return false;
        }).length;
    }

    /**
     * Truncate text
     */
    truncate(text, length) {
        if (!text) return '';
        return text.length > length ? text.substring(0, length) + '...' : text;
    }

    /**
     * Logging methods
     */
    logInfo(message) {
        console.log('[PUNCH_LIST] ℹ️ ' + message);
    }

    logSuccess(message) {
        console.log('[PUNCH_LIST] ✅ ' + message);
    }

    logError(message) {
        console.error('[PUNCH_LIST] ❌ ' + message);
    }

    /**
     * Show project-styled confirmation and open the report on confirm
     */
    showOpenReportConfirm(reportId) {
        const modalEl = document.getElementById('confirmModal');
        const msgEl = document.getElementById('confirmModalMessage');
        const yesBtn = document.getElementById('confirmModalYesBtn');
        if (!modalEl || !msgEl || !yesBtn) {
            // Fallback to direct navigation if modal not present
            window.location.href = `comissionamento.php?report_id=${reportId}&tab=punch-list`;
            return;
        }

        // Set message and primary button label
        msgEl.textContent = 'Changes will be made within the report. Let\'s open the report for you.';
        yesBtn.innerHTML = '<i class="fas fa-door-open me-1"></i>Open Report';

        // Remove previous handlers to avoid stacking
        const newYesBtn = yesBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYesBtn, yesBtn);

        newYesBtn.addEventListener('click', () => {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
            window.location.href = `comissionamento.php?report_id=${reportId}&tab=punch-list`;
        }, { once: true });

        // Show modal
        const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
        modal.show();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('punch-list-container')) {
        window.punchListManager = new PunchListManager();
    }
});
