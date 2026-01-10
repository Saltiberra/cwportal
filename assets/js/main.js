// DEBUG: Modal lifecycle - safe checks to avoid runtime errors
try {
    const addBrandModal = document.getElementById('addBrandModal');
    if (addBrandModal) {
        addBrandModal.addEventListener('show.bs.modal', function () {
            console.log('[DEBUG] Evento show.bs.modal disparado');
        });
        addBrandModal.addEventListener('shown.bs.modal', function () {
            console.log('[DEBUG] Evento shown.bs.modal disparado');
        });
        addBrandModal.addEventListener('hide.bs.modal', function () {
            console.log('[DEBUG] Evento hide.bs.modal disparado');
        });
        addBrandModal.addEventListener('hidden.bs.modal', function () {
            console.log('[DEBUG] Evento hidden.bs.modal disparado');
        });
        console.log('[DEBUG] addNewBrand called (modal listeners attached)');
    }
} catch (err) {
    console.warn('[DEBUG] Skipping modal lifecycle logs - addBrandModal not available', err);
}
/**
 * Main JavaScript functionality for PV Commissioning System
 * 
 * This file contains core functionality for the commissioning application
 * including form handling, data persistence, and dynamic UI updates
 */

// Simple initialization
(function () {
    'use strict';

    function init() {
        // Skip on reports page
        if (window.REPORTS_PAGE) {
            console.log('[Main.js] ⏭️ Skipping initialization on reports page');
            return;
        }

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Check if we need to force a clean reload after data clear
        if (sessionStorage.getItem('force_reload') === 'true') {
            console.log('Force reload detected, clearing sessionStorage flag');
            sessionStorage.removeItem('force_reload');
            return;
        }

        // Initialize form autosave functionality
        initAutoSave();

        // Initialize dynamic form sections
        initDynamicSections();

        // Load saved form data first, then initialize EPC dropdowns
        loadSavedFormData().then(() => {
            initEpcRepresentativeDropdowns();
            // Ensure Additional Notes persistence is initialized after saved data is loaded
            if (typeof initNotesPersistence === 'function') initNotesPersistence();
        }).catch(error => {
            console.error('Error loading form data:', error);
            // Initialize dropdowns anyway
            initEpcRepresentativeDropdowns();
        });
    }

    // Check if document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/**
 * Initialize autosave functionality for forms
 */
function initAutoSave() {
    // Get all forms with autosave attribute
    const autoSaveForms = document.querySelectorAll('form[data-autosave]');

    autoSaveForms.forEach(form => {
        // Add input event listener to all form elements
        form.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', function () {
                saveFormData(form);
            });

            // For text inputs, also save on keyup with delay
            if (element.type === 'text' || element.tagName.toLowerCase() === 'textarea') {
                let typingTimer;
                element.addEventListener('keyup', function () {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function () {
                        saveFormData(form);
                    }, 1000); // 1 second delay after typing stops
                });
            }
        });
    });
}

/**
 * Format cable size to display a sensible representation.
 * - If the value contains alphabetic characters (e.g. '4x95', '4x95mm2'), return it as-is to preserve textual details.
 * - Otherwise extract the numeric portion (e.g. '11mmÂ²' -> '11').
 * Returns '-' when no usable value found.
 */
function formatCableSize(size) {
    if (size === null || size === undefined || size === '') return '-';
    try {
        const s = String(size).trim();
        // If the string contains letters or 'x' (e.g., '4x95'), preserve the full text
        if (/[a-zA-Z×x]/.test(s)) return s;
        // Match first numeric group, allowing decimals with dot or comma
        const m = s.match(/[0-9]+(?:[.,][0-9]+)*/);
        if (m && m[0]) return m[0];
        // Fallback: strip non-digit characters
        const stripped = s.replace(/[^0-9.,]/g, '');
        return stripped || '-';
    } catch (e) {
        return '-';
    }
}

/**
 * Save form data to database via AJAX
 *
 * @param {HTMLFormElement} form - The form element to save
 */
function saveFormData(form) {
    // In EDIT MODE, do not persist the main form to database to avoid cross-report contamination
    if (window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        return;
    }
    const formId = form.id || form.getAttribute('data-autosave');
    if (!formId) return;

    const formData = {};

    // Get all form elements
    form.querySelectorAll('input, select, textarea').forEach(element => {
        if (!element.name) return;

        // Handle different input types
        if (element.type === 'checkbox') {
            formData[element.name] = element.checked;
        } else if (element.type === 'radio') {
            if (element.checked) {
                formData[element.name] = element.value;
            }
        } else {
            formData[element.name] = element.value;
        }
    });

    // Save to database via AJAX instead of localStorage
    const reportId = getReportId();
    if (reportId) {
        fetch((window.BASE_URL || '') + 'ajax/save_form_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: `report_id=${encodeURIComponent(reportId)}&form_id=${encodeURIComponent(formId)}&form_data=${encodeURIComponent(JSON.stringify(formData))}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`[saveFormData] Form ${formId} saved to database`);
                } else {
                    console.warn(`[saveFormData] Failed to save form ${formId}:`, data.message);
                }
            })
            .catch(error => {
                console.error(`[saveFormData] Error saving form ${formId}:`, error);
            });
    } else {
        console.log(`[saveFormData] No report ID available, skipping database save for form ${formId}`);
    }

    // Show saved indicator
    showSavedIndicator();
}

/**
 * Load saved form data from database via AJAX
 */
function loadSavedFormData() {
    return new Promise((resolve) => {
        // In EDIT MODE we must NOT restore from database; use SQL-rendered values only
        if (window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            console.log('[Main.js] Edit mode detected – skipping database restore for autosave forms');
            // Still ensure selects honor server-provided data-selected attributes
            try { restoreDataSelectedValues(); } catch (e) { }
            resolve();
            return;
        }

        const reportId = getReportId();
        if (!reportId) {
            console.log('[loadSavedFormData] No report ID available, skipping database load');
            restoreDataSelectedValues();
            resolve();
            return;
        }

        const autoSaveForms = document.querySelectorAll('form[data-autosave]');
        if (autoSaveForms.length === 0) {
            // Even if no autosave forms, restore data-selected values for edit mode
            restoreDataSelectedValues();
            resolve();
            return;
        }

        let formsProcessed = 0;

        autoSaveForms.forEach(form => {
            const formId = form.id || form.getAttribute('data-autosave');
            if (!formId) {
                formsProcessed++;
                if (formsProcessed === autoSaveForms.length) {
                    restoreDataSelectedValues();
                    resolve();
                }
                return;
            }

            // Load saved data from database via AJAX
            fetch((window.BASE_URL || "") + `ajax/load_form_draft.php?report_id=${encodeURIComponent(reportId)}&form_id=${encodeURIComponent(formId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.form_data) {
                        try {
                            const formData = JSON.parse(data.form_data);

                            // Set values to form elements
                            for (const [key, value] of Object.entries(formData)) {
                                const elements = form.querySelectorAll(`[name="${key}"]`);

                                elements.forEach(element => {
                                    if (element.type === 'checkbox') {
                                        element.checked = value;
                                    } else if (element.type === 'radio') {
                                        element.checked = (element.value === value);
                                    } else {
                                        element.value = value;
                                    }
                                });
                            }
                            console.log(`[loadSavedFormData] Loaded form ${formId} from database`);
                        } catch (error) {
                            console.error(`[loadSavedFormData] Error parsing form data for ${formId}:`, error);
                        }
                    } else {
                        console.log(`[loadSavedFormData] No saved data found for form ${formId}`);
                    }
                })
                .catch(error => {
                    console.error(`[loadSavedFormData] Error loading form ${formId}:`, error);
                })
                .finally(() => {
                    formsProcessed++;
                    if (formsProcessed === autoSaveForms.length) {
                        // After loading form data, also load modules data from database
                        loadModulesFromLocalStorage();
                        // Restore data-selected values for edit mode (if not already in localStorage)
                        restoreDataSelectedValues();
                        resolve();
                    }
                });
        });
    });
}

/**
 * Restore values from data-selected attributes (used for edit mode)
 * This ensures form fields from database are properly restored before dropdown initialization
 */
function restoreDataSelectedValues() {
    const selectsWithDataSelected = document.querySelectorAll('[data-selected]');
    selectsWithDataSelected.forEach(element => {
        const selectedValue = element.dataset.selected;
        if (selectedValue && !element.value) {
            element.value = selectedValue;
            console.log(`[Data-Selected] Restored ${element.id || element.name} to: ${selectedValue}`);
        }
    });
}

/**
 * Shows a temporary "Saved" indicator
 */
function showSavedIndicator() {
    // Check if the indicator already exists
    let savedIndicator = document.getElementById('saved-indicator');

    if (!savedIndicator) {
        // Create the indicator element
        savedIndicator = document.createElement('div');
        savedIndicator.id = 'saved-indicator';
        savedIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Autosaved';
        savedIndicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--success-color);
            color: white;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1050;
        `;
        document.body.appendChild(savedIndicator);
    }

    // Show and fade out the indicator
    savedIndicator.style.opacity = '1';
    setTimeout(() => {
        savedIndicator.style.opacity = '0';
    }, 2000);
}

/**
 * Load modules and layouts data from database and populate the hidden fields
 */
function loadModulesFromLocalStorage() {
    try {
        // Load modules data - prioritize SQL data in edit mode
        let modulesData = null;

        // In edit mode, prioritize window.existingModules from SQL
        if (window.existingModules && Array.isArray(window.existingModules)) {
            modulesData = window.existingModules;
            console.log('[Edit Mode] Loaded modules from SQL:', modulesData.length, 'modules');
        } else if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            // Only use database if NOT in edit mode
            const reportId = getReportId();
            if (reportId) {
                // Load from database instead of localStorage
                fetch((window.BASE_URL || "") + `ajax/load_modules_draft.php?report_id=${encodeURIComponent(reportId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.modules_data) {
                            try {
                                modulesData = JSON.parse(data.modules_data);
                                if (Array.isArray(modulesData) && modulesData.length > 0) {
                                    console.log('Loaded modules data from database:', modulesData.length, 'modules');
                                    // Set the modules data to the hidden field
                                    const modulesDataField = document.getElementById('modules_data');
                                    if (modulesDataField) {
                                        modulesDataField.value = JSON.stringify(modulesData);
                                    }
                                }
                            } catch (e) {
                                console.warn('Could not parse modules data from database', e);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading modules from database:', error);
                    });
            }
        }

        // Set the modules data to the hidden field if we have any data
        if (modulesData && Array.isArray(modulesData)) {
            const modulesDataField = document.getElementById('modules_data');
            if (modulesDataField) {
                modulesDataField.value = JSON.stringify(modulesData);
            }
        }

        // Load layouts data - ONLY from SQL in edit mode, NEVER localStorage
        let layoutsData = null;

        // In edit mode, ONLY use window.existingLayouts from SQL
        if (window.EDIT_MODE_SKIP_LOCALSTORAGE && window.existingLayouts && Array.isArray(window.existingLayouts)) {
            layoutsData = window.existingLayouts;
            console.log('[Edit Mode] Loaded layouts from SQL:', layoutsData.length, 'layouts');
        }
        // In NEW report mode, use database if available
        else if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            const reportId = getReportId();
            if (reportId) {
                // Load from database instead of localStorage
                fetch((window.BASE_URL || "") + `ajax/load_layouts_draft.php?report_id=${encodeURIComponent(reportId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.layouts_data) {
                            try {
                                layoutsData = JSON.parse(data.layouts_data);
                                if (Array.isArray(layoutsData) && layoutsData.length > 0) {
                                    console.log('[New Report] Loaded layouts from database:', layoutsData.length, 'layouts');
                                    // Set the layouts data to the hidden field
                                    const layoutsDataField = document.getElementById('layouts_data');
                                    if (layoutsDataField) {
                                        layoutsDataField.value = JSON.stringify(layoutsData);
                                    }
                                }
                            } catch (e) {
                                console.warn('Could not parse layouts data from database', e);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading layouts from database:', error);
                    });
            }
        }

        // Set the layouts data to the hidden field if we have any data
        if (layoutsData && Array.isArray(layoutsData)) {
            const layoutsDataField = document.getElementById('layouts_data');
            if (layoutsDataField) {
                layoutsDataField.value = JSON.stringify(layoutsData);
            }
        }

        // Load protection data - BUT ONLY IF NOT FROM SQL (edit mode)
        // In edit mode, window.LOAD_PROTECTION_FROM_SQL is true and window.existingProtection has SQL data
        if (!window.LOAD_PROTECTION_FROM_SQL) {
            const reportId = getReportId();
            if (reportId) {
                // Load from database instead of localStorage
                fetch((window.BASE_URL || "") + `ajax/load_protection_draft.php?report_id=${encodeURIComponent(reportId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.protection_data) {
                            try {
                                const protectionData = JSON.parse(data.protection_data);
                                if (Array.isArray(protectionData) && protectionData.length > 0) {
                                    // Set the protection data to the hidden field
                                    const protectionDataField = document.getElementById('protection_data');
                                    if (protectionDataField) {
                                        protectionDataField.value = JSON.stringify(protectionData);
                                        console.log('Loaded protection data from database:', protectionData.length, 'protection items');
                                    }
                                }
                            } catch (e) {
                                console.warn('Could not parse protection data from database', e);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading protection from database:', error);
                    });
            }
        } else {
            console.log('[Edit Mode] Skipping database protection data - using SQL/draft data');
        }

        // Load protection cables data - prioritize SQL data in edit mode
        let protectionCablesData = null;

        // In edit mode, prioritize window.existingProtectionCables from SQL
        if (window.existingProtectionCables && Array.isArray(window.existingProtectionCables)) {
            protectionCablesData = window.existingProtectionCables;
            console.log('[Edit Mode] Loaded protection cables from SQL:', protectionCablesData.length, 'items');
        } else if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            // Only use database if NOT in edit mode
            const reportId = getReportId();
            if (reportId) {
                // Load from database instead of localStorage
                fetch((window.BASE_URL || "") + `ajax/load_protection_cables_draft.php?report_id=${encodeURIComponent(reportId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.protection_cables_data) {
                            try {
                                protectionCablesData = JSON.parse(data.protection_cables_data);
                                if (Array.isArray(protectionCablesData) && protectionCablesData.length > 0) {
                                    console.log('Loaded protection cables data from database:', protectionCablesData.length, 'protection cable items');
                                    // Set the protection cables data to the hidden field
                                    const protectionCablesDataField = document.getElementById('protection_cable_data');
                                    if (protectionCablesDataField) {
                                        protectionCablesDataField.value = JSON.stringify(protectionCablesData);
                                    }
                                }
                            } catch (e) {
                                console.warn('Could not parse protection cables data from database', e);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading protection cables from database:', error);
                    });
            }
        }

        // Set the protection cables data to the hidden field if we have any data
        if (protectionCablesData && Array.isArray(protectionCablesData)) {
            const protectionCablesDataField = document.getElementById('protection_cable_data');
            if (protectionCablesDataField) {
                protectionCablesDataField.value = JSON.stringify(protectionCablesData);
            }
        }
    } catch (error) {
        console.error('Error loading modules/layouts data from database:', error);
    }
}

/**
 * Initialize EPC and Representative dropdowns with persistence
 */
function initEpcRepresentativeDropdowns() {
    const epcSelect = document.getElementById('epc_id');
    const repSelect = document.getElementById('representative_id');
    const addRepBtn = document.getElementById('add-representative-btn');

    if (!epcSelect || !repSelect) {
        console.warn('EPC or Representative dropdowns not found');
        return;
    }

    // Get pre-selected values from multiple sources with priority
    let preSelectedEpcId = '';
    let preSelectedRepId = '';

    // Dedicated localStorage keys for quick lookup
    const lsEpcKey = 'commissioning_epc_id';
    const lsRepKey = 'commissioning_representative_id';

    // 🔒 NOVO RELATÓRIO: Pular localStorage completamente
    const isNewReport = window.IS_NEW_REPORT === true;

    if (isNewReport) {
        console.log('[EPC/REP] 🆕 New report mode - skipping localStorage');
    }

    // Priority 1: Current form values (might already be set by other code)
    preSelectedEpcId = epcSelect.value || '';
    preSelectedRepId = repSelect.value || '';

    // Priority 2: dedicated database draft keys (explicit save of these selects)
    // 🔒 SKIP para novos relatórios
    if (!isNewReport) {
        try {
            // Load EPC selection from database draft
            fetch((window.BASE_URL || '') + 'ajax/load_epc_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.epc_id && !preSelectedEpcId) {
                        preSelectedEpcId = data.epc_id;
                        console.log('[EPC/REP] ✓ Loaded EPC from database draft:', preSelectedEpcId);
                    }
                })
                .catch(error => {
                    console.warn('Could not load EPC selection from database draft:', error);
                });

            // Load Representative selection from database draft
            fetch((window.BASE_URL || '') + 'ajax/load_representative_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.representative_id && !preSelectedRepId) {
                        preSelectedRepId = data.representative_id;
                        console.log('[EPC/REP] ✓ Loaded Representative from database draft:', preSelectedRepId);
                    }
                })
                .catch(error => {
                    console.warn('Could not load Representative selection from database draft:', error);
                });
        } catch (error) {
            console.warn('Could not read dedicated database draft keys for EPC/Rep:', error);
        }
    }

    // Priority 3: data-selected attributes (from PHP/database)
    if (!preSelectedEpcId) {
        preSelectedEpcId = epcSelect.dataset.selected || '';
    }
    if (!preSelectedRepId) {
        preSelectedRepId = repSelect.dataset.selected || '';
    }

    // Function to set loading state
    function setLoadingState(selectElement, loading = true) {
        if (loading) {
            selectElement.innerHTML = '<option value="">Loading...</option>';
            selectElement.disabled = true;
        } else {
            selectElement.disabled = false;
        }
    }

    // Function to restore selection after options are loaded
    function restoreSelection(selectElement, valueToRestore, callback) {
        if (valueToRestore && selectElement.querySelector(`option[value="${valueToRestore}"]`)) {
            selectElement.value = valueToRestore;
            if (callback) callback();
            return true;
        }
        return false;
    }

    // Load EPC options
    setLoadingState(epcSelect, true);

    fetch((window.BASE_URL || '') + 'ajax/get_epcs.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            setLoadingState(epcSelect, false);
            epcSelect.innerHTML = '<option value="">Select EPC...</option>';

            // Ensure data is an array before iterating
            if (!Array.isArray(data)) {
                console.error('[EPC] Unexpected response format (expected array):', data);
                if (data && data.error) {
                    console.error('[EPC] Server error:', data.error);
                }
                return;
            }

            // Add new options
            data.forEach(epc => {
                const option = document.createElement('option');
                option.value = epc.id;
                option.textContent = epc.name;
                epcSelect.appendChild(option);
            });

            // Restore EPC selection and load representatives
            if (restoreSelection(epcSelect, preSelectedEpcId, () => {
                // Save to database draft if not in edit mode
                if (!isNewReport) {
                    fetch((window.BASE_URL || '') + 'ajax/save_epc_draft.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            epc_id: preSelectedEpcId
                        })
                    })
                        .catch(error => {
                            console.error('Error saving EPC selection to database draft:', error);
                        });
                }
            })) {
                console.log('[EPC] ✓ Restored EPC selection:', preSelectedEpcId);
                loadRepresentatives(preSelectedEpcId, preSelectedRepId);
            } else {
                // Try to load from database draft if not in edit mode
                if (!isNewReport) {
                    fetch((window.BASE_URL || '') + 'ajax/load_epc_draft.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.epc_id) {
                                if (restoreSelection(epcSelect, data.epc_id)) {
                                    console.log('[EPC] ✓ Restored EPC selection from database draft:', data.epc_id);
                                    loadRepresentatives(data.epc_id, preSelectedRepId);
                                }
                            } else {
                                console.log('[EPC] ✗ No EPC selection found in database draft');
                                repSelect.innerHTML = '<option value="">Select Representative...</option>';
                            }
                        })
                        .catch(err => {
                            console.warn('Could not load EPC selection from database draft', err);
                            repSelect.innerHTML = '<option value="">Select Representative...</option>';
                        });
                } else {
                    console.log('[EPC] ✗ No pre-selected EPC found');
                    repSelect.innerHTML = '<option value="">Select Representative...</option>';
                }
            }
        })
        .catch(error => {
            console.error('Error loading EPC options:', error);
            setLoadingState(epcSelect, false);
            epcSelect.innerHTML = '<option value="">Error loading EPCs</option>';
        });

    // Function to load representatives
    function loadRepresentatives(epcId, preSelectedRepId = '') {
        console.log('[REP] Loading representatives for EPC:', epcId, 'Pre-selected:', preSelectedRepId);
        console.log('[REP] DEBUG - repSelect.dataset.selected:', repSelect.dataset.selected);

        if (!epcId) {
            repSelect.innerHTML = '<option value="">Select Representative...</option>';
            if (addRepBtn) {
                addRepBtn.disabled = true;
                addRepBtn.title = 'Select an EPC Company first';
            }
            return;
        }

        setLoadingState(repSelect, true);

        fetch((window.BASE_URL || "") + `ajax/get_representatives.php?epc_id=${epcId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                setLoadingState(repSelect, false);
                repSelect.innerHTML = '<option value="">Select Representative...</option>';
                // Add new options
                if (!Array.isArray(data)) {
                    console.error('[REP] Unexpected response (not array):', data);
                    if (data && data.error) {
                        // show error message if available
                        console.error('[REP] Server error:', data.error);
                    }
                    return;
                }

                data.forEach(rep => {
                    const option = document.createElement('option');
                    option.value = rep.id;
                    option.textContent = `${rep.name} (${rep.phone})`;
                    repSelect.appendChild(option);
                });

                // Restore representative selection - try preSelectedRepId first, then data-selected
                let selectedValue = preSelectedRepId || repSelect.dataset.selected || '';
                console.log('[REP] Attempting to restore with value:', selectedValue);

                if (selectedValue && restoreSelection(repSelect, selectedValue)) {
                    console.log('[REP] ✓ Selection restored successfully:', selectedValue);
                } else {
                    console.log('[REP] No selection to restore or representative not found');
                }

                console.log('[REP] ✓ Loaded', data.length, 'representatives');
                console.log('[REP] Final select value:', repSelect.value);

                // Enable add representative button
                if (addRepBtn) {
                    addRepBtn.disabled = false;
                    addRepBtn.title = 'Add New Representative';
                    console.log('[REP] ✓ Add Representative button enabled');
                }
            })
            .catch(error => {
                console.error('Error loading representatives:', error);
                setLoadingState(repSelect, false);
                repSelect.innerHTML = '<option value="">Error loading representatives</option>';
            });
    }

    // Handle EPC selection change
    epcSelect.addEventListener('change', function () {
        const selectedEpcId = this.value;

        // When EPC changes, also pass the preSelectedRepId from data-selected if it exists
        // This preserves the originally saved representative selection
        const repIdToRestore = !preSelectedRepId && repSelect.dataset.selected
            ? repSelect.dataset.selected
            : preSelectedRepId;

        loadRepresentatives(selectedEpcId, repIdToRestore);

        // Save the selection immediately
        // Save to database draft if not in edit mode
        if (!isNewReport) {
            if (selectedEpcId) {
                fetch((window.BASE_URL || '') + 'ajax/save_epc_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        epc_id: selectedEpcId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('[JS] EPC selection saved to database draft');
                        } else {
                            console.warn('Could not save EPC selection to database draft:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving EPC selection to database draft:', error);
                    });
            } else {
                // Clear EPC selection
                fetch((window.BASE_URL || '') + 'ajax/save_epc_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        epc_id: null
                    })
                })
                    .catch(error => {
                        console.error('Error clearing EPC selection from database draft:', error);
                    });
            }
            // Clear previously selected representative when EPC changes
            fetch((window.BASE_URL || '') + 'ajax/save_representative_draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    representative_id: null
                })
            })
                .catch(error => {
                    console.error('Error clearing representative selection from database draft:', error);
                });
        }

        const form = document.getElementById('commissioningForm');
        if (form) saveFormData(form);
    });

    // Handle Representative selection change
    repSelect.addEventListener('change', function () {
        const selectedRepId = this.value;
        // Save to database draft if not in edit mode
        if (!isNewReport) {
            if (selectedRepId) {
                fetch((window.BASE_URL || '') + 'ajax/save_representative_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        representative_id: selectedRepId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('[JS] Representative selection saved to database draft');
                        } else {
                            console.warn('Could not save representative selection to database draft:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving representative selection to database draft:', error);
                    });
            } else {
                // Clear representative selection
                fetch((window.BASE_URL || '') + 'ajax/save_representative_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        representative_id: null
                    })
                })
                    .catch(error => {
                        console.error('Error clearing representative selection from database draft:', error);
                    });
            }
        }

        // Save the selection immediately to the main form autosave object as well
        const form = document.getElementById('commissioningForm');
        if (form) saveFormData(form);
    });

    // Initialize add representative button state
    if (addRepBtn) {
        addRepBtn.disabled = true;
        addRepBtn.title = 'Select an EPC Company first';
    }

    // Load representative selection from database draft if not in edit mode
    if (!isNewReport) {
        console.log('[REP] Loading representative selection from database draft...');
        fetch((window.BASE_URL || '') + 'ajax/load_representative_draft.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.representative_id) {
                    console.log('[REP] ✓ Found representative selection in database draft:', data.representative_id);
                    repSelect.dataset.selected = data.representative_id;
                } else {
                    console.log('[REP] ✗ No representative selection found in database draft');
                }
            })
            .catch(err => {
                console.warn('Could not load representative selection from database draft', err);
            });
    }
}

/**
 * Initialize dynamic form sections (show/hide, add/remove)
 */
function initDynamicSections() {
    // Toggle visibility of collapsible sections
    document.querySelectorAll('[data-toggle-section]').forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-toggle-section');
            const targetSection = document.getElementById(targetId);

            if (targetSection) {
                if (targetSection.classList.contains('d-none')) {
                    targetSection.classList.remove('d-none');
                    this.innerHTML = this.innerHTML.replace('Show', 'Hide');
                    this.classList.replace('btn-outline-primary', 'btn-outline-secondary');
                } else {
                    targetSection.classList.add('d-none');
                    this.innerHTML = this.innerHTML.replace('Hide', 'Show');
                    this.classList.replace('btn-outline-secondary', 'btn-outline-primary');
                }
            }
        });
    });

    // Add new item functionality
    document.querySelectorAll('[data-add-item]').forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-add-item');
            const template = document.getElementById(`${targetId}-template`);
            const container = document.getElementById(`${targetId}-container`);

            if (template && container) {
                // Clone the template
                const newItem = template.content.cloneNode(true);

                // Update the IDs and names to make them unique
                const itemCount = container.children.length;
                newItem.querySelectorAll('[id]').forEach(el => {
                    el.id = `${el.id}-${itemCount}`;
                });
                newItem.querySelectorAll('[name]').forEach(el => {
                    el.name = `${el.name}-${itemCount}`;
                });

                // Add delete button functionality
                const deleteBtn = newItem.querySelector('.delete-item');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        this.closest('.dynamic-item').remove();
                    });
                }

                // Append to container
                container.appendChild(newItem);
            }
        });
    });
}

/**
 * Load equipment data from database via AJAX
 * 
 * @param {string} type - Equipment type (e.g., 'inverter', 'module')
 * @param {number} brandId - Selected brand ID
 * @param {string} targetElement - ID of select element to populate
 */
function loadEquipmentModels(type, brandId, targetElement) {
    if (!brandId) return Promise.resolve();

    const target = document.getElementById(targetElement);
    if (!target) return Promise.resolve();

    // Clear current options
    target.innerHTML = '<option value="">Select model...</option>';

    // Show loading indicator
    target.disabled = true;

    console.log(`DEBUG: Loading models for ${type}, brand ID ${brandId}, target ${targetElement}`);

    // Get base URL for AJAX requests
    const baseUrl = window.BASE_URL || '/';

    // Make AJAX request and return the promise
    const url = `${baseUrl}ajax/get_equipment_models.php?type=${type}&brand_id=${brandId}`;
    console.log(`DEBUG: Fetching from URL: ${url}`);

    return fetch(url)
        .then(response => {
            console.log(`DEBUG: Response status: ${response.status}`);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(response => {
            console.log(`DEBUG: Received response:`, response);

            // Handle both old and new response formats
            const data = response.data || response;

            if (response.debug) {
                console.log(`DEBUG info:`, response.debug);
            }

            // Add new options
            if (Array.isArray(data)) {
                data.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model.id;
                    option.textContent = model.model_name;
                    // Store power_options if available (for PV modules)
                    if (model.power_options) {
                        option.setAttribute('data-power-options', model.power_options);
                    }
                    target.appendChild(option);
                });
                console.log(`DEBUG: Added ${data.length} options to dropdown`);
            } else {
                console.error('DEBUG: Received non-array data:', data);
            }

            // Enable select
            target.disabled = false;
            return data;
        })
        .catch(error => {
            console.warn('[loadEquipmentModels] Error loading models:', error.message);
            target.disabled = false;
            // Don't show toast for model loading errors - might be handled by other scripts
            return [];
        });
}

/**
 * Manage PV modules table with add/edit/delete functionality
 */
let modulesList = [];
let editingModuleIndex = -1;
// Declare globally so other scripts can access
if (!window.invertersList) {
    window.invertersList = [];
    console.log('[Main] Criando window.invertersList vazio');
} else {
    console.log('[Main] window.invertersList já existe com', window.invertersList.length, 'items');
}
window.editingInverterIndex = (typeof window.editingInverterIndex !== 'undefined') ? window.editingInverterIndex : -1;

document.addEventListener('DOMContentLoaded', function () {
    // Skip on reports page
    if (window.REPORTS_PAGE) {
        console.log('[Main.js DOMContentLoaded] ⏭️ Skipping on reports page');
        return;
    }

    // Garante que os dados do layout são enviados como arrays para o PHP
    const commissioningForm = document.getElementById('commissioningForm');
    if (commissioningForm) {
        commissioningForm.addEventListener('submit', function (e) {
            // Debug: Check notes field value before submit
            const notesField = document.getElementById('notes');
            if (notesField) {
                console.log('[SUBMIT DEBUG] Notes field value:', notesField.value);
                console.log('[SUBMIT DEBUG] Notes field name:', notesField.name);
            } else {
                console.warn('[SUBMIT DEBUG] Notes field not found!');
            }

            // Remove inputs antigos
            document.querySelectorAll('.layout-hidden-generated').forEach(el => el.remove());
            // Adiciona inputs hidden para cada item do layout
            layoutsList.forEach((layout, idx) => {
                const fields = [
                    { name: 'roof_id[]', value: layout.roof_id },
                    { name: 'layout_quantity[]', value: layout.quantity },
                    { name: 'azimuth[]', value: layout.azimuth },
                    { name: 'tilt[]', value: layout.tilt },
                    { name: 'mounting[]', value: layout.mounting }
                ];
                fields.forEach(f => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = f.name;
                    input.value = f.value;
                    input.classList.add('layout-hidden-generated');
                    commissioningForm.appendChild(input);
                });
            });
        });
    }
    // Initialize module management
    initModulesTable();

    // Initialize inverter management
    initInvertersTable();

    // Initialize Associated Equipment validation
    initAssociatedEquipmentValidation();

    // Initialize installed power calculation
    updateInstalledPower();

    // Initialize all equipment dropdowns (includes PV modules)
    if (typeof initEquipmentDropdowns === 'function') {
        console.log('Calling initEquipmentDropdowns from main.js');
        initEquipmentDropdowns();
    }
});

/**
 * Initialize the modules table and related functionality
 */
function initModulesTable() {
    const addModuleBtn = document.getElementById('add-module-btn');
    if (addModuleBtn) {
        addModuleBtn.addEventListener('click', addModuleToTable);
    }

    // Initialize add brand button
    const addModuleBrandBtn = document.getElementById('add-module-brand-btn');
    if (addModuleBrandBtn) {
        console.log('DEBUG: Found add-module-brand-btn, adding event listener');
        addModuleBrandBtn.addEventListener('click', () => {
            console.log('DEBUG: add-module-brand-btn clicked');
            showAddBrandModal('pv_module');
        });
    } else {
        console.log('DEBUG: add-module-brand-btn not found');
    }

    // Initialize add model button
    const addModuleModelBtn = document.getElementById('add-module-model-btn');
    if (addModuleModelBtn) {
        console.log('DEBUG: Found add-module-model-btn, adding event listener');
        addModuleModelBtn.addEventListener('click', () => {
            console.log('DEBUG: add-module-model-btn clicked');
            showAddModelModal('pv_module');
        });
    } else {
        console.log('DEBUG: add-module-model-btn not found');
    }

    // Initialize model selection event listener for power options
    const modelSelect = document.getElementById('new_module_model');
    if (modelSelect) {
        modelSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const modelId = this.value;
            const powerSelect = document.getElementById('new_module_power');

            if (modelId) {
                // Fetch power options for the selected model
                fetch((window.BASE_URL || "") + `ajax/get_module_power.php?model_id=${modelId}`)
                    .then(response => response.json())
                    .then(modelData => {
                        // Store power options in a global variable for later use
                        window.currentPowerOptions = modelData.power_options || null;
                        console.log('DEBUG: Power options loaded for model:', modelId, window.currentPowerOptions);

                        // Populate power dropdown
                        if (powerSelect) {
                            powerSelect.innerHTML = '<option value="">Select Power...</option>';
                            if (window.currentPowerOptions) {
                                const options = window.currentPowerOptions.split(',');
                                options.forEach(option => {
                                    const val = option.trim();
                                    powerSelect.innerHTML += `<option value="${val}">${val} W</option>`;
                                });
                                // Select first option by default
                                if (options.length > 0) {
                                    powerSelect.value = options[0].trim();
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching power options:', error);
                        window.currentPowerOptions = null;
                        if (powerSelect) {
                            powerSelect.innerHTML = '<option value="">Error!</option>';
                        }
                    });
            } else {
                window.currentPowerOptions = null;
                if (powerSelect) {
                    powerSelect.innerHTML = '<option value="">Select Power...</option>';
                }
            }
        });
    }

    // NOTE: PV module brands are loaded by inject_module_dropdowns.php (preloaded from PHP)
    // Do NOT call loadEquipmentBrands here to avoid duplicate loading and "Error loading brands" toast

    // Setup event listener for brand dropdown to load models
    const moduleBrandSelect = document.getElementById('new_module_brand');
    if (moduleBrandSelect) {
        moduleBrandSelect.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                // Load models for the selected brand
                if (typeof window.loadEquipmentModels === 'function') {
                    window.loadEquipmentModels('pv_module', brandId, 'new_module_model');
                } else {
                    console.error('loadEquipmentModels function not available');
                }
            } else {
                // Clear model dropdown if no brand selected
                const modelSelect = document.getElementById('new_module_model');
                if (modelSelect) {
                    modelSelect.innerHTML = '<option value="">Select Model...</option>';
                }
            }
        });
    }

    // Create global function to load existing modules (called by autosave_sql.js)
    window.loadExistingModules = function () {
        console.log('[loadExistingModules] Called - window.existingModules:', window.existingModules);

        if (!window.existingModules || !Array.isArray(window.existingModules)) {
            console.log('[loadExistingModules] No modules to load');
            return;
        }

        console.log('[loadExistingModules] Loading', window.existingModules.length, 'modules');

        modulesList = window.existingModules.map(module => {
            // Extract datasheet URL from characteristics if present
            let datasheetUrl = '';
            if (module.characteristics && module.characteristics.includes('Datasheet:')) {
                const match = module.characteristics.match(/Datasheet: ([^|]+)/);
                if (match && match[1]) {
                    datasheetUrl = match[1].trim();
                }
            }

            return {
                brand_id: module.brand_id || '',
                brand_name: module.brand_name || module.brand || '',
                model_id: module.model_id || '',
                model_name: module.model_name || module.model || '',
                quantity: module.quantity || '',
                power_rating: module.power_rating || 0,
                power_options: module.power_options || null,
                status: module.status || 'existing',
                status_text: (module.status || 'existing').charAt(0).toUpperCase() + (module.status || 'existing').slice(1),
                location: module.location || '',
                datasheet_url: datasheetUrl
            };
        });

        // Update the table and UI
        updateModulesTable();
        document.getElementById('modules_data').value = JSON.stringify(modulesList);

        setTimeout(() => {
            updateInstalledPower();
        }, 100);

        console.log('[loadExistingModules] ✅ Modules loaded successfully');
    };

    // Load existing modules if available
    console.log('[Main.js DEBUG] Checking for window.existingModules...');
    console.log('[Main.js DEBUG] window.existingModules exists?', typeof window.existingModules !== 'undefined');
    console.log('[Main.js DEBUG] window.existingModules:', window.existingModules);

    if (window.existingModules && Array.isArray(window.existingModules)) {
        console.log('[Main.js DEBUG] Loading', window.existingModules.length, 'existing modules');
        modulesList = window.existingModules.map(module => {
            // Extract datasheet URL from characteristics if present
            let datasheetUrl = '';
            if (module.characteristics && module.characteristics.includes('Datasheet:')) {
                const match = module.characteristics.match(/Datasheet: ([^|]+)/);
                if (match && match[1]) {
                    datasheetUrl = match[1].trim();
                }
            }

            const mappedModule = {
                brand_id: module.brand_id || '', // Now we have brand_id from PHP
                brand_name: module.brand,
                model_id: module.model_id || '', // Now we have model_id from PHP
                model_name: module.model,
                quantity: module.quantity,
                power_rating: module.power_rating || 0, // Add power_rating
                power_options: module.power_options || null, // Add power_options
                status: module.status,
                status_text: module.status.charAt(0).toUpperCase() + module.status.slice(1),
                location: module.location || '',
                datasheet_url: datasheetUrl
            };

            console.log('[Main.js DEBUG] Mapped module:', {
                original_status: module.status,
                mapped_status: mappedModule.status,
                status_type: typeof mappedModule.status,
                power_rating: mappedModule.power_rating,
                quantity: mappedModule.quantity
            });

            return mappedModule;
        });

        // Update the table with existing modules
        console.log('[Main.js DEBUG] Calling updateModulesTable() with modulesList:', modulesList);
        console.log('[Main.js DEBUG] First module detailed:', JSON.stringify(modulesList[0], null, 2));
        updateModulesTable();
        console.log('[Main.js DEBUG] updateModulesTable() completed');

        // Update hidden field with the data
        document.getElementById('modules_data').value = JSON.stringify(modulesList);

        // Calculate installed power - with slight delay to ensure table is rendered
        console.log('[Main.js DEBUG] About to calculate installed power...');
        console.log('[Main.js DEBUG] modulesList for power calculation:', modulesList);
        setTimeout(() => {
            updateInstalledPower();
            console.log('[Main.js DEBUG] updateInstalledPower() completed');
        }, 100);
        console.log('[Main.js DEBUG] Modules loaded and table updated successfully!');
    } else {
        // Try to load from hidden input as fallback
        try {
            const hidden = document.getElementById('modules_data');
            if (hidden && hidden.value) {
                const parsed = JSON.parse(hidden.value);
                if (Array.isArray(parsed)) {
                    modulesList = parsed;
                    updateModulesTable();
                    updateInstalledPower();
                }
            }
        } catch (e) {
            console.warn('Could not parse modules data from hidden input');
        }
    }
}

/**
 * Show modal for adding a new brand
 * 
 * @param {string} equipmentType - Type of equipment (pv_module, inverter, etc.)
 */
function showAddBrandModal(equipmentType) {
    // Prevent multiple modals
    if (window.addBrandModalOpen) {
        console.log('Brand modal already opening, ignoring');
        return;
    }
    window.addBrandModalOpen = true;

    // Equipment type display name
    const typeDisplayName = equipmentType === 'pv_module' ? 'PV Module' :
        equipmentType === 'inverter' ? 'Inverter' :
            equipmentType.charAt(0).toUpperCase() + equipmentType.slice(1);

    let modal = document.getElementById('addBrandModal');
    if (modal) {
        // Update existing modal
        modal.querySelector('.modal-title').textContent = `Add New ${typeDisplayName} Brand`;
        document.getElementById('equipment_type').value = equipmentType;
        document.getElementById('brand_name').value = '';
        document.getElementById('brand_name').classList.remove('is-invalid');
    } else {
        // Create modal HTML
        let modalHtml = `
            <div class="modal fade" id="addBrandModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New ${typeDisplayName} Brand</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addBrandForm">
                                <div class="mb-3">
                                    <label for="brand_name" class="form-label">Brand Name</label>
                                    <input type="text" class="form-control" id="brand_name" required>
                                    <div class="invalid-feedback">Please provide a brand name</div>
                                </div>
                                <input type="hidden" id="equipment_type" value="${equipmentType}">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="saveBrandBtn">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to document
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer);

        modal = document.getElementById('addBrandModal');
    }

    // Initialize the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Focus on brand name input
    document.getElementById('brand_name').focus();

    // Add event listener to save button
    document.getElementById('saveBrandBtn').addEventListener('click', function () {
        addNewBrand(bsModal);
    });

    // Allow form submission on Enter key
    document.getElementById('addBrandForm').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addNewBrand(bsModal);
        }
    });

    // Remove modal from DOM apenas após Bootstrap terminar
    modal.addEventListener('hidden.bs.modal', function () {
        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.remove();
            }
            window.addBrandModalOpen = false;
        }, 10);
    });
}

