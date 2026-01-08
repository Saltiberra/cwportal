/**
 * Autosave System for Site Survey (SQL-based)
 * Similar to autosave_sql.js but adapted for site_survey.php
 * Saves form data every 5 seconds to SQL database
 */

(function () {
    'use strict';

    const AUTOSAVE_INTERVAL = 5000; // 5 segundos
    const AUTOSAVE_ENDPOINT = (window.getAjaxUrl ? window.getAjaxUrl('ajax/autosave_draft.php') : 'ajax/autosave_draft.php');
    const LOAD_DRAFT_ENDPOINT = (window.getAjaxUrl ? window.getAjaxUrl('ajax/load_draft.php') : 'ajax/load_draft.php');
    const INIT_SESSION_ENDPOINT = (window.getAjaxUrl ? window.getAjaxUrl('ajax/init_form_session.php') : 'ajax/init_form_session.php');

    let autosaveTimer = null;
    let lastSaveTime = null;
    let isSaving = false;
    let pendingSave = false;
    let lastSavedPayloadHash = null;
    let saveIndicator = null;

    console.log('[SITE_SURVEY_AUTOSAVE] 🚀 Initializing system...');

    /**
     * Criar indicador visual de salvamento
     */
    function createSaveIndicator() {
        if (document.getElementById('autosave-indicator')) return;

        const indicator = document.createElement('div');
        indicator.id = 'autosave-indicator';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 16px;
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        `;
        // create a small 'last saved' badge that persists
        const lastBadge = document.createElement('div');
        lastBadge.id = 'autosave-last';
        lastBadge.style.cssText = `
            position: fixed;
            top: 56px;
            right: 20px;
            padding: 6px 10px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            border-radius: 20px;
            font-size: 12px;
            z-index: 1000;
            opacity: 0.9;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        `;
        lastBadge.textContent = '';

        document.body.appendChild(indicator);
        document.body.appendChild(lastBadge);
        saveIndicator = indicator;
        window.autosaveLastBadge = lastBadge;
    }

    /**
     * Atualizar indicador de status
     */
    function updateIndicator(status, customMessage = null) {
        if (!saveIndicator) return;

        switch (status) {
            case 'saving':
                saveIndicator.textContent = '💾 Saving...';
                saveIndicator.style.background = '#fff3cd';
                saveIndicator.style.color = '#856404';
                saveIndicator.style.opacity = '1';
                break;
            case 'saved':
                saveIndicator.textContent = '✅ Saved';
                saveIndicator.style.background = '#d4edda';
                saveIndicator.style.color = '#155724';
                saveIndicator.style.opacity = '1';
                setTimeout(() => saveIndicator.style.opacity = '0', 2000);
                break;
            case 'error':
                const errorMsg = customMessage || '❌ Error saving';
                saveIndicator.textContent = errorMsg;
                saveIndicator.style.background = '#f8d7da';
                saveIndicator.style.color = '#721c24';
                saveIndicator.style.opacity = '1';
                // Keep error visible longer
                setTimeout(() => saveIndicator.style.opacity = '0', 5000);
                break;
            case 'warning':
                saveIndicator.textContent = customMessage || '⚠️ Warning';
                saveIndicator.style.background = '#fff3cd';
                saveIndicator.style.color = '#856404';
                saveIndicator.style.opacity = '1';
                setTimeout(() => saveIndicator.style.opacity = '0', 4000);
                break;
        }
    }

    function updateLastSaved(ts) {
        // ts: JS Date or ISO string
        const badge = window.autosaveLastBadge;
        if (!badge) return;
        try {
            const date = (ts instanceof Date) ? ts : new Date(ts);
            const hh = String(date.getHours()).padStart(2, '0');
            const mm = String(date.getMinutes()).padStart(2, '0');
            const ss = String(date.getSeconds()).padStart(2, '0');
            badge.textContent = `Draft saved ${hh}:${mm}:${ss}`;
            try { localStorage.setItem('site_survey_last_saved_ts', new Date(date).toISOString()); } catch (e) { /* ignore */ }
            badge.style.opacity = '0.95';
        } catch (e) {
            // ignore
        }
    }

    // Initialize form session similar to commissioning autosave
    function initFormSession() {
        return new Promise((resolve) => {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const reportId = urlParams.get('survey_id') || urlParams.get('report_id');
                const url = reportId ? `${INIT_SESSION_ENDPOINT}?report_id=${reportId}` : INIT_SESSION_ENDPOINT;
                const timeoutPromise = new Promise(resolve => setTimeout(() => resolve({ success: true, fallback: true }), 3000));

                Promise.race([
                    fetch(url).then(r => r.json()),
                    timeoutPromise
                ]).then(data => {
                    if (data && (data.success || data.fallback)) {
                        window.formSessionToken = data.session_token || data.session_id || window.formSessionToken;
                        window.formSessionId = data.session_id || data.session_id || window.formSessionId;
                        resolve(data);
                    } else {
                        resolve({ success: true, fallback: true });
                    }
                }).catch(() => resolve({ success: true, fallback: true }));
            } catch (e) {
                resolve({ success: true, fallback: true });
            }
        });
    }

    function clearNewReportData() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const reportId = urlParams.get('survey_id') || urlParams.get('report_id');
            if (!reportId && typeof window.IS_NEW_REPORT !== 'undefined' && window.IS_NEW_REPORT) {
                fetch(AUTOSAVE_ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_old_drafts', session_id: window.formSessionToken || null })
                }).then(r => r.json()).then(data => {
                    if (data.success) console.log('[SITE_SURVEY_AUTOSAVE] Old drafts cleared');
                }).catch(e => console.warn('[SITE_SURVEY_AUTOSAVE] Clear old drafts failed', e));
            }
        } catch (e) { /* ignore */ }
    }

    /**
     * Coletar dados do formulário
     */
    function collectFormData() {
        // Prefer using global buildSurveyFormData if it's defined on the page (keeps field mapping consistent)
        try {
            if (typeof window.buildSurveyFormData === 'function') {
                const built = window.buildSurveyFormData();
                // Ensure survey_id is included: prefer hidden input, then window.surveyId
                const sid = document.querySelector('input[name="survey_id"]')?.value || (typeof window.surveyId !== 'undefined' ? window.surveyId : null);
                if (sid && !built.survey_id) built.survey_id = parseInt(sid);
                return built;
            }
        } catch (e) {
            console.warn('[SITE_SURVEY_AUTOSAVE] buildSurveyFormData error, falling back to local collection', e);
        }
        const surveyId = document.querySelector('input[name="survey_id"]')?.value || null;
        const data = {
            survey_id: surveyId ? parseInt(surveyId) : null,
            project_name: document.querySelector('input[name="project_name"]')?.value || '',
            date: document.querySelector('input[name="date"]')?.value || '',
            location: document.querySelector('input[name="location"]')?.value || '',
            gps: document.querySelector('input[name="gps"]')?.value || '',
            responsible: document.querySelector('input[name="responsible"]')?.value || '',
            site_survey_responsible_id: document.querySelector('select[name="site_survey_responsible_id"]')?.value || '',
            accompanied_by_name: document.querySelector('input[name="accompanied_by_name"]')?.value || '',
            accompanied_by_phone: document.querySelector('input[name="accompanied_by_phone"]')?.value || '',
            power_to_install: document.querySelector('input[name="power_to_install"]')?.value || '',
            certified_power: document.querySelector('input[name="certified_power"]')?.value || '',

            // Electrical fields
            injection_point_type: document.querySelector('input[name="injection_point_type"]')?.value || '',
            circuit_type: document.querySelector('select[name="circuit_type"]')?.value || '',
            inverter_location: document.querySelector('input[name="inverter_location"]')?.value || '',
            pv_protection_board_location: document.querySelector('input[name="pv_protection_board_location"]')?.value || '',
            pv_board_to_injection_distance_m: document.querySelector('input[name="pv_board_to_injection_distance_m"]')?.value || '',
            injection_has_space_for_switch: (document.querySelector('input[name="injection_has_space_for_switch"]:checked')?.value || document.querySelector('select[name="injection_has_space_for_switch"]')?.value || ''),
            injection_has_busbar_space: (document.querySelector('input[name="injection_has_busbar_space"]:checked')?.value || document.querySelector('select[name="injection_has_busbar_space"]')?.value || ''),

            // Panel
            panel_cable_exterior_to_main_gauge: document.querySelector('input[name="panel_cable_exterior_to_main_gauge"]')?.value || '',
            panel_brand_model: document.querySelector('input[name="panel_brand_model"]')?.value || '',
            breaker_brand_model: document.querySelector('input[name="breaker_brand_model"]')?.value || '',
            breaker_rated_current_a: document.querySelector('input[name="breaker_rated_current_a"]')?.value || '',
            breaker_short_circuit_current_ka: document.querySelector('input[name="breaker_short_circuit_current_ka"]')?.value || '',
            residual_current_ma: document.querySelector('input[name="residual_current_ma"]')?.value || '',
            earth_measurement_ohms: document.querySelector('input[name="earth_measurement_ohms"]')?.value || '',
            is_bidirectional_meter: document.querySelector('select[name="is_bidirectional_meter"]')?.value || '',

            // Generator
            generator_exists: document.querySelector('select[name="generator_exists"]')?.value || '',
            generator_mode: document.querySelector('input[name="generator_mode"]')?.value || '',
            generator_scope: document.querySelector('input[name="generator_scope"]')?.value || '',

            // Communications
            comm_wifi_near_pv: document.querySelector('select[name="comm_wifi_near_pv"]')?.value || '',
            comm_ethernet_near_pv: document.querySelector('select[name="comm_ethernet_near_pv"]')?.value || '',
            comm_utp_requirement: document.querySelector('input[name="comm_utp_requirement"]')?.value || '',
            comm_utp_length_m: document.querySelector('input[name="comm_utp_length_m"]')?.value || '',
            comm_router_port_open_available: document.querySelector('select[name="comm_router_port_open_available"]')?.value || '',
            comm_router_port_number: document.querySelector('input[name="comm_router_port_number"]')?.value || '',
            comm_mobile_coverage_level: document.querySelector('select[name="comm_mobile_coverage_level"]')?.value || '',

            // Notes
            // Prefer select by name for consistency; if not present fallback to getElementById values
            survey_notes: (document.querySelector('textarea[name="survey_notes"]')?.value || document.getElementById('survey_notes')?.value || ''),
            challenges: (document.querySelector('textarea[name="challenges"]')?.value || document.getElementById('challenges')?.value || ''),
            installation_site_notes: (document.querySelector('textarea[name="installation_site_notes"]')?.value || document.getElementById('installation_site_notes')?.value || ''),
            roof_access_available: (function () {
                const v = document.querySelector('input[name="roof_access_available"]:checked')?.value; return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            permanent_ladder_feasible: (function () {
                const v = document.querySelector('input[name="permanent_ladder_feasible"]:checked')?.value; return v ? (v === 'YES' ? 1 : 0) : null;
            })(),
            // Complex JSON fields (fallbacks if builder isn't present)
            building_details: (function () {
                try {
                    if (typeof window.getBuildingDetails === 'function') return window.getBuildingDetails();
                    const el = document.getElementById('building_details');
                    if (el && el.value) return JSON.parse(el.value);
                } catch (e) { }
                return [];
            })(),
            roof_details: (function () {
                try {
                    if (typeof window.getRoofDetails === 'function') return window.getRoofDetails();
                    const el = document.getElementById('roof_details');
                    if (el && el.value) return JSON.parse(el.value);
                } catch (e) { }
                return {};
            })(),
            shading_details: (function () {
                try {
                    if (typeof window.getShadingDetails === 'function') return window.getShadingDetails();
                    const el = document.getElementById('shading_details');
                    if (el && el.value) return JSON.parse(el.value);
                } catch (e) { }
                return {};
            })(),
            checklist_data: (function () {
                try { if (typeof window.collectSurveyChecklist === 'function') return window.collectSurveyChecklist(); } catch (e) { }
                try { const el = document.getElementById('survey_checklist_data'); if (el && el.value) return JSON.parse(el.value); } catch (e) { }
                return [];
            })(),
            photo_checklist: (function () {
                try { if (typeof window.collectPhotoChecklist === 'function') return window.collectPhotoChecklist(); } catch (e) { }
                return [];
            })(),
        };

        return data;
    }

    /**
     * Salvar dados no servidor
     */
    function saveFormDataToSQL(force = false) {
        if (isSaving) return;

        const data = collectFormData();
        // Normalize keys for generic autosave endpoint
        if (data.survey_id && !data.report_id) data.report_id = data.survey_id;
        // Set report type so backend may distinguish data
        data.report_type = data.report_type || 'site_survey';
        // detect whether data actually changed since last save
        const payloadHash = JSON.stringify(data);
        if (!force && lastSavedPayloadHash && payloadHash === lastSavedPayloadHash) {
            // nothing changed since last saved payload
            return;
        }
        // Only skip save if the entire form appears empty (no fields with content)
        const hasValue = Object.keys(data).some(k => {
            const v = data[k];
            if (v === null || typeof v === 'undefined') return false;
            if (typeof v === 'string' && v.trim() === '') return false;
            return true;
        });
        if (!hasValue) {
            console.log('[SITE_SURVEY_AUTOSAVE] Skipping save - no meaningful fields');
            return;
        }

        isSaving = true;
        updateIndicator('saving');

        fetch(AUTOSAVE_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                // Check if response is JSON before parsing
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, it's likely an HTML redirect (session expired)
                    if (response.status === 302 || response.status === 401 || response.redirected) {
                        console.error('[SITE_SURVEY_AUTOSAVE] Session expired or authentication required');
                        updateIndicator('error', '🔐 Sessão expirada - faça login novamente');

                        // Show a more prominent alert to the user
                        setTimeout(() => {
                            const shouldLogin = confirm('Sua sessão expirou. Você precisa fazer login novamente para continuar salvando.\n\nDeseja ir para a página de login agora?');
                            if (shouldLogin) {
                                window.location.href = 'login.php';
                            }
                        }, 1000);

                        throw new Error('Session expired');
                    }
                    throw new Error('Server returned non-JSON response: ' + contentType);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log('[SITE_SURVEY_AUTOSAVE] ✅ Saved at', new Date().toLocaleTimeString());
                    lastSaveTime = new Date();
                    updateIndicator('saved');

                    // Atualizar hidden field se existir
                    const surveyIdField = document.querySelector('input[name="survey_id"]');
                    // Backend returns `survey_id` or `draft_id` depending on endpoint used.
                    const newSurveyId = data.survey_id || (data.data && data.data.survey_id) || (data.data && data.data.id) || null;
                    const newDraftId = data.draft_id || (data.data && data.data.draft_id) || null;
                    if (newSurveyId) {
                        // Ensure we update global var and create hidden field if missing
                        window.surveyId = parseInt(newSurveyId);
                        if (typeof surveyId !== 'undefined') surveyId = window.surveyId;
                        if (!surveyIdField) {
                            // create hidden field in body to persist survey_id
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'survey_id';
                            hidden.value = window.surveyId;
                            document.body.appendChild(hidden);
                            console.log('[SITE_SURVEY_AUTOSAVE] Created hidden survey_id field with', window.surveyId);
                            try { localStorage.setItem('site_survey_last_id', String(window.surveyId)); } catch (e) { /* ignore */ }
                        } else if (!surveyIdField.value) {
                            surveyIdField.value = window.surveyId;
                            console.log('[SITE_SURVEY_AUTOSAVE] Updated survey_id to', window.surveyId);
                            try { localStorage.setItem('site_survey_last_id', String(window.surveyId)); } catch (e) { /* ignore */ }
                        }
                        if (newDraftId) {
                            window.draftId = newDraftId;
                            try { localStorage.setItem('site_survey_last_draft_id', String(window.draftId)); } catch (e) { /* ignore */ }
                        }
                    }
                    // Set a last-saved timestamp on success
                    try {
                        const ts = new Date();
                        updateLastSaved(ts);
                    } catch (e) { /* ignore */ }
                    // mark payload as saved
                    lastSavedPayloadHash = payloadHash;
                } else {
                    console.error('[SITE_SURVEY_AUTOSAVE] ❌ Save failed:', data.message);
                    updateIndicator('error');
                }
            })
            .catch(error => {
                console.error('[SITE_SURVEY_AUTOSAVE] Network error:', error);
                updateIndicator('error');
            })
            .finally(() => {
                isSaving = false;
                if (pendingSave) {
                    pendingSave = false;
                    setTimeout(() => saveFormDataToSQL(true), 50); // schedule immediate save
                }
            });
    }

    /**
     * Carregar dados do servidor
     */
    function loadDraft() {
        const surveyIdFromDOM = document.querySelector('input[name="survey_id"]')?.value || '';
        const surveyId = surveyIdFromDOM || (typeof window.surveyId !== 'undefined' ? window.surveyId : '');
        // If we are editing an existing report (server-rendered `survey_id`), load draft via report_id to show server-side data.
        // Otherwise, call `load_draft.php` with no params (load session-based draft).
        const url = surveyIdFromDOM ? `${LOAD_DRAFT_ENDPOINT}?report_id=${surveyIdFromDOM}` : `${LOAD_DRAFT_ENDPOINT}`;

        fetch(url)
            .then(response => {
                // Check if response is JSON before parsing
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, it's likely an HTML redirect (session expired)
                    if (response.status === 302 || response.status === 401 || response.redirected) {
                        console.error('[SITE_SURVEY_AUTOSAVE] Session expired or authentication required for draft load');
                        updateIndicator('error', '🔐 Sessão expirada - faça login novamente');

                        // Show a more prominent alert to the user
                        setTimeout(() => {
                            const shouldLogin = confirm('Sua sessão expirou. Você precisa fazer login novamente para continuar.\n\nDeseja ir para a página de login agora?');
                            if (shouldLogin) {
                                window.location.href = 'login.php';
                            }
                        }, 1000);

                        throw new Error('Session expired');
                    }
                    throw new Error('Server returned non-JSON response: ' + contentType);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    console.log('[SITE_SURVEY_AUTOSAVE] ✅ Loaded draft from SQL');
                    populateFormWithData(data.data);

                    // Apply complex JSON fields to UI components (buildings, roofs, shading)
                    try {
                        // Buildings
                        if (typeof data.data.building_details !== 'undefined' && data.data.building_details !== null) {
                            let list = data.data.building_details;
                            if (typeof list === 'string') {
                                try { list = JSON.parse(list); } catch (e) { list = []; }
                            }
                            if (Array.isArray(list)) {
                                console.log('[SITE_SURVEY_AUTOSAVE] Applying building_details from draft, count=', list.length);
                                if (typeof setBuildingDetails === 'function') {
                                    setBuildingDetails(list);
                                } else {
                                    const el = document.getElementById('building_details');
                                    if (el) el.value = JSON.stringify(list);
                                    if (typeof renderBuildingCards === 'function') renderBuildingCards();
                                }
                            }
                        }
                        // Roofs
                        if (typeof data.data.roof_details !== 'undefined' && data.data.roof_details !== null) {
                            let roofs = data.data.roof_details;
                            if (typeof roofs === 'string') {
                                try { roofs = JSON.parse(roofs); } catch (e) { roofs = {}; }
                            }
                            if (typeof roofs === 'object') {
                                try { console.log('[SITE_SURVEY_AUTOSAVE] Applying roof_details from draft, keys=', Object.keys(roofs).length); } catch (e) { }
                                if (typeof setRoofDetails === 'function') setRoofDetails(roofs);
                            }
                        }
                        // Shading
                        if (typeof data.data.shading_details !== 'undefined' && data.data.shading_details !== null) {
                            let shading = data.data.shading_details;
                            if (typeof shading === 'string') {
                                try { shading = JSON.parse(shading); } catch (e) { shading = {}; }
                            }
                            if (typeof shading === 'object') {
                                try { console.log('[SITE_SURVEY_AUTOSAVE] Applying shading_details from draft, keys=', Object.keys(shading).length); } catch (e) { }
                                const el = document.getElementById('shading_details');
                                if (el) {
                                    const current = el.value ? JSON.parse(el.value) : {};
                                    const currentKeys = Object.keys(current).length;
                                    if (Object.keys(shading).length > 0 || currentKeys === 0) {
                                        el.value = JSON.stringify(shading);
                                        if (typeof renderShadingTable === 'function') renderShadingTable();
                                        if (typeof refreshShadingBuildingOptions === 'function') refreshShadingBuildingOptions();
                                        if (typeof refreshShadingStatusUI === 'function') refreshShadingStatusUI();
                                        if (typeof enableShadingControls === 'function') try { enableShadingControls(); } catch (e) { console.warn('[SITE_SURVEY_AUTOSAVE] enableShadingControls failed', e); }
                                    } else {
                                        console.log('[SITE_SURVEY_AUTOSAVE] Skipping shading_details overwrite, current has data');
                                    }
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('[SITE_SURVEY_AUTOSAVE] Failed to apply complex draft fields to UI', e);
                    }

                    // Ensure the survey_id hidden field is updated when loading a draft without an explicit id
                    const surveyIdField = document.querySelector('input[name="survey_id"]');
                    if (data.data.id) {
                        window.surveyId = parseInt(data.data.id);
                        if (typeof surveyId !== 'undefined') surveyId = window.surveyId;
                        if (surveyIdField && !surveyIdField.value) {
                            surveyIdField.value = data.data.id;
                        }
                        // Persist to local storage for faster reload and cross-navigation retrieval
                        try { localStorage.setItem('site_survey_last_id', String(window.surveyId)); } catch (e) { /* ignore */ }
                        console.log('[SITE_SURVEY_AUTOSAVE] Set survey_id from loaded draft to', data.data.id);
                    }
                    // Update last-saved timestamp if available from the load endpoint
                    if (data.last_updated) {
                        updateLastSaved(data.last_updated);
                    }
                } else {
                    console.log('[SITE_SURVEY_AUTOSAVE] No draft found');
                }
            })
            .catch(error => {
                console.error('[SITE_SURVEY_AUTOSAVE] Error loading draft:', error);
            });
    }

    /**
     * Preencher formulário com dados
     */
    function populateFormWithData(data) {
        function normalizeBoolValue(v) {
            if (v === null || v === undefined) return null;
            const s = String(v).trim().toUpperCase();
            if (['1', 'YES', 'SIM', 'TRUE', 'T'].includes(s)) return '1';
            if (['0', 'NO', 'NAO', 'NÃO', 'FALSE', 'F'].includes(s)) return '0';
            return s;
        }

        Object.keys(data).forEach(key => {
            const fields = document.querySelectorAll(`[name="${key}"]`);
            // If the value was not provided in the draft payload, do not overwrite server-rendered defaults
            if (typeof data[key] === 'undefined' || data[key] === null) return;
            if (!fields || fields.length === 0) {
                // fallback: try by id (hidden inputs e.g., building_details, roof_details, shading_details)
                const elById = document.getElementById(key);
                if (!elById) return;
                if (elById.tagName.toLowerCase() === 'input' || elById.tagName.toLowerCase() === 'textarea' || elById.tagName.toLowerCase() === 'select') {
                    if (typeof data[key] === 'object') {
                        try { elById.value = JSON.stringify(data[key]); } catch (e) { elById.value = ''; }
                    } else { elById.value = data[key] || ''; }
                }
                return;
            }
            // If this is a radio group, set the checked radio
            if (fields[0].type === 'radio') {
                // handle stored numeric 1/0 or strings 'YES'/'NO' and other variants
                const storedRaw = data[key];
                const storedNorm = normalizeBoolValue(storedRaw);
                fields.forEach(f => {
                    const valRaw = f.value;
                    const valNorm = normalizeBoolValue(valRaw);
                    if (valNorm !== null && storedNorm !== null) {
                        f.checked = (valNorm === storedNorm);
                    } else {
                        // fallback to direct string comparison
                        f.checked = String(valRaw) === String(storedRaw);
                    }
                });
                return;
            }
            // checkbox or single field
            const field = fields[0];
            if (field.type === 'checkbox') {
                field.checked = !!data[key];
            } else {
                if (typeof data[key] === 'object') {
                    try { field.value = JSON.stringify(data[key]); } catch (e) { field.value = ''; }
                } else {
                    field.value = data[key] || '';
                }
            }
        });
        console.log('[SITE_SURVEY_AUTOSAVE] Form populated with loaded data');
    }

    /**
     * Inicializar sistema de autosave
     */
    function startAutoSave() {
        if (autosaveTimer) return;
        autosaveTimer = setInterval(() => {
            saveFormDataToSQL();
        }, AUTOSAVE_INTERVAL);
    }

    function attachFormListeners() {
        // Attach same listeners to site survey fields as commissioning autosave
        const fields = document.querySelectorAll('input, textarea, select');
        fields.forEach(field => {
            if (field.getAttribute('data-skip-autosave') === 'true') return;
            let timeout = null;
            field.addEventListener('input', function () {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    saveFormDataToSQL();
                }, 900);
            });
            field.addEventListener('change', function () {
                saveFormDataToSQL();
            });
            field.addEventListener('blur', function () {
                saveFormDataToSQL();
            });
        });
    }

    function initAutoSave() {
        console.log('[SITE_SURVEY_AUTOSAVE] Initializing...');
        createSaveIndicator();
        initFormSession()
            .then(() => {
                clearNewReportData();
                loadDraft();
                startAutoSave();
                attachFormListeners();
                window.addEventListener('beforeunload', () => {
                    if (autosaveTimer) clearInterval(autosaveTimer);
                    saveFormDataToSQL(true);
                });
                // Restore last saved ts from localStorage (if present)
                try {
                    const stored = localStorage.getItem('site_survey_last_saved_ts');
                    if (stored) updateLastSaved(stored);
                } catch (e) { }
                console.log('[SITE_SURVEY_AUTOSAVE] ✅ System initialized');
            })
            .catch(() => {
                // fallback: start save without session
                loadDraft();
                startAutoSave();
                attachFormListeners();
            });

        // Salvar antes de sair da página
        window.addEventListener('beforeunload', () => {
            if (autosaveTimer) clearInterval(autosaveTimer);
            saveFormDataToSQL();
        });

        // Salvar ao clicar em botões de navegação
        document.querySelectorAll('.nav-link').forEach(tab => {
            tab.addEventListener('click', () => {
                console.log('[SITE_SURVEY_AUTOSAVE] Tab change - saving...');
                saveFormDataToSQL();
            });
        });

        // Salvar ao editar campos (immediate with debounce)
        const debounce = (fn, wait) => {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn.apply(this, args), wait);
            };
        };
        const scheduleSave = debounce(() => {
            if (isSaving) {
                pendingSave = true;
                return;
            }
            saveFormDataToSQL();
        }, 900);

        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', () => scheduleSave());
            field.addEventListener('change', () => scheduleSave());
            field.addEventListener('blur', () => scheduleSave());
        });

        console.log('[SITE_SURVEY_AUTOSAVE] ✅ System initialized');

        // Verificar sessão periodicamente (a cada 5 minutos)
        setInterval(() => {
            checkSessionStatus();
        }, 5 * 60 * 1000); // 5 minutos
    }

    /**
     * Verificar se a sessão ainda é válida
     */
    function checkSessionStatus() {
        // Fazer uma chamada simples para verificar se estamos logados
        fetch((window.BASE_URL || '') + 'ajax/check_session.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => {
                if (response.status === 401 || response.status === 302) {
                    console.warn('[SITE_SURVEY_AUTOSAVE] Session appears to be expired');
                    updateIndicator('warning', '⚠️ Sessão pode ter expirado - salve seu trabalho');

                    // Verificar novamente em 30 segundos
                    setTimeout(() => {
                        checkSessionStatus();
                    }, 30000);
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (data && !data.logged_in) {
                    console.warn('[SITE_SURVEY_AUTOSAVE] Session expired');
                    updateIndicator('error', '🔐 Sessão expirada - faça login novamente');

                    setTimeout(() => {
                        const shouldLogin = confirm('Sua sessão expirou. Você precisa fazer login novamente para continuar salvando.\n\nDeseja ir para a página de login agora?');
                        if (shouldLogin) {
                            window.location.href = 'login.php';
                        }
                    }, 1000);
                }
            })
            .catch(error => {
                console.warn('[SITE_SURVEY_AUTOSAVE] Could not verify session status:', error);
            });
    }

    // Iniciar quando documento estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutoSave);
    } else {
        initAutoSave();
    }
})();
