<?php
// 🔒 Require login
require_once 'includes/auth.php';
requireLogin();

include 'includes/header.php';
?>

<!-- TOAST UI Calendar CSS -->
<link rel="stylesheet" href="https://uicdn.toast.com/calendar/latest/toastui-calendar.min.css" />
<!-- Calendar Custom Styles -->
<link rel="stylesheet" href="assets/css/calendar.css">

<div class="container py-5 main-content-container">
    <div class="text-center mb-4 gsap-fade-up">
        <img src="assets/img/logo-main.png" alt="Cleanwatts Logo" width="280" style="height:auto;">
        <h2 class="fw-bold mt-3 gsap-text-reveal">Choose a Report Type</h2>
        <p class="text-muted">Select a module to access its dashboard</p>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- Commissioning Card -->
        <div class="col-md-5">
            <a href="commissioning_dashboard.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-shadow gsap-bounce-in card-hover-lift">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3" style="font-size:2.2rem; color:#2CCCD3;"><i class="fas fa-clipboard-check"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Commissioning Report</h5>
                            <p class="text-muted mb-0">Create, edit, and manage commissioning reports</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Site Survey Card -->
        <div class="col-md-5">
            <a href="survey_index.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-shadow gsap-bounce-in card-hover-lift">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3" style="font-size:2.2rem; color:#2CCCD3;"><i class="fas fa-clipboard-list"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Site Survey Report</h5>
                            <p class="text-muted mb-0">Plan and record pre-installation site surveys</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Field Supervision Card -->
        <div class="col-md-5">
            <a href="field_supervision.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-shadow gsap-bounce-in card-hover-lift">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3" style="font-size:2.2rem; color:#2CCCD3;"><i class="fas fa-hard-hat"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Field Supervision</h5>
                            <p class="text-muted mb-0">Register site visits, inspections, and technical/safety audits</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Procedures & Credentials Card -->
        <div class="col-md-5">
            <a href="procedures_credentials.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-shadow gsap-bounce-in card-hover-lift">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3" style="font-size:2.2rem; color:#2CCCD3;"><i class="fas fa-key"></i></div>
                        <div>
                            <h5 class="fw-bold mb-1">Procedures & Credentials</h5>
                            <p class="text-muted mb-0">Central library for procedures and credentials</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================
         SCHEDULE CALENDAR - TOAST UI
         ============================================ -->
    <div class="calendar-section gsap-fade-up">
        <!-- Calendar Header -->
        <div class="calendar-header">
            <h3 class="calendar-title">
                <i class="fas fa-calendar-alt"></i>
                Work Schedule
            </h3>

            <!-- Navigation -->
            <div class="calendar-nav">
                <button class="calendar-nav-btn" id="btn-prev" title="Previous">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="calendar-nav-btn" id="btn-today">Today</button>
                <span class="calendar-current-date" id="current-date">December 2025</span>
                <button class="calendar-nav-btn" id="btn-next" title="Next">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- View Controls and Filters -->
            <div class="calendar-controls">
                <div class="btn-group" role="group">
                    <button class="calendar-nav-btn" id="btn-month" data-view="month">Month</button>
                    <button class="calendar-nav-btn active" id="btn-week" data-view="week">Week</button>
                    <button class="calendar-nav-btn" id="btn-day" data-view="day">Day</button>
                    <button class="calendar-nav-btn" id="btn-list" data-view="list">List</button>
                </div>
                <div class="btn-group ms-2" role="group">
                    <button class="calendar-nav-btn" id="btn-compact" title="Toggle compact view" aria-label="Toggle compact view"><i class="fas fa-compress"></i></button>
                </div>
                <button class="btn btn-add-event" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                    <i class="fas fa-plus"></i> New Event
                </button>
            </div>
        </div>

        <!-- Type Filters -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <button class="calendar-filter-btn filter-all active" data-filter="all">
                <i class="fas fa-globe"></i> All
            </button>
            <button class="calendar-filter-btn filter-commissioning" data-filter="commissioning">
                <i class="fas fa-clipboard-check"></i> Commissioning
            </button>
            <button class="calendar-filter-btn filter-site_survey" data-filter="site_survey">
                <i class="fas fa-clipboard-list"></i> Site Survey
            </button>
            <button class="calendar-filter-btn filter-field_supervision" data-filter="field_supervision">
                <i class="fas fa-hard-hat"></i> Supervision
            </button>
        </div>

        <!-- Calendar Container -->
        <div id="calendar-container">
            <div class="calendar-loading">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>

        <!-- List Container (hidden by default) -->
        <div id="calendar-list-container" class="calendar-list" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Lista de Agendamentos</h5>
                <div>
                    <button class="btn btn-danger btn-sm me-2" id="btn-delete-selected" style="display:none;">Delete Selected</button>
                    <button class="btn btn-secondary btn-sm" id="btn-back-to-calendar">Voltar ao Calendário</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover" id="schedule-list-table">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all-schedules"></th>
                            <th>Título / Projeto</th>
                            <th>Tipo</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Local</th>
                            <th>Atribuído</th>
                            <th style="width:120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legend -->
        <div class="calendar-legend">
            <div class="legend-item">
                <span class="legend-color legend-commissioning"></span>
                <span>Commissioning</span>
            </div>
            <div class="legend-item">
                <span class="legend-color legend-site_survey"></span>
                <span>Site Survey</span>
            </div>
            <div class="legend-item">
                <span class="legend-color legend-field_supervision"></span>
                <span>Field Supervision</span>
            </div>
            <div class="legend-item">
                <span class="legend-color legend-other"></span>
                <span>Other</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Create/Edit Event -->