/**
 * Add a new brand to the database
 * 
 * @param {bootstrap.Modal} modal - The bootstrap modal instance
 */
function addNewBrand(modal) {
    const brandNameInput = document.getElementById('brand_name');
    const brandName = brandNameInput.value.trim();
    const equipmentType = document.getElementById('equipment_type').value;

    // Validate input
    if (brandName === '') {
        brandNameInput.classList.add('is-invalid');
        return;
    } else {
        brandNameInput.classList.remove('is-invalid');
    }

    // Create form data
    const formData = new FormData();
    formData.append('type', equipmentType);
    formData.append('brand_name', brandName);

    // Show loading state
    const saveBtn = document.getElementById('saveBrandBtn');
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

    // Send request to add brand
    fetch((window.BASE_URL || '') + 'ajax/add_equipment_brand.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }

            // Close modal
            console.log('[DEBUG] modal.hide() chamado após sucesso');
            modal.hide();

            // Show success notification
            showNotification('Brand added successfully', 'success');

            // Add new brand to dropdown and select it
            const dropdownMapping = {
                'pv_module': 'new_module_brand',
                'inverter': 'new_inverter_brand',
                'circuit_breaker': 'new_circuit_breaker_brand',
                'differential': 'new_differential_brand',
                'meter': 'meter_brand',
                'energy_meter': 'energy_meter_brand'
            };

            const selectElementId = dropdownMapping[equipmentType];
            let selectElement = selectElementId ? document.getElementById(selectElementId) : null;

            // If specific dropdown not found, try to find any dropdown for this equipment type
            if (!selectElement) {
                // Try alternative IDs that might exist
                const alternativeIds = [
                    `${equipmentType}_brand`,
                    `new_${equipmentType}_brand`,
                    `protection_${equipmentType}_brand`,
                    `protection_circuit_brand` // Support for Protection tab circuit breaker
                ];
                for (const altId of alternativeIds) {
                    selectElement = document.getElementById(altId);
                    if (selectElement) break;
                }
            }

            if (selectElement) {
                // Option 1: Add the new option directly (faster)
                // Check if the option already exists to avoid duplicates
                const existingOption = Array.from(selectElement.options).find(o => String(o.value) === String(data.brand.id) || o.textContent === data.brand.brand_name);
                if (existingOption) {
                    selectElement.value = existingOption.value;
                } else {
                    const option = document.createElement('option');
                    option.value = data.brand.id;
                    option.textContent = data.brand.brand_name;
                    selectElement.appendChild(option);
                    selectElement.value = data.brand.id;
                }

                // Trigger change event to load models (will be empty for new brand)
                const changeEvent = new Event('change');
                selectElement.dispatchEvent(changeEvent);

                console.log(`Added new ${equipmentType} brand "${data.brand.brand_name}" to dropdown`);
                // Defensive dedupe in case other script added the same option
                try { if (typeof dedupeSelectOptions === 'function') dedupeSelectOptions(selectElement.id); } catch (e) { /* ignore */ }
                // Notify other tabs that a brand was added
                try {
                    const payload = JSON.stringify({ type: equipmentType, brand_id: data.brand.id, brand_name: data.brand.brand_name, t: Date.now() });
                    localStorage.setItem('cw_brands_updated', payload);
                    try {
                        window.dispatchEvent(new StorageEvent('storage', { key: 'cw_brands_updated', newValue: payload }));
                    } catch (e) { /* ignore */ }
                    try {
                        if (window.__cwModelsChannel && typeof window.__cwModelsChannel.postMessage === 'function') {
                            window.__cwModelsChannel.postMessage(JSON.parse(payload));
                        } else if (typeof BroadcastChannel !== 'undefined') {
                            window.__cwModelsChannel = new BroadcastChannel('cw_models');
                            window.__cwModelsChannel.postMessage(JSON.parse(payload));
                        }
                    } catch (e) { /* ignore */ }
                } catch (e) { console.warn('cw: failed to write brand_updated', e); }
            } else {
                // Option 2: Reload all brands for this type (more reliable)
                console.log(`Dropdown for ${equipmentType} not found, will reload on next interaction`);
                // The brands will be reloaded when the user next interacts with the dropdown
            }
        })
        .catch(error => {
            // Always close modal on error
            console.log('[DEBUG] modal.hide() chamado após erro');
            modal.hide();

            // Restore button state
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;

            // Show error message
            showNotification('Error: ' + error.message, 'danger');
        });
}

/**
 * Show modal for adding a new model
 * 
 * @param {string} equipmentType - Type of equipment (pv_module, inverter, etc.)
 */
