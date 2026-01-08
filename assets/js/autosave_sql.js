/**
 * Sistema de AutoSave para SQL
 * Guarda automaticamente todos os dados do formulário no servidor
 * 
 * @author Cleanwatts
 * @version 2.0
 */

(function () {
    'use strict';

    const AUTOSAVE_INTERVAL = 5000; // 5 segundos
    const AUTOSAVE_ENDPOINT = 'ajax/autosave_draft.php';
    const LOAD_DRAFT_ENDPOINT = 'ajax/load_draft.php';
    const INIT_SESSION_ENDPOINT = 'ajax/init_form_session.php';

    let autosaveTimer = null;
    let lastSaveTime = null;
    let isSaving = false;
    let saveIndicator = null;
    let formSessionInitialized = false; // 🔒 Flag para garantir sessão inicializada

    /**
     * 🔒 Inicializar sessão de formulário isolada
     * Esta sessão garante que cada edição é completamente isolada
     * 
     * FALLBACK MODE: Se a sessão não conseguir inicializar,
     * continua de qualquer forma (não é crítico para funcionalidades principais)
     */
    function initFormSession() {
        return new Promise((resolve) => { // resolve, não reject - sempre resolve
            if (formSessionInitialized) {
                resolve();
                return;
            }

            console.log('[FORM SESSION] 🔐 Initializing form session...');

            // Obter report_id da URL se existir
            const urlParams = new URLSearchParams(window.location.search);
            const reportId = urlParams.get('report_id');

            const url = reportId
                ? `${INIT_SESSION_ENDPOINT}?report_id=${reportId}`
                : INIT_SESSION_ENDPOINT;

            // Timeout de 3 segundos - se não responder, continua mesmo assim
            const timeoutPromise = new Promise(resolve =>
                setTimeout(() => resolve({ success: true, fallback: true }), 3000)
            );

            Promise.race([
                fetch(url).then(response => response.json()),
                timeoutPromise
            ])
                .then(data => {
                    if (data.success || data.fallback) {
                        formSessionInitialized = true;
                        if (!data.fallback) {
                            console.log('[FORM SESSION] ✅ Session initialized');
                        } else {
                            console.log('[FORM SESSION] ⚠️ Session timeout - continuing with fallback');
                        }
                        resolve(data);
                    } else {
                        // Mesmo que falhe, continua
                        formSessionInitialized = true;
                        console.log('[FORM SESSION] ⚠️ Session init skipped - continuing anyway');
                        resolve({ success: true, fallback: true });
                    }
                })
                .catch(error => {
                    // Em caso de erro, continua mesmo assim (não é crítico)
                    formSessionInitialized = true;
                    console.log('[FORM SESSION] ⚠️ Session init error - continuing with fallback (não crítico)');
                    resolve({ success: true, fallback: true });
                });
        });
    }

    /**
     * Limpar dados antigos quando é um novo relatório
     * Agora limpa apenas dados na BD via AJAX
     * NOTE: Desativado em favor da limpeza via PHP (?new=1) para evitar perda de dados em refresh
     */
    function clearNewReportData() {
        return new Promise((resolve) => {
            // A limpeza agora é feita pelo PHP no carregamento da página se ?new=1 estiver presente
            // Isso evita que um simples refresh (F5/Ctrl+R) apague o progresso do usuário
            resolve();
        });
    }

    let initAttempts = 0; // Contador para não tentar infinitamente

    /**
     * Inicializar sistema de autosave
     */
    function initAutoSaveSQL() {
        // Skip on reports page
        if (window.REPORTS_PAGE) {
            console.log('[AUTOSAVE SQL] ⏭️ Skipping initialization on reports page');
            return;
        }

        console.log('═══════════════════════════════════════════════════');
        console.log('🚀 [AUTOSAVE SQL] Initializing System...');
        console.log('═══════════════════════════════════════════════════');

        // Criar indicador visual
        createSaveIndicator();

        // 1. Limpar dados antigos se for novo relatório
        // 2. Inicializar sessão e carregar rascunho
        clearNewReportData()
            .then(() => initFormSession())
            .then(() => {
                console.log('[AUTOSAVE SQL] ✅ Form session ready, proceeding with autosave init');
                initAttempts = 0; // Reset contador

                // Carregar rascunho existente (agora seguro - sessão isolada)
                loadDraft();

                // Iniciar autosave periódico
                startAutoSave();

                // Guardar antes de sair da página
                window.addEventListener('beforeunload', function (e) {
                    saveFormDataToSQL(true); // sync save
                });

                // Guardar ao mudar de aba
                document.querySelectorAll('.nav-link').forEach(tab => {
                    tab.addEventListener('click', function () {
                        console.log('[AUTOSAVE SQL] Tab change detected, saving...');
                        saveFormDataToSQL();
                    });
                });

                // Guardar ao alterar campos importantes
                attachFormListeners();

                // ⭐ Carregar MPPT values
                if (typeof window.loadStringMeasurementsDraft === 'function') {
                    setTimeout(() => {
                        console.log('[AUTOSAVE SQL] Loading MPPT string measurements...');
                        window.loadStringMeasurementsDraft();
                    }, 100);
                }

                console.log('✅ [AUTOSAVE SQL] System initialized successfully');
            })
            .catch(error => {
                // Limitar tentativas de retry para evitar spam de logs
                initAttempts++;
                if (initAttempts > 3) {
                    console.warn('[AUTOSAVE SQL] ⚠️ Failed to initialize after 3 attempts - continuing with fallback mode');
                    // Mesmo assim, continuar com funcionalidades básicas
                    loadDraft();
                    startAutoSave();
                    attachFormListeners();

                    // ⭐ IMPORTANTE: Carregar MPPT values se string_autosave.js estiver disponível
                    if (typeof window.loadStringMeasurementsDraft === 'function') {
                        console.log('[AUTOSAVE SQL] Loading MPPT string measurements...');
                        window.loadStringMeasurementsDraft();
                    }

                    return;
                }

                console.warn('[AUTOSAVE SQL] ⚠️ Initialize attempt ' + initAttempts + ' failed - will retry');
                updateIndicator('warning');

                // Tentar novamente após 5 segundos
                setTimeout(() => {
                    console.log('[AUTOSAVE SQL] 🔄 Retrying initialization... (attempt ' + (initAttempts + 1) + ')');
                    initAutoSaveSQL();
                }, 5000);
            });
    }

    /**
     * Anexar listeners aos campos do formulário
     */
    function attachFormListeners() {
        const form = document.getElementById('commissioningForm');
        if (!form) return;

        // Campos de texto e textarea
        form.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], textarea, select').forEach(field => {
            // Skip fields marked with data-skip-autosave
            if (field.getAttribute('data-skip-autosave') === 'true') {
                console.log(`[AUTOSAVE SQL] Skipping field: ${field.name || field.id}`);
                return;
            }

            let timeout = null;
            field.addEventListener('input', function () {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    console.log(`[AUTOSAVE SQL] Field changed: ${field.name || field.id}`);
                    saveFormDataToSQL();
                }, 1000);
            });
        });

        // Checkboxes e radios
        form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(field => {
            field.addEventListener('change', function () {
                console.log(`[AUTOSAVE SQL] Checkbox/Radio changed: ${field.name || field.id}`);
                saveFormDataToSQL();
            });
        });

        console.log('[AUTOSAVE SQL] ✓ Form listeners attached');
    }

    /**
     * Criar indicador visual de salvamento
     */
    function createSaveIndicator() {
        saveIndicator = document.createElement('div');
        saveIndicator.id = 'sql-save-indicator';
        saveIndicator.innerHTML = `
            <div class="d-flex align-items-center">
                <span class="status-icon me-2">
                    <i class="fas fa-database"></i>
                </span>
                <span class="status-text">Inicializando...</span>
            </div>
        `;
        saveIndicator.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 9999;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            opacity: 1;
        `;

        document.body.appendChild(saveIndicator);
        console.log('[AUTOSAVE SQL] ✓ Save indicator created');
    }

    /**
     * Atualizar estado do indicador
     */
    function updateIndicator(status, message) {
        if (!saveIndicator) return;

        const statusIcon = saveIndicator.querySelector('.status-icon i');
        const statusText = saveIndicator.querySelector('.status-text');

        saveIndicator.style.display = 'block';
        saveIndicator.style.opacity = '1';

        switch (status) {
            case 'saving':
                statusIcon.className = 'fas fa-spinner fa-spin';
                statusText.textContent = 'Saving...';
                saveIndicator.style.background = 'linear-gradient(135deg, #ffc107 0%, #ff9800 100%)';
                break;

            case 'saved':
                statusIcon.className = 'fas fa-check-circle';
                statusText.textContent = message || 'Saved ✓';
                saveIndicator.style.background = 'linear-gradient(135deg, #26989D 0%, #1BA5B7 100%)';

                // Esconder após 3 segundos
                setTimeout(() => {
                    saveIndicator.style.opacity = '0';
                    setTimeout(() => {
                        saveIndicator.style.display = 'none';
                    }, 300);
                }, 3000);
                break;

            case 'error':
                statusIcon.className = 'fas fa-exclamation-triangle';
                statusText.textContent = 'Error saving';
                saveIndicator.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';

                // Esconder após 5 segundos
                setTimeout(() => {
                    saveIndicator.style.opacity = '0';
                    setTimeout(() => {
                        saveIndicator.style.display = 'none';
                    }, 300);
                }, 5000);
                break;

            case 'loading':
                statusIcon.className = 'fas fa-database';
                statusText.textContent = 'Loading data...';
                saveIndicator.style.background = 'linear-gradient(135deg, #2CCCD3 0%, #254A5D 100%)';
                break;
        }
    }

    /**
     * Coletar todos os dados do formulário
     */
    function collectFormData() {
        const form = document.getElementById('commissioningForm');
        if (!form) {
            console.error('[AUTOSAVE SQL] ❌ Form #commissioningForm not found');
            return null;
        }

        const formData = {};
        let fieldCount = 0;

        // Coletar campos básicos do formulário
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.name) return;

            if (input.type === 'checkbox') {
                formData[input.name] = input.checked;
            } else if (input.type === 'radio') {
                if (input.checked) {
                    formData[input.name] = input.value;
                }
            } else {
                formData[input.name] = input.value;
            }
            fieldCount++;
        });

        // Coletar dados JSON dos campos hidden
        // NOTE: These fields get updated to hidden fields whenever user clicks Add/Delete buttons
        // We collect them periodically to ensure data persists even if autosave endpoint fails
        // This prevents data loss after cache clear (Ctrl+Shift+Del)
        const jsonFields = [
            'modules_data',
            'inverters_data',
            'layouts_data',
            'protection_data',  // ✅ NOW AUTO-COLLECTED (prevents data loss after cache clear)
            'protection_cable_data',  // ✅ NOW AUTO-COLLECTED
            'clamp_measurements_data',
            'telemetry_credential_data',
            'telemetry_meter_data',
            'communications_data',
            'energy_meter_data',
            'punch_list_data',
            'finish_photos_data',
            'string_measurements_data',
            // Simple text fields like homopolar_* and earth_resistance are collected above
            // as plain inputs and should NOT be JSON.parsed here to avoid warnings.
            // Keep only true JSON payload fields in this list.
        ];

        jsonFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field && field.value) {
                try {
                    formData[fieldName] = JSON.parse(field.value);
                    console.log(`[AUTOSAVE SQL] ✓ Collected ${fieldName}: ${field.value.length} chars`);
                } catch (e) {
                    console.warn(`[AUTOSAVE SQL] ⚠️ Could not parse ${fieldName}:`, e);
                    formData[fieldName] = field.value;
                }
            }
        });

        // ALWAYS include protection_data and protection_cable_data if they have values
        // This is called after Add/Delete user actions, so the hidden fields have the correct values
        const protectionDataField = document.getElementById('protection_data');
        if (protectionDataField && protectionDataField.value) {
            try {
                const parsedProt = JSON.parse(protectionDataField.value);
                // Always include, even if empty array, so deletions são refletidas no draft
                formData['protection_data'] = Array.isArray(parsedProt) ? parsedProt : [];
                const len = protectionDataField.value.length;
                if (formData['protection_data'].length > 0) {
                    console.log(`[AUTOSAVE SQL] ✓ Collected protection_data (explicit): ${len} chars`);
                    console.log('[AUTOSAVE SQL] 🔍 protection_data DETAILED:', parsedProt);
                    if (parsedProt.length > 0 && parsedProt[0]) {
                        console.log('[AUTOSAVE SQL] 🔍 protection_data[0].rated_current:', parsedProt[0].rated_current);
                    }
                } else {
                    console.log('[AUTOSAVE SQL] ✓ Collected protection_data: empty list (deletions will be saved)');
                }
            } catch (e) {
                console.warn(`[AUTOSAVE SQL] ⚠️ Could not parse protection_data:`, e);
                console.log('[AUTOSAVE SQL] 🔍 protection_data raw value:', protectionDataField.value);
            }
        }

        const protectionCableDataField = document.getElementById('protection_cable_data');
        if (protectionCableDataField && protectionCableDataField.value && protectionCableDataField.value !== '[]') {
            try {
                formData['protection_cable_data'] = JSON.parse(protectionCableDataField.value);
                console.log(`[AUTOSAVE SQL] ✓ Collected protection_cable_data (explicit): ${protectionCableDataField.value.length} chars`);
            } catch (e) {
                console.warn(`[AUTOSAVE SQL] ⚠️ Could not parse protection_cable_data:`, e);
            }
        }

        // Incluir report_id se existir
        const reportIdField = document.querySelector('input[name="report_id"]');
        if (reportIdField && reportIdField.value) {
            formData['report_id'] = reportIdField.value;
            console.log(`[AUTOSAVE SQL] ✓ Report ID found: ${formData['report_id']}`);
        }

        console.log(`[AUTOSAVE SQL] ✓ Collected ${fieldCount} fields`);
        return formData;
    }

    /**
     * Guardar dados no SQL via AJAX
     */
    function saveFormDataToSQL(isSync = false) {
        // 🔒 Garantir que a sessão foi inicializada
        if (!formSessionInitialized) {
            console.warn('[AUTOSAVE SQL] ⚠️ Session not initialized, skipping save');
            return;
        }

        if (isSaving) {
            console.log('[AUTOSAVE SQL] ⏳ Already saving, skipping...');
            return;
        }

        console.log('─────────────────────────────────────────────────');
        console.log('[AUTOSAVE SQL] 💾 Starting save operation...');

        isSaving = true;
        updateIndicator('saving');

        const formData = collectFormData();
        if (!formData) {
            isSaving = false;
            updateIndicator('error');
            return;
        }

        const startTime = Date.now();

        const xhr = new XMLHttpRequest();
        xhr.open('POST', AUTOSAVE_ENDPOINT, !isSync);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onload = function () {
            isSaving = false;
            const duration = Date.now() - startTime;

            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        lastSaveTime = new Date();
                        const timeStr = lastSaveTime.toLocaleTimeString('pt-PT');
                        updateIndicator('saved', `Saved at ${timeStr}`);
                        console.log(`[AUTOSAVE SQL] ✅ Saved successfully in ${duration}ms`);
                        console.log('[AUTOSAVE SQL] Response:', response);
                    } else {
                        console.error('[AUTOSAVE SQL] ❌ Save failed:', response.error);
                        updateIndicator('error');
                    }
                } catch (e) {
                    console.error('[AUTOSAVE SQL] ❌ Parse error:', e);
                    console.error('[AUTOSAVE SQL] ❌ Raw response:', xhr.responseText);
                    updateIndicator('error');
                }
            } else {
                console.error(`[AUTOSAVE SQL] ❌ HTTP error: ${xhr.status}`);
                updateIndicator('error');
            }
            console.log('─────────────────────────────────────────────────');
        };

        xhr.onerror = function () {
            isSaving = false;
            console.error('[AUTOSAVE SQL] ❌ Network error');
            updateIndicator('error');
            console.log('─────────────────────────────────────────────────');
        };

        xhr.send(JSON.stringify(formData));
    }

    /**
     * Carregar rascunho do SQL
     */
    function loadDraft() {
        console.log('─────────────────────────────────────────────────');
        console.log('[AUTOSAVE SQL] 📥 Loading draft from SQL...');

        updateIndicator('loading');

        // Verificar se temos report_id na URL
        const urlParams = new URLSearchParams(window.location.search);
        const reportId = urlParams.get('report_id');

        // Se estamos EDITANDO um relatório existente (report_id presente), NÃO carregar rascunho
        // O PHP já carregou os dados do banco de dados e os colocou nos campos
        if (reportId) {
            console.log(`[AUTOSAVE SQL] ℹ️ Editing existing report (ID: ${reportId}) - skipping draft load`);
            console.log('[AUTOSAVE SQL] Using data from commissioning_reports table instead');
            updateIndicator('saved', 'Carregado do servidor');
            console.log('─────────────────────────────────────────────────');
            return;
        }

        // Para NOVOS relatórios (sem report_id), SEMPRE tentar carregar draft
        // Isto permite que o utilizador continue a trabalhar após refresh
        let url = LOAD_DRAFT_ENDPOINT;
        console.log('[AUTOSAVE SQL] Loading draft for new report (no report_id)');

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    console.log('[AUTOSAVE SQL] ✅ Draft loaded successfully');
                    console.log(`[AUTOSAVE SQL] Last updated: ${data.last_updated}`);
                    console.log(`[AUTOSAVE SQL] Draft ID: ${data.draft_id}`);

                    restoreFormData(data.data);

                    const updateDate = new Date(data.last_updated);
                    updateIndicator('saved', `Carregado de ${updateDate.toLocaleString('pt-PT')}`);
                } else {
                    console.log('[AUTOSAVE SQL] ℹ️ No draft found, starting fresh');
                    updateIndicator('saved', 'Novo relatório');
                }
                console.log('─────────────────────────────────────────────────');
            })
            .catch(error => {
                console.error('[AUTOSAVE SQL] ❌ Load error:', error);
                updateIndicator('error');
                console.log('─────────────────────────────────────────────────');
            });
    }

    /**
     * Restaurar dados do formulário
     */
    function restoreFormData(data) {
        console.log('[AUTOSAVE SQL] 🔄 Restoring form data...');

        const form = document.getElementById('commissioningForm');
        if (!form) {
            console.error('[AUTOSAVE SQL] ❌ Form not found for restore');
            return;
        }

        let restoredFields = 0;

        // Restaurar campos básicos
        for (const [key, value] of Object.entries(data)) {
            // Skip JSON fields - we'll handle them separately
            if (key.endsWith('_data')) continue;
            // Skip representative_id - será restaurado depois do EPC
            if (key === 'representative_id') continue;

            const inputs = form.querySelectorAll(`[name="${key}"]`);
            inputs.forEach(input => {
                if (input.type === 'checkbox') {
                    input.checked = value;
                } else if (input.type === 'radio') {
                    input.checked = (input.value === value);
                } else {
                    input.value = value || '';
                }
                restoredFields++;
            });
        }


        // If GPS was restored, trigger change so map will update (map listens to change on #gps)
        try {
            const gpsEl = document.querySelector('input[name="gps"]');
            if (gpsEl) {
                gpsEl.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // If polygon coords were restored into hidden field, trigger change so map can re-render
            const polyEl = document.querySelector('input[name="map_polygon_coords"]');
            if (polyEl) {
                polyEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } catch (e) {
            console.warn('[AUTOSAVE SQL] Could not dispatch change events for map fields', e);
        }
        // 🎯 Restaurar Representative DEPOIS do EPC estar carregado
        if (data.epc_id && data.representative_id) {
            console.log('[AUTOSAVE SQL] 🔄 Restoring EPC and Representative...');
            const epcSelect = document.getElementById('epc_id');
            const repSelect = document.getElementById('representative_id');

            if (epcSelect && repSelect) {
                // Primeiro, definir o EPC
                epcSelect.value = data.epc_id;

                // Depois, carregar os representatives desse EPC
                fetch((window.BASE_URL || "") + `ajax/get_representatives.php?epc_id=${data.epc_id}`)
                    .then(response => response.json())
                    .then(reps => {
                        repSelect.innerHTML = '<option value="">Select Representative...</option>';
                        reps.forEach(rep => {
                            const option = document.createElement('option');
                            option.value = rep.id;
                            option.textContent = `${rep.name} (${rep.phone})`;
                            repSelect.appendChild(option);
                        });

                        // Finalmente, selecionar o representative correto
                        repSelect.value = data.representative_id;
                        console.log(`[AUTOSAVE SQL] ✅ Representative restored: ${data.representative_id}`);
                        restoredFields++;
                    })
                    .catch(err => {
                        console.error('[AUTOSAVE SQL] ❌ Failed to load representatives:', err);
                    });
            }
        }

        // Restaurar campos JSON
        const jsonFields = [
            'modules_data',
            'inverters_data',
            'layouts_data',
            'protection_data',
            'protection_cable_data',
            'clamp_measurements_data',
            'telemetry_credential_data',
            'telemetry_meter_data',
            'communications_data',
            'energy_meter_data',
            'punch_list_data',
            'finish_photos_data',
            'string_measurements_data'
        ];

        jsonFields.forEach(fieldName => {
            if (data[fieldName]) {
                const field = document.getElementById(fieldName);
                if (field) {
                    const jsonValue = typeof data[fieldName] === 'string'
                        ? data[fieldName]
                        : JSON.stringify(data[fieldName]);
                    field.value = jsonValue;

                    console.log(`[AUTOSAVE SQL] ✓ Restored ${fieldName}`);

                    // Trigger UI updates
                    triggerDataReload(fieldName, data[fieldName]);
                    restoredFields++;
                }
            }
        });

        console.log(`[AUTOSAVE SQL] ✅ Restored ${restoredFields} fields`);
    }

    /**
     * Disparar recarga de dados nas tabelas/UI
     */
    function triggerDataReload(fieldName, data) {
        console.log(`[AUTOSAVE SQL] 🔄 Triggering UI reload for: ${fieldName}`);

        // Wait a bit for other scripts to load
        setTimeout(() => {
            switch (fieldName) {
                case 'modules_data':
                    try {
                        // Parse the data and populate window.existingModules
                        const modulesData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 modules_data raw:', data);
                        console.log('[AUTOSAVE SQL] 🔍 modules_data parsed:', modulesData);

                        if (Array.isArray(modulesData) && modulesData.length > 0) {
                            window.existingModules = modulesData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingModules with', modulesData.length, 'modules');
                            console.log('[AUTOSAVE SQL] 🔍 First module:', modulesData[0]);

                            // Call load function if available
                            if (typeof window.loadExistingModules === 'function') {
                                console.log('[AUTOSAVE SQL] 📞 Calling window.loadExistingModules()...');
                                window.loadExistingModules();
                            } else {
                                console.warn('[AUTOSAVE SQL] ⚠️ window.loadExistingModules function not found!');
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No modules data to restore (empty or null)');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading modules:', err);
                    }
                    break;
                case 'inverters_data':
                    if (typeof window.loadExistingInverters === 'function') {
                        window.loadExistingInverters();
                        console.log('[AUTOSAVE SQL] ✓ Inverters UI reloaded');
                    }
                    // Also update inverter cards
                    if (typeof window.updateInvertersCards === 'function') {
                        setTimeout(() => {
                            window.updateInvertersCards();
                            console.log('[AUTOSAVE SQL] ✓ Inverter cards updated');
                        }, 500); // Small delay to ensure data is set
                    }
                    // Also reload string tables since they depend on inverters
                    setTimeout(() => {
                        if (typeof window.loadAllInverterStringTables === 'function') {
                            console.log('[AUTOSAVE SQL] 🔄 Also reloading string tables after inverters...');
                            window.loadAllInverterStringTables();
                        }
                    }, 1000); // Wait for inverters to load first
                    break;
                case 'layouts_data':
                    try {
                        const layoutsData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 layouts_data parsed:', layoutsData);

                        if (Array.isArray(layoutsData) && layoutsData.length > 0) {
                            window.existingLayouts = layoutsData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingLayouts with', layoutsData.length, 'layouts');

                            if (typeof window.loadExistingLayouts === 'function') {
                                console.log('[AUTOSAVE SQL] 📞 Calling window.loadExistingLayouts()...');
                                window.loadExistingLayouts();
                            } else {
                                console.warn('[AUTOSAVE SQL] ⚠️ window.loadExistingLayouts function not found!');
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No layouts data to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading layouts:', err);
                    }
                    break;
                case 'protection_data':
                    try {
                        const protectionData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 protection_data parsed:', protectionData);

                        if (Array.isArray(protectionData) && protectionData.length > 0) {
                            window.existingProtection = protectionData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingProtection with', protectionData.length, 'items');

                            if (typeof window.loadExistingProtection === 'function') {
                                console.log('[AUTOSAVE SQL] 📞 Calling window.loadExistingProtection()...');
                                window.loadExistingProtection();
                            } else {
                                console.warn('[AUTOSAVE SQL] ⚠️ window.loadExistingProtection function not found!');
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No protection data to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading protection:', err);
                    }
                    break;
                case 'protection_cable_data':
                    try {
                        const cableData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 protection_cable_data parsed:', cableData);

                        if (Array.isArray(cableData) && cableData.length > 0) {
                            window.existingProtectionCables = cableData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingProtectionCables with', cableData.length, 'items');

                            if (typeof window.loadExistingProtectionCables === 'function') {
                                console.log('[AUTOSAVE SQL] 📞 Calling window.loadExistingProtectionCables()...');
                                window.loadExistingProtectionCables();
                            } else {
                                console.warn('[AUTOSAVE SQL] ⚠️ window.loadExistingProtectionCables function not found!');
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No cable data to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading cables:', err);
                    }
                    break;
                case 'clamp_measurements_data':
                    try {
                        // If the page exposes the clampMeasurements model, replace it with restored data
                        if (window && Array.isArray(data)) {
                            // Normalize shape if needed (older code used different field names)
                            window.clampMeasurements = data.map(m => ({
                                equipment: m.equipment || m.equipment || '',
                                l1_current: m.l1_current || m.l1 || m.l1_current || '',
                                l2_current: m.l2_current || m.l2 || m.l2_current || '',
                                l3_current: m.l3_current || m.l3 || m.l3_current || '',
                                match_with_meter: (m.match_with_meter || m.match_meter || m.match_with_meter === 'yes' ? 'yes' : (m.match_meter === 'yes' ? 'yes' : 'no'))
                            }));

                            console.log('[AUTOSAVE SQL] ✓ Populated window.clampMeasurements with', window.clampMeasurements.length, 'items');

                            // Sync to local variable
                            if (typeof window.loadExistingClampMeasurements === 'function') {
                                window.loadExistingClampMeasurements();
                            }

                            // Update hidden field so form submission contains the restored value
                            const hidden = document.getElementById('clamp_measurements_data');
                            if (hidden) hidden.value = JSON.stringify(window.clampMeasurements);

                            // Re-render the UI if render function exists
                            if (typeof window.renderClampMeasurementsTable === 'function') {
                                window.renderClampMeasurementsTable();
                                console.log('[AUTOSAVE SQL] ✓ Clamp Measurements UI re-rendered');
                            }

                            // Delayed authoritative reapply: in case another script rehydrates
                            // localStorage after this restore, reapply authoritative data again
                            // after a short delay to ensure server data wins.
                            setTimeout(() => {
                                try {
                                    // Overwrite hidden input and in-memory model again
                                    const hidden2 = document.getElementById('clamp_measurements_data');
                                    if (hidden2) hidden2.value = JSON.stringify(window.clampMeasurements || []);
                                    if (typeof window.renderClampMeasurementsTable === 'function') {
                                        window.renderClampMeasurementsTable();
                                        console.log('[AUTOSAVE SQL] 🔁 Reapplied authoritative clamp measurements after delay');
                                    }
                                } catch (err) {
                                    console.error('[AUTOSAVE SQL] Error during delayed clamp reapply:', err);
                                }
                            }, 350);
                        } else if (typeof window.renderClampMeasurementsTable === 'function') {
                            // Fallback: just trigger render
                            window.renderClampMeasurementsTable();
                            console.log('[AUTOSAVE SQL] ✓ Clamp Measurements UI reloaded');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] Error restoring clamp measurements:', err);
                    }
                    break;
                case 'telemetry_credential_data':
                    try {
                        const telemetryData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 telemetry_credential_data parsed:', telemetryData);

                        if (Array.isArray(telemetryData) && telemetryData.length > 0) {
                            window.existingTelemetryCredentials = telemetryData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingTelemetryCredentials with', telemetryData.length, 'items');

                            if (typeof window.loadExistingTelemetryCredentials === 'function') {
                                window.loadExistingTelemetryCredentials();
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No telemetry credentials to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading telemetry credentials:', err);
                    }
                    break;
                case 'communications_data':
                    try {
                        const commData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 communications_data parsed:', commData);

                        if (Array.isArray(commData) && commData.length > 0) {
                            window.existingCommunications = commData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingCommunications with', commData.length, 'items');

                            if (typeof window.loadExistingCommunications === 'function') {
                                window.loadExistingCommunications();
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No communications to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading communications:', err);
                    }
                    break;
                case 'telemetry_meter_data':
                    try {
                        const meterData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 telemetry_meter_data parsed:', meterData);

                        if (Array.isArray(meterData) && meterData.length > 0) {
                            window.existingTelemetryMeters = meterData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingTelemetryMeters with', meterData.length, 'items');

                            if (typeof window.loadExistingTelemetryMeters === 'function') {
                                window.loadExistingTelemetryMeters();
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No telemetry meters to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading telemetry meters:', err);
                    }
                    break;
                case 'energy_meter_data':
                    try {
                        const energyData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 energy_meter_data parsed:', energyData);

                        if (Array.isArray(energyData) && energyData.length > 0) {
                            window.existingEnergyMeters = energyData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingEnergyMeters with', energyData.length, 'items');

                            if (typeof window.loadExistingEnergyMeters === 'function') {
                                window.loadExistingEnergyMeters();
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No energy meters to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading energy meters:', err);
                    }
                    break;
                case 'punch_list_data':
                    try {
                        const punchData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 punch_list_data parsed:', punchData);

                        if (Array.isArray(punchData) && punchData.length > 0) {
                            window.existingPunchList = punchData;
                            console.log('[AUTOSAVE SQL] ✓ Populated window.existingPunchList with', punchData.length, 'items');

                            if (typeof window.loadExistingPunchList === 'function') {
                                window.loadExistingPunchList();
                            }
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No punch list items to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading punch list:', err);
                    }
                    break;
                case 'finish_photos_data':
                    try {
                        const finishData = typeof data === 'string' ? JSON.parse(data) : data;
                        console.log('[AUTOSAVE SQL] 🔍 finish_photos_data parsed:', finishData);

                        if (Array.isArray(finishData) && finishData.length > 0) {
                            // Restore checkboxes/radios state
                            const rows = document.querySelectorAll('#finish tbody tr');
                            rows.forEach((tr, idx) => {
                                if (finishData[idx]) {
                                    const item = finishData[idx];
                                    // Restore status (radio buttons)
                                    if (item.status) {
                                        const radios = tr.querySelectorAll('.finish-status-choice');
                                        radios.forEach(radio => {
                                            radio.checked = (radio.value === item.status);
                                        });
                                    }
                                    // Restore note
                                    const noteField = tr.querySelector('.finish-note');
                                    if (noteField && item.note) {
                                        noteField.value = item.note;
                                    }
                                }
                            });
                            console.log('[AUTOSAVE SQL] ✓ Restored finish photos checklist with', finishData.length, 'items');
                        } else {
                            console.log('[AUTOSAVE SQL] ℹ No finish photos data to restore');
                        }
                    } catch (err) {
                        console.error('[AUTOSAVE SQL] ❌ Error loading finish photos:', err);
                    }
                    break;
                case 'string_measurements_data':
                    // String tables depend on inverters being loaded first
                    console.log('[AUTOSAVE SQL] 🔄 String measurements data found:', data);
                    if (typeof window.loadAllInverterStringTables === 'function') {
                        console.log('[AUTOSAVE SQL] Calling loadAllInverterStringTables...');
                        window.loadAllInverterStringTables();
                        console.log('[AUTOSAVE SQL] ✓ String Measurements UI reloaded');
                        // After loading tables, restore the saved values
                        setTimeout(() => {
                            restoreStringMeasurementsFromData(data);
                        }, 2000); // Wait for tables to load
                    } else {
                        console.log('[AUTOSAVE SQL] ❌ loadAllInverterStringTables function not found');
                    }
                    break;
            }
        }, 500);
    }

    /**
     * Iniciar autosave periódico
     */
    function startAutoSave() {
        if (autosaveTimer) {
            clearInterval(autosaveTimer);
        }

        autosaveTimer = setInterval(() => {
            saveFormDataToSQL();
        }, AUTOSAVE_INTERVAL);

        console.log(`[AUTOSAVE SQL] ⏰ Timer started (every ${AUTOSAVE_INTERVAL / 1000}s)`);
    }

    /**
     * Parar autosave
     */
    function stopAutoSave() {
        if (autosaveTimer) {
            clearInterval(autosaveTimer);
            autosaveTimer = null;
            console.log('[AUTOSAVE SQL] ⏹️ Timer stopped');
        }
    }

    // Expor funções globalmente
    window.initAutoSaveSQL = initAutoSaveSQL;
    window.saveFormDataToSQL = saveFormDataToSQL;
    window.stopAutoSave = stopAutoSave;
    window.triggerAutosave = saveFormDataToSQL; // Alias para compatibilidade

    // Inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutoSaveSQL);
    } else {
        initAutoSaveSQL();
    }

})();