<div class="modal fade schedule-modal" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalLabel">
                    <i class="fas fa-calendar-plus me-2"></i>New Schedule
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <input type="hidden" id="schedule-id" name="id">

                    <div class="row g-3">
                        <!-- Title removed per request: Title will be auto-generated as [Event Type] - [Project Name] -->

                        <!-- Event Type -->
                        <div class="col-md-6">
                            <label for="schedule-type" class="form-label">Event Type *</label>
                            <select class="form-select" id="schedule-type" name="event_type" required>
                                <option value="commissioning">🔵 Commissioning</option>
                                <option value="site_survey">🟢 Site Survey</option>
                                <option value="field_supervision">🟠 Field Supervision</option>
                                <option value="other">⚫ Other</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label for="schedule-status" class="form-label">Status</label>
                            <select class="form-select" id="schedule-status" name="status">
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-6">
                            <label for="schedule-start" class="form-label">Start Date/Time *</label>
                            <input type="datetime-local" class="form-control" id="schedule-start" name="start_date" required>
                        </div>

                        <!-- End Date -->
                        <div class="col-md-6">
                            <label for="schedule-end" class="form-label">End Date/Time *</label>
                            <input type="datetime-local" class="form-control" id="schedule-end" name="end_date" required>
                        </div>

                        <!-- All Day -->
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="schedule-allday" name="all_day">
                                <label class="form-check-label" for="schedule-allday">
                                    All day event
                                </label>
                            </div>
                        </div>

                        <!-- Project Name -->
                        <div class="col-md-6">
                            <label for="schedule-project" class="form-label">Project Name</label>
                            <select class="form-select" id="schedule-project" name="project_name">
                                <option value="">-- Select project (Field Supervision) --</option>
                                <option value="__custom__">Custom...</option>
                                <?php
                                // Server-side fallback: populate projects so the select works even if AJAX fetch fails
                                if (isset($pdo)) {
                                    try {
                                        $ps = $pdo->query("SELECT id,title FROM field_supervision_project ORDER BY title");
                                        foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $p) {
                                            $id = (int)$p['id'];
                                            $title = htmlspecialchars($p['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                            echo "<option value=\"p{$id}\" data-project-id=\"{$id}\">{$title}</option>\n";
                                        }
                                    } catch (Exception $e) {
                                        // ignore, AJAX will be attempted on client
                                    }
                                }
                                ?>
                            </select>
                            <input type="text" class="form-control mt-2" id="schedule-project-custom" name="project_name_custom" placeholder="Custom project name" style="display:none;">
                        </div>

                        <!-- Location -->
                        <div class="col-md-6">
                            <label for="schedule-location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="schedule-location" name="location"
                                placeholder="E.g.: Lisbon, Portugal">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="schedule-description" class="form-label">Description</label>
                            <textarea class="form-control" id="schedule-description" name="description" rows="3"
                                placeholder="Additional notes about the event..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="btn-delete-schedule" style="display:none;">
                    <i class="fas fa-trash me-1"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-schedule">
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TOAST UI Calendar JS -->
<script src="https://uicdn.toast.com/calendar/latest/toastui-calendar.min.js"></script>

<!-- Calendar Controller Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ============================================
        // Initialize TOAST UI Calendar
        // ============================================
        const Calendar = tui.Calendar;

        // Escape HTML for titles used in templates to avoid breaking attributes
        function escapeHtml(str) {
            if (!str && str !== 0) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        const calendarEl = document.getElementById('calendar-container');
        calendarEl.innerHTML = ''; // Remove loading spinner
        calendarEl.style.height = '600px'; // Set container height

        // Switch calendar default view to a compact list for mobile devices
        const defaultView = (window.innerWidth <= 576) ? 'list' : 'week';
        const calendar = new Calendar(calendarEl, {
            defaultView: defaultView,
            useFormPopup: false,
            useDetailPopup: false,
            usageStatistics: false,
            week: {
                startDayOfWeek: 1, // Monday
                dayNames: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                taskView: false,
                eventView: ['time', 'allday'],
                hourStart: 7,
                hourEnd: 20
            },
            month: {
                startDayOfWeek: 1,
                dayNames: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                visibleWeeksCount: 0,
                isAlways6Weeks: false
            },
            calendars: [{
                    id: 'commissioning',
                    name: 'Commissioning',
                    backgroundColor: '#2CCCD3',
                    borderColor: '#2CCCD3'
                },
                {
                    id: 'site_survey',
                    name: 'Site Survey',
                    backgroundColor: '#28a745',
                    borderColor: '#28a745'
                },
                {
                    id: 'field_supervision',
                    name: 'Field Supervision',
                    backgroundColor: '#fd7e14',
                    borderColor: '#fd7e14'
                },
                {
                    id: 'other',
                    name: 'Other',
                    backgroundColor: '#6c757d',
                    borderColor: '#6c757d'
                }
            ],
            template: {
                allday: function(event) {
                    // Display Event Type + Project Name combined
                    const typeLabel = event.raw?.event_type_label || event.calendarId;
                    const proj = event.raw?.project_name || '';
                    const displayTitle = proj ? `${typeLabel} - ${proj}` : typeLabel;
                    return `<span data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" title=\"${escapeHtml(displayTitle)}\" style=\"color:#fff;font-weight:500;line-height:1.1;\">${escapeHtml(displayTitle)}</span>`;
                },
                time: function(event) {
                    // Display Event Type + Project Name combined
                    const typeLabel = event.raw?.event_type_label || event.calendarId;
                    const proj = event.raw?.project_name || '';
                    const displayTitle = proj ? `${typeLabel} - ${proj}` : typeLabel;
                    return `<span data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" title=\"${escapeHtml(displayTitle)}\" style=\"color:#fff;font-weight:500;line-height:1.1;\">${escapeHtml(displayTitle)}</span>`;
                },
                monthGridHeader: function(model) {
                    const date = parseInt(model.date.split('-')[2], 10);
                    const isToday = model.isToday ? 'font-weight:bold;color:#2CCCD3;' : '';
                    return `<span style="${isToday}">${date}</span>`;
                },
                monthGridHeaderExceed: function(hiddenEvents) {
                    return `<span style="color:#2CCCD3;font-size:11px;cursor:pointer;">+${hiddenEvents} more</span>`;
                }
            },
            gridSelection: true
        });

        // Map of event type values to display labels (used to build auto-title)
        const eventTypeLabels = {
            commissioning: 'Commissioning',
            site_survey: 'Site Survey',
            field_supervision: 'Field Supervision',
            other: 'Other'
        };

        // ============================================
        // Variáveis de Estado
        // ============================================
        let currentFilter = 'all';
        let allEvents = [];

        // ============================================
        // Funções de Utilidade
        // ============================================
        function updateCurrentDate() {
            const date = calendar.getDate();
            const options = {
                year: 'numeric',
                month: 'long'
            };
            document.getElementById('current-date').textContent =
                date.toDate().toLocaleDateString('en-GB', options);
        }

        function loadSchedules() {
            fetch('ajax/get_schedules.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allEvents = data.events;
                        applyFilter();
                    } else {
                        console.error('Error loading schedules:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                });
        }

        function applyFilter() {
            calendar.clear();

            let filteredEvents = allEvents;
            if (currentFilter !== 'all') {
                filteredEvents = allEvents.filter(e => e.calendarId === currentFilter);
            }

            filteredEvents.forEach(event => {
                calendar.createEvents([event]);
            });

            // Re-enable Bootstrap tooltips for event elements (so full titles are shown)
            enableTooltips();
            // If list view is active, re-render the list
            if (document.getElementById('calendar-list-container').style.display === 'block') {
                renderListView();
            }
        }

        // Utility: Pretty format date/time for list view
        function formatDateTimePretty(dateStrOrObj) {
            if (!dateStrOrObj) return '';
            let d = (typeof dateStrOrObj === 'string') ? new Date(dateStrOrObj.replace(' ', 'T')) : new Date(dateStrOrObj);
            if (isNaN(d.getTime())) return dateStrOrObj;
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return d.toLocaleString('en-GB', options).replace(',', '');
        }

        function findEventById(id) {
            return allEvents.find(e => String(e.id) === String(id));
        }

        // Renders the list view based on allEvents and currentFilter
        function renderListView() {
            const tbody = document.querySelector('#schedule-list-table tbody');
            tbody.innerHTML = '';

            let filtered = allEvents;
            if (currentFilter !== 'all') filtered = allEvents.filter(e => e.calendarId === currentFilter);

            if (!filtered.length) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">Nenhum agendamento encontrado</td></tr>`;
                document.getElementById('btn-delete-selected').style.display = 'none';
                return;
            }

            filtered.forEach(event => {
                const raw = event.raw || {};
                const typeLabel = escapeHtml(event.raw?.event_type_label || event.calendarId);
                const proj = escapeHtml(event.raw?.project_name || '');
                const displayTitle = proj ? `${typeLabel} - ${proj}` : typeLabel;
                const projectHtml = proj ? `<div style="font-size:11px;color:#6c757d;">${proj}</div>` : '';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="checkbox" class="select-schedule" data-id="${escapeHtml(event.id)}"></td>
                        <td>
                        <div style="font-weight:600;">${displayTitle}</div>
                        ${proj ? `<div style="font-size:12px;color:#6c757d;">${proj}</div>` : ''}
                    </td>
                    <td>${escapeHtml(raw.event_type_label || event.calendarId)}</td>
                    <td>${escapeHtml(formatDateTimePretty(raw.start_date || event.start))}</td>
                    <td>${escapeHtml(formatDateTimePretty(raw.end_date || event.end))}</td>
                    <td>${escapeHtml(event.location || '')}</td>
                    <td>${escapeHtml(raw.assigned_name || '')}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${escapeHtml(event.id)}" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar" aria-label="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger ms-1 btn-delete" data-id="${escapeHtml(event.id)}" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete" aria-label="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;

                tbody.appendChild(tr);
            });

            // Wire up checkbox handlers
            const selectAll = document.getElementById('select-all-schedules');
            selectAll.checked = false;
            selectAll.onchange = function() {
                const checked = this.checked;
                document.querySelectorAll('.select-schedule').forEach(cb => cb.checked = checked);
                toggleBulkDelete();
            };

            document.querySelectorAll('.select-schedule').forEach(cb => {
                cb.addEventListener('change', toggleBulkDelete);
            });

            // Edit buttons
            document.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const event = findEventById(id);
                    if (!event) return;
                    // Reuse clickEvent handler logic: populate the modal and show
                    document.getElementById('schedule-id').value = event.id;
                    // Title removed: value is auto-generated from event type + project name in save
                    document.getElementById('schedule-type').value = event.calendarId;
                    document.getElementById('schedule-status').value = event.raw?.status || 'scheduled';
                    // Ensure projects are loaded, then set selection
                    loadFieldProjects().then(() => {
                        const projName = event.raw?.project_name || '';
                        const projSelect = document.getElementById('schedule-project');
                        if (projName) {
                            let found = false;
                            for (let i = 0; i < projSelect.options.length; i++) {
                                if (projSelect.options[i].text === projName) {
                                    projSelect.selectedIndex = i;
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) {
                                projSelect.value = '__custom__';
                                const custom = document.getElementById('schedule-project-custom');
                                custom.style.display = 'block';
                                custom.value = projName;
                            } else {
                                document.getElementById('schedule-project-custom').style.display = 'none';
                                document.getElementById('schedule-project-custom').value = '';
                            }
                        } else {
                            projSelect.value = '';
                            document.getElementById('schedule-project-custom').style.display = 'none';
                            document.getElementById('schedule-project-custom').value = '';
                        }
                    });
                    document.getElementById('schedule-location').value = event.location || '';
                    document.getElementById('schedule-description').value = event.body || '';
                    document.getElementById('schedule-allday').checked = event.raw?.all_day || false;

                    document.getElementById('schedule-start').value = (function(dateStr) {
                        if (!dateStr) return '';
                        const d = new Date(dateStr.replace(' ', 'T'));
                        if (isNaN(d.getTime())) return dateStr.slice(0, 16).replace(' ', 'T');
                        return d.toISOString().slice(0, 16);
                    })(event.raw?.start_date || event.start);
                    document.getElementById('schedule-end').value = (function(dateStr) {
                        if (!dateStr) return '';
                        const d = new Date(dateStr.replace(' ', 'T'));
                        if (isNaN(d.getTime())) return dateStr.slice(0, 16).replace(' ', 'T');
                        return d.toISOString().slice(0, 16);
                    })(event.raw?.end_date || event.end);

                    document.getElementById('scheduleModalLabel').innerHTML = '<i class="fas fa-calendar-edit me-2"></i>Edit Schedule';
                    document.getElementById('btn-delete-schedule').style.display = 'block';
                    scheduleModal.show();
                });
            });

            // Delete buttons
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    if (!confirm('Are you sure you want to delete this schedule?')) return;
                    fetch('ajax/delete_schedule.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: id
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Reload schedules
                                loadSchedules();
                            } else {
                                alert('Error: ' + data.error);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Error deleting schedule');
                        });
                });
            });
            // Enable tooltips for list items
            enableTooltips();
        }

        // Load Field Supervision projects into the project select
        function loadFieldProjects() {
            const select = document.getElementById('schedule-project');
            // Remove existing dynamic options first (keep placeholder + custom)
            for (let i = select.options.length - 1; i >= 0; i--) {
                const v = select.options[i].value;
                if (v && v !== '' && v !== '__custom__') select.remove(i);
            }

            return fetch(window.getAjaxUrl ? window.getAjaxUrl('ajax/manage_field_supervision.php?action=list_projects') : 'ajax/manage_field_supervision.php?action=list_projects')
                .then(r => r.json())
                .then(resp => {
                    if (!resp.success) return resp;
                    const projects = resp.data || resp;
                    const insertAt = 1; // after placeholder
                    let idx = insertAt;
                    projects.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = 'p' + (p.id || '');
                        opt.text = p.title || ('Project ' + p.id);
                        opt.dataset.projectId = p.id;
                        select.add(opt, select.options[idx++] || null);
                    });
                    return resp;
                }).catch(err => {
                    console.error('Error loading field supervision projects', err);
                    return {
                        success: false
                    };
                });
        }

        // Toggle custom project input when select changes
        document.getElementById('schedule-project').addEventListener('change', function() {
            const val = this.value;
            const custom = document.getElementById('schedule-project-custom');
            if (val === '__custom__') {
                custom.style.display = 'block';
            } else {
                custom.style.display = 'none';
                custom.value = '';
            }
        });

        function toggleBulkDelete() {
            const any = Array.from(document.querySelectorAll('.select-schedule')).some(cb => cb.checked);
            document.getElementById('btn-delete-selected').style.display = any ? 'inline-block' : 'none';
        }

        // Delete selected schedules
        document.getElementById('btn-delete-selected').addEventListener('click', function() {
            const ids = Array.from(document.querySelectorAll('.select-schedule:checked')).map(cb => cb.dataset.id);
            if (!ids.length) return;
            if (!confirm(`Delete ${ids.length} selected schedules?`)) return;

            // delete them sequentially or in parallel
            Promise.all(ids.map(id => fetch('ajax/delete_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id
                    })
                }).then(r => r.json())))
                .then(results => {
                    const failures = results.filter(r => !r.success);
                    if (failures.length) {
                        alert(`${failures.length} items failed to delete`);
                    }
                    loadSchedules();
                })
                .catch(err => {
                    console.error(err);
                    alert('Error deleting selected schedules');
                });
        });

        function enableTooltips() {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            // Find all calendar elements with a title and initialize tooltip; avoid re-initializing duplicates
            const nodes = document.querySelectorAll('#calendar-container [data-bs-toggle="tooltip"]');
            nodes.forEach(node => {
                if (!node._tooltip) {
                    node._tooltip = new bootstrap.Tooltip(node, {
                        container: 'body',
                        boundary: 'window'
                    });
                }
            });
        }

        // ============================================
        // Event Listeners - Navegação
        // ============================================
        document.getElementById('btn-prev').addEventListener('click', function() {
            calendar.prev();
            updateCurrentDate();
        });

        document.getElementById('btn-next').addEventListener('click', function() {
            calendar.next();
            updateCurrentDate();
        });

        document.getElementById('btn-today').addEventListener('click', function() {
            calendar.today();
            updateCurrentDate();
        });

        // Vistas
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', function() {
                const view = this.dataset.view;
                if (view === 'list') {
                    // Show list view
                    document.getElementById('calendar-container').style.display = 'none';
                    document.getElementById('calendar-list-container').style.display = 'block';
                    // Render list
                    renderListView();
                } else {
                    // Ensure calendar visible
                    document.getElementById('calendar-container').style.display = 'block';
                    document.getElementById('calendar-list-container').style.display = 'none';
                    calendar.changeView(view);
                }

                document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                updateCurrentDate();
            });
        });

        // Compact view toggle
        const compactBtn = document.getElementById('btn-compact');
        compactBtn.addEventListener('click', function() {
            const container = document.getElementById('calendar-container');
            const isCompact = container.classList.toggle('compact-view');
            this.classList.toggle('active', isCompact);
        });

        // Filtros
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', function() {
                currentFilter = this.dataset.filter;

                document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                applyFilter();
            });
        });

        // ============================================
        // Event Listeners - Modal
        // ============================================
        const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        const scheduleForm = document.getElementById('scheduleForm');

        // Click on event to edit
        calendar.on('clickEvent', function(info) {
            const event = info.event;
            document.getElementById('schedule-id').value = event.id;
            document.getElementById('schedule-type').value = event.calendarId;
            document.getElementById('schedule-status').value = event.raw?.status || 'scheduled';
            // Ensure projects are loaded then set selection
            loadFieldProjects().then(() => {
                const projName = event.raw?.project_name || '';
                const projSelect = document.getElementById('schedule-project');
                if (projName) {
                    let found = false;
                    for (let i = 0; i < projSelect.options.length; i++) {
                        if (projSelect.options[i].text === projName) {
                            projSelect.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        projSelect.value = '__custom__';
                        const custom = document.getElementById('schedule-project-custom');
                        custom.style.display = 'block';
                        custom.value = projName;
                    } else {
                        document.getElementById('schedule-project-custom').style.display = 'none';
                        document.getElementById('schedule-project-custom').value = '';
                    }
                } else {
                    projSelect.value = '';
                    document.getElementById('schedule-project-custom').style.display = 'none';
                    document.getElementById('schedule-project-custom').value = '';
                }
            });
            document.getElementById('schedule-location').value = event.location || '';
            document.getElementById('schedule-description').value = event.body || '';
            document.getElementById('schedule-allday').checked = event.raw?.all_day || false;

            // Use original dates from raw data (preserves exact time)
            const formatDateTimeFromString = (dateStr) => {
                if (!dateStr) return '';
                // Handle MySQL datetime format: "2025-12-13 09:00:00"
                const d = new Date(dateStr.replace(' ', 'T'));
                if (isNaN(d.getTime())) return dateStr.slice(0, 16).replace(' ', 'T');
                return d.toISOString().slice(0, 16);
            };

            // Use raw dates which have the original times
            const startDate = event.raw?.start_date || event.start;
            const endDate = event.raw?.end_date || event.end;

            document.getElementById('schedule-start').value = formatDateTimeFromString(startDate);
            document.getElementById('schedule-end').value = formatDateTimeFromString(endDate);

            document.getElementById('scheduleModalLabel').innerHTML =
                '<i class="fas fa-calendar-edit me-2"></i>Edit Schedule';
            document.getElementById('btn-delete-schedule').style.display = 'block';

            scheduleModal.show();
        });

        // Back to Calendar button (in list view)
        document.getElementById('btn-back-to-calendar').addEventListener('click', function() {
            document.getElementById('calendar-container').style.display = 'block';
            document.getElementById('calendar-list-container').style.display = 'none';
            // Set active view to week
            document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-week').classList.add('active');
            calendar.changeView('week');
            updateCurrentDate();
        });

        // Click on a date to create event
        calendar.on('selectDateTime', function(info) {
            scheduleForm.reset();
            document.getElementById('schedule-id').value = '';

            const formatDateTime = (date) => {
                const d = new Date(date);
                return d.toISOString().slice(0, 16);
            };

            document.getElementById('schedule-start').value = formatDateTime(info.start);
            document.getElementById('schedule-end').value = formatDateTime(info.end);
            document.getElementById('schedule-allday').checked = info.isAllday;

            document.getElementById('scheduleModalLabel').innerHTML =
                '<i class="fas fa-calendar-plus me-2"></i>New Schedule';
            document.getElementById('btn-delete-schedule').style.display = 'none';

            scheduleModal.show();
            calendar.clearGridSelections();
        });

        // Reset modal when opening for new event
        document.querySelector('[data-bs-target="#scheduleModal"]').addEventListener('click', function() {
            scheduleForm.reset();
            document.getElementById('schedule-id').value = '';
            document.getElementById('scheduleModalLabel').innerHTML =
                '<i class="fas fa-calendar-plus me-2"></i>New Schedule';
            document.getElementById('btn-delete-schedule').style.display = 'none';

            // Set current date/time
            const now = new Date();
            const formatDateTime = (date) => date.toISOString().slice(0, 16);
            document.getElementById('schedule-start').value = formatDateTime(now);
            now.setHours(now.getHours() + 1);
            document.getElementById('schedule-end').value = formatDateTime(now);
            // Load Field Supervision projects for the dropdown
            loadFieldProjects();
            // Ensure custom input hidden
            document.getElementById('schedule-project-custom').style.display = 'none';
        });

        // Auto-uncheck "All day" when user changes time
        document.getElementById('schedule-start').addEventListener('change', function() {
            // If user selects a specific time, uncheck all-day
            const time = this.value.split('T')[1];
            if (time && time !== '00:00') {
                document.getElementById('schedule-allday').checked = false;
            }
        });

        document.getElementById('schedule-end').addEventListener('change', function() {
            // If user selects a specific time, uncheck all-day
            const time = this.value.split('T')[1];
            if (time && time !== '23:59') {
                document.getElementById('schedule-allday').checked = false;
            }
        });

        // Save event
        document.getElementById('btn-save-schedule').addEventListener('click', function() {
            const formData = {
                id: document.getElementById('schedule-id').value || null,
                event_type: document.getElementById('schedule-type').value,
                status: document.getElementById('schedule-status').value,
                start_date: document.getElementById('schedule-start').value,
                end_date: document.getElementById('schedule-end').value,
                all_day: document.getElementById('schedule-allday').checked ? 1 : 0,
                // project_name will be set below depending on selection/custom
                project_name: null,
                project_id: null,
                location: document.getElementById('schedule-location').value,
                description: document.getElementById('schedule-description').value
            };

            // Determine project selection: either selected project title, custom text, or empty
            const projSelect = document.getElementById('schedule-project');
            const selVal = projSelect.value;
            if (selVal === '__custom__') {
                formData.project_name = document.getElementById('schedule-project-custom').value || null;
            } else if (selVal) {
                // If an existing project selected, use its displayed title and id if present
                const opt = projSelect.options[projSelect.selectedIndex];
                formData.project_name = opt ? opt.text : selVal;
                formData.project_id = opt && opt.dataset && opt.dataset.projectId ? opt.dataset.projectId : null;
            } else {
                formData.project_name = null;
            }

            // Auto-generate title as "[Event Type] - [Project Name]" if project name provided, otherwise just event type label.
            const typeLabel = eventTypeLabels[formData.event_type] || formData.event_type;
            const generatedTitle = formData.project_name ? `${typeLabel} - ${formData.project_name}` : typeLabel;
            formData.title = generatedTitle;

            if (!formData.start_date || !formData.end_date) {
                alert('Please complete required fields');
                return;
            }

            fetch('ajax/save_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        scheduleModal.hide();
                        loadSchedules();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving schedule');
                });
        });

        // Delete event
        document.getElementById('btn-delete-schedule').addEventListener('click', function() {
            const id = document.getElementById('schedule-id').value;
            if (!id) return;

            if (!confirm('Are you sure you want to delete this schedule?')) return;

            fetch('ajax/delete_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        scheduleModal.hide();
                        loadSchedules();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting schedule');
                });
        });

        // ============================================
        // Inicialização
        // ============================================
        updateCurrentDate();
        loadSchedules();
    });
</script>

<?php include 'includes/footer.php'; ?>