function showAddModelModal(equipmentType) {
    // Prevent multiple modals
    if (window.addModelModalOpen) {
        console.log('Model modal already opening, ignoring');
        return;
    }
    window.addModelModalOpen = true;

    // Get the selected brand
    let brandSelect;
    const dropdownMapping = {
        'pv_module': 'new_module_brand',
        'inverter': 'new_inverter_brand',
        'circuit_breaker': 'new_circuit_breaker_brand',
        'differential': 'new_differential_brand',
        'meter': 'meter_brand',
        'energy_meter': 'energy_meter_brand'
    };

    let selectElementId = dropdownMapping[equipmentType];
    brandSelect = selectElementId ? document.getElementById(selectElementId) : null;

    // If specific dropdown not found or is empty, try to find any dropdown for this equipment type that has a value
    if (!brandSelect || !brandSelect.value) {
        // More comprehensive list of possible IDs based on the various tabs
        const alternativeIds = [
            `new_${equipmentType}_brand`,
            `${equipmentType}_brand`,
            `protection_${equipmentType}_brand`,
            `protection_circuit_brand`, // Support for Protection tab circuit breaker
            `new_module_brand`, // Special case for pv_module
            `meter_brand`, // Special case for meter
            `energy_meter_brand` // Special case for energy_meter
        ];

        for (const altId of alternativeIds) {
            const altSelect = document.getElementById(altId);
            if (altSelect && altSelect.value) {
                // Verify this altSelect actually belongs to the correct equipment type if possible
                brandSelect = altSelect;
                console.log(`[showAddModelModal] Found alternative brand selection in #${altId} for ${equipmentType}`);
                break;
            }
        }
    }

    // Check one last time if we found something
    if (!brandSelect || !brandSelect.value) {
        console.warn(`[showAddModelModal] No brand selection found for ${equipmentType}`);
        showNotification('Please select a brand first', 'warning');
        window.addModelModalOpen = false;
        return;
    }

    const brandId = brandSelect.value;
    const brandName = brandSelect.selectedIndex >= 0 ?
        brandSelect.options[brandSelect.selectedIndex].text :
        'Selected Brand';

    // Equipment type display name
    const typeDisplayName = equipmentType === 'pv_module' ? 'PV Module' :
        equipmentType === 'inverter' ? 'Inverter' :
            equipmentType.charAt(0).toUpperCase() + equipmentType.slice(1);

    // Check if modal already exists and update it instead of creating new one
    let modal = document.getElementById('addModelModal');
    if (modal) {
        // Clear existing modal and let it be recreated below to avoid field mismatch
        modal.remove();
        modal = null;
    }

    // Create different forms based on equipment type
    let formFields = '';

    if (equipmentType === 'pv_module') {
        formFields = `
                <div class="mb-3">
                    <label for="model_name" class="form-label">Model Name</label>
                    <input type="text" class="form-control" id="model_name" required>
                    <div class="invalid-feedback">Please provide a model name</div>
                </div>
                <div class="mb-3">
                    <label for="power_options" class="form-label">Power Options (W)</label>
                    <input type="text" class="form-control" id="power_options" 
                           placeholder="e.g., 540,545,550,555,560,565" required>
                    <div class="form-text">Enter power ratings separated by commas</div>
                    <div class="invalid-feedback">Please provide at least one power option</div>
                </div>
                <div class="mb-3">
                    <label for="characteristics" class="form-label">Characteristics</label>
                    <textarea class="form-control" id="characteristics" rows="3" 
                        placeholder="e.g., Mono-PERC, Half-cell, etc."></textarea>
                </div>
            `;
    } else if (equipmentType === 'inverter') {
        formFields = `
                <div class="mb-3">
                    <label for="model_name" class="form-label">Model Name</label>
                    <input type="text" class="form-control" id="model_name" required>
                    <div class="invalid-feedback">Please provide a model name</div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="nominal_power" class="form-label">Nominal Power (kW)</label>
                            <input type="number" step="0.01" class="form-control" id="nominal_power" required>
                            <div class="invalid-feedback">Please provide the nominal power</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="max_output_current" class="form-label">Max Output Current (A)</label>
                            <input type="number" step="0.01" class="form-control" id="max_output_current">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="mppts" class="form-label">Number of MPPTs</label>
                            <input type="number" class="form-control" id="mppts" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="strings_per_mppt" class="form-label">Strings per MPPT</label>
                            <input type="number" class="form-control" id="strings_per_mppt" min="1" value="1" required>
                        </div>
                    </div>
                </div>
            `;
    } else if (equipmentType === 'circuit_breaker') {
        formFields = `
                <div class="mb-3">
                    <label for="model_name" class="form-label">Model Name</label>
                    <input type="text" class="form-control" id="model_name" required>
                    <div class="invalid-feedback">Please provide a model name</div>
                </div>
                <div class="mb-3">
                    <label for="characteristics" class="form-label">Characteristics</label>
                    <textarea class="form-control" id="characteristics" rows="3"
                        placeholder="e.g., 16A, 2P, C-curve, etc."></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="rated_current" class="form-label">Rated Current (A)</label>
                            <input type="number" step="0.1" class="form-control" id="rated_current">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="poles" class="form-label">Number of Poles</label>
                            <input type="number" class="form-control" id="poles" min="1" max="4" value="1">
                        </div>
                    </div>
                </div>
            `;
    } else if (equipmentType === 'differential') {
        formFields = `
                <div class="mb-3">
                    <label for="model_name" class="form-label">Model Name</label>
                    <input type="text" class="form-control" id="model_name" required>
                    <div class="invalid-feedback">Please provide a model name</div>
                </div>
                <div class="mb-3">
                    <label for="characteristics" class="form-label">Characteristics</label>
                    <textarea class="form-control" id="characteristics" rows="3"
                        placeholder="e.g., 30mA, Type A, 2P, etc."></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="rated_current" class="form-label">Rated Current (A)</label>
                            <input type="number" step="0.1" class="form-control" id="rated_current">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="sensitivity" class="form-label">Sensitivity (mA)</label>
                            <input type="number" class="form-control" id="sensitivity">
                        </div>
                    </div>
                </div>
            `;
    } else if (equipmentType === 'meter') {
        formFields = `
                <div class="mb-3">
                    <label for="model_name" class="form-label">Model Name</label>
                    <input type="text" class="form-control" id="model_name" required>
                    <div class="invalid-feedback">Please provide a model name</div>
                </div>
                <div class="mb-3">
                    <label for="characteristics" class="form-label">Characteristics</label>
                    <textarea class="form-control" id="characteristics" rows="3"
                        placeholder="e.g., Smart meter, 3-phase, communication protocol, etc."></textarea>
                </div>
            `;
    } else if (equipmentType === 'energy_meter') {
        formFields = `
                <div class="mb-3">
                    <label for="model_name" class="form-label">Model Name</label>
                    <input type="text" class="form-control" id="model_name" required>
                    <div class="invalid-feedback">Please provide a model name</div>
                </div>
            `;
    }

    // Create modal HTML
    let modalHtml = `
            <div class="modal fade" id="addModelModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New ${typeDisplayName} Model</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Adding new model for brand: <strong>${brandName}</strong>
                            </div>
                            <form id="addModelForm">
                                ${formFields}
                                <input type="hidden" id="equipment_type" value="${equipmentType}">
                                <input type="hidden" id="brand_id" value="${brandId}">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="saveModelBtn">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    // Add modal to document
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);

    modal = document.getElementById('addModelModal');

    // Initialize the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    // Focus on model name input (if it exists)
    const modelNameInput = document.getElementById('model_name');
    if (modelNameInput) {
        modelNameInput.focus();
    }

    // Add event listener to save button
    document.getElementById('saveModelBtn').addEventListener('click', function () {
        addNewModel(bsModal, equipmentType);
    });

    // Allow form submission on Enter key
    document.getElementById('addModelForm').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addNewModel(bsModal, equipmentType);
        }
    });

    // Remove modal from DOM apenas após Bootstrap terminar
    modal.addEventListener('hidden.bs.modal', function () {
        setTimeout(() => {
            if (modal.parentNode) {
                modal.parentNode.remove();
            }
            window.addModelModalOpen = false;
        }, 10);
    });
}

/**
 * Add a new model to the database
 * 
 * @param {bootstrap.Modal} modal - The bootstrap modal instance
 * @param {string} equipmentType - Type of equipment (pv_module, inverter)
 */
function addNewModel(modal, equipmentType) {
    if (!window.BASE_URL) console.warn('BASE_URL not defined in addNewModel');
    const modelNameInput = document.getElementById('model_name');
    const modelName = modelNameInput.value.trim();
    const brandId = document.getElementById('brand_id').value;

    // Validate input
    if (modelName === '') {
        modelNameInput.classList.add('is-invalid');
        return;
    } else {
        modelNameInput.classList.remove('is-invalid');
    }

    // Create form data
    const formData = new FormData();
    formData.append('type', equipmentType);
    formData.append('brand_id', brandId);
    formData.append('model_name', modelName);

    // Add type-specific fields
    if (equipmentType === 'pv_module') {
        const characteristics = document.getElementById('characteristics').value.trim();
        const powerOptions = document.getElementById('power_options').value.trim();

        // Validate power options
        if (!powerOptions) {
            document.getElementById('power_options').classList.add('is-invalid');
            return;
        } else {
            document.getElementById('power_options').classList.remove('is-invalid');
        }

        formData.append('characteristics', characteristics);
        formData.append('power_options', powerOptions);
    } else if (equipmentType === 'inverter') {
        const nominalPowerInput = document.getElementById('nominal_power');
        const nominalPower = nominalPowerInput.value;

        if (!nominalPower || nominalPower <= 0) {
            nominalPowerInput.classList.add('is-invalid');
            return;
        } else {
            nominalPowerInput.classList.remove('is-invalid');
        }

        formData.append('nominal_power', nominalPower);
        formData.append('max_output_current', document.getElementById('max_output_current').value);
        formData.append('mppts', document.getElementById('mppts').value);
        formData.append('strings_per_mppt', document.getElementById('strings_per_mppt').value);
    } else if (equipmentType === 'circuit_breaker') {
        const characteristics = document.getElementById('characteristics').value.trim();
        const ratedCurrent = document.getElementById('rated_current').value;
        const poles = document.getElementById('poles').value;

        formData.append('characteristics', characteristics);
        formData.append('rated_current', ratedCurrent);
        formData.append('poles', poles);
    } else if (equipmentType === 'differential') {
        const characteristics = document.getElementById('characteristics').value.trim();
        const ratedCurrent = document.getElementById('rated_current').value;
        const sensitivity = document.getElementById('sensitivity').value;

        formData.append('characteristics', characteristics);
        formData.append('rated_current', ratedCurrent);
        formData.append('sensitivity', sensitivity);
    } else if (equipmentType === 'meter') {
        const characteristics = document.getElementById('characteristics').value.trim();
        formData.append('characteristics', characteristics);
    } else if (equipmentType === 'energy_meter') {
        // For energy_meter, only model_name is required, no extra fields
    }

    // Show loading state
    const saveBtn = document.getElementById('saveModelBtn');
    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

    // Send request to add model
    fetch((window.BASE_URL || '') + 'ajax/add_equipment_model.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }

            // Close modal and show success notification
            modal.hide();
            showNotification('Model added successfully', 'success');

            // Determine which select should be refreshed/updated
            const modelDropdownMapping = {
                'pv_module': 'new_module_model',
                'inverter': 'new_inverter_model',
                'circuit_breaker': 'new_circuit_breaker_model',
                'differential': 'new_differential_model',
                'meter': 'meter_model',
                'energy_meter': 'energy_meter_model'
            };

            // Find all potential selects for this equipment type
            const alternativeModelIds = [
                modelDropdownMapping[equipmentType],
                `new_${equipmentType}_model`,
                `${equipmentType}_model`,
                `protection_${equipmentType}_model`,
                `protection_circuit_model`
            ];

            let selectorList = [];
            alternativeModelIds.forEach(id => {
                if (!id) return;
                const matches = document.querySelectorAll(`select[id^="${id}"]`);
                matches.forEach(m => {
                    if (!selectorList.includes(m)) selectorList.push(m);
                });
            });

            // Refresh the model dropdown from server to ensure consistency across UI
            // and then select the newly added model
            if (selectorList.length > 0) {
                selectorList.forEach(sel => {
                    // Deduce the corresponding brand select id by replacing 'model' with 'brand'
                    const brandSelectId = sel.id.replace('model', 'brand');
                    const brandSelect = document.getElementById(brandSelectId);
                    const selBrandId = brandSelect ? brandSelect.value : brandId; // fallback to modal brand

                    // If the select corresponds to the brand used in modal, reload the options
                    if (String(selBrandId) === String(brandId)) {
                        if (typeof window.loadEquipmentModels === 'function') {
                            // Some implementations of loadEquipmentModels return a Promise (preferred),
                            // while other legacy scripts populate selects directly and return nothing.
                            // Handle both cases: if a Promise is returned, wait for it; otherwise fallback
                            // to a short timeout and then append/select the new model.
                            let loaderResult = null;
                            try {
                                loaderResult = window.loadEquipmentModels(equipmentType, brandId, sel.id);
                            } catch (e) {
                                console.warn('[addNewModel] loadEquipmentModels threw:', e && e.message);
                                loaderResult = null;
                            }

                            if (loaderResult && typeof loaderResult.then === 'function') {
                                loaderResult.then(models => {
                                    // If new model present, select it
                                    if (models && Array.isArray(models)) {
                                        const found = models.find(m => String(m.id) === String(data.model.id));
                                        if (found) {
                                            sel.value = data.model.id;
                                            sel.dispatchEvent(new Event('change'));
                                            return;
                                        }
                                    }

                                    // If not found in returned models, append fallback option
                                    const option = document.createElement('option');
                                    option.value = data.model.id;
                                    option.textContent = data.model.model_name;
                                    sel.appendChild(option);
                                    sel.value = data.model.id;
                                    sel.dispatchEvent(new Event('change'));
                                }).catch(err => {
                                    console.warn('[addNewModel] Failed to reload models for select ' + sel.id + ':', err && err.message);
                                    const option = document.createElement('option');
                                    option.value = data.model.id;
                                    option.textContent = data.model.model_name;
                                    sel.appendChild(option);
                                    sel.value = data.model.id;
                                    sel.dispatchEvent(new Event('change'));
                                });
                            } else {
                                // Legacy loader didn't return a Promise. Wait briefly to let it populate,
                                // then try to select the new option; if not present, append it.
                                setTimeout(() => {
                                    const existing = Array.from(sel.options).find(o => String(o.value) === String(data.model.id));
                                    if (existing) {
                                        sel.value = data.model.id;
                                        sel.dispatchEvent(new Event('change'));
                                    } else {
                                        const option = document.createElement('option');
                                        option.value = data.model.id;
                                        option.textContent = data.model.model_name;
                                        sel.appendChild(option);
                                        sel.value = data.model.id;
                                        sel.dispatchEvent(new Event('change'));
                                    }
                                }, 300);
                            }
                        } else {
                            // No loader available - append option as fallback
                            const option = document.createElement('option');
                            option.value = data.model.id;
                            option.textContent = data.model.model_name;
                            sel.appendChild(option);
                            sel.value = data.model.id;
                            sel.dispatchEvent(new Event('change'));
                        }
                    }
                });
            }
        })
        .catch(error => {
            // Always close modal on error
            modal.hide();

            // Restore button state
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;

            // Show error message
            showNotification('Error: ' + error.message, 'danger');
        });
}

/**
 * Show modal for adding datasheet URL
 * 
 * @param {string} type - Type of equipment (module, inverter)
 * @param {number} index - Index of the equipment in the respective array
 */
function showDatasheetModal(type, index, onSelectCallback = null) {
    // Handle the case where we're adding manually without selecting an equipment
    let equipment = null;
    let equipmentType = type === 'module' ? 'PV Module' : 'Inverter';
    let equipmentDetails = '';

    if (index !== null && index !== undefined) {
        // Get equipment data from list
        equipment = type === 'module' ? modulesList[index] : invertersList[index];
        if (!equipment) return;
        equipmentDetails = `${equipment.brand_name} ${equipment.model_name}`;
    }

    // Create modal HTML
    let modalHtml = `
        <div class="modal fade" id="addDatasheetModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Datasheet URL</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        ${equipmentDetails ? `
                        <div class="alert alert-info mb-3">
                            <strong>${equipmentType}:</strong> ${equipmentDetails}
                        </div>` : ''}
                        <form id="datasheetForm">
                            <div class="mb-3">
                                <label for="datasheet_url" class="form-label">Datasheet URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    <input type="url" class="form-control" id="datasheet_url" 
                                        value="${equipment?.datasheet_url || ''}" 
                                        placeholder="https://manufacturer.com/datasheet.pdf" required>
                                </div>
                                <div class="form-text text-muted">
                                    Enter the full URL to the datasheet PDF or product page
                                </div>
                            </div>
                            <input type="hidden" id="equipment_type" value="${type}">
                            ${index !== null ? `<input type="hidden" id="equipment_index" value="${index}">` : ''}
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveDatasheetBtn">Save</button>
                        ${index !== null ? `
                        <button type="button" class="btn btn-info" id="searchDatasheetsBtn">
                            <i class="fas fa-search me-1"></i> Search Online
                        </button>` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to document
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);

    // Initialize the modal
    const modal = new bootstrap.Modal(document.getElementById('addDatasheetModal'));
    modal.show();

    // Focus on URL input
    document.getElementById('datasheet_url').focus();

    // Add event listener to save button
    document.getElementById('saveDatasheetBtn').addEventListener('click', function () {
        if (onSelectCallback) {
            // Use the callback to return the URL
            const url = document.getElementById('datasheet_url').value.trim();
            modal.hide();
            onSelectCallback(url);

            // Remove modal from DOM after hiding
            setTimeout(() => {
                document.getElementById('addDatasheetModal').remove();
            }, 500);
        } else {
            saveDatasheetUrl(modal);
        }
    });

    // Add event listener to search button if present
    const searchButton = document.getElementById('searchDatasheetsBtn');
    if (searchButton && equipment) {
        searchButton.addEventListener('click', function () {
            // Close the current modal
            modal.hide();

            // Remove modal from DOM after hiding
            setTimeout(() => {
                document.getElementById('addDatasheetModal').remove();
            }, 500);

            // Direct search like inverter cards - open Google search in new tab
            const searchTerm = `${equipment.brand_name} ${equipment.model_name} datasheet pdf`;
            window.open(`https://www.google.com/search?q=${encodeURIComponent(searchTerm)}`, '_blank');
        });
    }

    // Allow form submission on Enter key
    document.getElementById('datasheetForm').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveDatasheetUrl(modal);
        }
    });

    // Remove modal from DOM after it's hidden
    document.getElementById('addDatasheetModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

/**
 * Save datasheet URL to equipment data
 * 
 * @param {bootstrap.Modal} modal - The bootstrap modal instance
 */
function saveDatasheetUrl(modal) {
    const urlInput = document.getElementById('datasheet_url');
    const url = urlInput.value.trim();
    const type = document.getElementById('equipment_type').value;
    const indexElement = document.getElementById('equipment_index');

    // If no index element, this is being used by the search callback
    if (!indexElement) {
        modal.hide();
        return;
    }

    const index = parseInt(indexElement.value);

    // Simple URL validation
    if (url && !url.match(/^https?:\/\/.+/i)) {
        urlInput.classList.add('is-invalid');
        urlInput.setCustomValidity('Please enter a valid URL starting with http:// or https://');
        urlInput.reportValidity();
        return;
    } else {
        urlInput.classList.remove('is-invalid');
        urlInput.setCustomValidity('');
    }

    // Update equipment data with datasheet URL
    if (type === 'module' && modulesList[index]) {
        modulesList[index].datasheet_url = url;
        updateModulesTable();

        // Update hidden field with the data
        document.getElementById('modules_data').value = JSON.stringify(modulesList);
    } else if (type === 'inverter' && invertersList[index]) {
        invertersList[index].datasheet_url = url;
        updateInvertersTable();

        // Update hidden field with the data
        document.getElementById('inverters_data').value = JSON.stringify(invertersList);
    }

    // Close modal
    modal.hide();

    // Show success notification
    showNotification('Datasheet URL saved successfully', 'success');
}

/**
 * Show a notification message
 * 
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, danger, warning, info)
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-alert`;
    notification.innerHTML = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 15px 25px;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: fadeIn 0.3s ease;
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

/**
 * Show a toast message (compatibility function for showNotification)
 * 
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    // Map toast types to notification types
    const notificationType = type === 'error' ? 'danger' : type;
    showNotification(message, notificationType);
}

/**
 * Load equipment brands from database via AJAX
 * 
 * @param {string} type - Equipment type (e.g., 'inverter', 'pv_module')
 * @param {string} targetElement - ID of select element to populate
 */
function loadEquipmentBrands(type, targetElement) {
    const target = document.getElementById(targetElement);
    if (!target) return;

    // Clear current options
    target.innerHTML = '<option value="">Select brand...</option>';

    // Show loading indicator
    target.disabled = true;

    // Get base URL for AJAX requests - detect from current path
    let baseUrl = window.BASE_URL || '/';

    // Make AJAX request
    const url = `${baseUrl}ajax/get_equipment_brands.php?type=${type}`;

    console.debug(`[loadEquipmentBrands] Fetching brands for type=${type} from: ${url}`, { targetElement });

    return fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(response => {
            // Handle both old and new response formats
            const data = response.data || response;

            // Add new options
            if (data && Array.isArray(data)) {
                data.forEach(brand => {
                    const option = document.createElement('option');
                    option.value = brand.id;
                    option.textContent = brand.brand_name;
                    target.appendChild(option);
                });
            }

            // Re-enable the dropdown
            target.disabled = false;
            console.debug(`[loadEquipmentBrands] Populated #${targetElement} with ${target.options.length - 1} options for type=${type}`);
        })
        .catch(error => {
            console.warn(`[loadEquipmentBrands] Error loading brands for ${type}:`, error.message);
            target.disabled = false;
            // Keep the "Select brand..." option instead of showing error
            // The dropdown might be populated by another script (e.g., inject_module_dropdowns.php)
        });
}/**
 * Add module to the table
 */
function addModuleToTable() {
    const brandSelect = document.getElementById('new_module_brand');
    const modelSelect = document.getElementById('new_module_model');
    const powerSelect = document.getElementById('new_module_power');
    const quantityInput = document.getElementById('new_module_quantity');
    const statusSelect = document.getElementById('new_module_status');

    // Get the values
    const brandId = brandSelect.value;
    const modelId = modelSelect.value;
    const powerRating = powerSelect ? powerSelect.value : '';
    const brandText = brandSelect.options[brandSelect.selectedIndex]?.text || '';
    const modelText = modelSelect.options[modelSelect.selectedIndex]?.text || '';
    const quantity = quantityInput.value;
    const status = statusSelect.value;
    const statusText = statusSelect.options[statusSelect.selectedIndex]?.text || '';

    // Validate required fields
    if (!brandId || !modelId || !quantity || !powerRating) {
        customAlert('Please select brand, model, power and quantity', 'warning');
        return;
    }

    // Create module object
    const moduleData = {
        brand_id: brandId,
        brand_name: brandText,
        model_id: modelId,
        model_name: modelText,
        quantity: quantity,
        status: status,
        status_text: statusText,
        datasheet_url: '',  // Add empty datasheet_url property
        power_rating: parseInt(powerRating),
        power_options: window.currentPowerOptions || powerRating
    };

    // Check if we have a report ID to save to database
    const reportId = getReportId();

    if (reportId) {
        // PROGRESSIVE SAVING: Save to database immediately
        const dbData = {
            pv_module_brand_id: brandId,
            pv_module_model_id: modelId,
            quantity: quantity,
            deployment_status: status,
            power_rating: moduleData.power_rating
        };

        // If editing, add the module_id
        if (editingModuleIndex >= 0 && modulesList[editingModuleIndex].id) {
            dbData.module_id = modulesList[editingModuleIndex].id;
        }

        saveModuleToDb(dbData)
            .then(result => {
                // Add module_id to the local object for future updates
                if (!moduleData.id) {
                    moduleData.id = result.module_id;
                }

                // Add or update module in the list
                if (editingModuleIndex >= 0) {
                    modulesList[editingModuleIndex] = moduleData;
                    showNotification('Module updated in database', 'success');
                    editingModuleIndex = -1;
                } else {
                    modulesList.push(moduleData);
                    showNotification('Module saved to database', 'success');
                }

                // Reset form
                brandSelect.value = '';
                modelSelect.innerHTML = '<option value="">Select Model...</option>';
                if (powerSelect) powerSelect.innerHTML = '<option value="">Select Power...</option>';
                quantityInput.value = '1';
                statusSelect.value = 'new';

                // Reset button text and style
                const addModuleBtn = document.getElementById('add-module-btn');
                if (addModuleBtn) {
                    addModuleBtn.innerHTML = '<i class="fas fa-plus"></i> Add Module';
                    addModuleBtn.classList.remove('btn-warning');
                    addModuleBtn.classList.add('btn-success');
                }

                // Update the table
                updateModulesTable();

                // Update hidden field with the data
                document.getElementById('modules_data').value = JSON.stringify(modulesList);
            })
            .catch(error => {
                console.error('Error saving module to database:', error);
                customAlert('Error saving module: ' + error.message, 'error');
            });
    } else {
        // NO REPORT ID: Fall back to local array (for new reports before save)
        console.warn('No report ID - saving locally only');

        // Add or update module in the list
        if (editingModuleIndex >= 0) {
            modulesList[editingModuleIndex] = moduleData;
            showNotification('Module updated (local)', 'info');
            editingModuleIndex = -1;
        } else {
            modulesList.push(moduleData);
            showNotification('Module added (will save after creating report)', 'info');
        }

        // Reset form
        brandSelect.value = '';
        modelSelect.innerHTML = '<option value="">Select Model...</option>';
        if (powerSelect) powerSelect.innerHTML = '<option value="">Select Power...</option>';
        quantityInput.value = '1';
        statusSelect.value = 'new';

        // Reset button
        const addModuleBtn = document.getElementById('add-module-btn');
        if (addModuleBtn) {
            addModuleBtn.innerHTML = '<i class="fas fa-plus"></i> Add Module';
            addModuleBtn.classList.remove('btn-warning');
            addModuleBtn.classList.add('btn-success');
        }

        // Update the table
        updateModulesTable();
        document.getElementById('modules_data').value = JSON.stringify(modulesList);
    }
}

/**
 * Update the modules table with current data
 */
function updateModulesTable() {
    const tableBody = document.getElementById('modules-table-body');
    if (!tableBody) return;

    // Clear existing rows
    tableBody.innerHTML = '';

    if (modulesList.length === 0) {
        // Show no data message
        const noDataRow = document.createElement('tr');
        noDataRow.innerHTML = '<td colspan="7" class="text-center py-3">No modules added yet</td>';
        tableBody.appendChild(noDataRow);
        return;
    }

    // Add a row for each module
    modulesList.forEach((module, index) => {
        const row = document.createElement('tr');

        // Determine datasheet button state
        const hasDatasheet = module.datasheet_url && module.datasheet_url.trim() !== '';
        let datasheetBtn;

        if (hasDatasheet) {
            datasheetBtn = `
                <a href="${module.datasheet_url}" target="_blank" class="btn btn-sm btn-info" title="View Datasheet">
                    <i class="fas fa-file-pdf"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary edit-datasheet" data-index="${index}" data-type="module" title="Edit Datasheet URL">
                    <i class="fas fa-edit"></i>
                </button>`;
        } else {
            datasheetBtn = `
                <button type="button" class="btn btn-sm btn-outline-info add-datasheet" data-index="${index}" data-type="module" title="Add Datasheet URL">
                    <i class="fas fa-link"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary search-datasheet" data-index="${index}" data-type="module" title="Search Datasheet Online">
                    <i class="fas fa-search"></i>
                </button>`;
        }

        // Generate power text (moved outside table as dropdown)
        const powerText = module.power_rating ? `${module.power_rating} W` : '-';

        row.innerHTML = `
            <td>${module.brand_name}</td>
            <td>${module.model_name}</td>
            <td>${powerText}</td>
            <td>${module.quantity}</td>
            <td>${module.status_text}</td>
            <td class="text-center">${datasheetBtn}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-primary edit-module" data-index="${index}" title="Edit Module">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger delete-module" data-index="${index}" title="Delete Module">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(row);
    });

    // Add total row if there are modules
    if (modulesList.length > 0) {
        console.log('[updateModulesTable] Calculating totals for', modulesList.length, 'modules');

        // DEBUG: Log all modules with their details
        modulesList.forEach((module, index) => {
            console.log(`[DEBUG TOTAL] Module ${index}:`, {
                model_name: module.model_name,
                status: module.status,
                power_rating: module.power_rating,
                quantity: module.quantity,
                power_rating_type: typeof module.power_rating,
                quantity_type: typeof module.quantity
            });
        });

        // Calculate total power for NEW modules only
        let totalNewPowerWatts = 0;
        let totalNewModules = 0;

        const newModules = modulesList.filter(module => module.status === 'new');
        console.log('[updateModulesTable] New modules:', newModules);
        newModules.forEach(module => {
            console.log('[updateModulesTable] New module:', module.model_name, 'power:', module.power_rating, 'qty:', module.quantity);
            if (module.power_rating && module.quantity) {
                totalNewPowerWatts += parseFloat(module.power_rating) * parseInt(module.quantity);
                totalNewModules += parseInt(module.quantity);
            }
        });

        const totalNewPowerKWp = totalNewPowerWatts / 1000;
        console.log('[updateModulesTable] Total NEW power:', totalNewPowerKWp, 'kWp');

        // Calculate total power for NEW + EXISTING modules
        let totalAllPowerWatts = 0;
        let totalAllModules = 0;

        const allModules = modulesList.filter(module => module.status === 'new' || module.status === 'existing');
        console.log('[updateModulesTable] All modules (new+existing):', allModules.length, 'filtered from', modulesList.length);

        allModules.forEach((module, index) => {
            console.log(`[DEBUG TOTAL] Processing module ${index}:`, module.model_name, 'status:', module.status, 'power:', module.power_rating, 'qty:', module.quantity);

            const powerRating = parseFloat(module.power_rating);
            const quantity = parseInt(module.quantity);

            console.log(`[DEBUG TOTAL] Parsed values - power: ${powerRating}, qty: ${quantity}, both valid:`, !isNaN(powerRating) && powerRating > 0 && !isNaN(quantity) && quantity > 0);

            if (!isNaN(powerRating) && powerRating > 0 && !isNaN(quantity) && quantity > 0) {
                const moduleWatts = powerRating * quantity;
                totalAllPowerWatts += moduleWatts;
                totalAllModules += quantity;
                console.log(`[DEBUG TOTAL] Added ${moduleWatts}W from ${module.model_name} (${quantity} units)`);
            } else {
                console.log(`[DEBUG TOTAL] Skipped ${module.model_name} - invalid power (${powerRating}) or quantity (${quantity})`);
            }
        });

        const totalAllPowerKWp = totalAllPowerWatts / 1000;
        console.log('[updateModulesTable] Total ALL power:', totalAllPowerKWp, 'kWp from', totalAllPowerWatts, 'W');

        // Create total row for NEW modules
        const totalNewRow = document.createElement('tr');
        totalNewRow.className = 'table-info fw-bold';
        totalNewRow.innerHTML = `
            <td colspan="4" class="text-end">TOTAL INSTALLED POWER (NEW):</td>
            <td class="text-center">
                <span class="badge bg-primary">${totalNewPowerKWp.toFixed(2)} kWp</span>
            </td>
            <td colspan="2" class="text-center">-</td>
        `;
        tableBody.appendChild(totalNewRow);

        // Create total row for NEW + EXISTING modules
        const totalAllRow = document.createElement('tr');
        totalAllRow.className = 'table-success fw-bold';
        totalAllRow.innerHTML = `
            <td colspan="4" class="text-end">TOTAL INSTALLED POWER (NEW+EXISTING):</td>
            <td class="text-center">
                <span class="badge bg-success">${totalAllPowerKWp.toFixed(2)} kWp</span>
            </td>
            <td colspan="2" class="text-center">-</td>
        `;
        tableBody.appendChild(totalAllRow);
    }

    // Add event listeners to buttons
    document.querySelectorAll('.edit-module').forEach(btn => {
        btn.addEventListener('click', function () {
            editModule(parseInt(this.getAttribute('data-index')));
        });
    });

    document.querySelectorAll('.delete-module').forEach(btn => {
        btn.addEventListener('click', function () {
            deleteModule(parseInt(this.getAttribute('data-index')));
        });
    });

    document.querySelectorAll('.add-datasheet[data-type="module"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            showDatasheetModal('module', index);
        });
    });

    document.querySelectorAll('.edit-datasheet[data-type="module"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            showDatasheetModal('module', index);
        });
    });

    document.querySelectorAll('.search-datasheet[data-type="module"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            const module = modulesList[index];

            if (module) {
                // Direct search like inverter cards - open Google search in new tab
                const searchTerm = `${module.brand_name} ${module.model_name} datasheet pdf`;
                window.open(`https://www.google.com/search?q=${encodeURIComponent(searchTerm)}`, '_blank');
            }
        });
    });

    // Update installed power after table refresh
    updateInstalledPower();

    // Update layout validation summary when modules change
    if (typeof updateLayoutValidationSummary === 'function') {
        updateLayoutValidationSummary();
    }

    // Trigger immediate autosave after modules change
    // This ensures modules are saved to database before Ctrl+F5
    if (typeof window.triggerAutosave === 'function') {
        window.triggerAutosave();
    }
}

/**
 * Load a module into the form for editing
 *
 * @param {number} index - Index of the module in the modulesList array
 */
