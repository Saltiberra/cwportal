/**
 * string_autosave.js
 * Real-time autosave for MPPT string measurement inputs
 * Sends individual field updates to the server as user types
 */

(function () {
    'use strict';

    let autosaveDebounceTimers = {};
    const AUTOSAVE_DELAY_MS = 800; // Wait 800ms after user stops typing

    /**
     * Get the report_id from the form or URL (if in edit mode)
     */
    function getReportId() {
        // Try from form input first
        const reportIdInput = document.querySelector('input[name="report_id"]');
        if (reportIdInput && reportIdInput.value) {
            return reportIdInput.value;
        }

        // Try from URL parameters (fallback)
        const urlParams = new URLSearchParams(window.location.search);
        const reportIdFromUrl = urlParams.get('report_id');
        if (reportIdFromUrl) {
            return reportIdFromUrl;
        }

        return null;
    }

    /**
     * Parse the input ID to extract inverter_index, mppt, string_num, and metric
     * Format: string_{metric}_{inverterIndex}_{mppt}_{string}
     *        or string_{metric}_{mppt}_{string} (if no inverter index)
     */
    function parseStringInputId(inputId) {
        const match = inputId.match(/^string_(.+?)_(\d+)_(\d+)(?:_(\d+))?$/);
        if (!match) return null;

        // Check if there's an inverter index (4 underscores) or not (3 underscores)
        const parts = inputId.split('_');
        if (parts.length === 5) {
            // Format: string_{metric}_{inverterIndex}_{mppt}_{string}
            return {
                metric: parts[1],
                inverter_index: parseInt(parts[2]),
                mppt: parseInt(parts[3]),
                string_num: parseInt(parts[4])
            };
        } else if (parts.length === 4) {
            // Format: string_{metric}_{mppt}_{string}
            return {
                metric: parts[1],
                inverter_index: 0, // Default to first inverter
                mppt: parseInt(parts[2]),
                string_num: parseInt(parts[3])
            };
        }
        return null;
    }

    /**
     * Send autosave request to server for a single metric
     */
    function autosaveStringMeasurement(inputElement) {
        const reportId = getReportId();
        if (!reportId) {
            // Not in edit mode; skip autosave
            return;
        }

        const inputId = inputElement.id;
        const parsed = parseStringInputId(inputId);
        if (!parsed) return;

        const payload = {
            report_id: reportId,
            inverter_index: parsed.inverter_index,
            mppt: parsed.mppt,
            string_num: parsed.string_num,
            metric: parsed.metric,
            value: inputElement.value
        };

        // Try to get inverter_id from somewhere if available
        const inverterIdField = document.querySelector(`input[data-inverter-id="${parsed.inverter_index}"]`);
        if (inverterIdField) {
            payload.inverter_id = inverterIdField.value;
        }

        console.log('[STRING_AUTOSAVE] Saving', parsed.metric, '=', inputElement.value, 'for inv', parsed.inverter_index, 'mppt', parsed.mppt, 'str', parsed.string_num);

        fetch((window.BASE_URL || '') + 'ajax/autosave_string_measurement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[STRING_AUTOSAVE] ✅ Saved:', data.message);

                    // ⭐ Update the hidden field to sync with autosave_sql.js
                    updateStringMeasurementsHiddenField();

                    // Optional: show a brief visual indication (e.g., input border flash)
                    inputElement.style.borderColor = '#28a745';
                    setTimeout(() => {
                        inputElement.style.borderColor = '';
                    }, 500);
                } else {
                    console.warn('[STRING_AUTOSAVE] ❌ Error:', data.error);
                }
            })
            .catch(error => {
                console.error('[STRING_AUTOSAVE] Network error:', error);
            });
    }

    /**
     * Update the hidden string_measurements_data field from all string inputs
     * This synchronizes the frontend with autosave_sql.js which needs this field
     */
    function updateStringMeasurementsHiddenField() {
        const hiddenField = document.getElementById('string_measurements_data');
        if (!hiddenField) return;

        const measurements = [];
        const stringInputs = document.querySelectorAll('input[id^="string_"]');

        // Group inputs by measurement (inverter_index, mppt, string_num)
        const measurementMap = {};

        stringInputs.forEach(input => {
            const parsed = parseStringInputId(input.id);
            if (!parsed || !input.value) return;

            const key = `${parsed.inverter_index}_${parsed.mppt}_${parsed.string_num}`;
            if (!measurementMap[key]) {
                // Get inverter_id from invertersList using inverter_index
                let inverterId = '';
                if (window.invertersList && window.invertersList[parsed.inverter_index]) {
                    inverterId = window.invertersList[parsed.inverter_index].model_id || '';
                }
                measurementMap[key] = {
                    inverter_index: parsed.inverter_index,
                    mppt: parsed.mppt,
                    string_num: parsed.string_num,
                    inverter_id: inverterId,
                    voc: '', isc: '', vmp: '', imp: '', rins: '', irr: '', temp: '', rlo: '', notes: '', current: ''
                };
            }
            measurementMap[key][parsed.metric] = input.value;
        });

        // Convert map to array
        for (const key in measurementMap) {
            if (measurementMap.hasOwnProperty(key)) {
                measurements.push(measurementMap[key]);
            }
        }

        // Update hidden field
        if (measurements.length > 0) {
            hiddenField.value = JSON.stringify(measurements);
            console.log('[STRING_AUTOSAVE] 📝 Updated hidden field with ' + measurements.length + ' measurements');
        }
    }

    /**
     * Attach autosave listeners to all string measurement inputs
     */
    function attachStringAutosaveListeners() {
        const stringInputs = document.querySelectorAll('input[id^="string_"]');
        // Only log on first call or when count changes significantly
        if (!window._lastStringInputCount || Math.abs(window._lastStringInputCount - stringInputs.length) > 5) {
            console.log('[STRING_AUTOSAVE] Found', stringInputs.length, 'string measurement inputs');
            window._lastStringInputCount = stringInputs.length;
        }

        stringInputs.forEach(input => {
            // Skip if this input already has a listener
            if (input.dataset.autosaveAttached) return;
            input.dataset.autosaveAttached = 'true';

            input.addEventListener('input', function () {
                const inputId = this.id;

                // Clear any pending debounce timer for this input
                if (autosaveDebounceTimers[inputId]) {
                    clearTimeout(autosaveDebounceTimers[inputId]);
                }

                // Set a new debounce timer
                autosaveDebounceTimers[inputId] = setTimeout(() => {
                    autosaveStringMeasurement(this);
                    delete autosaveDebounceTimers[inputId];
                }, AUTOSAVE_DELAY_MS);
            });

            input.addEventListener('blur', function () {
                // On blur, immediately autosave (don't wait for debounce)
                const inputId = this.id;
                if (autosaveDebounceTimers[inputId]) {
                    clearTimeout(autosaveDebounceTimers[inputId]);
                    delete autosaveDebounceTimers[inputId];
                }
                autosaveStringMeasurement(this);

                // ⭐ After autosave, update hidden field immediately
                setTimeout(updateStringMeasurementsHiddenField, 100);
            });
        });
    }

    /**
     * Load and populate MPPT tables with autosaved values from the latest draft
     */
    function loadAndPopulateMPPTTables() {
        const reportId = getReportId();
        if (!reportId) {
            // Only log once per session to avoid spam
            if (!window._noReportIdLogged) {
                console.log('[STRING_AUTOSAVE] No report_id found, skipping draft load');
                window._noReportIdLogged = true;
            }
            return;
        }

        console.log('[STRING_AUTOSAVE] Loading draft measurements for report_id=' + reportId);

        // Use absolute URL path - the BASE_URL is already defined elsewhere  on the page
        let url = 'ajax/load_string_measurements_draft.php?report_id=' + reportId;

        // Try multiple URL formats
        if (typeof BASE_URL !== 'undefined') {
            url = BASE_URL + 'ajax/load_string_measurements_draft.php?report_id=' + reportId;
        } else if (typeof window.BASE_URL !== 'undefined') {
            url = window.BASE_URL + 'ajax/load_string_measurements_draft.php?report_id=' + reportId;
        }

        console.log('[STRING_AUTOSAVE] Fetch URL:', url);

        fetch(url)
            .then(response => {
                console.log('[STRING_AUTOSAVE] Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Response status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('[STRING_AUTOSAVE] Response data:', JSON.stringify(data));

                if (!data.success) {
                    console.warn('[STRING_AUTOSAVE] Failed to load draft:', data.error);
                    return;
                }

                const count = data.count || (data.string_measurements_data ? data.string_measurements_data.length : 0);

                if (count === 0) {
                    console.log('[STRING_AUTOSAVE] No draft measurements found (first edit or empty), fetching from DB...');
                    // Buscar ao endpoint SQL
                    fetch((window.BASE_URL || '') + 'ajax/mppt_crud.php?action=load&report_id=' + reportId)
                        .then(resp => resp.json())
                        .then(sqlData => {
                            if (sqlData && sqlData.success && Array.isArray(sqlData.measurements)) {
                                console.log('[STRING_AUTOSAVE] ✅ Loaded ' + sqlData.measurements.length + ' measurements from DB');
                                populateStringInputsFromMeasurements(sqlData.measurements);
                            } else {
                                console.warn('[STRING_AUTOSAVE] No measurements found in DB');
                            }
                        })
                        .catch(err => {
                            console.error('[STRING_AUTOSAVE] ❌ Error loading measurements from DB:', err);
                        });
                    return;
                }

                console.log('[STRING_AUTOSAVE] ✅ Loaded ' + count + ' measurements from draft');

                // Immediately populate
                populateStringInputsFromMeasurements(data.string_measurements_data);
            })
            .catch(error => {
                console.error('[STRING_AUTOSAVE] ❌ Error loading draft measurements:', error);
            });
    }

    /**
     * Populate string input fields from measurements array
     */
    function populateStringInputsFromMeasurements(measurements) {
        if (!Array.isArray(measurements) || measurements.length === 0) {
            return;
        }

        let populatedCount = 0;

        measurements.forEach(function (measurement) {
            const inverterIndex = measurement.inverter_index || 0;
            const mppt = measurement.mppt;
            const stringNum = measurement.string_num;

            // For each metric (voc, isc, vmp, imp, rins, irr, temp, rlo, notes, current)
            const metrics = ['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'notes', 'current'];
            metrics.forEach(function (metric) {
                const inputId = 'string_' + metric + '_' + inverterIndex + '_' + mppt + '_' + stringNum;
                const input = document.getElementById(inputId);

                if (input && measurement[metric]) {
                    const oldValue = input.value;
                    input.value = measurement[metric];
                    if (oldValue !== measurement[metric]) {
                        populatedCount++;
                        console.log('[STRING_AUTOSAVE] 📝 Pre-filled ' + inputId + ' = ' + measurement[metric]);
                    }
                }
            });
        });

        if (populatedCount > 0) {
            console.log('[STRING_AUTOSAVE] ✅ Pre-filled ' + populatedCount + ' input fields from draft');
        }
    }

    /**
     * Initialize: run when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function () {
        console.log('[STRING_AUTOSAVE] Initializing string measurement autosave...');
        attachStringAutosaveListeners();

        // Load draft measurements and pre-populate tables - try immediately and with delays
        loadAndPopulateMPPTTables();
        setTimeout(loadAndPopulateMPPTTables, 100);
        setTimeout(loadAndPopulateMPPTTables, 300);
        setTimeout(loadAndPopulateMPPTTables, 500);

        // Re-attach listeners if MPPT tables are dynamically added (e.g., when changing tabs)
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length > 0) {
                    // Check if new string inputs were added
                    const newStringInputs = document.querySelectorAll('input[id^="string_"]');
                    if (newStringInputs.length > 0) {
                        setTimeout(attachStringAutosaveListeners, 100);
                        // Also re-populate on tab switch or dynamic load
                        setTimeout(loadAndPopulateMPPTTables, 150);
                    }
                }
            });
        });

        // Observe the DOM for new nodes (throttle to avoid excessive checking)
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('[STRING_AUTOSAVE] ✅ Initialized successfully');
    });

    // Also expose as global so it can be called from elsewhere if needed
    window.loadStringMeasurementsDraft = loadAndPopulateMPPTTables;

    // Expose a manual trigger for batch autosave if needed
    window.autosaveAllStringMeasurements = function () {
        console.log('[STRING_AUTOSAVE] Triggering batch autosave for all strings...');
        const stringInputs = document.querySelectorAll('input[id^="string_"]');
        stringInputs.forEach(input => {
            if (input.value && input.value !== '') {
                autosaveStringMeasurement(input);
            }
        });
    };

})();