function editModule(index) {
    const module = modulesList[index];
    if (!module) return;

    // Set form values
    const brandSelect = document.getElementById('new_module_brand');
    const quantityInput = document.getElementById('new_module_quantity');
    const statusSelect = document.getElementById('new_module_status');

    brandSelect.value = module.brand_id;

    // Trigger change event on brand dropdown to load models, then set the model
    // Use the global loadEquipmentModels function if available, otherwise trigger change event
    if (typeof window.loadEquipmentModels === 'function') {
        // Chama a função normalmente
        const maybePromise = window.loadEquipmentModels('pv_module', module.brand_id, 'new_module_model');
        if (maybePromise && typeof maybePromise.then === 'function') {
            // Se for Promise, usa .then normalmente
            maybePromise.then(() => {
                const modelSelect = document.getElementById('new_module_model');
                modelSelect.value = module.model_id;
                if (module.model_id) {
                    fetch((window.BASE_URL || "") + `ajax/get_module_power.php?model_id=${module.model_id}`)
                        .then(response => response.json())
                        .then(modelData => {
                            window.currentPowerOptions = modelData.power_options || null;
                            console.log('DEBUG: Power options loaded for editing model:', module.model_id, window.currentPowerOptions);

                            const powerSelect = document.getElementById('new_module_power');
                            if (powerSelect) {
                                powerSelect.innerHTML = '<option value="">Select Power...</option>';
                                if (window.currentPowerOptions) {
                                    const options = window.currentPowerOptions.split(',');
                                    options.forEach(option => {
                                        const val = option.trim();
                                        powerSelect.innerHTML += `<option value="${val}">${val} W</option>`;
                                    });
                                    powerSelect.value = module.power_rating;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching power options for editing:', error);
                            window.currentPowerOptions = null;
                        });
                }
            }).catch(error => {
                console.error('Error loading models for editing:', error);
            });
        } else {
            // Se não for Promise, espera um pouco e seta o valor
            setTimeout(() => {
                const modelSelect = document.getElementById('new_module_model');
                modelSelect.value = module.model_id;
                if (module.model_id) {
                    fetch((window.BASE_URL || "") + `ajax/get_module_power.php?model_id=${module.model_id}`)
                        .then(response => response.json())
                        .then(modelData => {
                            window.currentPowerOptions = modelData.power_options || null;
                            console.log('DEBUG: Power options loaded for editing model:', module.model_id, window.currentPowerOptions);

                            const powerSelect = document.getElementById('new_module_power');
                            if (powerSelect) {
                                powerSelect.innerHTML = '<option value="">Select Power...</option>';
                                if (window.currentPowerOptions) {
                                    const options = window.currentPowerOptions.split(',');
                                    options.forEach(option => {
                                        const val = option.trim();
                                        powerSelect.innerHTML += `<option value="${val}">${val} W</option>`;
                                    });
                                    powerSelect.value = module.power_rating;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching power options for editing:', error);
                            window.currentPowerOptions = null;
                        });
                }
            }, 400);
        }
    } else {
        // Fallback: trigger change event on brand dropdown
        const changeEvent = new Event('change');
        brandSelect.dispatchEvent(changeEvent);
        setTimeout(() => {
            const modelSelect = document.getElementById('new_module_model');
            modelSelect.value = module.model_id;
            if (module.model_id) {
                fetch((window.BASE_URL || "") + `ajax/get_module_power.php?model_id=${module.model_id}`)
                    .then(response => response.json())
                    .then(modelData => {
                        window.currentPowerOptions = modelData.power_options || null;
                        console.log('DEBUG: Power options loaded for editing model:', module.model_id, window.currentPowerOptions);

                        const powerSelect = document.getElementById('new_module_power');
                        if (powerSelect) {
                            powerSelect.innerHTML = '<option value="">Select Power...</option>';
                            if (window.currentPowerOptions) {
                                const options = window.currentPowerOptions.split(',');
                                options.forEach(option => {
                                    const val = option.trim();
                                    powerSelect.innerHTML += `<option value="${val}">${val} W</option>`;
                                });
                                powerSelect.value = module.power_rating;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching power options for editing:', error);
                        window.currentPowerOptions = null;
                    });
            }
        }, 400);
    }

    quantityInput.value = module.quantity;
    statusSelect.value = module.status;

    // Set editing index
    editingModuleIndex = index;

    // Change button text and style
    const addModuleBtn = document.getElementById('add-module-btn');
    if (addModuleBtn) {
        addModuleBtn.innerHTML = '<i class="fas fa-save"></i> Update Module';
        addModuleBtn.classList.remove('btn-success');
        addModuleBtn.classList.add('btn-warning');
    }
}

/**
 * Delete a module from the table
 * 
 * @param {number} index - Index of the module in the modulesList array
 */
function deleteModule(index) {
    customConfirm('Are you sure you want to delete this module?').then(confirmed => {
        if (confirmed) {
            modulesList.splice(index, 1);
            updateModulesTable();

            // Update hidden field with the data
            document.getElementById('modules_data').value = JSON.stringify(modulesList);

            // Recalculate installed power
            updateInstalledPower();
        }
    });
}

/**
 * Validate Associated Equipment fields and enable/disable submit button
 */
function validateAssociatedEquipment() {
    const requiredFields = {
        // Circuit Breaker
        'new_circuit_breaker_brand': 'Circuit Breaker Brand',
        'new_circuit_breaker_model': 'Circuit Breaker Model',
        'new_circuit_breaker_rated_current': 'Circuit Breaker Rated Current',

        // Differential
        'new_differential_brand': 'Differential Brand',
        'new_differential_model': 'Differential Model',
        'new_differential_rated_current': 'Differential Rated Current',

        // Cable
        'new_cable_brand': 'Cable Brand',
        'new_cable_model': 'Cable Model',
        'new_cable_size': 'Cable Size',
        'new_cable_insulation': 'Cable Insulation'
    };

    let allFilled = true;
    let missingFields = [];

    // Check each required field
    for (const [fieldId, fieldName] of Object.entries(requiredFields)) {
        const field = document.getElementById(fieldId);
        if (field) {
            const value = field.value ? field.value.trim() : '';
            if (!value) {
                allFilled = false;
                missingFields.push(fieldName);
            }
        }
    }

    // Get submit button and update its state
    const submitBtn = document.getElementById('submit-inverter-btn');
    if (submitBtn) {
        if (allFilled) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50');
            submitBtn.title = 'Add This Inverter to Project';
            console.log('[Associated Equipment Validation] ✓ All fields filled - Button ENABLED');
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50');
            const fieldsText = missingFields.slice(0, 3).join(', ');
            const moreText = missingFields.length > 3 ? ` and ${missingFields.length - 3} more` : '';
            submitBtn.title = `Please complete Associated Equipment: ${fieldsText}${moreText}`;
            console.log('[Associated Equipment Validation] ❌ Missing fields:', missingFields);
        }
    }

    return allFilled;
}

/**
 * Initialize Associated Equipment field validation
 */
function initAssociatedEquipmentValidation() {
    console.log('[Associated Equipment Validation] Initializing...');

    const fieldsToMonitor = [
        'new_circuit_breaker_brand',
        'new_circuit_breaker_model',
        'new_circuit_breaker_rated_current',
        'new_differential_brand',
        'new_differential_model',
        'new_differential_rated_current',
        'new_cable_brand',
        'new_cable_model',
        'new_cable_size',
        'new_cable_insulation'
    ];

    fieldsToMonitor.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            // Initial validation
            field.addEventListener('change', validateAssociatedEquipment);
            field.addEventListener('input', validateAssociatedEquipment);
            field.addEventListener('blur', validateAssociatedEquipment);
        }
    });

    // Initial validation on page load
    validateAssociatedEquipment();

    console.log('[Associated Equipment Validation] Initialized successfully');
}

/**
 * Initialize the inverters table and related functionality
 */
function initInvertersTable() {
    console.log('[Main] Inicializando tabela de inversores');
    const addInverterBtn = document.getElementById('submit-inverter-btn');
    if (addInverterBtn) {
        console.log('[Main] Botão submit-inverter-btn encontrado, adicionando evento de clique');
        addInverterBtn.addEventListener('click', function (e) {
            console.log('[Main] Botão de adicionar inversor clicado');

            // Validate before submitting
            if (!validateAssociatedEquipment()) {
                console.warn('[Main] Associated Equipment validation failed');
                return;
            }

            addInverterToTable();
        });
    } else {
        console.error('[Main] ERRO: Botão submit-inverter-btn não encontrado!');
    }

    // Initialize clear inverters button
    const clearInvertersBtn = document.getElementById('clear-inverters-btn');
    if (clearInvertersBtn) {
        clearInvertersBtn.addEventListener('click', function () {
            customConfirm('Are you sure you want to clear all inverters? This cannot be undone.').then(confirmed => {
                if (confirmed) {
                    clearAllInverters();
                }
            });
        });
    }

    // Initialize add brand button
    const addInverterBrandBtn = document.getElementById('add-inverter-brand-btn');
    if (addInverterBrandBtn) {
        addInverterBrandBtn.addEventListener('click', () => showAddBrandModal('inverter'));
    }

    // Initialize add model button
    const addInverterModelBtn = document.getElementById('add-inverter-model-btn');
    if (addInverterModelBtn) {
        addInverterModelBtn.addEventListener('click', () => showAddModelModal('inverter'));
    }

    // Initialize circuit breaker buttons
    const addCircuitBreakerBrandBtn = document.getElementById('add-circuit-breaker-brand-btn');
    if (addCircuitBreakerBrandBtn) {
        addCircuitBreakerBrandBtn.addEventListener('click', () => showAddBrandModal('circuit_breaker'));
    }

    const addCircuitBreakerModelBtn = document.getElementById('add-circuit-breaker-model-btn');
    if (addCircuitBreakerModelBtn) {
        addCircuitBreakerModelBtn.addEventListener('click', () => showAddModelModal('circuit_breaker'));
    }

    // Initialize differential buttons
    const addDifferentialBrandBtn = document.getElementById('add-differential-brand-btn');
    if (addDifferentialBrandBtn) {
        addDifferentialBrandBtn.addEventListener('click', () => showAddBrandModal('differential'));
    }

    const addDifferentialModelBtn = document.getElementById('add-differential-model-btn');
    if (addDifferentialModelBtn) {
        addDifferentialModelBtn.addEventListener('click', () => showAddModelModal('differential'));
    }

    // Load brands into the dropdown
    // loadEquipmentBrands('inverter', 'new_inverter_brand'); // Removed to avoid conflict with module_table.js

    // Load circuit breaker brands
    loadEquipmentBrands('circuit_breaker', 'new_circuit_breaker_brand');

    // Load differential brands
    loadEquipmentBrands('differential', 'new_differential_brand');

    // Load cable brands
    loadEquipmentBrands('cable', 'new_cable_brand');

    // Load energy meter brands
    console.debug('[Main] Calling loadEquipmentBrands for energy_meter at initInvertersTable');
    loadEquipmentBrands('energy_meter', 'energy_meter_brand');

    // Setup event listeners for circuit breaker and differential dropdowns
    setupCircuitBreakerDropdowns();
    setupDifferentialDropdowns();
    setupCableDropdowns();

    // Load existing inverters if available
    if (window.existingInverters && Array.isArray(window.existingInverters)) {
        console.log(`[Main] Carregando ${window.existingInverters.length} inversores existentes do banco de dados`);
        console.log('[Main] Raw inverters data:', window.existingInverters);
        // Clear current list
        window.invertersList = [];

        // Mapear os inversores existentes
        window.invertersList = window.existingInverters.map(inverter => {
            console.log('[Main] Processing inverter:', inverter);
            console.log('[Main] Model ID from DB:', inverter.model_id);
            console.log('[Main] Circuit breaker text:', inverter.circuit_breaker_text);
            console.log('[Main] Differential text:', inverter.differential_text);
            console.log('[Main] Cable text:', inverter.cable_text);

            // Extract datasheet URL from characteristics if present
            let datasheetUrl = inverter.datasheet_url || '';

            // Extract max_output_current
            let maxOutputCurrent = inverter.max_output_current || '';

            // Parse Circuit Breaker info
            let cbBrandName = '';
            let cbModelName = '';
            let cbRatedCurrent = '';
            if (inverter.circuit_breaker_text) {
                const cbParts = inverter.circuit_breaker_text.split(' ');
                // Try to extract brand, model, and rated current
                const ratedMatch = inverter.circuit_breaker_text.match(/Rated:\s*(\d+(?:\.\d+)?)\s*A/);
                if (ratedMatch) {
                    cbRatedCurrent = ratedMatch[1];
                    // Everything before "Rated:" is brand and model
                    const beforeRated = inverter.circuit_breaker_text.split('Rated:')[0].trim();
                    const parts = beforeRated.split(' ');
                    if (parts.length >= 2) {
                        cbBrandName = parts[0];
                        cbModelName = parts.slice(1).join(' ');
                    } else if (parts.length === 1) {
                        cbBrandName = parts[0];
                    }
                } else {
                    // No rated current, try to split brand and model
                    if (cbParts.length >= 2) {
                        cbBrandName = cbParts[0];
                        cbModelName = cbParts.slice(1).join(' ');
                    } else if (cbParts.length === 1) {
                        cbBrandName = cbParts[0];
                    }
                }
            }

            // Parse Differential info
            let diffBrandName = '';
            let diffModelName = '';
            let diffRatedCurrent = '';
            let diffMilliamp = '';
            if (inverter.differential_text) {
                const ratedMatch = inverter.differential_text.match(/Rated:\s*(\d+(?:\.\d+)?)\s*A/);
                if (ratedMatch) {
                    diffRatedCurrent = ratedMatch[1];
                    // Everything before "Rated:" is brand and model
                    const beforeRated = inverter.differential_text.split('Rated:')[0].trim();
                    const parts = beforeRated.split(' ');
                    if (parts.length >= 2) {
                        diffBrandName = parts[0];
                        diffModelName = parts.slice(1).join(' ');
                    } else if (parts.length === 1) {
                        diffBrandName = parts[0];
                    }
                } else {
                    // No rated current, try to split brand and model
                    const diffParts = inverter.differential_text.split(' ');
                    if (diffParts.length >= 2) {
                        diffBrandName = diffParts[0];
                        diffModelName = diffParts.slice(1).join(' ');
                    } else if (diffParts.length === 1) {
                        diffBrandName = diffParts[0];
                    }
                }

                // Extract Differential Current (mA) if present, e.g., "30mA" or "30 mA"
                const maMatch = inverter.differential_text.match(/(\d+(?:\.\d+)?)\s*mA/i);
                if (maMatch) {
                    diffMilliamp = maMatch[1];
                }
            }

            // Parse Cable info
            let cableBrandName = '';
            let cableModelName = '';
            let cableSize = '';
            let cableInsulation = '';
            if (inverter.cable_text) {
                // Try to extract size (e.g., "6mm2")
                const sizeMatch = inverter.cable_text.match(/(\d+(?:\.\d+)?)\s*mm2|(\d+(?:\.\d+)?)\s*mm²/);
                if (sizeMatch) {
                    cableSize = sizeMatch[1] || sizeMatch[2];
                }

                // Common insulation types
                const insulationTypes = ['XLPE', 'PVC', 'EPR', 'Rubber'];
                for (const type of insulationTypes) {
                    if (inverter.cable_text.includes(type)) {
                        cableInsulation = type;
                        break;
                    }
                }

                // Extract brand and model (first words before size/insulation)
                let textCopy = inverter.cable_text;
                if (sizeMatch) textCopy = textCopy.replace(sizeMatch[0], '').trim();
                if (cableInsulation) textCopy = textCopy.replace(cableInsulation, '').trim();

                const parts = textCopy.split(' ').filter(p => p.length > 0);
                if (parts.length >= 2) {
                    cableBrandName = parts[0];
                    cableModelName = parts.slice(1).join(' ');
                } else if (parts.length === 1) {
                    cableBrandName = parts[0];
                }
            }

            return {
                id: inverter.id || null,
                brand_id: '', // We don't have the ID in the data from DB
                brand_name: inverter.brand,
                model_id: inverter.model_id || '', // Now we have model_id from the JOIN
                model_name: inverter.model,
                quantity: inverter.quantity,
                status: inverter.status,
                status_text: inverter.status.charAt(0).toUpperCase() + inverter.status.slice(1),
                serial_number: inverter.serial_number || '',
                location: inverter.location || '',
                datasheet_url: datasheetUrl,
                max_output_current: maxOutputCurrent,
                circuit_breaker_brand_id: '',
                circuit_breaker_brand_name: cbBrandName,
                circuit_breaker_model_id: '',
                circuit_breaker_model_name: cbModelName,
                circuit_breaker_rated_current: cbRatedCurrent,
                differential_brand_id: '',
                differential_brand_name: diffBrandName,
                differential_model_id: '',
                differential_model_name: diffModelName,
                differential_rated_current: diffRatedCurrent,
                differential_current: diffMilliamp,
                cable_brand_id: '',
                cable_brand_name: cableBrandName,
                cable_model_name: cableModelName,
                cable_size: cableSize,
                cable_insulation: cableInsulation,
                validation_status: '',
                validation_message: '',
                from_database: true // Marcar como vindo do banco de dados
            };
        });

        console.log('[Main] Mapped inverters list:', window.invertersList);

        // Update the table with existing inverters
        updateInvertersTable();
        // Ensure Telemetry inverter select reflects loaded inverters
        if (typeof window.refreshTelemetryInverterOptions === 'function') {
            window.refreshTelemetryInverterOptions();
        }

        // Update hidden field with the data
        document.getElementById('inverters_data').value = JSON.stringify(invertersList);

        // Dispatch event to load string measurements tables for existing inverters
        console.log('[Main] Disparando evento invertersListUpdated para inversores existentes');
        const event = new CustomEvent('invertersListUpdated', {
            detail: { invertersList: window.invertersList }
        });
        document.dispatchEvent(event);

        // Also directly call the function to ensure tables are loaded in edit mode
        // Wait for string_functions.js to be loaded
        function tryLoadStringTables(attempts = 0) {
            if (typeof window.loadAllInverterStringTables === 'function') {
                console.log('[Main] ✅ Chamando loadAllInverterStringTables() para inversores existentes');
                window.loadAllInverterStringTables();
            } else if (attempts < 20) {
                console.log('[Main] ⏳ Aguardando string_functions.js... tentativa', attempts + 1);
                setTimeout(() => tryLoadStringTables(attempts + 1), 100);
            } else {
                console.error('[Main] ❌ string_functions.js não carregou após 2 segundos');
            }
        }
        setTimeout(tryLoadStringTables, 300);
    }
}

/**
 * Setup circuit breaker dropdown event listeners
 */
function setupCircuitBreakerDropdowns() {
    const circuitBreakerBrandSelect = document.getElementById('new_circuit_breaker_brand');
    if (circuitBreakerBrandSelect) {
        circuitBreakerBrandSelect.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                loadEquipmentModels('circuit_breaker', brandId, 'new_circuit_breaker_model');
            } else {
                // Clear model dropdown if no brand selected
                const modelSelect = document.getElementById('new_circuit_breaker_model');
                if (modelSelect) {
                    modelSelect.innerHTML = '<option value="">Select Model...</option>';
                }
            }
        });
    }
}

/**
 * Setup differential dropdown event listeners
 */
function setupDifferentialDropdowns() {
    const differentialBrandSelect = document.getElementById('new_differential_brand');
    if (differentialBrandSelect) {
        differentialBrandSelect.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                loadEquipmentModels('differential', brandId, 'new_differential_model');
            } else {
                // Clear model dropdown if no brand selected
                const modelSelect = document.getElementById('new_differential_model');
                if (modelSelect) {
                    modelSelect.innerHTML = '<option value="">Select Model...</option>';
                }
            }
        });
    }
}

/**
 * Add inverter to the table
 * Explicitly defined as a global function to ensure accessibility
 */
window.addInverterToTable = function () {
    console.log('✅ Função addInverterToTable executada globalmente');

    // Verificar se os elementos existem antes de acessá-los
    const brandSelect = document.getElementById('new_inverter_brand');
    const modelSelect = document.getElementById('new_inverter_model');
    const statusSelect = document.getElementById('new_inverter_status');
    const serialInput = document.getElementById('new_inverter_serial');
    const locationInput = document.getElementById('new_inverter_location');

    if (!brandSelect || !modelSelect || !statusSelect) {
        console.error('❌ Elementos obrigatórios do formulário não encontrados');
        customAlert('Form elements not found. Please refresh the page.');
        return;
    }

    // New circuit breaker fields (only if they exist)
    const circuitBreakerBrandSelect = document.getElementById('new_circuit_breaker_brand');
    const circuitBreakerModelSelect = document.getElementById('new_circuit_breaker_model');
    const circuitBreakerRatedCurrentInput = document.getElementById('new_circuit_breaker_rated_current');

    // New differential fields (only if they exist)
    const differentialBrandSelect = document.getElementById('new_differential_brand');
    const differentialModelSelect = document.getElementById('new_differential_model');
    const differentialRatedCurrentInput = document.getElementById('new_differential_rated_current');
    const differentialCurrentInput = document.getElementById('new_differential_current');

    // New cable fields (only if they exist)
    const cableBrandSelect = document.getElementById('new_cable_brand');
    const cableModelSelect = document.getElementById('new_cable_model');
    const cableSizeInput = document.getElementById('new_cable_size');
    const cableInsulationInput = document.getElementById('new_cable_insulation');

    // Get the values
    const brandId = brandSelect.value;
    const modelId = modelSelect.value;
    const brandText = brandSelect.options[brandSelect.selectedIndex]?.text || '';
    const modelText = modelSelect.options[modelSelect.selectedIndex]?.text || '';
    const quantity = 1; // Default quantity since field was removed
    const status = statusSelect.value;
    const statusText = statusSelect.options[statusSelect.selectedIndex]?.text || '';
    const serialNumber = serialInput.value;
    const location = locationInput.value;
    const maxOutputCurrentElement = document.getElementById('new_inverter_max_current');
    const maxOutputCurrent = maxOutputCurrentElement ? maxOutputCurrentElement.value : '';

    // Validar campos obrigatórios básicos
    if (!brandId || !modelId || !status) {
        // Mostrar mensagem de erro
        customAlert('Please fill in all required basic fields: Brand, Model and Status.');

        // Abrir a aba "Basic Info" para mostrar os campos que precisam ser preenchidos
        const basicInfoTab = document.getElementById('basic-tab');
        if (basicInfoTab) {
            const tab = new bootstrap.Tab(basicInfoTab);
            tab.show();
        }

        return; // Impedir a adição do inversor
    }

    // Get circuit breaker values (only if elements exist)
    const circuitBreakerBrandId = circuitBreakerBrandSelect ? circuitBreakerBrandSelect.value : '';
    const circuitBreakerModelId = circuitBreakerModelSelect ? circuitBreakerModelSelect.value : '';
    const circuitBreakerBrandText = (circuitBreakerBrandSelect && circuitBreakerBrandSelect.selectedIndex >= 0) ? (circuitBreakerBrandSelect.options[circuitBreakerBrandSelect.selectedIndex]?.text || '') : '';
    const circuitBreakerModelText = (circuitBreakerModelSelect && circuitBreakerModelSelect.selectedIndex >= 0) ? (circuitBreakerModelSelect.options[circuitBreakerModelSelect.selectedIndex]?.text || '') : '';

    // Get differential values (only if elements exist)
    const differentialBrandId = differentialBrandSelect ? differentialBrandSelect.value : '';
    const differentialModelId = differentialModelSelect ? differentialModelSelect.value : '';
    const differentialBrandText = (differentialBrandSelect && differentialBrandSelect.selectedIndex >= 0) ? (differentialBrandSelect.options[differentialBrandSelect.selectedIndex]?.text || '') : '';
    const differentialModelText = (differentialModelSelect && differentialModelSelect.selectedIndex >= 0) ? (differentialModelSelect.options[differentialModelSelect.selectedIndex]?.text || '') : '';

    // Get cable values (only if elements exist)
    const cableBrandId = cableBrandSelect ? cableBrandSelect.value : '';
    const cableModelId = cableModelSelect ? cableModelSelect.value : '';
    const cableBrandText = (cableBrandSelect && cableBrandSelect.selectedIndex >= 0) ? (cableBrandSelect.options[cableBrandSelect.selectedIndex]?.text || '') : '';
    const cableModelText = (cableModelSelect && cableModelSelect.selectedIndex >= 0) ? (cableModelSelect.options[cableModelSelect.selectedIndex]?.text || '') : '';
    const cableSize = cableSizeInput ? cableSizeInput.value : '';

    // DEBUG: Log cable model dropdown state
    console.log('[DEBUG] Cable Model Dropdown:', {
        exists: !!cableModelSelect,
        value: cableModelId,
        text: cableModelText,
        selectedIndex: cableModelSelect ? cableModelSelect.selectedIndex : 'N/A'
    });

    // Validate required fields
    if (!brandId || !modelId) {
        customAlert('Please select brand and model', 'warning');
        return;
    }

    // Validation: Check Max Output Current vs Rated Current
    const maxOutputCurrentValue = parseFloat(maxOutputCurrent);
    const ratedCurrentValue = parseFloat(circuitBreakerRatedCurrentInput ? circuitBreakerRatedCurrentInput.value : '');

    let validationStatus = null;
    let validationMessage = '';

    // Validação obrigatória: Differential Rated Current deve ser preenchido
    if (differentialBrandId && differentialModelId && (isNaN(parseFloat(differentialRatedCurrentInput ? differentialRatedCurrentInput.value : '')) || parseFloat(differentialRatedCurrentInput ? differentialRatedCurrentInput.value : '') <= 0)) {
        customAlert('⚠️ Differential Rated Current is required and must be greater than 0', 'warning');
        return;
    }

    if (!isNaN(maxOutputCurrentValue) && !isNaN(ratedCurrentValue)) {
        const minRequiredCurrent = maxOutputCurrentValue * 1.2; // Disjuntor deve suportar pelo menos 120% da corrente máxima
        if (ratedCurrentValue < minRequiredCurrent) {
            // Insufficient breaker current
            validationStatus = 'warning';
            validationMessage = `⚠️ Safety Alert: The circuit breaker rated current (${ratedCurrentValue} A) is insufficient for the inverter's maximum output current (${maxOutputCurrentValue} A). The circuit breaker must support at least 120% of the maximum current (${minRequiredCurrent.toFixed(1)} A) to ensure adequate protection.`;

            // Show alarm message
            customAlert(validationMessage, 'warning');
        } else {
            // Validation passed
            validationStatus = 'success';
            validationMessage = `✅ Validation Approved: The circuit breaker rated current (${ratedCurrentValue} A) is adequate for the inverter's maximum output current (${maxOutputCurrentValue} A).`;

            // Show success message when validation passes
            customAlert(validationMessage, 'success');
        }
    } else if (!isNaN(maxOutputCurrentValue) && isNaN(ratedCurrentValue)) {
        // No breaker current specified
        validationStatus = 'info';
        validationMessage = `ℹ️ Information: Circuit breaker rated current not specified. Please ensure the breaker can handle at least ${maxOutputCurrentValue * 1.2} A (120% of ${maxOutputCurrentValue} A).`;
    }    // Create inverter object
    const inverterData = {
        brand_id: brandId,
        brand_name: brandText,
        model_id: modelId,
        model_name: modelText,
        quantity: quantity,
        status: status,
        status_text: statusText,
        serial_number: serialNumber,
        location: location,
        max_output_current: maxOutputCurrent,
        circuit_breaker_brand_id: circuitBreakerBrandId,
        circuit_breaker_brand_name: circuitBreakerBrandText,
        circuit_breaker_model_id: circuitBreakerModelId,
        circuit_breaker_model_name: circuitBreakerModelText,
        circuit_breaker_rated_current: circuitBreakerRatedCurrentInput ? circuitBreakerRatedCurrentInput.value : '',
        differential_brand_id: differentialBrandId,
        differential_brand_name: differentialBrandText,
        differential_model_id: differentialModelId,
        differential_model_name: differentialModelText,
        differential_rated_current: differentialRatedCurrentInput ? differentialRatedCurrentInput.value : '',
        differential_current: differentialCurrentInput ? differentialCurrentInput.value : '',
        cable_brand_id: cableBrandId,
        cable_brand_name: cableBrandText,
        cable_model_id: cableModelId,
        cable_model_name: cableModelText,
        cable_size: cableSize,
        cable_insulation: cableInsulationInput ? cableInsulationInput.value : '',
        datasheet_url: '',  // Add empty datasheet_url property
        validation_status: validationStatus,
        validation_message: validationMessage
    };

    if (window.editingInverterIndex >= 0) {
        // Update existing inverter
        // Preserve DB id if present
        const prev = window.invertersList[editingInverterIndex] || {};
        if (prev && (prev.id || prev.inverter_id)) {
            inverterData.id = prev.id || prev.inverter_id;
        }
        window.invertersList[editingInverterIndex] = inverterData;

        // Persist update to DB if this item has an ID and we are editing an existing report
        const urlParams = new URLSearchParams(window.location.search);
        const reportId = urlParams.get('report_id');
        if ((inverterData.id || inverterData.inverter_id) && reportId && typeof saveInverterToDb === 'function') {
            const payload = Object.assign({}, inverterData, { inverter_id: inverterData.id || inverterData.inverter_id, report_id: reportId });
            // Capture index to update in place after server confirms
            const editedIndex = window.editingInverterIndex;
            console.log('[Main] Saving inverter UPDATE to DB payload', payload);
            saveInverterToDb(payload).then(res => {
                console.log('[Main] Inverter updated in database', res);
                if (res && res.success && res.inverter && typeof editedIndex !== 'undefined' && editedIndex >= 0) {
                    // Update local entry with returned DB fields where appropriate
                    const r = res.inverter;
                    const existing = window.invertersList[editedIndex] || {};
                    existing.id = r.id || existing.id || (r && r.id) || existing.id;
                    // Update simple fields from response and form inputs
                    existing.brand_name = inverterData.brand_name || existing.brand_name;
                    existing.model_name = inverterData.model_name || existing.model_name;
                    existing.serial_number = inverterData.serial_number || existing.serial_number;
                    existing.location = inverterData.location || existing.location;
                    existing.max_output_current = inverterData.max_output_current || existing.max_output_current;
                    existing.circuit_breaker_model_name = inverterData.circuit_breaker_model_name || existing.circuit_breaker_model_name;
                    existing.differential_model_name = inverterData.differential_model_name || existing.differential_model_name;
                    existing.cable_model_name = inverterData.cable_model_name || existing.cable_model_name;

                    window.invertersList[editedIndex] = existing;

                    // Re-render UI
                    updateInvertersTable();
                    if (typeof window.updateInvertersCards === 'function') window.updateInvertersCards();

                    // Update hidden field
                    const hiddenInvField = document.getElementById('inverters_data');
                    if (hiddenInvField) hiddenInvField.value = JSON.stringify(window.invertersList);
                }
            }).catch(err => {
                console.error('[Main] Failed to update inverter in database:', err);
            });
        }

        window.editingInverterIndex = -1;
    } else {
        // Add new inverter
        window.invertersList.push(inverterData);

        // Persist to database and update local entry with returned ID (if any)
        const urlParams = new URLSearchParams(window.location.search);
        const reportId = urlParams.get('report_id');
        const newIndex = window.invertersList.length - 1;
        if (reportId && typeof saveInverterToDb === 'function') {
            console.log('[Main] Saving inverter CREATE to DB payload', inverterData);
            saveInverterToDb(inverterData).then(res => {
                console.log('[Main] Inverter saved to database', res);
                if (res && res.success) {
                    // Prefer structured inverter object, fallback to inverter_id
                    const returnedId = (res.inverter && res.inverter.id) ? res.inverter.id : (res.inverter_id || null);
                    if (returnedId) {
                        window.invertersList[newIndex].id = returnedId;
                    }

                    // Update UI and hidden field
                    updateInvertersTable();
                    if (typeof window.updateInvertersCards === 'function') window.updateInvertersCards();
                    const hiddenInvField = document.getElementById('inverters_data');
                    if (hiddenInvField) hiddenInvField.value = JSON.stringify(window.invertersList);
                }
            }).catch(error => {
                console.error('[Main] Failed to save inverter to database:', error);
            });
        }
    }

    // Reset form
    brandSelect.value = '';
    modelSelect.innerHTML = '<option value="">Select Model...</option>';
    statusSelect.value = 'new';
    serialInput.value = '';
    locationInput.value = '';

    // Reset circuit breaker fields (only if they exist)
    if (circuitBreakerBrandSelect) circuitBreakerBrandSelect.value = '';
    if (circuitBreakerModelSelect) circuitBreakerModelSelect.innerHTML = '<option value="">Select Model...</option>';
    if (circuitBreakerRatedCurrentInput) circuitBreakerRatedCurrentInput.value = '';

    // Reset differential fields (only if they exist)
    if (differentialBrandSelect) differentialBrandSelect.value = '';
    if (differentialModelSelect) differentialModelSelect.innerHTML = '<option value="">Select Model...</option>';
    if (differentialRatedCurrentInput) differentialRatedCurrentInput.value = '';
    if (differentialCurrentInput) differentialCurrentInput.value = '';

    // Reset cable fields (only if they exist)
    if (cableBrandSelect) cableBrandSelect.value = '';
    if (cableModelSelect) cableModelSelect.innerHTML = '<option value="">Select Model...</option>';
    if (cableSizeInput) cableSizeInput.value = '';
    if (cableInsulationInput) cableInsulationInput.value = '';

    // Clear max output current field
    document.getElementById('new_inverter_max_current').value = '';

    // Update the table and cards view
    console.log('[Main] Atualizando visualização após adicionar inversor');
    updateInvertersTable();

    // Keep window-scoped list in sync and update hidden field BEFORE triggering dependent logic
    try {
        window.invertersList = Array.isArray(invertersList) ? [...invertersList] : [];
        const hiddenInvField = document.getElementById('inverters_data');
        if (hiddenInvField) {
            hiddenInvField.value = JSON.stringify(invertersList);
            console.log('[Main] inverters_data hidden updated. length:', hiddenInvField.value.length);
        } else {
            console.warn('[Main] Campo hidden inverters_data não encontrado');
        }
    } catch (e) {
        console.warn('[Main] Falha ao sincronizar window.invertersList/hidden:', e);
    }

    // Dispatch event to update string measurements tables
    const event = new CustomEvent('invertersListUpdated', {
        detail: { invertersList: window.invertersList }
    });
    document.dispatchEvent(event);
    console.log('[Main] Evento invertersListUpdated disparado com', (window.invertersList || []).length, 'inversores');

    // Load string measurement tables for all inverters
    if (typeof loadAllInverterStringTables === 'function') {
        console.log('[Main] Chamando loadAllInverterStringTables()');
        loadAllInverterStringTables();
    } else {
        console.warn('[Main] Função loadAllInverterStringTables não encontrada');
    }

    // Refresh Telemetry inverter reference options
    if (typeof window.refreshTelemetryInverterOptions === 'function') {
        window.refreshTelemetryInverterOptions();
    }

    // Chamar diretamente a função global window.updateInvertersCards
    if (typeof window.updateInvertersCards === 'function') {
        console.log('[Main] Chamando window.updateInvertersCards diretamente');
        window.updateInvertersCards();

        // Certifique-se de que os cards são recriados
        console.log('[Main] Recriando cards de inversores com', window.invertersList.length, 'inversores');

        // Scroll to the inverters container to show the new card
        setTimeout(() => {
            // Force rerender dos cards
            const invertersContainer = document.getElementById('inverters-container');
            if (invertersContainer) {
                console.log('[Main] Container de inversores encontrado, procurando último card');
                // Forçar a atualização dos cards
                window.updateInvertersCards();

                // Dar tempo para os cards serem renderizados
                setTimeout(() => {
                    // Get the last added card
                    const newCard = invertersContainer.querySelector('.inverter-card:last-child');
                    if (newCard) {
                        console.log('[Main] Card encontrado, aplicando animação');
                        // Scroll to the new card with smooth animation
                        newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        // Add highlight animation to the new card
                        newCard.classList.add('highlight-new-card');
                        setTimeout(() => {
                            newCard.classList.remove('highlight-new-card');
                        }, 2000);
                    } else {
                        console.error('[Main] ERRO: Card não encontrado após adicionar inversor!');
                    }
                }, 100);
            }

            // Show a toast message
            showToast(`Inverter ${window.editingInverterIndex >= 0 ? 'updated' : 'added'} successfully!`, 'success');

            // Reset submit button text and style to default Add mode
            const submitBtn = document.getElementById('submit-inverter-btn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-plus-circle me-2"></i> Add This Inverter to Project';
                submitBtn.classList.remove('btn-warning');
                submitBtn.classList.add('btn-primary');
            }
        }, 100);
    }

    // Update hidden field with the data (redundant safeguard post-animations)
    const hiddenInvField2 = document.getElementById('inverters_data');
    if (hiddenInvField2) hiddenInvField2.value = JSON.stringify(invertersList);
}

/**
 * Clear all inverters from the list
 */
function clearAllInverters() {
    // Reset the inverters list
    window.invertersList = [];

    // Update UI
    updateInvertersTable();
    if (typeof window.refreshTelemetryInverterOptions === 'function') {
        window.refreshTelemetryInverterOptions();
    }

    // Update hidden field
    document.getElementById('inverters_data').value = JSON.stringify(window.invertersList);

    showToast('All inverters have been removed', 'success');
}

/**
 * Update the inverters table with current data
 */
function updateInvertersTable() {
    const tableBody = document.getElementById('inverters-table-body');
    if (!tableBody) return;

    // Clear existing rows
    tableBody.innerHTML = '';

    if (invertersList.length === 0) {
        // Show no data message
        const noDataRow = document.createElement('tr');
        noDataRow.innerHTML = '<td colspan="10" class="text-center py-3">No inverters added yet</td>';
        tableBody.appendChild(noDataRow);

        // Also update cards view
        if (typeof updateInvertersCards === 'function') {
            updateInvertersCards();
        }
        return;
    }

    // Add a row for each inverter
    invertersList.forEach((inverter, index) => {
        const row = document.createElement('tr');

        // Determine datasheet button state
        const hasDatasheet = inverter.datasheet_url && inverter.datasheet_url.trim() !== '';
        let datasheetBtn;

        if (hasDatasheet) {
            datasheetBtn = `
                <a href="${inverter.datasheet_url}" target="_blank" class="btn btn-sm btn-info" title="View Datasheet">
                    <i class="fas fa-file-pdf"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary edit-datasheet" data-index="${index}" data-type="inverter" title="Edit Datasheet URL">
                    <i class="fas fa-edit"></i>
                </button>`;
        } else {
            datasheetBtn = `
                <button type="button" class="btn btn-sm btn-outline-info add-datasheet" data-index="${index}" data-type="inverter" title="Add Datasheet URL">
                    <i class="fas fa-link"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary search-datasheet" data-index="${index}" data-type="inverter" title="Search Datasheet Online">
                    <i class="fas fa-search"></i>
                </button>`;
        }

        row.innerHTML = `
            <td>${inverter.brand_name}</td>
            <td>${inverter.model_name}</td>
            <td>${inverter.quantity}</td>
            <td>${inverter.status_text}</td>
            <td>${inverter.serial_number || '-'}</td>
            <td>${inverter.location || '-'}</td>
            <td>${inverter.circuit_breaker_brand_name && inverter.circuit_breaker_model_name ?
                `${inverter.circuit_breaker_brand_name} ${inverter.circuit_breaker_model_name}` : '-'}</td>
            <td>${inverter.differential_brand_name && inverter.differential_model_name ?
                `${inverter.differential_brand_name} ${inverter.differential_model_name}` : '-'}</td>
            <td>${(inverter.cable_brand_name || inverter.cable_model_name || inverter.cable_size) ?
                `${inverter.cable_brand_name || ''} ${inverter.cable_model_name ? inverter.cable_model_name + ' ' : ''}${inverter.cable_size ? inverter.cable_size + ' mm2' : ''}`.trim() : '-'}</td>
            <td class="text-center">${datasheetBtn}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger delete-inverter" data-index="${index}" title="Delete Inverter">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(row);
    });

    // Update cards view
    if (typeof window.updateInvertersCards === 'function') {
        window.updateInvertersCards();
    }

    // Add event listeners to buttons
    document.querySelectorAll('.delete-inverter').forEach(btn => {
        btn.addEventListener('click', function () {
            deleteInverter(parseInt(this.getAttribute('data-index')));
        });
    });

    document.querySelectorAll('.add-datasheet[data-type="inverter"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            showDatasheetModal('inverter', index);
        });
    });

    document.querySelectorAll('.edit-datasheet[data-type="inverter"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            showDatasheetModal('inverter', index);
        });
    });

    document.querySelectorAll('.search-datasheet[data-type="inverter"]').forEach(btn => {
        btn.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            const inverter = invertersList[index];

            if (inverter) {
                // Direct search like inverter cards - open Google search in new tab
                const searchTerm = `${inverter.brand_name} ${inverter.model_name} datasheet pdf`;
                window.open(`https://www.google.com/search?q=${encodeURIComponent(searchTerm)}`, '_blank');
            }
        });
    });
}


/**
 * Delete an inverter from the table
 * 
 * @param {number} index - Index of the inverter in the invertersList array
 */
function deleteInverter(index) {
    customConfirm('Are you sure you want to delete this inverter?', 'Delete Inverter')
        .then(confirmed => {
            if (!confirmed) return;

            const inverter = invertersList[index];
            const hasDbId = inverter && (inverter.id || inverter.inverter_id);

            // If inverter exists in DB, call server to delete the report_equipment row
            if (hasDbId) {
                const idToDelete = inverter.id || inverter.inverter_id;
                fetch((window.BASE_URL || '') + 'ajax/delete_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table: 'report_equipment', id: idToDelete })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            // Remove from local list and update UI
                            invertersList.splice(index, 1);
                            updateInvertersTable();
                            if (typeof window.refreshTelemetryInverterOptions === 'function') {
                                window.refreshTelemetryInverterOptions();
                            }
                            const hidden = document.getElementById('inverters_data');
                            if (hidden) hidden.value = JSON.stringify(invertersList);
                            customAlert('Inverter successfully deleted', 'success');
                        } else {
                            console.error('Failed to delete inverter on server:', data);
                            customAlert('Could not delete inverter on server: ' + (data && data.error ? data.error : 'Unknown error'), 'danger');
                        }
                    })
                    .catch(err => {
                        console.error('Error calling delete endpoint:', err);
                        customAlert('Error deleting inverter: ' + err.message, 'danger');
                    });
            } else {
                // No DB id — just remove locally
                invertersList.splice(index, 1);
                updateInvertersTable();
                if (typeof window.refreshTelemetryInverterOptions === 'function') {
                    window.refreshTelemetryInverterOptions();
                }

                // Update hidden field with the data
                document.getElementById('inverters_data').value = JSON.stringify(invertersList);

                // Show success message
                customAlert('Inverter successfully deleted', 'success');
            }
        });
}

/**
 * Select inverter for string measurements
 */
function selectInverterForStrings() {
    // Create modal for selecting inverter
    if (invertersList.length === 0) {
        customAlert('Please add at least one inverter in the Equipment tab first.', 'warning', 'No Inverters Found');
        document.getElementById('equipment-tab').click();
        return;
    }

    // Create modal HTML
    let modalHtml = `
        <div class="modal fade" id="selectInverterModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Select Inverter for String Measurements</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Brand</th>
                                        <th>Model</th>
                                        <th>Serial</th>
                                        <th>Location</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>`;

    // Add inverters to the table
    invertersList.forEach((inverter, index) => {
        modalHtml += `
            <tr>
                <td>${inverter.brand_name}</td>
                <td>${inverter.model_name}</td>
                <td>${inverter.serial_number || '-'}</td>
                <td>${inverter.location || '-'}</td>
                <td>
                    <button type="button" class="btn btn-primary btn-sm select-inverter-for-strings" 
                        data-index="${index}" data-model-id="${inverter.model_id}">
                        Select
                    </button>
                </td>
            </tr>`;
    });

    modalHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to document
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer);

    // Initialize the modal
    const modal = new bootstrap.Modal(document.getElementById('selectInverterModal'));
    modal.show();

    // Add event listeners to select buttons
    document.querySelectorAll('.select-inverter-for-strings').forEach(btn => {
        btn.addEventListener('click', function () {
            const modelId = this.getAttribute('data-model-id');
            const inverterIndex = this.getAttribute('data-index');
            const inverter = invertersList[inverterIndex];

            // Hide the modal
            modal.hide();

            // Remove the modal element after hidden
            document.getElementById('selectInverterModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });

            // Generate the string measurement table
            generateStringMeasurementTable(modelId, 'string-tables-container', inverter);
        });
    });
}

/**
 * Generate MPPT string measurement table based on inverter data
 * 
 * @param {number} inverterId - Selected inverter ID
 * @param {string} targetContainerId - ID of container element for the table
 * @param {Object} inverterData - Optional inverter data object with details
 */
function generateStringMeasurementTable(inverterId, targetContainerId, inverterData = null) {
    if (!inverterId) return;

    const container = document.getElementById(targetContainerId);
    if (!container) return;

    // Show loading indicator
    container.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading...</div></div>';

    // Make AJAX request
    fetch((window.BASE_URL || "") + `ajax/get_inverter_data.php?inverter_id=${inverterId}`)
        .then(response => response.json())
        .then(data => {
            // Generate the measurement table
            let tableHtml = '';

            // Add inverter information if provided
            if (inverterData) {
                tableHtml += `
                <div class="alert alert-info mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Selected Inverter:</strong> ${inverterData.brand_name} ${inverterData.model_name}
                            ${inverterData.serial_number ? '<br><strong>Serial:</strong> ' + inverterData.serial_number : ''}
                            ${inverterData.location ? '<br><strong>Location:</strong> ' + inverterData.location : ''}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="change-inverter-btn">
                            Change Inverter
                        </button>
                    </div>
                </div>
                `;
            }

            // Store inverter model ID in hidden field
            tableHtml += `<input type="hidden" name="string_inverter_id" value="${inverterId}">`;

            for (let i = 1; i <= data.mppts; i++) {
                tableHtml += `
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">MPPT ${i}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>String</th>
                                        <th>Voc (V)</th>
                                        <th>Isc (A)</th>
                                        <th>Vmp (V)</th>
                                        <th>Imp (A)</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                // Add rows for each string
                for (let j = 1; j <= data.strings_per_mppt; j++) {
                    tableHtml += `
                    <tr>
                        <td>${j}</td>
                        <td>
                            <input type="number" step="0.01" class="form-control" 
                                name="string_voc_${i}_${j}" id="string_voc_${i}_${j}">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control" 
                                name="string_isc_${i}_${j}" id="string_isc_${i}_${j}">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control" 
                                name="string_vmp_${i}_${j}" id="string_vmp_${i}_${j}">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control" 
                                name="string_imp_${i}_${j}" id="string_imp_${i}_${j}">
                        </td>
                        <td>
                            <input type="text" class="form-control" 
                                name="string_notes_${i}_${j}" id="string_notes_${i}_${j}">
                        </td>
                    </tr>
                    `;
                }

                tableHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                `;
            }

            // Update container
            container.innerHTML = tableHtml;

            // Attempt to restore persisted string inputs for the compact layout
            try {
                if (typeof restoreStringInputs === 'function') {
                    restoreStringInputs(container);
                }
            } catch (err) {
                console.warn('Could not restore string inputs for compact table', err);
            }

            // Add event listener to change inverter button
            const changeInverterBtn = document.getElementById('change-inverter-btn');
            if (changeInverterBtn) {
                changeInverterBtn.addEventListener('click', selectInverterForStrings);
            }
        })
        .catch(error => {
            console.error('Error generating string table:', error);
            container.innerHTML = '<div class="alert alert-danger">Error loading inverter data.</div>';
        });
}

/**
 * Search for datasheets online based on brand and model
 * @param {string} brand Equipment brand
 * @param {string} model Equipment model
 * @param {string} type Equipment type ('module' or 'inverter')
 * @returns {Promise<Array>} Array of datasheet URLs
 */
function searchDatasheets(brand, model, type) {
    return new Promise((resolve, reject) => {
        // Show loading indicator
        const loadingToast = showToast('Searching for datasheets...', 'info', false);

        const formData = new FormData();
        formData.append('brand', brand);
        formData.append('model', model);
        formData.append('type', type);

        fetch((window.BASE_URL || '') + 'ajax/get_datasheet_links.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                // Hide loading indicator
                if (loadingToast) {
                    loadingToast.hide();
                }

                if (data.success) {
                    resolve(data.results);
                } else {
                    showToast(data.message || 'Failed to find datasheets', 'warning');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Error searching for datasheets:', error);
                if (loadingToast) {
                    loadingToast.hide();
                }
                showToast('Error connecting to datasheet search service', 'error');
                reject(error);
            });
    });
}

/**
 * Shows a modal with datasheet search results
 * @param {string} brand Equipment brand
 * @param {string} model Equipment model
 * @param {string} type Equipment type ('module' or 'inverter')
 * @param {Function} onSelect Callback function when a datasheet is selected
 */
function showDatasheetSearchResults(brand, model, type, onSelect) {
    // Create modal if it doesn't exist
    if (!document.getElementById('datasheetSearchModal')) {
        const modalHTML = `
            <div class="modal fade" id="datasheetSearchModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Datasheet Search Results</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="datasheetSearchResults">
                                <div class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p>Searching for datasheets...</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="manualDatasheetBtn">
                                Enter URL Manually
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Add event listener for manual entry button

        document.getElementById('manualDatasheetBtn').addEventListener('click', function () {
            const modal = bootstrap.Modal.getInstance(document.getElementById('datasheetSearchModal'));
            modal.hide();

            // Show manual entry modal
            showDatasheetModal(type === 'module' ? 'module' : 'inverter', null, onSelect);
        });
    }

    // Show the search modal
    const searchModal = new bootstrap.Modal(document.getElementById('datasheetSearchModal'));
    searchModal.show();

    // Get results container
    const resultsContainer = document.getElementById('datasheetSearchResults');

    // Search for datasheets
    searchDatasheets(brand, model, type)
        .then(results => {
            if (results.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No datasheets found for ${brand} ${model}.
                        <p class="mt-2">You can enter a datasheet URL manually using the button below.</p>
                    </div>
                `;
                return;
            }

            // Display results
            let resultsHTML = `
                <p>Found ${results.length} potential datasheet(s) for <strong>${brand} ${model}</strong>:</p>
                <div class="list-group">
            `;

            results.forEach((result, index) => {
                resultsHTML += `
                    <a href="#" class="list-group-item list-group-item-action datasheet-result" data-url="${result.url}" data-index="${index}">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${result.title}</h6>
                            <small>${result.source}</small>
                        </div>
                        <small class="text-muted text-truncate d-block">${result.url}</small>
                    </a>
                `;
            });

            resultsHTML += '</div>';
            resultsContainer.innerHTML = resultsHTML;

            // Add event listeners to results
            document.querySelectorAll('.datasheet-result').forEach(item => {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = this.getAttribute('data-url');

                    // Close the modal
                    searchModal.hide();

                    // Call the callback with the selected URL
                    if (typeof onSelect === 'function') {
                        onSelect(url);
                    }
                });
            });
        })
        .catch(error => {
            console.error('Error displaying datasheet search results:', error);
            resultsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error searching for datasheets. Please try again later or enter a URL manually.
                </div>
            `;
        });
}


// ===== System Layout Management =====
let layoutsList = [];
let editingLayoutIndex = -1;

function addLayoutToTable() {
    const roofIdInput = document.getElementById('layout_roof_id');
    const qtyInput = document.getElementById('layout_quantity');
    const azimuthInput = document.getElementById('layout_azimuth');
    const tiltInput = document.getElementById('layout_tilt');
    const mountingInput = document.getElementById('layout_mounting');
    const addBtn = document.getElementById('add-layout-btn');

    if (!roofIdInput || !qtyInput || !azimuthInput || !tiltInput || !mountingInput) {
        showNotification('Layout form elements not found', 'danger');
        return;
    }

    // Validate required fields
    if (!roofIdInput.value.trim() || !qtyInput.value || !azimuthInput.value || !tiltInput.value || !mountingInput.value) {
        showNotification('Please fill in all layout fields', 'warning');
        return;
    }

    // Validate that total layouts doesn't exceed total modules
    const layoutQty = parseInt(qtyInput.value);
    const totalLayoutQty = layoutsList.reduce((sum, l) => sum + parseInt(l.quantity || 0), 0);
    const totalModuleQty = modulesList.reduce((sum, m) => sum + parseInt(m.quantity || 0), 0);

    // If editing, subtract the old quantity first
    let newLayoutTotal = totalLayoutQty;
    if (editingLayoutIndex >= 0) {
        newLayoutTotal = totalLayoutQty - parseInt(layoutsList[editingLayoutIndex].quantity || 0) + layoutQty;
    } else {
        newLayoutTotal = totalLayoutQty + layoutQty;
    }

    if (newLayoutTotal > totalModuleQty) {
        showNotification(`❌ Validation Error: Total layouts (${newLayoutTotal}) cannot exceed total PV modules (${totalModuleQty})`, 'danger');
        return;
    }

    const layoutData = {
        roof_id: roofIdInput.value.trim(),
        quantity: qtyInput.value,
        azimuth: azimuthInput.value,
        tilt: tiltInput.value,
        mounting: mountingInput.value
    };

    if (editingLayoutIndex >= 0) {
        layoutsList[editingLayoutIndex] = layoutData;
        editingLayoutIndex = -1;
        showNotification('✅ Layout updated successfully', 'success');
    } else {
        layoutsList.push(layoutData);
        showNotification('✅ Layout added successfully', 'success');
    }

    updateLayoutsTable();
    updateLayoutsHidden();

    // Trigger immediate autosave after layouts change
    if (typeof window.triggerAutosave === 'function') {
        window.triggerAutosave();
    }

    // Reset form
    roofIdInput.value = '';
    qtyInput.value = '';
    azimuthInput.value = '';
    tiltInput.value = '';
    mountingInput.value = '';

    // Reset button
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-plus"></i> Add Layout';
        addBtn.classList.remove('btn-warning');
        addBtn.classList.add('btn-success');
    }
}

function updateLayoutsTable() {
    const tableBody = document.getElementById('layouts-table-body');
    if (!tableBody) return;
    tableBody.innerHTML = '';

    if (layoutsList.length === 0) {
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = `<td colspan="6" class="text-center text-muted"><em>No layout items added yet. Click "Add Layout" to get started.</em></td>`;
        tableBody.appendChild(emptyRow);
        updateLayoutValidationSummary();
        return;
    }

    layoutsList.forEach((layout, idx) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${layout.roof_id}</td>
            <td>${layout.quantity}</td>
            <td>${layout.azimuth}</td>
            <td>${layout.tilt}</td>
            <td>${layout.mounting.charAt(0).toUpperCase() + layout.mounting.slice(1)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-primary me-1" data-index="${idx}" data-action="edit-layout"><i class="fas fa-edit"></i></button>
                <button type="button" class="btn btn-sm btn-danger" data-index="${idx}" data-action="delete-layout"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tableBody.appendChild(row);
    });

    // Add event listeners
    tableBody.querySelectorAll('button[data-action="edit-layout"]').forEach(btn => {
        btn.addEventListener('click', () => editLayout(parseInt(btn.getAttribute('data-index'))));
    });
    tableBody.querySelectorAll('button[data-action="delete-layout"]').forEach(btn => {
        btn.addEventListener('click', () => deleteLayout(parseInt(btn.getAttribute('data-index'))));
    });

    // ALWAYS update hidden field with current layouts
    updateLayoutsHidden();

    // In new report mode, save to database draft instead of localStorage
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        fetch((window.BASE_URL || '') + 'ajax/save_layouts_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                layouts: layoutsList
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Layouts data saved to database draft');
                } else {
                    console.warn('Could not save layouts data to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving layouts data to database draft:', error);
            });
    }
    // In edit mode: autosave will be handled by the periodic interval (every 5 seconds)
    // Don't autosave immediately here to avoid conflicts with form submit

    // Update validation summary
    updateLayoutValidationSummary();
}

// Update the layout validation summary with quantity check
function updateLayoutValidationSummary() {
    const totalModuleQty = modulesList.reduce((sum, m) => sum + parseInt(m.quantity || 0), 0);
    const totalLayoutQty = layoutsList.reduce((sum, l) => sum + parseInt(l.quantity || 0), 0);

    const modulesEl = document.getElementById('total-modules-qty');
    const layoutsEl = document.getElementById('total-layouts-qty');
    const summaryEl = document.getElementById('layout-validation-summary');

    if (modulesEl) modulesEl.textContent = totalModuleQty;
    if (layoutsEl) {
        layoutsEl.textContent = totalLayoutQty;
        // Change color based on validation
        if (totalLayoutQty > totalModuleQty && totalModuleQty > 0) {
            layoutsEl.className = 'badge bg-danger'; // Red if exceeds
        } else {
            layoutsEl.className = 'badge bg-success'; // Green if ok
        }
    }

    // Show summary if there are modules or layouts
    if (summaryEl && (totalModuleQty > 0 || totalLayoutQty > 0)) {
        summaryEl.style.display = 'block';
    }
}

/**
 * Save System Layout to SQL Database
 * Called in edit mode to persist layouts data
 */
function saveSystemLayoutToSQL() {
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE || !window.reportId) {
        console.warn('[saveSystemLayoutToSQL] Skipping - EDIT_MODE:', window.EDIT_MODE_SKIP_LOCALSTORAGE, 'reportId:', window.reportId);
        return; // Only in edit mode
    }

    console.log('[saveSystemLayoutToSQL] Sending ' + layoutsList.length + ' layouts for report ' + window.reportId);

    // FIRST: Sync hidden field with current layoutsList
    updateLayoutsHidden();

    fetch((window.BASE_URL || '') + 'ajax/save_system_layout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            report_id: window.reportId,
            layouts: layoutsList
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ System layouts autosaved to SQL:', data.count, 'layouts');
            } else {
                console.warn('⚠️ Failed to autosave layouts:', data.error);
            }
        })
        .catch(error => {
            console.error('❌ Error autosaving layouts to SQL:', error);
        });
}

/**
 * Start periodic autosave of System Layouts to SQL
 * Runs every 3 seconds in edit mode to ensure data persistence
 */
let layoutAutosaveInterval = null;
let lastLayoutsChecksum = null;

function startSystemLayoutAutosave() {
    if (layoutAutosaveInterval) {
        clearInterval(layoutAutosaveInterval);
    }

    console.log('✅ System Layout autosave to SQL started (every 3 seconds)');

    // Periodic autosave
    layoutAutosaveInterval = setInterval(() => {
        if (window.EDIT_MODE_SKIP_LOCALSTORAGE && window.reportId) {
            // Only autosave if data has changed
            const currentChecksum = JSON.stringify(layoutsList);
            if (currentChecksum !== lastLayoutsChecksum) {
                lastLayoutsChecksum = currentChecksum;
                saveSystemLayoutToSQL();
            }
        }
    }, 3000);
}

function editLayout(index) {
    const layout = layoutsList[index];
    if (!layout) return;
    document.getElementById('layout_roof_id').value = layout.roof_id;
    document.getElementById('layout_quantity').value = layout.quantity;
    document.getElementById('layout_azimuth').value = layout.azimuth;
    document.getElementById('layout_tilt').value = layout.tilt;
    document.getElementById('layout_mounting').value = layout.mounting;
    editingLayoutIndex = index;
    const addBtn = document.getElementById('add-layout-btn');
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-save"></i> Update Layout';
        addBtn.classList.remove('btn-success');
        addBtn.classList.add('btn-warning');
    }
}

function deleteLayout(index) {
    layoutsList.splice(index, 1);
    updateLayoutsTable();
    updateLayoutsHidden();
    showNotification('Layout item removed successfully!', 'info');
}

function updateLayoutsHidden() {
    const hidden = document.getElementById('layouts_data');
    if (hidden) hidden.value = JSON.stringify(layoutsList);
}

// Global function to load existing layouts (called by autosave_sql.js)
window.loadExistingLayouts = function () {
    console.log('[loadExistingLayouts] Called - window.existingLayouts:', window.existingLayouts);

    if (!window.existingLayouts || !Array.isArray(window.existingLayouts)) {
        console.log('[loadExistingLayouts] No layouts to load');
        return;
    }

    console.log('[loadExistingLayouts] Loading', window.existingLayouts.length, 'layouts');

    layoutsList = window.existingLayouts.map(layout => ({
        roof_id: layout.roof_id || '',
        quantity: layout.quantity || '',
        azimuth: layout.azimuth || '',
        tilt: layout.tilt || '',
        mounting: layout.mounting || ''
    }));

    // Update the table and UI
    updateLayoutsTable();
    updateLayoutsHidden();

    console.log('[loadExistingLayouts] ✅ Layouts loaded successfully');
};

// Initialize layout table and form on page load
document.addEventListener('DOMContentLoaded', function () {
    // Add layout form fields dynamically if not present
    const layoutFormRow = document.getElementById('layout-form-row');
    if (layoutFormRow && layoutFormRow.children.length === 0) {
        layoutFormRow.innerHTML = `
            <td><input type="text" class="form-control" id="layout_roof_id" name="layout_roof_id" placeholder="Roof/Area ID"></td>
            <td><input type="number" class="form-control" id="layout_quantity" name="layout_quantity" placeholder="Quantity" min="1"></td>
            <td><input type="number" class="form-control" id="layout_azimuth" name="layout_azimuth" placeholder="Azimuth (°)" step="0.1"></td>
            <td><input type="number" class="form-control" id="layout_tilt" name="layout_tilt" placeholder="Tilt (°)" step="0.1"></td>
            <td><select class="form-control" id="layout_mounting" name="layout_mounting"><option value="">Select Mounting</option><option value="coplanar">Coplanar</option><option value="triangular">Triangular</option></select></td>
            <td><button type="button" class="btn btn-success" id="add-layout-btn"><i class="fas fa-plus"></i> Add Layout</button></td>
        `;
    }
    // Attach event
    const addBtn = document.getElementById('add-layout-btn');
    if (addBtn) {
        addBtn.addEventListener('click', addLayoutToTable);
    }

    // Load existing layouts if available
    try {
        const hidden = document.getElementById('layouts_data');
        if (hidden && hidden.value) {
            const parsed = JSON.parse(hidden.value);
            if (Array.isArray(parsed)) {
                layoutsList = parsed;
                updateLayoutsTable();
                updateLayoutsHidden();
            }
        }
    } catch (e) {
        console.warn('Could not parse layouts data from hidden input');
    }

    // Load existing layouts from database if available
    if (window.existingLayouts && Array.isArray(window.existingLayouts)) {
        layoutsList = window.existingLayouts.map(layout => ({
            roof_id: layout.roof_id,
            quantity: layout.quantity,
            azimuth: layout.azimuth,
            tilt: layout.tilt,
            mounting: layout.mounting
        }));

        // Update the table with existing layouts
        updateLayoutsTable();

        // Update hidden field with the data
        updateLayoutsHidden();

        // Start autosave to SQL in edit mode
        if (window.EDIT_MODE_SKIP_LOCALSTORAGE && window.reportId) {
            startSystemLayoutAutosave();
        }
    }

    updateLayoutsTable();
    updateLayoutsHidden();
});

// =====================
// Protection Tab Logic
// =====================
let protectionList = [];
// Protection cable uses a single-entry object instead of list
let protectionCable = null;
let protectionCablesList = [];
let clampMeasurements = [];
let punchListItems = [];

// Global function to load existing punch list (called by autosave_sql.js)
window.loadExistingPunchList = function () {
    console.log('[loadExistingPunchList] Called - window.existingPunchList:', window.existingPunchList);

    if (!window.existingPunchList || !Array.isArray(window.existingPunchList)) {
        console.log('[loadExistingPunchList] No punch list items to load');
        return;
    }

    console.log('[loadExistingPunchList] Loading', window.existingPunchList.length, 'punch list items');

    // Preserve fields needed for filtering (resolution_date) and display
    punchListItems = window.existingPunchList.map(item => ({
        id: item.id || '',
        description: item.description || item.issue_description || '',
        severity: item.severity || item.issue_priority || item.severity_level || '',
        opening_date: item.opening_date || item.openingDate || item.opening || '',
        resolution_date: item.resolution_date || item.resolutionDate || item.resolution || '',
        responsible: item.responsible || item.assigned_to || item.assignedTo || ''
    }));

    console.log('[loadExistingPunchList] Mapped punchListItems:', punchListItems);

    ensurePunchUids(punchListItems);

    // Update the table
    if (typeof renderPunchListTable === 'function') {
        renderPunchListTable();
    }

    const hidden = document.getElementById('punch_list_data');
    if (hidden) {
        hidden.value = JSON.stringify(punchListItems);
    }

    console.log('[loadExistingPunchList] Load complete');
};

// Attach filter event to checkbox once DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    const openFilter = document.getElementById('punch_filter_open');
    if (openFilter) {
        openFilter.addEventListener('change', function () {
            try { renderPunchListTable(); } catch (e) { console.warn('Could not re-render punch list on filter change', e); }
        });
    }
});

// Global function to load existing protection (called by autosave_sql.js)
window.loadExistingProtection = function () {
    console.log('[loadExistingProtection] Called - window.existingProtection:', window.existingProtection);

    if (!window.existingProtection || !Array.isArray(window.existingProtection)) {
        console.log('[loadExistingProtection] No protection to load');
        return;
    }

    console.log('[loadExistingProtection] Loading', window.existingProtection.length, 'protection items');
    console.log('[loadExistingProtection] First item raw:', window.existingProtection[0]);

    protectionList = window.existingProtection.map(item => ({
        scope: item.scope || '',
        scope_text: item.scope_text || '',
        brand_id: item.brand_id || '',
        brand_name: item.brand_name || '',
        model_id: item.model_id || '',
        model_name: item.model_name || '',
        quantity: item.quantity || 1,
        rated_current: item.rated_current
    }));

    console.log('[loadExistingProtection] Mapped protectionList:', protectionList);
    console.log('[loadExistingProtection] First mapped item:', protectionList[0]);

    // Update the table and UI
    updateProtectionTable();

    const hidden = document.getElementById('protection_data');
    if (hidden) {
        hidden.value = JSON.stringify(protectionList);
    }

    console.log('[loadExistingProtection] ✅ Protection loaded successfully');
};

function updateProtectionTable() {
    console.log('[PROTECTION] ===== updateProtectionTable CALLED =====');

    const tbody = document.getElementById('protection-table-body');
    console.log('[PROTECTION] tbody element:', tbody);
    if (!tbody) {
        console.log('[PROTECTION] ❌ tbody NOT FOUND!');
        return;
    }
    console.log('[PROTECTION] protectionList.length:', protectionList.length);
    console.log('[PROTECTION] protectionList:', protectionList);

    tbody.innerHTML = '';
    if (protectionList.length === 0) {
        console.log('[PROTECTION] Empty list, showing placeholder');
        const qtyEl = document.getElementById('protection_circuit_qty');
        const colspan = qtyEl ? '6' : '5';
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="${colspan}" class="text-center text-muted"><em>No protection items added</em></td>`;
        tbody.appendChild(row);
        return;
    }
    protectionList.forEach((item, index) => {
        console.log(`[PROTECTION] Rendering row ${index}:`, item);
        console.log(`[PROTECTION]   - scope_text: ${item.scope_text}`);
        console.log(`[PROTECTION]   - brand_name: ${item.brand_name}`);
        console.log(`[PROTECTION]   - model_name: ${item.model_name}`);
        console.log(`[PROTECTION]   - rated_current: ${item.rated_current} (TYPE: ${typeof item.rated_current})`);
        console.log(`[PROTECTION]   - quantity: ${item.quantity}`);

        const row = document.createElement('tr');
        const qtyEl = document.getElementById('protection_circuit_qty');

        // Determine rated display with robust fallback from characteristics if needed
        let ratedDisplay;
        if (item.rated_current !== undefined && item.rated_current !== null && item.rated_current !== '') {
            ratedDisplay = item.rated_current;
        } else {
            const chars = (item.characteristics || item.raw_characteristics || '').toString();
            let m = null;
            const rxList = [
                /Rated\s*Current\s*\(A\)?:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i,
                /Rated\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i,
                /\bIn\b\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*A?/i
            ];
            for (const rx of rxList) { m = chars.match(rx); if (m) break; }
            ratedDisplay = m ? parseFloat(String(m[1]).replace(',', '.')) : '-';
        }

        if (qtyEl) {
            // Include quantity column if field exists
            console.log(`[PROTECTION]   - ratedDisplay (will show in table): ${ratedDisplay}`);
            row.innerHTML = `
                <td>${item.scope_text}</td>
                <td>${item.brand_name}</td>
                <td>${item.model_name}</td>
                <td>${ratedDisplay}</td>
                <td>${item.quantity}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary me-1" data-index="${index}" data-action="edit-protection"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-danger" data-index="${index}" data-action="delete-protection"><i class="fas fa-trash"></i></button>
                </td>
            `;
        } else {
            // Exclude quantity column if field doesn't exist
            console.log(`[PROTECTION]   - ratedDisplay (no qty col): ${ratedDisplay}`);
            row.innerHTML = `
                <td>${item.scope_text}</td>
                <td>${item.brand_name}</td>
                <td>${item.model_name}</td>
                <td>${ratedDisplay}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary me-1" data-index="${index}" data-action="edit-protection"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-danger" data-index="${index}" data-action="delete-protection"><i class="fas fa-trash"></i></button>
                </td>
            `;
        }
        tbody.appendChild(row);
        console.log(`[PROTECTION] Row ${index} appended to DOM`);
    });
    console.log('[PROTECTION] ✅ All rows rendered in table');

    // Hook up actions
    tbody.querySelectorAll('button[data-action="edit-protection"]').forEach(btn => {
        btn.addEventListener('click', () => editProtection(parseInt(btn.getAttribute('data-index'))));
    });
    tbody.querySelectorAll('button[data-action="delete-protection"]').forEach(btn => {
        btn.addEventListener('click', () => deleteProtection(parseInt(btn.getAttribute('data-index'))));
    });

    // In edit mode we persist via SQL only; optionally use localStorage only outside edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        // Save to database draft instead of localStorage
        fetch((window.BASE_URL || '') + 'ajax/save_protection_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                protection: protectionList
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Protection data saved to database draft');
                } else {
                    console.warn('Could not save protection data to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving protection data to database draft:', error);
            });
    }
}

function updateProtectionCableHidden() {
    const hidden = document.getElementById('protection_cable_data');
    if (!hidden) return;
    // If we're using the dynamic list and it has items, that list is authoritative
    if (Array.isArray(protectionCablesList) && protectionCablesList.length > 0) {
        hidden.value = JSON.stringify(protectionCablesList);
        return;
    }
    // Fallback to legacy single-cable behavior when list is empty
    if (protectionCable) hidden.value = JSON.stringify([protectionCable]);
    else hidden.value = '[]';
}

function updateProtectionCablesHidden() {
    const hidden = document.getElementById('protection_cable_data');
    if (!hidden) return;
    hidden.value = JSON.stringify(protectionCablesList);
}

// Global function to load existing protection cables (called by autosave_sql.js)
window.loadExistingProtectionCables = function () {
    console.log('[loadExistingProtectionCables] Called - window.existingProtectionCables:', window.existingProtectionCables);

    if (!window.existingProtectionCables || !Array.isArray(window.existingProtectionCables)) {
        console.log('[loadExistingProtectionCables] No cables to load');
        return;
    }

    console.log('[loadExistingProtectionCables] Loading', window.existingProtectionCables.length, 'cable items');
    console.log('[loadExistingProtectionCables] First item raw:', window.existingProtectionCables[0]);

    protectionCablesList = window.existingProtectionCables.map(cable => ({
        scope: cable.scope || '',
        scope_text: cable.scope_text || '',
        brand_id: cable.brand_id || '',
        brand_name: cable.brand_name || '',
        model_id: cable.model_id || '',
        model_name: cable.model_name || '',
        size: cable.size || '',
        insulation: cable.insulation || ''
    }));

    console.log('[loadExistingProtectionCables] Mapped protectionCablesList:', protectionCablesList);

    // Update the table and UI
    updateProtectionCablesTable();
    updateProtectionCablesHidden();

    console.log('[loadExistingProtectionCables] ✅ Cables loaded successfully');
};

function addProtectionCableFromForm() {
    const brandEl = document.getElementById('protection_cable_brand');
    const modelEl = document.getElementById('protection_cable_model');
    const sizeEl = document.getElementById('protection_cable_size');
    const insEl = document.getElementById('protection_cable_insulation');
    const addBtn = document.getElementById('add-protection-cable-btn');

    if (!brandEl || !modelEl) return;

    if (!brandEl.value || !modelEl.value.trim()) {
        // showNotification('Please select brand and enter model', 'warning');
        return;
    }

    const cableData = {
        scope: 'pv_board_to_injection',
        scope_text: 'PV Board to Point of Injection',
        brand_id: brandEl.value,
        brand_name: brandEl.options[brandEl.selectedIndex]?.text || '',
        model_name: modelEl.value.trim(),
        size: (sizeEl?.value || '').trim(),
        insulation: (insEl?.value || '').trim()
    };

    // Check if we're editing an existing cable
    if (editingProtectionCableIndex >= 0) {
        // Update existing cable
        protectionCablesList[editingProtectionCableIndex] = cableData;
        editingProtectionCableIndex = -1;
        showNotification('Cable updated successfully', 'success');
    } else {
        // Add new cable
        protectionCablesList.push(cableData);
        showNotification('Cable added successfully', 'success');
    }

    updateProtectionCablesTable();
    updateProtectionCablesHidden();

    // Trigger autosave so SQL draft reflects the change immediately
    if (typeof window.triggerAutosave === 'function') {
        window.triggerAutosave();
    } else if (typeof saveFormDataToSQL === 'function') {
        setTimeout(() => saveFormDataToSQL(), 150);
    }

    // Reset form
    if (brandEl) brandEl.value = '';
    if (modelEl) modelEl.value = '';
    if (sizeEl) sizeEl.value = '';
    if (insEl) insEl.value = '';

    // Reset button to add mode
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-plus me-1"></i> Add Cable';
        addBtn.classList.remove('btn-warning');
        addBtn.classList.add('btn-success');
    }
}

function updateProtectionCablesTable() {
    const tbody = document.getElementById('protection-cables-tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (protectionCablesList.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="5" class="text-center text-muted"><em>No cables added</em></td>';
        tbody.appendChild(row);
        return;
    }

    protectionCablesList.forEach((cable, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${cable.brand_name}</td>
            <td>${cable.model_name}</td>
            <td>${formatCableSize(cable.size)}</td>
            <td>${cable.insulation || '-'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-primary me-1" data-index="${index}" data-action="edit-cable"><i class="fas fa-edit"></i></button>
                <button type="button" class="btn btn-sm btn-danger" data-index="${index}" data-action="delete-cable"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(row);
    });

    // Add event listeners
    tbody.querySelectorAll('button[data-action="edit-cable"]').forEach(btn => {
        btn.addEventListener('click', () => editProtectionCable(parseInt(btn.getAttribute('data-index'))));
    });
    tbody.querySelectorAll('button[data-action="delete-cable"]').forEach(btn => {
        btn.addEventListener('click', () => deleteProtectionCable(parseInt(btn.getAttribute('data-index'))));
    });

    // In edit mode we persist via SQL only; optionally use localStorage only outside edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        // Save to database draft instead of localStorage
        fetch((window.BASE_URL || '') + 'ajax/save_protection_cables_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                protection_cables: protectionCablesList
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Protection cables data saved to database draft');
                } else {
                    console.warn('Could not save protection cables data to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving protection cables data to database draft:', error);
            });
    }
}

function editProtectionCable(index) {
    const cable = protectionCablesList[index];
    if (!cable) return;

    // Set form values
    document.getElementById('protection_cable_brand').value = cable.brand_id;
    document.getElementById('protection_cable_model').value = cable.model_name;
    document.getElementById('protection_cable_size').value = cable.size || '';
    document.getElementById('protection_cable_insulation').value = cable.insulation || '';

    // Set editing index
    editingProtectionCableIndex = index;

    // Change button text to indicate editing
    const addBtn = document.getElementById('add-protection-cable-btn');
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-save me-1"></i> Update Cable';
        addBtn.classList.remove('btn-success');
        addBtn.classList.add('btn-warning');
    }
}

function deleteProtectionCable(index) {
    if (index >= 0 && index < protectionCablesList.length) {
        protectionCablesList.splice(index, 1);
        updateProtectionCablesTable();
        updateProtectionCablesHidden();
        showNotification('Cable deleted successfully', 'info');
        if (typeof saveFormDataToSQL === 'function') {
            // Use sync to defend against quick refreshes
            try { saveFormDataToSQL(true); } catch (e) { saveFormDataToSQL(); }
        }
    }
}

function addProtectionFromForm() {
    const scopeEl = document.getElementById('protection_scope');
    const brandEl = document.getElementById('protection_circuit_brand');
    const modelEl = document.getElementById('protection_circuit_model');
    const qtyEl = document.getElementById('protection_circuit_qty');
    const ratedEl = document.getElementById('protection_circuit_rated_current');

    console.log('[PROTECTION] ===== addProtectionFromForm CALLED =====');
    console.log('[PROTECTION] scopeEl:', scopeEl?.value);
    console.log('[PROTECTION] brandEl:', brandEl?.value);
    console.log('[PROTECTION] modelEl:', modelEl?.value);
    console.log('[PROTECTION] ratedEl:', ratedEl);
    console.log('[PROTECTION] ratedEl.value (RAW):', ratedEl?.value);
    console.log('[PROTECTION] ratedEl.value TYPE:', typeof ratedEl?.value);
    console.log('[PROTECTION] ratedEl.value LENGTH:', ratedEl?.value?.length);
    console.log('[PROTECTION] Boolean (ratedEl && ratedEl.value):', ratedEl && ratedEl.value);
    console.log('[PROTECTION] Editing index:', window.editingProtectionIndex);

    if (!scopeEl || !brandEl || !modelEl) {
        console.log('[PROTECTION] ❌ Missing required elements');
        return;
    }

    if (!brandEl.value || !modelEl.value) {
        console.log('[PROTECTION] ❌ Brand or Model empty');
        showNotification('Please select brand and model', 'warning');
        return;
    }

    // DETAILED CHECK FOR RATED CURRENT
    let ratedValue = null;
    if (ratedEl) {
        console.log('[PROTECTION] ratedEl EXISTS');
        if (ratedEl.value) {
            console.log('[PROTECTION] ratedEl.value is TRUTHY:', ratedEl.value);
            ratedValue = parseFloat(ratedEl.value);
            console.log('[PROTECTION] parseFloat result:', ratedValue);
            console.log('[PROTECTION] typeof ratedValue:', typeof ratedValue);
            console.log('[PROTECTION] isNaN(ratedValue):', isNaN(ratedValue));
        } else {
            console.log('[PROTECTION] ratedEl.value is FALSY (empty string or null)');
            ratedValue = null;
        }
    } else {
        console.log('[PROTECTION] ratedEl DOES NOT EXIST!');
        ratedValue = null;
    }

    const data = {
        scope: scopeEl.value,
        scope_text: scopeEl.options[scopeEl.selectedIndex]?.text || '',
        brand_id: brandEl.value,
        brand_name: brandEl.options[brandEl.selectedIndex]?.text || '',
        model_id: modelEl.value,
        model_name: modelEl.options[modelEl.selectedIndex]?.text || '',
        quantity: qtyEl && qtyEl.value ? parseInt(qtyEl.value || '1', 10) : 1,
        rated_current: ratedValue
    };

    console.log('[PROTECTION] ✅ FINAL DATA OBJECT:');
    console.log('[PROTECTION]   scope:', data.scope);
    console.log('[PROTECTION]   scope_text:', data.scope_text);
    console.log('[PROTECTION]   brand_id:', data.brand_id);
    console.log('[PROTECTION]   brand_name:', data.brand_name);
    console.log('[PROTECTION]   model_id:', data.model_id);
    console.log('[PROTECTION]   model_name:', data.model_name);
    console.log('[PROTECTION]   quantity:', data.quantity);
    console.log('[PROTECTION]   rated_current (FINAL):', data.rated_current, 'TYPE:', typeof data.rated_current);

    // Check if we're editing an existing item
    if (window.editingProtectionIndex !== undefined) {
        console.log('[PROTECTION] ✏️ UPDATE MODE - replacing item at index:', window.editingProtectionIndex);
        protectionList[window.editingProtectionIndex] = data;
        window.editingProtectionIndex = undefined;

        // Reset button back to "Add"
        const addBtn = document.getElementById('add-protection-row-btn');
        if (addBtn) {
            addBtn.textContent = '✚ Add';
            addBtn.classList.remove('btn-warning');
            addBtn.classList.add('btn-primary');
        }

        // Remove highlight from form
        const formCard = document.querySelector('.card:has(#protection_scope)');
        if (formCard) {
            formCard.style.backgroundColor = '';
            formCard.style.borderLeft = '';
        }

        showNotification('Protection item updated!', 'success');
    } else {
        console.log('[PROTECTION] ➕ ADD MODE - adding new item');
        protectionList.push(data);
        showNotification('Protection item added!', 'success');
    }

    console.log('[PROTECTION] protectionList length:', protectionList.length);
    console.log('[PROTECTION] protectionList:', protectionList);

    // Clear form fields after adding/updating
    scopeEl.value = 'injection';
    brandEl.value = '';
    modelEl.innerHTML = '<option value="">Select Model...</option>';
    if (qtyEl) qtyEl.value = '1';
    if (ratedEl) ratedEl.value = '';

    updateProtectionTable();
    const hidden = document.getElementById('protection_data');
    if (hidden) {
        hidden.value = JSON.stringify(protectionList);
        console.log('[PROTECTION] Hidden field updated with:', hidden.value);
        console.log('[PROTECTION] Hidden field length:', hidden.value.length);
        console.log('[PROTECTION] 🔍 Hidden field protectionList[0].rated_current:', protectionList[0]?.rated_current);
    }

    // Trigger autosave to persist the new protection item immediately
    console.log('[PROTECTION] About to trigger autosave...');
    if (typeof saveFormDataToSQL === 'function') {
        console.log('[PROTECTION] ✅ Triggering autosave after Add...');
        setTimeout(() => {
            saveFormDataToSQL();
        }, 100); // Small delay to ensure everything is ready
    } else {
        console.error('[PROTECTION] ❌ saveFormDataToSQL is NOT available!');
    }

    // reset minimal fields
    if (qtyEl) qtyEl.value = '1';
    if (ratedEl) {
        console.log('[PROTECTION] Clearing ratedEl (was:', ratedEl.value, ')');
        ratedEl.value = '';
        console.log('[PROTECTION] ratedEl after clear:', ratedEl.value);
    }

    console.log('[PROTECTION] ===== addProtectionFromForm COMPLETED =====');
}

function editProtection(index) {
    const item = protectionList[index];
    if (!item) return;

    console.log('[Protection Edit] Editing item at index:', index);
    console.log('[Protection Edit] Item data:', JSON.stringify(item));
    console.log('[Protection Edit] item.brand_name:', item.brand_name);
    console.log('[Protection Edit] item.model_name:', item.model_name);

    const scopeEl = document.getElementById('protection_scope');
    const brandEl = document.getElementById('protection_circuit_brand');
    const modelEl = document.getElementById('protection_circuit_model');
    const ratedEl = document.getElementById('protection_circuit_rated_current');

    // Set scope
    if (scopeEl) {
        scopeEl.value = item.scope || 'injection';
        console.log('[Protection Edit] Scope set to:', scopeEl.value);
    }

    // Set rated current immediately
    if (ratedEl) {
        ratedEl.value = item.rated_current ?? '';
        console.log('[Protection Edit] Rated current set to:', ratedEl.value);
    }

    // Set brand by finding the option with matching brand_name
    if (brandEl && item.brand_name) {
        console.log('[Protection Edit] Looking for brand_name:', item.brand_name);

        // Check if brands are already loaded
        const hasOptions = brandEl.options.length > 1;
        console.log('[Protection Edit] Brand dropdown already has options:', hasOptions, '(' + brandEl.options.length + ' total)');
        console.log('[Protection Edit] Available options:', Array.from(brandEl.options).map(o => ({ value: o.value, text: o.text })));

        // If no options yet, reload them
        if (!hasOptions) {
            console.log('[Protection Edit] Reloading brands...');
            loadEquipmentBrands('circuit_breaker', 'protection_circuit_brand').then(() => {
                console.log('[Protection Edit] Brands reloaded');
                // Try to set brand now
                setTimeout(() => selectBrandAndLoadModels(item, brandEl, modelEl), 200);
            });
        } else {
            // Brands are already loaded, proceed with selection
            selectBrandAndLoadModels(item, brandEl, modelEl);
        }
    } else {
        console.warn('[Protection Edit] Brand element missing or no brand_name:', { brandEl: !!brandEl, brand_name: item.brand_name });
    }

    // Store the index so we know we're editing
    window.editingProtectionIndex = index;

    // Change button text to "Update" and change color
    const addBtn = document.getElementById('add-protection-row-btn');
    if (addBtn) {
        addBtn.textContent = '✎ Update';
        addBtn.classList.remove('btn-primary');
        addBtn.classList.add('btn-warning');
    }

    // Highlight the form section to show it's in edit mode
    const formCard = document.querySelector('.card:has(#protection_scope)');
    if (formCard) {
        formCard.style.backgroundColor = '#fffbf0';
        formCard.style.borderLeft = '4px solid #ffc107';
    }

    // Scroll to the form so user sees it
    formCard?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Helper function to select brand and load models
function selectBrandAndLoadModels(item, brandEl, modelEl) {
    console.log('[Brand Selection] Starting brand selection for:', item.brand_name);

    // Find the option that matches the brand_name
    let foundOption = null;
    for (let option of brandEl.options) {
        console.log('[Brand Selection] Comparing option text "' + option.text + '" with "' + item.brand_name + '"');
        if (option.text === item.brand_name) {
            foundOption = option;
            break;
        }
    }

    if (foundOption) {
        brandEl.value = foundOption.value;
        console.log('[Brand Selection] ✅ Brand selected: value=' + brandEl.value + ', text=' + foundOption.text);

        // Now trigger the change event to load models
        const event = new Event('change', { bubbles: true });
        brandEl.dispatchEvent(event);
        console.log('[Brand Selection] Change event dispatched to load models');

        // After models load, select the matching model
        setTimeout(() => selectMatchingModel(item, modelEl), 500);
    } else {
        console.warn('[Brand Selection] ⚠️ Brand not found in options. Looking for:', item.brand_name);
        console.warn('[Brand Selection] Available brands:', Array.from(brandEl.options).map(o => o.text));
    }
}

// Helper function to select the matching model
function selectMatchingModel(item, modelEl) {
    if (!modelEl || !item.model_name) return;

    console.log('[Model Selection] Looking for model_name:', item.model_name);
    console.log('[Model Selection] Available models:', Array.from(modelEl.options).map(o => ({ value: o.value, text: o.text })));

    // Find the option that matches the model_name
    let foundModel = null;
    for (let option of modelEl.options) {
        console.log('[Model Selection] Comparing option text "' + option.text + '" with "' + item.model_name + '"');
        if (option.text === item.model_name) {
            foundModel = option;
            break;
        }
    }

    if (foundModel) {
        modelEl.value = foundModel.value;
        console.log('[Model Selection] ✅ Model selected: value=' + modelEl.value + ', text=' + foundModel.text);
    } else {
        console.warn('[Model Selection] ⚠️ Model not found in options. Looking for:', item.model_name);
        console.warn('[Model Selection] Available models:', Array.from(modelEl.options).map(o => o.text));
    }
} function deleteProtection(index) {
    protectionList.splice(index, 1);
    updateProtectionTable();
    const hidden = document.getElementById('protection_data');
    if (hidden) hidden.value = JSON.stringify(protectionList);

    // Trigger autosave to persist the deletion immediately
    if (typeof saveFormDataToSQL === 'function') {
        console.log('[PROTECTION] ✅ Triggering autosave after Delete (sync)...');
        // Use synchronous save to ensure it's persisted before a quick refresh (Ctrl+R)
        try {
            saveFormDataToSQL(true);
        } catch (e) {
            console.warn('[PROTECTION] ⚠️ Sync save failed, falling back to async', e);
            saveFormDataToSQL();
        }
    }
}

function updateProtectionCableFromFields() {
    const brandEl = document.getElementById('protection_cable_brand');
    const modelEl = document.getElementById('protection_cable_model');
    const sizeEl = document.getElementById('protection_cable_size');
    const insEl = document.getElementById('protection_cable_insulation');
    if (!brandEl || !modelEl) return;
    if (!brandEl.value || !modelEl.value.trim()) {
        protectionCable = null;
        updateProtectionCableHidden();
        return;
    }
    protectionCable = {
        scope: 'pv_board_to_injection',
        scope_text: 'PV Board to Point of Injection',
        brand_id: brandEl.value,
        brand_name: brandEl.options[brandEl.selectedIndex]?.text || '',
        model_name: modelEl.value.trim(),
        size: (sizeEl?.value || '').trim(),
        insulation: (insEl?.value || '').trim()
    };
    updateProtectionCableHidden();
}

// =====================
// Clamp Measurements Functions
// =====================

function readClampMeasurementForm() {
    const equipment = document.getElementById('clamp_equipment');
    const l1 = document.getElementById('clamp_l1');
    const l2 = document.getElementById('clamp_l2');
    const l3 = document.getElementById('clamp_l3');
    const matchMeter = document.getElementById('clamp_match_meter');

    const measurement = {
        equipment: equipment?.value || '',
        l1_current: l1?.value?.trim() || '',
        l2_current: l2?.value?.trim() || '',
        l3_current: l3?.value?.trim() || '',
        match_with_meter: matchMeter?.value || 'no'
    };

    console.log('readClampMeasurementForm result:', measurement);
    return measurement;
}

function addClampMeasurementFromForm() {
    console.log('addClampMeasurementFromForm called');
    const measurement = readClampMeasurementForm();
    console.log('Measurement data:', measurement);
    // Basic validation: require equipment type
    if (!measurement.equipment) {
        console.log('Validation failed: no equipment selected');
        return customAlert('Please select Equipment type before adding.');
    }
    console.log('Validation passed, adding measurement to array');
    clampMeasurements.push(measurement);
    console.log('Current clampMeasurements array:', clampMeasurements);
    renderClampMeasurementsTable();
    updateClampMeasurementsHidden();

    // Trigger autosave so SQL draft reflects the change immediately
    if (typeof saveFormDataToSQL === 'function') {
        setTimeout(() => saveFormDataToSQL(), 150);
    }

    // Clear form fields after successful addition
    clearClampMeasurementForm();
}

function clearClampMeasurementForm() {
    document.getElementById('clamp_equipment').value = '';
    document.getElementById('clamp_l1').value = '';
    document.getElementById('clamp_l2').value = '';
    document.getElementById('clamp_l3').value = '';
    document.getElementById('clamp_match_meter').value = 'no';
}

// Global function to sync window.clampMeasurements to local variable (called by autosave_sql.js)
window.loadExistingClampMeasurements = function () {
    console.log('[loadExistingClampMeasurements] Called - window.clampMeasurements:', window.clampMeasurements);

    if (!window.clampMeasurements || !Array.isArray(window.clampMeasurements)) {
        console.log('[loadExistingClampMeasurements] No clamp measurements to load');
        return;
    }

    console.log('[loadExistingClampMeasurements] Syncing', window.clampMeasurements.length, 'measurements');

    // Sync window.clampMeasurements to local clampMeasurements variable
    clampMeasurements = window.clampMeasurements;

    console.log('[loadExistingClampMeasurements] ✅ Clamp measurements synced successfully');
};

function renderClampMeasurementsTable() {
    console.log('renderClampMeasurementsTable called');
    const tbody = document.getElementById('clamp-measurements-tbody');
    console.log('tbody element:', tbody);
    if (!tbody) {
        console.log('tbody not found!');
        return;
    }
    tbody.innerHTML = '';
    if (!clampMeasurements.length) {
        console.log('No measurements in array, showing empty message');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="6" class="text-center text-muted"><em>No measurements added</em></td>';
        tbody.appendChild(tr);
        return;
    }
    console.log('Rendering', clampMeasurements.length, 'measurements');
    clampMeasurements.forEach((measurement, idx) => {
        console.log('Rendering measurement', idx, ':', measurement);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${measurement.equipment || '-'}</td>
            <td>${measurement.l1_current || '-'}</td>
            <td>${measurement.l2_current || '-'}</td>
            <td>${measurement.l3_current || '-'}</td>
            <td>${measurement.match_with_meter === 'yes' ? 'Yes' : 'No'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" data-action="del" data-idx="${idx}"><i class="fas fa-trash"></i></button>
            </td>`;
        tbody.appendChild(tr);
    });
    console.log('Attaching delete event listeners');
    tbody.querySelectorAll('button[data-action="del"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            console.log('Delete button clicked, index:', e.currentTarget.getAttribute('data-idx'));
            const i = parseInt(e.currentTarget.getAttribute('data-idx'));
            clampMeasurements.splice(i, 1);
            renderClampMeasurementsTable();
            updateClampMeasurementsHidden();
            if (typeof saveFormDataToSQL === 'function') {
                try { saveFormDataToSQL(true); } catch (err) { saveFormDataToSQL(); }
            }
        });
    });
}

// Make function globally accessible for autosave
window.renderClampMeasurementsTable = renderClampMeasurementsTable;

// Function to update clamp measurements hidden field
function updateClampMeasurementsHidden() {
    const hidden = document.getElementById('clamp_measurements_data');
    if (!hidden) return;
    hidden.value = JSON.stringify(clampMeasurements);

    // Save to database draft instead of localStorage
    fetch((window.BASE_URL || '') + 'ajax/save_clamp_measurements_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            clamp_measurements: clampMeasurements
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Clamp measurements draft saved to SQL:', data.count, 'measurements');
            } else {
                console.warn('⚠️ Failed to save clamp measurements draft:', data.error);
            }
        })
        .catch(error => {
            console.error('❌ Error saving clamp measurements draft to SQL:', error);
        });
}

// ===== PUNCH LIST MANAGEMENT =====

let editingPunchListIndex = -1; // -1 means not editing, otherwise index of item being edited
let editingProtectionIndex = undefined; // undefined means not editing, otherwise index of protection item being edited
let editingProtectionCableIndex = -1; // -1 means not editing, otherwise index of cable being edited

function readPunchListForm() {
    const id = document.getElementById('punch_id');
    const severity = document.getElementById('punch_severity');
    const description = document.getElementById('punch_description');
    const openingDate = document.getElementById('punch_opening_date');
    const responsible = document.getElementById('punch_responsible');
    const resolutionDate = document.getElementById('punch_resolution_date');

    return {
        id: id?.value?.trim() || '',
        severity: severity?.value || '',
        description: description?.value?.trim() || '',
        opening_date: openingDate?.value || '',
        responsible: responsible?.value || '',
        resolution_date: resolutionDate?.value || ''
    };
}

// Generate a new unique Punch List ID (e.g. PL001) based on existing items
function generatePunchId() {
    const existing = [];
    try {
        (punchListItems || []).forEach(i => { if (i && i.id) existing.push(String(i.id)); });
    } catch (e) { }
    try {
        if (Array.isArray(window.existingPunchList)) window.existingPunchList.forEach(i => { if (i && i.id) existing.push(String(i.id)); });
    } catch (e) { }

    let max = 0;
    existing.forEach(id => {
        const m = id.match(/PL\s*0*(\d+)$/i) || id.match(/(\d+)$/);
        if (m) {
            const n = parseInt(m[1], 10);
            if (!isNaN(n) && n > max) max = n;
        }
    });
    const next = max + 1;
    return 'PL' + String(next).padStart(3, '0');
}
function addPunchListFromForm() {
    const item = readPunchListForm();
    // ensure unique uid for item
    function genUid() { return 'puid-' + Date.now().toString(36) + '-' + Math.floor(Math.random() * 100000).toString(36); }
    if (editingPunchListIndex >= 0) {
        // keep existing uid
        if (punchListItems[editingPunchListIndex] && punchListItems[editingPunchListIndex].__uid) {
            item.__uid = punchListItems[editingPunchListIndex].__uid;
        } else if (!item.__uid) item.__uid = genUid();
    } else {
        if (!item.__uid) item.__uid = genUid();
    }
    // If no ID provided, auto-generate one. If provided, ensure no duplicate when adding new.
    if (!item.id) {
        item.id = generatePunchId();
    } else if (editingPunchListIndex === -1 && punchListItems.some(p => p.id === item.id)) {
        return customAlert('The provided ID already exists. Please choose a different ID or leave empty to auto-generate.');
    }
    if (!item.description) {
        return customAlert('Please enter a description for the punch list item.');
    }
    if (!item.severity) {
        return customAlert('Please select a severity level.');
    }

    if (editingPunchListIndex >= 0) {
        // Prevent duplicate ID when editing (exclude current index)
        const duplicate = punchListItems.some((p, idx) => idx !== editingPunchListIndex && p.id === item.id);
        if (duplicate) return customAlert('The provided ID already exists. Please choose a different ID.');

        // Update existing item (preserve uid)
        if (punchListItems[editingPunchListIndex] && punchListItems[editingPunchListIndex].__uid) {
            item.__uid = punchListItems[editingPunchListIndex].__uid;
        }
        punchListItems[editingPunchListIndex] = item;
        editingPunchListIndex = -1;
        updatePunchListButton();
    } else {
        // Add new item
        if (!item.__uid) item.__uid = 'puid-' + Date.now().toString(36) + '-' + Math.floor(Math.random() * 100000).toString(36);
        punchListItems.push(item);
    }

    renderPunchListTable();
    // Persist to hidden
    const hidden = document.getElementById('punch_list_data');
    if (hidden) {
        hidden.value = JSON.stringify(punchListItems);
        if (typeof window.triggerAutosave === 'function') window.triggerAutosave();
    }
    // Save to database draft if not in edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        fetch((window.BASE_URL || '') + 'ajax/save_punch_list_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                punch_list: punchListItems
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Punch list saved to database draft');
                } else {
                    console.warn('Could not save punch list to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving punch list to database draft:', error);
            });
    }

    // Clear form
    clearPunchListForm();
}

function clearPunchListForm() {
    document.getElementById('punch_id').value = '';
    document.getElementById('punch_severity').value = '';
    document.getElementById('punch_description').value = '';
    document.getElementById('punch_opening_date').value = '';
    document.getElementById('punch_responsible').value = '';
    document.getElementById('punch_resolution_date').value = '';

    // Reset editing state
    editingPunchListIndex = -1;
    updatePunchListButton();
}

function editPunchListItem(index) {
    const item = punchListItems[index];
    if (!item) {
        console.warn('[Punch List] editPunchListItem: item not found at index', index);
        return;
    }

    // Fill form with item data
    document.getElementById('punch_id').value = item.id || '';
    document.getElementById('punch_severity').value = item.severity || '';
    document.getElementById('punch_description').value = item.description || '';
    document.getElementById('punch_opening_date').value = item.opening_date || '';
    document.getElementById('punch_responsible').value = item.responsible || '';
    document.getElementById('punch_resolution_date').value = item.resolution_date || '';

    // Set editing state
    editingPunchListIndex = index;
    updatePunchListButton();

    // Smoothly scroll to the top of the page so the user sees the Punch List form clearly
    try {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        console.log('[Punch List] Scrolling to top for editing item', item.id || '');
    } catch (e) {
        // Fallback for older browsers
        window.scrollTo(0, 0);
    }
}

function updatePunchListButton() {
    const button = document.getElementById('add-punch-list-btn');
    const cancelButton = document.getElementById('cancel-punch-list-btn');

    if (!button) return;

    if (editingPunchListIndex >= 0) {
        button.innerHTML = '<i class="fas fa-save"></i> Update Item';
        button.className = 'btn btn-sm btn-warning';
        if (cancelButton) {
            cancelButton.style.display = 'inline-block';
        }
    } else {
        button.innerHTML = '<i class="fas fa-plus"></i> Add Item';
        button.className = 'btn btn-sm btn-success';
        if (cancelButton) {
            cancelButton.style.display = 'none';
        }
    }
}

function ensurePunchUids(arr) {
    if (!Array.isArray(arr)) return;
    arr.forEach(i => {
        if (!i) return;
        if (!i.__uid) i.__uid = 'puid-' + Date.now().toString(36) + '-' + Math.floor(Math.random() * 100000).toString(36);
    });
}

function renderPunchListTable() {
    const tbody = document.getElementById('punch-list-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    // Apply 'Open only' filter if enabled
    const openOnlyEl = document.getElementById('punch_filter_open');
    let itemsToRender = Array.isArray(punchListItems) ? punchListItems.slice() : [];
    if (openOnlyEl && openOnlyEl.checked) {
        itemsToRender = itemsToRender.filter(i => !i.resolution_date || String(i.resolution_date).trim() === '');
    }

    if (!itemsToRender.length) {
        const tr = document.createElement('tr');
        const msg = (openOnlyEl && openOnlyEl.checked) ? 'No open items' : 'No items added';
        tr.innerHTML = `<td colspan="7" class="text-center text-muted"><em>${msg}</em></td>`;
        tbody.appendChild(tr);
        return;
    }

    itemsToRender.forEach((item, idx) => {
        const tr = document.createElement('tr');
        const severityClass = getSeverityClass(item.severity);
        // find original index by uid for robust mapping
        const origIdx = punchListItems.findIndex(pi => pi && item && pi.__uid && item.__uid && pi.__uid === item.__uid);
        tr.setAttribute('data-uid', item.__uid || '');
        tr.innerHTML = `
            <td>${item.id || '-'}</td>
            <td><span class="badge ${severityClass}">${item.severity || '-'}</span></td>
            <td>${item.description || '-'}</td>
            <td>${item.opening_date ? formatDate(item.opening_date) : '-'}</td>
            <td>${item.responsible || '-'}</td>
            <td>${item.resolution_date ? formatDate(item.resolution_date) : '-'}</td>
            <td class="text-center">
                <div class="d-inline-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-primary punch-action-btn" data-action="edit" data-idx="${origIdx}" data-uid="${item.__uid || ''}" title="Edit item" aria-label="Edit item"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-danger punch-action-btn" data-action="del" data-idx="${origIdx}" data-uid="${item.__uid || ''}" title="Delete item" aria-label="Delete item"><i class="fas fa-trash"></i></button>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });

    tbody.querySelectorAll('button[data-action="del"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const origIdx = parseInt(e.currentTarget.getAttribute('data-idx'));
            if (isNaN(origIdx) || origIdx < 0) return;
            punchListItems.splice(origIdx, 1);
            renderPunchListTable();
            const hidden = document.getElementById('punch_list_data');
            if (hidden) {
                hidden.value = JSON.stringify(punchListItems);
                if (typeof window.triggerAutosave === 'function') window.triggerAutosave();
            }
            // Save to database draft if not in edit mode
            if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
                fetch((window.BASE_URL || '') + 'ajax/save_punch_list_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        punch_list: punchListItems
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('[JS] Punch list updated in database draft after deletion');
                        } else {
                            console.warn('Could not update punch list in database draft:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating punch list in database draft:', error);
                    });
            }
        });
    });

    tbody.querySelectorAll('button[data-action="edit"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const origIdx = parseInt(e.currentTarget.getAttribute('data-idx'));
            if (isNaN(origIdx) || origIdx < 0) return;
            editPunchListItem(origIdx);
        });
    });
}

function getSeverityClass(severity) {
    switch ((severity || '').toLowerCase()) {
        case 'low':
        case 'minor':
            return 'bg-secondary';
        case 'medium':
        case 'major':
            return 'bg-warning text-dark';
        case 'high':
        case 'critical':
        case 'severe':
            return 'bg-danger';
        default:
            return 'bg-light text-dark';
    }
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB'); // DD/MM/YYYY format
}

// ===== ENERGY METERS MANAGEMENT =====
let energyMetersList = [];

// Global function to load existing energy meters (called by autosave_sql.js)
window.loadExistingEnergyMeters = function () {
    console.log('[loadExistingEnergyMeters] Called - window.existingEnergyMeters:', window.existingEnergyMeters);

    if (!window.existingEnergyMeters || !Array.isArray(window.existingEnergyMeters)) {
        console.log('[loadExistingEnergyMeters] No energy meters to load');
        return;
    }

    console.log('[loadExistingEnergyMeters] Syncing', window.existingEnergyMeters.length, 'energy meters');
    energyMetersList = window.existingEnergyMeters;

    const hidden = document.getElementById('energy_meter_data');
    if (hidden) hidden.value = JSON.stringify(energyMetersList);

    // Render the table
    if (typeof updateEnergyMetersTable === 'function') {
        updateEnergyMetersTable();
    }

    console.log('[loadExistingEnergyMeters] ✅ Energy meters synced successfully');
};

// Initialize Energy Meters on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
    // Load energy meter brands - moved to initInvertersTable()

    const energyMeterBrandEl = document.getElementById('energy_meter_brand');
    if (energyMeterBrandEl) {
        energyMeterBrandEl.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                loadEquipmentModels('energy_meter', brandId, 'energy_meter_model');
            } else {
                const modelEl = document.getElementById('energy_meter_model');
                if (modelEl) modelEl.innerHTML = '<option value="">Select Model...</option>';
            }
            // Enable/disable add model button based on brand selection
            const addModelBtn = document.getElementById('add-energy-meter-model-btn');
            if (addModelBtn) {
                addModelBtn.disabled = !brandId;
            }
        });
    }

    // Add energy meter button
    const addEnergyMeterBtn = document.getElementById('add-energy-meter-btn');
    if (addEnergyMeterBtn) {
        addEnergyMeterBtn.addEventListener('click', addEnergyMeterFromForm);
    }

    // Add brand/model creation buttons
    const addEnergyMeterBrandBtn = document.getElementById('add-energy-meter-brand-btn');
    if (addEnergyMeterBrandBtn) addEnergyMeterBrandBtn.addEventListener('click', () => showAddBrandModal('energy_meter'));
    const addEnergyMeterModelBtn = document.getElementById('add-energy-meter-model-btn');
    if (addEnergyMeterModelBtn) {
        addEnergyMeterModelBtn.addEventListener('click', () => showAddModelModal('energy_meter'));
        // Initially disable if no brand is selected
        const brandEl = document.getElementById('energy_meter_brand');
        if (brandEl && !brandEl.value) {
            addEnergyMeterModelBtn.disabled = true;
        }
    }

    // Load existing energy meters: prefer SQL data in edit mode, then database draft, then hidden input
    try {
        const hidden = document.getElementById('energy_meter_data');
        let loaded = false;
        // Ensure 'stored' is defined in outer scope to avoid ReferenceError
        let stored = null;

        // In edit mode, prioritize window.existingEnergyMeters from SQL
        if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            fetch((window.BASE_URL || '') + 'ajax/load_energy_meters_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.energy_meters) && data.energy_meters.length > 0) {
                        energyMetersList = data.energy_meters;
                        updateEnergyMetersTable();
                        if (hidden) hidden.value = JSON.stringify(energyMetersList);
                        loaded = true;
                        console.log('[JS] Loaded energy meters from database draft');
                    }
                })
                .catch(err => {
                    console.warn('Could not load energy meters from database draft', err);
                });
        }

        if (!loaded && window.existingEnergyMeters && Array.isArray(window.existingEnergyMeters) && window.existingEnergyMeters.length > 0) {
            energyMetersList = window.existingEnergyMeters;
            updateEnergyMetersTable();
            if (hidden) hidden.value = JSON.stringify(energyMetersList);
            loaded = true;
        }

        if (!loaded && hidden && hidden.value) {
            const parsed = JSON.parse(hidden.value);
            if (Array.isArray(parsed) && parsed.length > 0) {
                energyMetersList = parsed;
                updateEnergyMetersTable();
                loaded = true;
            }
        }

        if (!loaded && stored) {
            try {
                const maybe = JSON.parse(stored);
                if (Array.isArray(maybe) && hidden) hidden.value = JSON.stringify(maybe);
            } catch (err) { }
        }
    } catch (e) { console.warn('Could not parse energy meters data from hidden input/localStorage', e); }
});

function addEnergyMeterFromForm() {
    const scopeEl = document.getElementById('energy_meter_scope');
    const brandEl = document.getElementById('energy_meter_brand');
    const modelEl = document.getElementById('energy_meter_model');
    const rs485AddressEl = document.getElementById('energy_meter_rs485_address');
    const ctRatioEl = document.getElementById('energy_meter_ct_ratio');

    if (!scopeEl || !brandEl || !modelEl || !rs485AddressEl || !ctRatioEl) {
        customAlert('Energy meter form elements not found', 'error');
        return;
    }

    const scope = scopeEl.value;
    const brandId = brandEl.value;
    const modelId = modelEl.value;
    const rs485Address = rs485AddressEl.value.trim();
    const ctRatio = ctRatioEl.value.trim();

    if (!scope || !brandId || !modelId || !ctRatio) {
        customAlert('Please fill in all required fields', 'warning');
        return;
    }

    // Check for duplicate RS485 address (only if not empty)
    if (rs485Address) {
        const existingMeter = energyMetersList.find(meter => meter.rs485_address === rs485Address);
        if (existingMeter) {
            customAlert('RS485 address already exists', 'warning');
            return;
        }
    }

    const brandName = brandEl.options[brandEl.selectedIndex]?.text || '';
    const modelName = modelEl.options[modelEl.selectedIndex]?.text || '';

    const newMeter = {
        scope: scope,
        scope_text: scope === 'grid_meter' ? 'Grid Meter' : 'PV Meter',
        brand_id: brandId,
        brand_name: brandName,
        model_id: modelId,
        model_name: modelName,
        rs485_address: rs485Address,
        ct_ratio: ctRatio
    };

    energyMetersList.push(newMeter);
    updateEnergyMetersTable();
    updateEnergyMetersHidden();

    // Clear form
    scopeEl.value = '';
    brandEl.value = '';
    const modelSelect = document.getElementById('energy_meter_model');
    if (modelSelect) modelSelect.innerHTML = '<option value="">Select Model...</option>';
    rs485AddressEl.value = '';
    ctRatioEl.value = '';

    customAlert('Energy meter added successfully', 'success');
}

function updateEnergyMetersTable() {
    const tbody = document.getElementById('energy-meters-tbody');
    if (!tbody) return;

    if (energyMetersList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><em>No energy meters added</em></td></tr>';
        return;
    }

    tbody.innerHTML = energyMetersList.map((meter, index) => `
        <tr>
            <td>${meter.scope_text}</td>
            <td>${meter.brand_name}</td>
            <td>${meter.model_name}</td>
            <td>${meter.rs485_address}</td>
            <td>${meter.ct_ratio}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteEnergyMeter(${index})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function deleteEnergyMeter(index) {
    if (index >= 0 && index < energyMetersList.length) {
        energyMetersList.splice(index, 1);
        updateEnergyMetersTable();
        updateEnergyMetersHidden();
        customAlert('Energy meter deleted', 'info');
    }
}

function updateEnergyMetersHidden() {
    const hidden = document.getElementById('energy_meter_data');
    if (hidden) {
        hidden.value = JSON.stringify(energyMetersList);
    }
    // Save to database draft if not in edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        fetch((window.BASE_URL || '') + 'ajax/save_energy_meters_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                energy_meters: energyMetersList
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Energy meters saved to database draft');
                } else {
                    console.warn('Could not save energy meters to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving energy meters to database draft:', error);
            });
    }
}

// no edit/delete handlers needed for single cable

// Initialize Protection tab controls on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
    // Load brands for protection circuit breaker select
    loadEquipmentBrands('circuit_breaker', 'protection_circuit_brand');

    const brandEl = document.getElementById('protection_circuit_brand');
    if (brandEl) {
        brandEl.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                loadEquipmentModels('circuit_breaker', brandId, 'protection_circuit_model');
            } else {
                const modelEl = document.getElementById('protection_circuit_model');
                if (modelEl) modelEl.innerHTML = '<option value="">Select Model...</option>';
            }
        });
    }

    const addBtn = document.getElementById('add-protection-row-btn');
    if (addBtn) {
        addBtn.addEventListener('click', addProtectionFromForm);
    }

    // Add Protection Cable button
    const addProtectionCableBtn = document.getElementById('add-protection-cable-btn');
    if (addProtectionCableBtn) {
        addProtectionCableBtn.addEventListener('click', addProtectionCableFromForm);
    }

    // Add brand/model creation buttons
    const addBrandBtn = document.getElementById('add-protection-cb-brand-btn');
    if (addBrandBtn) addBrandBtn.addEventListener('click', () => showAddBrandModal('circuit_breaker'));
    const addModelBtn = document.getElementById('add-protection-cb-model-btn');
    if (addModelBtn) addModelBtn.addEventListener('click', () => showAddModelModal('circuit_breaker'));

    // Cable dropdowns
    const cableBrandsPromise = loadEquipmentBrands('cable', 'protection_cable_brand');
    const cableBrandEl = document.getElementById('protection_cable_brand');
    if (cableBrandEl) {
        cableBrandEl.addEventListener('change', function () {
            updateProtectionCableFromFields();
        });
    }
    const cableModelEl = document.getElementById('protection_cable_model');
    if (cableModelEl) {
        cableModelEl.addEventListener('input', updateProtectionCableFromFields);
    }
    const cableSizeEl = document.getElementById('protection_cable_size');
    if (cableSizeEl) cableSizeEl.addEventListener('input', updateProtectionCableFromFields);
    const cableInsEl = document.getElementById('protection_cable_insulation');
    if (cableInsEl) cableInsEl.addEventListener('input', updateProtectionCableFromFields);

    const addCableBrandBtn = document.getElementById('add-protection-cable-brand-btn');
    // Removed: if (addCableBrandBtn) addCableBrandBtn.addEventListener('click', () => showAddBrandModal('cable'));

    // preload existing protection if provided by PHP
    if (window.existingProtection && Array.isArray(window.existingProtection)) {
        protectionList = window.existingProtection;
        const hidden = document.getElementById('protection_data');
        if (hidden) hidden.value = JSON.stringify(protectionList);
        // Render the table with existing protection items
        if (typeof updateProtectionTable === 'function') {
            updateProtectionTable();
        }
    } else {
        // Try to load from hidden input as fallback
        try {
            const hidden = document.getElementById('protection_data');
            if (hidden && hidden.value) {
                const parsed = JSON.parse(hidden.value);
                if (Array.isArray(parsed)) {
                    protectionList = parsed;
                    updateProtectionTable();
                }
            }
        } catch (e) {
            console.warn('Could not parse protection data from hidden input');
        }
    }
    if (window.existingProtectionCables && Array.isArray(window.existingProtectionCables) && window.existingProtectionCables.length > 0) {
        // Load existing cables into the new dynamic list
        protectionCablesList = window.existingProtectionCables.map(cable => ({
            scope: cable.scope || 'pv_board_to_injection',
            scope_text: cable.scope_text || 'PV Board to Point of Injection',
            brand_id: cable.brand_id || '',
            brand_name: cable.brand_name || cable.brand || '',
            model_name: cable.model_name || cable.model || '',
            size: cable.size || '',
            insulation: cable.insulation || ''
        }));

        updateProtectionCablesTable();
        updateProtectionCablesHidden();

        // For backward compatibility, also set the old single cable variable
        if (window.existingProtectionCables.length > 0) {
            protectionCable = window.existingProtectionCables[0];
            updateProtectionCableHidden();
        }
    } else {
        // Try to load from hidden input as fallback
        try {
            const hidden = document.getElementById('protection_cable_data');
            if (hidden && hidden.value) {
                const parsed = JSON.parse(hidden.value);
                if (Array.isArray(parsed)) {
                    protectionCablesList = parsed;
                    updateProtectionCablesTable();
                }
            }
        } catch (e) {
            console.warn('Could not parse protection cables data from hidden input');
        }
    }
    try {
        const hidden = document.getElementById('clamp_measurements_data');

        // Priority 1: in-memory data (e.g. restored by autosave_sql)
        if (Array.isArray(window.clampMeasurements) && window.clampMeasurements.length > 0) {
            // Use the in-memory model as authoritative
            clampMeasurements = window.clampMeasurements.slice();
            // Sync hidden input and UI
            if (hidden) hidden.value = JSON.stringify(clampMeasurements);
            if (typeof window.renderClampMeasurementsTable === 'function') window.renderClampMeasurementsTable();
            console.log('[JS] Loaded clamp measurements from in-memory model (window.clampMeasurements)');

            // Priority 2: server-injected data from PHP
        } else if (window.existingClampMeasurements && Array.isArray(window.existingClampMeasurements) && window.existingClampMeasurements.length > 0) {
            clampMeasurements = window.existingClampMeasurements.map(measurement => ({
                equipment: measurement.equipment || '',
                l1_current: measurement.l1 || measurement.l1_current || '',
                l2_current: measurement.l2 || measurement.l2_current || '',
                l3_current: measurement.l3 || measurement.l3_current || '',
                match_with_meter: (measurement.match_meter || measurement.match_with_meter || 'no').toString().toLowerCase() === 'yes' ? 'yes' : 'no'
            }));
            if (hidden) hidden.value = JSON.stringify(clampMeasurements);
            if (typeof window.renderClampMeasurementsTable === 'function') window.renderClampMeasurementsTable();
            console.log('[JS] Loaded clamp measurements from server (window.existingClampMeasurements)');

            // Defensive delayed reapply: ensure authoritative server data overwrites
            // any late localStorage rehydration by re-syncing hidden input and UI
            setTimeout(() => {
                try {
                    const hidden2 = document.getElementById('clamp_measurements_data');
                    if (hidden2) hidden2.value = JSON.stringify(clampMeasurements || []);
                    if (typeof window.renderClampMeasurementsTable === 'function') {
                        window.renderClampMeasurementsTable();
                        console.log('[JS] 🔁 Reapplied authoritative clamp measurements after delay');
                    }
                } catch (err) {
                    console.error('[JS] Error during delayed clamp reapply:', err);
                }
            }, 400);

        } else {
            // Priority 3: database draft (only if not in edit mode)
            let loaded = false;
            if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
                try {
                    fetch((window.BASE_URL || '') + 'ajax/load_clamp_measurements_draft.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && Array.isArray(data.clamp_measurements) && data.clamp_measurements.length > 0) {
                                clampMeasurements = data.clamp_measurements;
                                if (typeof window.renderClampMeasurementsTable === 'function') window.renderClampMeasurementsTable();
                                if (hidden) hidden.value = JSON.stringify(clampMeasurements);
                                loaded = true;
                                console.log('[JS] Loaded clamp measurements from database draft');
                            }
                        })
                        .catch(err => {
                            console.warn('Could not load clamp measurements from database draft', err);
                        });
                } catch (err) {
                    console.warn('Could not load clamp measurements from database draft', err);
                }
            }

            if (!loaded && hidden && hidden.value) {
                try {
                    const parsed = JSON.parse(hidden.value);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        clampMeasurements = parsed;
                        if (typeof window.renderClampMeasurementsTable === 'function') window.renderClampMeasurementsTable();
                        console.log('[JS] Loaded clamp measurements from hidden input');
                    }
                } catch (err) {
                    console.warn('Could not parse clamp measurements from hidden input', err);
                }
            }
        }
    } catch (e) {
        console.warn('Could not initialize clamp measurements', e);
    }
    updateProtectionTable();
    updateProtectionCablesTable();
    // Update table header based on quantity field existence
    updateProtectionTableHeader();

    // Add Clamp Measurement button
    const addClampBtn = document.getElementById('add-clamp-measurement-btn');
    if (addClampBtn) {
        addClampBtn.addEventListener('click', addClampMeasurementFromForm);
    }
});

// Function to update protection table header based on quantity field existence
function updateProtectionTableHeader() {
    const thead = document.querySelector('#protection-table thead tr');
    if (!thead) return;

    const qtyEl = document.getElementById('protection_circuit_qty');

    if (qtyEl) {
        // Include quantity column header if field exists
        thead.innerHTML = `
            <th>Scope</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Rated Current (A)</th>
            <th>Quantity</th>
            <th>Actions</th>
        `;
    } else {
        // Exclude quantity column header if field doesn't exist
        thead.innerHTML = `
            <th>Scope</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Rated Current (A)</th>
            <th>Actions</th>
        `;
    }
}

// Wrapper function for custom modal alerts
function showSuccessModal(message) {
    if (typeof customAlert === 'function') {
        customAlert(message, 'success');
    } else {
        // Fallback to native alert if custom modal is not available
        alert(message);
    }
}

/**
 * Setup cable dropdown event listeners
 */
function setupCableDropdowns() {
    const cableBrandSelect = document.getElementById('new_cable_brand');
    const cableModelEl = document.getElementById('new_cable_model');
    // Only attach behavior if model element is a SELECT; if it's an INPUT, skip (free text model)
    if (cableBrandSelect && cableModelEl && cableModelEl.tagName === 'SELECT') {
        cableBrandSelect.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                loadEquipmentModels('cable', brandId, 'new_cable_model');
            } else {
                cableModelEl.innerHTML = '<option value="">Select Model...</option>';
            }
        });
    }
}

// =====================
// Telemetry Tab Logic
// =====================
let telemetryCredential = {};
let telemetryMeters = [];
let communicationsDevices = [];
let telemetryCredentialsList = [];

// Global functions to load existing data (called by autosave_sql.js)
window.loadExistingTelemetryCredentials = function () {
    console.log('[loadExistingTelemetryCredentials] Called - window.existingTelemetryCredentials:', window.existingTelemetryCredentials);

    if (!window.existingTelemetryCredentials || !Array.isArray(window.existingTelemetryCredentials)) {
        console.log('[loadExistingTelemetryCredentials] No credentials to load');
        return;
    }

    console.log('[loadExistingTelemetryCredentials] Syncing', window.existingTelemetryCredentials.length, 'credentials');
    telemetryCredentialsList = window.existingTelemetryCredentials;

    const hidden = document.getElementById('telemetry_credential_data');
    if (hidden) hidden.value = JSON.stringify(telemetryCredentialsList);

    // Render the table
    if (typeof renderTelemetryCredentialsTable === 'function') {
        renderTelemetryCredentialsTable();
    }

    console.log('[loadExistingTelemetryCredentials] ✅ Credentials synced successfully');
};

window.loadExistingTelemetryMeters = function () {
    console.log('[loadExistingTelemetryMeters] Called - window.existingTelemetryMeters:', window.existingTelemetryMeters);

    if (!window.existingTelemetryMeters || !Array.isArray(window.existingTelemetryMeters)) {
        console.log('[loadExistingTelemetryMeters] No telemetry meters to load');
        return;
    }

    console.log('[loadExistingTelemetryMeters] Syncing', window.existingTelemetryMeters.length, 'meters');
    telemetryMeters = window.existingTelemetryMeters;

    const hidden = document.getElementById('telemetry_meter_data');
    if (hidden) hidden.value = JSON.stringify(telemetryMeters);

    // Render the table
    if (typeof renderTelemetryMetersTable === 'function') {
        renderTelemetryMetersTable();
    }

    console.log('[loadExistingTelemetryMeters] ✅ Telemetry meters synced successfully');
};

window.loadExistingCommunications = function () {
    console.log('[loadExistingCommunications] Called - window.existingCommunications:', window.existingCommunications);

    if (!window.existingCommunications || !Array.isArray(window.existingCommunications)) {
        console.log('[loadExistingCommunications] No communications to load');
        return;
    }

    console.log('[loadExistingCommunications] Syncing', window.existingCommunications.length, 'communications');
    communicationsDevices = window.existingCommunications;

    const hidden = document.getElementById('communications_data');
    if (hidden) hidden.value = JSON.stringify(communicationsDevices);

    if (typeof renderCommunicationsTable === 'function') {
        renderCommunicationsTable();
    }

    console.log('[loadExistingCommunications] ✅ Communications synced successfully');
};

// Helper to (re)build the Telemetry inverter reference options from current invertersList
window.refreshTelemetryInverterOptions = function (selectedIdx) {
    const invRef = document.getElementById('telemetry_inverter_ref');
    if (!invRef) return;
    // Preserve current selection unless explicitly provided
    const current = typeof selectedIdx !== 'undefined' ? String(selectedIdx) : invRef.value;
    invRef.innerHTML = '<option value="">Select Inverter...</option>';
    (window.invertersList || []).forEach((inv, idx) => {
        const opt = document.createElement('option');
        opt.value = String(idx);
        opt.text = `${inv.brand_name} ${inv.model_name}${inv.serial_number ? ' - ' + inv.serial_number : ''}`;
        invRef.appendChild(opt);
    });
    // Restore selection if still valid
    if (current && Number(current) >= 0 && Number(current) < (window.invertersList || []).length) {
        invRef.value = current;
    }
};

document.addEventListener('DOMContentLoaded', function () {
    // Initial populate of inverter reference dropdown
    window.refreshTelemetryInverterOptions();

    // Ensure dropdown is up to date when Telemetry tab is shown
    const telemetryTabBtn = document.getElementById('telemetry-tab');
    if (telemetryTabBtn) {
        telemetryTabBtn.addEventListener('shown.bs.tab', function () {
            window.refreshTelemetryInverterOptions();
            // Ensure energy meter brands are loaded each time Telemetry tab is shown
            setTimeout(function () {
                try {
                    loadEquipmentBrands('energy_meter', 'energy_meter_brand');
                    console.debug('[Telemetry] Reloading energy_meter brands on tab show');
                } catch (e) { console.warn('[Telemetry] Failed to reload energy_meter brands', e); }
            }, 50);
        });
    }

    // Wire credential inputs to hidden JSON
    const credFields = ['telemetry_inverter_ref', 'telemetry_username', 'telemetry_password', 'telemetry_ip'];
    credFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncTelemetryCredential);
            el.addEventListener('change', syncTelemetryCredential);
        }
    });

    // Load meter brands and wire models
    loadEquipmentBrands('meter', 'meter_brand');
    const meterBrandEl = document.getElementById('meter_brand');
    if (meterBrandEl) {
        meterBrandEl.addEventListener('change', function () {
            const brandId = this.value;
            if (brandId) {
                loadEquipmentModels('meter', brandId, 'meter_model');
            } else {
                const modelEl = document.getElementById('meter_model');
                if (modelEl) modelEl.innerHTML = '<option value="">Select Model...</option>';
            }
            syncTelemetryMeter();
        });
    }

    // Also ensure energy meter brands are loaded on page load
    try {
        console.debug('[Telemetry] Attempting initial loadEquipmentBrands for energy_meter (Telemetry DOMContentLoaded)');
        loadEquipmentBrands('energy_meter', 'energy_meter_brand');
        console.debug('[Telemetry] Initial load: energy_meter brands requested');
    } catch (e) { console.warn('[Telemetry] Failed to request energy_meter brands on init', e); }
    const meterModelEl = document.getElementById('meter_model');
    if (meterModelEl) meterModelEl.addEventListener('change', () => {/* noop: values read on add */ });

    // Wire other meter fields
    ['meter_mode', 'meter_serial', 'meter_ct_ratio', 'meter_sim_number', 'meter_location', 'meter_led1', 'meter_led2', 'meter_led6', 'meter_gsm_signal']
        .forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                // No live sync; we read values when adding to the list
            }
        });

    // Add brand/model buttons
    const addMeterBrandBtn = document.getElementById('add-meter-brand-btn');
    if (addMeterBrandBtn) addMeterBrandBtn.addEventListener('click', () => showAddBrandModal('meter'));
    const addMeterModelBtn = document.getElementById('add-meter-model-btn');
    if (addMeterModelBtn) addMeterModelBtn.addEventListener('click', () => showAddModelModal('meter'));

    // Add Meter button
    const addMeterBtn = document.getElementById('add-meter-btn');
    if (addMeterBtn) {
        addMeterBtn.addEventListener('click', addTelemetryMeterFromForm);
    }

    // Add Communication Device button
    const addCommunicationBtn = document.getElementById('add-communication-btn');
    if (addCommunicationBtn) {
        addCommunicationBtn.addEventListener('click', addCommunicationFromForm);
    }

    // Add Telemetry Credential button
    const addTelemetryCredBtn = document.getElementById('add-telemetry-cred-btn');
    if (addTelemetryCredBtn) {
        addTelemetryCredBtn.addEventListener('click', addTelemetryCredentialFromForm);
    }

    // Wire live sync for telemetry credential form so hidden value updates when user edits
    const telemetryFields = ['telemetry_inverter_ref', 'telemetry_username', 'telemetry_password', 'telemetry_ip'];
    telemetryFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncTelemetryCredential);
            el.addEventListener('change', syncTelemetryCredential);
        }
    });

    // Communications equipment type change handler
    const commEquipmentSelect = document.getElementById('comm_equipment');
    if (commEquipmentSelect) {
        commEquipmentSelect.addEventListener('change', function () {
            loadCommunicationsModels(this.value);
        });
    }

    // Add Communications Model button
    const addCommModelBtn = document.getElementById('add-comm-model-btn');
    if (addCommModelBtn) {
        addCommModelBtn.addEventListener('click', () => showAddCommModelModal());
    }

    // Initialize telemetry meters: prefer database draft in new mode, otherwise hidden input
    try {
        const hidden = document.getElementById('telemetry_meter_data');
        let loaded = false;

        // In edit mode, skip database draft and prefer hidden input
        if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            fetch((window.BASE_URL || '') + 'ajax/load_telemetry_meters_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.telemetry_meters) && data.telemetry_meters.length > 0) {
                        telemetryMeters = data.telemetry_meters;
                        renderTelemetryMetersTable();
                        if (hidden) hidden.value = JSON.stringify(telemetryMeters);
                        loaded = true;
                        console.log('[JS] Loaded telemetry meters from database draft');
                    }
                })
                .catch(err => {
                    console.warn('Could not load telemetry meters from database draft', err);
                });
        }

        if (!loaded && hidden && hidden.value) {
            const parsed = JSON.parse(hidden.value);
            if (Array.isArray(parsed) && parsed.length > 0) {
                telemetryMeters = parsed;
                renderTelemetryMetersTable();
                loaded = true;
            }
        }
    } catch (e) { console.warn('Could not parse telemetry meters data from hidden input/localStorage'); }

    // Initialize communications list: prefer database draft in new mode, otherwise hidden input
    try {
        const hidden = document.getElementById('communications_data');
        let loaded = false;

        // Try database draft first only if NOT in edit mode
        if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            fetch((window.BASE_URL || '') + 'ajax/load_communications_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.communications) && data.communications.length > 0) {
                        communicationsDevices = data.communications;
                        renderCommunicationsTable();
                        // Ensure hidden input is in sync
                        if (hidden) hidden.value = JSON.stringify(communicationsDevices);
                        loaded = true;
                        console.log('[JS] Loaded communications from database draft');
                    }
                })
                .catch(err => {
                    console.warn('Could not load communications from database draft', err);
                });
        }

        // If nothing loaded from database draft, try hidden input (only if it contains a non-empty array)
        if (!loaded && hidden && hidden.value) {
            const parsed = JSON.parse(hidden.value);
            if (Array.isArray(parsed) && parsed.length > 0) {
                communicationsDevices = parsed;
                renderCommunicationsTable();
                loaded = true;
            }
        }
    } catch (e) { console.warn('Could not parse communications data from hidden input/localStorage', e); }

    // Initialize telemetry credentials from database draft or hidden (BUT NOT in edit mode)
    try {
        const hiddenCred = document.getElementById('telemetry_credential_data');
        if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            fetch((window.BASE_URL || '') + 'ajax/load_telemetry_credentials_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.telemetry_credentials) && data.telemetry_credentials.length > 0) {
                        telemetryCredentialsList = data.telemetry_credentials;
                        console.log('[JS] Loaded telemetry credentials from database draft');
                    } else if (hiddenCred && hiddenCred.value) {
                        const parsedHidden = JSON.parse(hiddenCred.value);
                        if (Array.isArray(parsedHidden)) telemetryCredentialsList = parsedHidden;
                    }
                    renderTelemetryCredentialsTable();
                })
                .catch(err => {
                    console.warn('Could not load telemetry credentials from database draft', err);
                    // Fallback to hidden input
                    if (hiddenCred && hiddenCred.value) {
                        const parsedHidden = JSON.parse(hiddenCred.value);
                        if (Array.isArray(parsedHidden)) telemetryCredentialsList = parsedHidden;
                    }
                    renderTelemetryCredentialsTable();
                });
        } else {
            // In edit mode, just use hidden input
            if (hiddenCred && hiddenCred.value) {
                const parsedHidden = JSON.parse(hiddenCred.value);
                if (Array.isArray(parsedHidden)) telemetryCredentialsList = parsedHidden;
            }
            renderTelemetryCredentialsTable();
        }
    } catch (e) { console.warn('Could not initialize telemetry credentials', e); }

    // Earth Resistance Validation and persistence (database)
    const earthResistanceInput = document.getElementById('earth_resistance');
    // Restore saved value from database draft (prefer server-saved draft when editing)
    try {
        // If we are editing an existing report, include report_id so server can load report_drafts
        const reportIdEl = document.querySelector('input[name="report_id"]');
        const reportId = reportIdEl ? reportIdEl.value : null;
        const loadUrl = reportId ? ('ajax/load_earth_resistance_draft.php?report_id=' + encodeURIComponent(reportId)) : 'ajax/load_earth_resistance_draft.php';
        fetch(loadUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.earth_resistance !== undefined && data.earth_resistance !== null && data.earth_resistance !== '') {
                    if (earthResistanceInput) earthResistanceInput.value = data.earth_resistance;
                    console.log('[JS] Loaded earth resistance from database draft');
                }
            })
            .catch(err => {
                console.warn('Could not load earth resistance from database draft', err);
            });
    } catch (e) { console.warn('Could not read earth resistance from database draft', e); }

    if (earthResistanceInput) {
        earthResistanceInput.addEventListener('input', function () {
            const raw = this.value;
            // Persist to database draft so value survives hard refresh
            try {
                // Include report_id when editing so server can save into report_drafts
                const reportIdEl2 = document.querySelector('input[name="report_id"]');
                const payload = { earth_resistance: raw };
                if (reportIdEl2 && reportIdEl2.value) payload.report_id = reportIdEl2.value;

                fetch((window.BASE_URL || '') + 'ajax/save_earth_resistance_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('[JS] Earth resistance saved to database draft');
                        } else {
                            console.warn('Could not save earth resistance to database draft:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving earth resistance to database draft:', error);
                    });
            } catch (e) { console.warn('Could not persist earth resistance draft', e); }

            const value = parseFloat(raw);
            const warningDiv = document.getElementById('earth-resistance-warning');

            if (!isNaN(value) && value > 10 && warningDiv) {
                warningDiv.style.display = 'block';
            } else if (warningDiv) {
                warningDiv.style.display = 'none';
            }
        });
    }

    // Punch List Add Item button
    const addPunchListBtn = document.getElementById('add-punch-list-btn');
    if (addPunchListBtn) {
        addPunchListBtn.addEventListener('click', addPunchListFromForm);
    }

    // Punch List Cancel Edit button
    const cancelPunchListBtn = document.getElementById('cancel-punch-list-btn');
    if (cancelPunchListBtn) {
        cancelPunchListBtn.addEventListener('click', clearPunchListForm);
    }

    // Initialize Punch List: prefer window.existingPunchList in edit mode, otherwise database draft
    try {
        const hidden = document.getElementById('punch_list_data');
        let loaded = false;

        // In edit mode, prefer SQL data (window.existingPunchList) over database draft
        if (Array.isArray(window.existingPunchList) && window.existingPunchList.length > 0) {
            punchListItems = window.existingPunchList;
            ensurePunchUids(punchListItems);
            renderPunchListTable();
            if (hidden) hidden.value = JSON.stringify(punchListItems);
            loaded = true;
        } else if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            // Load from database draft if not in edit mode
            fetch((window.BASE_URL || '') + 'ajax/load_punch_list_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.punch_list) && data.punch_list.length > 0) {
                        punchListItems = data.punch_list;
                        ensurePunchUids(punchListItems);
                        renderPunchListTable();
                        if (hidden) hidden.value = JSON.stringify(punchListItems);
                        loaded = true;
                        console.log('[JS] Loaded punch list from database draft');
                    }
                })
                .catch(err => {
                    console.warn('Could not load punch list from database draft', err);
                });
        }

        if (!loaded && hidden && hidden.value) {
            try {
                const parsedHidden = JSON.parse(hidden.value);
                if (Array.isArray(parsedHidden) && parsedHidden.length > 0) {
                    punchListItems = parsedHidden;
                    ensurePunchUids(punchListItems);
                    renderPunchListTable();
                    loaded = true;
                }
            } catch (e) {
                console.warn('Could not parse hidden punch_list_data', e);
            }
        }
    } catch (err) { console.warn('Error initializing punch list', err); }
});

function syncTelemetryCredential() {
    const invRef = document.getElementById('telemetry_inverter_ref');
    const user = document.getElementById('telemetry_username');
    const pass = document.getElementById('telemetry_password');
    const ip = document.getElementById('telemetry_ip');
    // Keep single-form sync for backward compatibility (not used when using multiple credentials table)
    telemetryCredential = {
        inverter_index: invRef?.value || '',
        username: user?.value?.trim() || '',
        password: pass?.value?.trim() || '',
        ip: ip?.value?.trim() || ''
    };
    const hidden = document.getElementById('telemetry_credential_data');
    if (hidden) hidden.value = JSON.stringify(telemetryCredentialsList.length ? telemetryCredentialsList : telemetryCredential);
}

// Read values from the Credential form and add to list
function addTelemetryCredentialFromForm() {
    const invRef = document.getElementById('telemetry_inverter_ref');
    const user = document.getElementById('telemetry_username');
    const pass = document.getElementById('telemetry_password');
    const ip = document.getElementById('telemetry_ip');

    if (!invRef || !user) return customAlert('Credential form elements not found');

    const cred = {
        inverter_index: invRef.value || '',
        inverter_text: invRef.options[invRef.selectedIndex]?.text || '',
        username: user.value.trim() || '',
        password: pass?.value?.trim() || '',
        ip: ip?.value?.trim() || ''
    };

    // Basic validation: ensure at least inverter or username is present
    if (!cred.inverter_index && !cred.username) return customAlert('Please select an inverter or enter username');

    telemetryCredentialsList.push(cred);
    renderTelemetryCredentialsTable();
    updateTelemetryCredentialsHidden();

    // Clear form
    invRef.value = '';
    if (user) user.value = '';
    if (pass) pass.value = '';
    if (ip) ip.value = '';
}

function renderTelemetryCredentialsTable() {
    const tbody = document.getElementById('telemetry-credentials-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!telemetryCredentialsList.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="5" class="text-center text-muted"><em>No credentials added</em></td>';
        tbody.appendChild(tr);
        return;
    }

    telemetryCredentialsList.forEach((c, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${c.inverter_text || c.inverter_index || '-'}</td>
            <td>${c.username || '-'}</td>
            <td>${c.password || '-'}</td>
            <td>${c.ip || '-'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" data-action="del" data-idx="${idx}"><i class="fas fa-trash"></i></button>
            </td>`;
        tbody.appendChild(tr);
    });

    tbody.querySelectorAll('button[data-action="del"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const i = parseInt(e.currentTarget.getAttribute('data-idx'));
            telemetryCredentialsList.splice(i, 1);
            renderTelemetryCredentialsTable();
            updateTelemetryCredentialsHidden();
        });
    });
}

function updateTelemetryCredentialsHidden() {
    const hidden = document.getElementById('telemetry_credential_data');
    if (!hidden) return;
    hidden.value = JSON.stringify(telemetryCredentialsList);

    // Save to database draft if not in edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        fetch((window.BASE_URL || '') + 'ajax/save_telemetry_credentials_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                telemetry_credentials: telemetryCredentialsList
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Telemetry credentials saved to database draft');
                } else {
                    console.warn('Could not save telemetry credentials to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving telemetry credentials to database draft:', error);
            });
    }
}

function syncTelemetryMeter() {
    const meter = readTelemetryMeterForm();
    // Store current meter form values (for potential future use)
    window.currentTelemetryMeter = meter;
}

function readTelemetryMeterForm() {
    const mode = document.getElementById('meter_mode');
    const brand = document.getElementById('meter_brand');
    const model = document.getElementById('meter_model');
    const serial = document.getElementById('meter_serial');
    const ct = document.getElementById('meter_ct_ratio');
    const sim = document.getElementById('meter_sim_number');
    const loc = document.getElementById('meter_location');
    const led1 = document.getElementById('meter_led1');
    const led2 = document.getElementById('meter_led2');
    const led6 = document.getElementById('meter_led6');
    const gsm = document.getElementById('meter_gsm_signal');
    return {
        mode: mode?.value || '',
        brand_id: brand?.value || '',
        brand_name: brand && brand.options[brand.selectedIndex] ? brand.options[brand.selectedIndex].text : '',
        model_id: model?.value || '',
        model_name: model && model.options[model.selectedIndex] ? model.options[model.selectedIndex].text : '',
        serial: serial?.value?.trim() || '',
        ct_ratio: ct?.value?.trim() || '',
        sim_number: sim?.value?.trim() || '',
        location: loc?.value?.trim() || '',
        led1: led1?.value || '',
        led2: led2?.value || '',
        led6: led6?.value || '',
        gsm_signal: gsm?.value || ''
    };
}

function addTelemetryMeterFromForm() {
    const m = readTelemetryMeterForm();
    // Basic validation: require brand & model
    if (!m.brand_id || !m.model_id) {
        return customAlert('Please select Meter Manufacturer and Model before adding.');
    }
    telemetryMeters.push(m);
    renderTelemetryMetersTable();
    updateTelemetryMetersHidden();
}

function renderTelemetryMetersTable() {
    const tbody = document.getElementById('telemetry-meters-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!telemetryMeters.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="12" class="text-center text-muted"><em>No meters added</em></td>';
        tbody.appendChild(tr);
        return;
    }
    telemetryMeters.forEach((m, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${m.mode || '-'}</td>
            <td>${m.brand_name || '-'}</td>
            <td>${m.model_name || '-'}</td>
            <td>${m.serial || '-'}</td>
            <td>${m.ct_ratio || '-'}</td>
            <td>${m.sim_number || '-'}</td>
            <td>${m.location || '-'}</td>
            <td>${m.led1 || '-'}</td>
            <td>${m.led2 || '-'}</td>
            <td>${m.led6 || '-'}</td>
            <td>${m.gsm_signal || '-'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" data-action="del" data-idx="${idx}"><i class="fas fa-trash"></i></button>
            </td>`;
        tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-action="del"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const i = parseInt(e.currentTarget.getAttribute('data-idx'));
            telemetryMeters.splice(i, 1);
            renderTelemetryMetersTable();
            updateTelemetryMetersHidden();
        });
    });
}

function updateTelemetryMetersHidden() {
    const hidden = document.getElementById('telemetry_meter_data');
    if (hidden) hidden.value = JSON.stringify(telemetryMeters);
    // Save to database draft if not in edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        fetch((window.BASE_URL || '') + 'ajax/save_telemetry_meters_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                telemetry_meters: telemetryMeters
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Telemetry meters saved to database draft');
                } else {
                    console.warn('Could not save telemetry meters to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving telemetry meters to database draft:', error);
            });
    }
}

// =====================
// Communications Functions
// =====================

function readCommunicationsForm() {
    const equipment = document.getElementById('comm_equipment');
    const modelSelect = document.getElementById('comm_model');
    const model = modelSelect && modelSelect.options[modelSelect.selectedIndex] ? modelSelect.options[modelSelect.selectedIndex].text : '';
    const serial = document.getElementById('comm_serial');
    const mac = document.getElementById('comm_mac');
    const ip = document.getElementById('comm_ip');
    const sim = document.getElementById('comm_sim');
    const location = document.getElementById('comm_location');
    const ftpServer = document.getElementById('comm_ftp_server');
    const ftpUsername = document.getElementById('comm_ftp_username');
    const ftpPassword = document.getElementById('comm_ftp_password');
    const fileFormat = document.getElementById('comm_file_format');

    return {
        equipment: equipment?.value || '',
        model: model || '',
        serial: serial?.value?.trim() || '',
        mac: mac?.value?.trim() || '',
        ip: ip?.value?.trim() || '',
        sim: sim?.value?.trim() || '',
        location: location?.value?.trim() || '',
        ftp_server: ftpServer?.value?.trim() || '',
        ftp_username: ftpUsername?.value?.trim() || '',
        ftp_password: ftpPassword?.value?.trim() || '',
        file_format: fileFormat?.value?.trim() || ''
    };
}

function addCommunicationFromForm() {
    const device = readCommunicationsForm();
    // Basic validation: require equipment type
    if (!device.equipment) {
        return customAlert('Please select Equipment type before adding.');
    }
    communicationsDevices.push(device);
    renderCommunicationsTable();
    updateCommunicationsHidden();
}

function renderCommunicationsTable() {
    const tbody = document.getElementById('communications-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!communicationsDevices.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="12" class="text-center text-muted"><em>No devices added</em></td>';
        tbody.appendChild(tr);
        return;
    }
    communicationsDevices.forEach((device, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${device.equipment || '-'}</td>
            <td>${device.model || '-'}</td>
            <td>${device.serial || '-'}</td>
            <td>${device.mac || '-'}</td>
            <td>${device.ip || '-'}</td>
            <td>${device.sim || '-'}</td>
            <td>${device.location || '-'}</td>
            <td>${device.ftp_server || '-'}</td>
            <td>${device.ftp_username || '-'}</td>
            <td>${device.ftp_password || '-'}</td>
            <td>${device.file_format || '-'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" data-action="del" data-idx="${idx}"><i class="fas fa-trash"></i></button>
            </td>`;
        tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-action="del"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const i = parseInt(e.currentTarget.getAttribute('data-idx'));
            communicationsDevices.splice(i, 1);
            renderCommunicationsTable();
            updateCommunicationsHidden();
        });
    });
}

function updateCommunicationsHidden() {
    const hidden = document.getElementById('communications_data');
    if (hidden) hidden.value = JSON.stringify(communicationsDevices);
    // Save to database draft if not in edit mode
    if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
        fetch((window.BASE_URL || '') + 'ajax/save_communications_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                communications: communicationsDevices
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[JS] Communications saved to database draft');
                } else {
                    console.warn('Could not save communications to database draft:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving communications to database draft:', error);
            });
    }
}

// =====================
// Communications Model Loading
// =====================

function loadCommunicationsModels(equipmentType) {
    const modelSelect = document.getElementById('comm_model');
    if (!modelSelect) return;

    // Clear current options
    modelSelect.innerHTML = '<option value="">Select Model...</option>';

    if (!equipmentType) return;

    // Fetch models from server
    fetch((window.BASE_URL || "") + `ajax/get_communications_models.php?equipment_type=${encodeURIComponent(equipmentType)}`)
        .then(response => response.json())
        .then(models => {
            if (!Array.isArray(models)) {
                console.error('[Comm] Unexpected response format:', models);
                return;
            }
            models.forEach(model => {
                const option = document.createElement('option');
                option.value = model.id;
                option.textContent = model.model_name;
                option.title = model.characteristics || '';
                modelSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading communications models:', error);
            customAlert('Error loading models. Please try again.');
        });
}

function showAddCommModelModal() {
    const equipmentType = document.getElementById('comm_equipment')?.value;
    if (!equipmentType) {
        customAlert('Please select an equipment type first.');
        return;
    }

    // Create modal for adding new communication model
    const modalHtml = `
        <div class="modal fade" id="addCommModelModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New ${equipmentType} Model</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addCommModelForm">
                            <div class="mb-3">
                                <label for="new_comm_model_name" class="form-label">Model Name</label>
                                <input type="text" class="form-control" id="new_comm_model_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_comm_manufacturer" class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" id="new_comm_manufacturer" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_comm_characteristics" class="form-label">Characteristics</label>
                                <textarea class="form-control" id="new_comm_characteristics" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveCommModelBtn">Save Model</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('addCommModelModal');
    if (existingModal) existingModal.remove();

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('addCommModelModal'));
    modal.show();

    // Ensure modal element is removed from DOM after it's fully hidden to avoid lingering backdrops
    const modalEl = document.getElementById('addCommModelModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function cleanup() {
            modalEl.removeEventListener('hidden.bs.modal', cleanup);
            if (modalEl.parentNode) modalEl.remove();
        });
    }

    // Handle save button
    document.getElementById('saveCommModelBtn').addEventListener('click', function () {
        const modelName = document.getElementById('new_comm_model_name').value.trim();
        const manufacturer = document.getElementById('new_comm_manufacturer').value.trim();
        const characteristics = document.getElementById('new_comm_characteristics').value.trim();

        if (!modelName || !manufacturer) {
            customAlert('Model name and manufacturer are required.');
            return;
        }

        // Here you would typically send to server to save new model
        // For now, just add to local dropdown
        const modelSelect = document.getElementById('comm_model');
        const option = document.createElement('option');
        option.value = `custom_${Date.now()}`;
        option.textContent = modelName;
        option.title = characteristics || '';
        modelSelect.appendChild(option);
        modelSelect.value = option.value;

        modal.hide();
        customAlert('Model added successfully!');
    });
}

// Form validation for commissioning form
document.addEventListener('DOMContentLoaded', function () {
    const commissioningForm = document.getElementById('commissioningForm');
    if (commissioningForm) {
        console.log('Commissioning form found and event listener attached');

        commissioningForm.addEventListener('submit', function (e) {
            console.log('Form submit event triggered');

            // SYNC: Ensure telemetry credentials data is in hidden field before submit
            const telemetryCrendentialsHidden = document.getElementById('telemetry_credential_data');
            if (telemetryCrendentialsHidden) {
                telemetryCrendentialsHidden.value = JSON.stringify(telemetryCredentialsList || []);
                console.log('📊 [SUBMIT] Synced telemetry credentials:', telemetryCredentialsList);
            }

            // SYNC: Ensure telemetry meters data is in hidden field before submit
            const telemetryMetersHidden = document.getElementById('telemetry_meter_data');
            if (telemetryMetersHidden) {
                telemetryMetersHidden.value = JSON.stringify(telemetryMeters || []);
                console.log('📊 [SUBMIT] Synced telemetry meters:', telemetryMeters);
            }

            // SYNC: Ensure communications data is in hidden field before submit
            const communicationsHidden = document.getElementById('communications_data');
            if (communicationsHidden) {
                communicationsHidden.value = JSON.stringify(communicationsDevices || []);
                console.log('📊 [SUBMIT] Synced communications devices:', communicationsDevices);
            }

            // DEBUG: Log inverters data being submitted
            const invertersDataField = document.getElementById('inverters_data');
            if (invertersDataField) {
                const invertersData = JSON.parse(invertersDataField.value || '[]');
                console.log('========== SUBMIT DEBUG ==========');
                console.log('Submitting', invertersData.length, 'inverters');
                invertersData.forEach((inv, idx) => {
                    console.log(`Inverter ${idx}:`, {
                        brand: inv.brand_name,
                        model: inv.model_name,
                        circuit_breaker_brand: inv.circuit_breaker_brand_name,
                        differential_brand: inv.differential_brand_name,
                        cable_brand: inv.cable_brand_name
                    });
                });
                console.log('==================================');
            }

            // Allow form submission without validation
        });
    } else {
        console.log('Commissioning form NOT found!');
    }
});

/**
 * Check all required fields in the form and return list of missing ones
 * @returns {Array} Array of objects with field information
 */
function checkRequiredFields() {
    const missingFields = [];

    // Define all required fields with their information
    const requiredFields = [
        // All fields are now optional - no validation required
    ];

    // Check each required field
    requiredFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (!element || !element.value.trim()) {
            missingFields.push(field);
        }
    });

    return missingFields;
}

/**
 * Show modal with list of required fields that need to be filled
 * @param {Array} missingFields - Array of missing field objects
 */
function showRequiredFieldsModal(missingFields) {
    // Validation modal removed - all fields are now optional
    return;
}

/**
 * Navigate to a specific field and tab
 * @param {string} fieldId - ID of the field to focus
 * @param {string} tabId - ID of the tab to activate
 */
function goToField(fieldId, tabId) {
    // Navigation function removed - all fields are now optional
    return;
}

/**
 * Initialize persistence for Additional Notes textarea (#notes)
 * Saves to dedicated database draft to avoid being
 * overwritten by server-injected hidden defaults and to restore reliably
 * after hard refresh (Ctrl+F5).
 */
function initNotesPersistence() {
    const notesEl = document.getElementById('notes');
    const lsKey = 'commissioning_notes';
    if (!notesEl) return;

    // Restore from database draft if present (highest priority)
    try {
        if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            fetch((window.BASE_URL || '') + 'ajax/load_notes_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notes) {
                        notesEl.value = data.notes;
                        console.log('[JS] Loaded notes from database draft');
                    }
                })
                .catch(err => {
                    console.warn('Could not load notes from database draft', err);
                });
        }
    } catch (e) {
        console.warn('Could not restore notes from database draft', e);
    }

    // Save on change and keyup (debounced by existing autosave, but we also keep dedicated key)
    notesEl.addEventListener('change', function () {
        if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
            fetch((window.BASE_URL || '') + 'ajax/save_notes_draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notes: notesEl.value
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('[JS] Notes saved to database draft');
                    } else {
                        console.warn('Could not save notes to database draft:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error saving notes to database draft:', error);
                });
        }
        const form = document.getElementById('commissioningForm'); if (form) saveFormData(form);
        // Also send to server if report_id is present
        const reportIdEl = document.querySelector('input[name="report_id"]');
        if (reportIdEl && reportIdEl.value) {
            // fire and forget
            fetch((window.BASE_URL || '') + 'ajax/save_additional_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: `report_id=${encodeURIComponent(reportIdEl.value)}&notes=${encodeURIComponent(notesEl.value)}`
            }).catch(() => { });
        }
        // also set preview cookie (short lived)
        try { document.cookie = 'commissioning_notes_preview=' + encodeURIComponent(notesEl.value) + '; path=/; max-age=300'; } catch (e) { }
    });

    let typingTimer;
    notesEl.addEventListener('keyup', function () {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function () {
            if (!window.EDIT_MODE_SKIP_LOCALSTORAGE) {
                fetch((window.BASE_URL || '') + 'ajax/save_notes_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        notes: notesEl.value
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('[JS] Notes saved to database draft');
                        } else {
                            console.warn('Could not save notes to database draft:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving notes to database draft:', error);
                    });
            }
            const form = document.getElementById('commissioningForm'); if (form) saveFormData(form);
            const reportIdEl = document.querySelector('input[name="report_id"]');
            if (reportIdEl && reportIdEl.value) {
                // debounce server save separately
                fetch((window.BASE_URL || '') + 'ajax/save_additional_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: `report_id=${encodeURIComponent(reportIdEl.value)}&notes=${encodeURIComponent(notesEl.value)}`
                }).catch(() => { });
            }
            try { document.cookie = 'commissioning_notes_preview=' + encodeURIComponent(notesEl.value) + '; path=/; max-age=300'; } catch (e) { }
        }, 800);
    });
}

/**
 * Initialize Commissioning Responsible dropdown
 */
function initCommissioningResponsibleDropdown() {
    const select = document.getElementById('commissioning_responsible_id');
    if (!select) {
        console.log('[COMM-RESP] ✗ Dropdown not found');
        return;
    }

    const preSelectedId = select.dataset.selected || '';
    console.log('[COMM-RESP] Initializing... Pre-selected:', preSelectedId);

    fetch((window.BASE_URL || '') + 'ajax/get_commissioning_responsibles.php')
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Responsible...</option>';
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                if (preSelectedId && item.id == preSelectedId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            console.log('[COMM-RESP] ✓ Loaded', data.length, 'items');
            if (preSelectedId) {
                console.log('[COMM-RESP] Restored selection:', preSelectedId, 'Success:', select.value == preSelectedId);
            }
        })
        .catch(error => {
            console.error('[COMM-RESP] ✗ Error loading:', error);
        });
}

// Initialize commissioning responsible dropdown on page load
document.addEventListener('DOMContentLoaded', function () {
    initCommissioningResponsibleDropdown();
});

/**
 * DEPRECATED: Modules are now automatically saved by autosave_sql.js
 * This function is no longer called and can be removed in the future
 */
/*
function saveModulesDraftToSQL() {
    console.log('[saveModulesDraftToSQL] Saving ' + modulesList.length + ' modules to database draft');

    fetch((window.BASE_URL || '') + 'ajax/save_modules_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            modules: modulesList
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Modules draft saved to SQL:', data.count, 'modules');
            } else {
                console.warn('⚠️ Failed to save modules draft:', data.error);
            }
        })
        .catch(error => {
            console.error('❌ Error saving modules draft to SQL:', error);
        });
}
*/
