/**
 * assets/js/mppt_manager.js
 * Gerenciador completo de MPPT String Measurements
 * 
 * Funcionalidades:
 * - Carrega medições do SQL
 * - Autosave ao editar (debounce 800ms)
 * - Salva final
 * - Exibe em Generate Report
 */

(function () {
    'use strict';

    // Configuração
    const CONFIG = {
        API_BASE: 'ajax/mppt_crud.php',
        AUTOSAVE_DELAY: 800, // ms
        DEBOUNCE_TIMERS: {}
    };

    // Estado global
    const STATE = {
        reportId: null,
        measurements: [], // Array de medições do SQL
        isDirty: false
    };

    /**
     * Inicializar gerenciador de MPPT
     */
    window.initMPPTManager = function (reportId) {
        STATE.reportId = reportId;
        console.log('[MPPT_MANAGER] ✅ Inicializado para report_id=' + reportId);

        // Carregar medições do SQL
        loadMeasurementsFromSQL();

        // Atachar listeners aos inputs
        attachMPPTListeners();
    };

    /**
     * Carregar medições do SQL
     */
    function loadMeasurementsFromSQL() {
        if (!STATE.reportId) {
            console.warn('[MPPT_MANAGER] ⚠️ report_id não disponível');
            return;
        }

        console.log('[MPPT_MANAGER] 📥 Carregando medições do SQL...');

        fetch(CONFIG.API_BASE + '?action=load&report_id=' + STATE.reportId)
            .then(response => {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    console.warn('[MPPT_MANAGER] ⚠️ Erro:', data.error);
                    return;
                }

                STATE.measurements = data.measurements || [];
                console.log('[MPPT_MANAGER] ✅ Carregadas ' + STATE.measurements.length + ' medições');

                // Preencher formulário com dados carregados
                populateFormWithMeasurements();
            })
            .catch(error => {
                console.error('[MPPT_MANAGER] ❌ Erro ao carregar:', error);
            });
    }

    /**
     * Preencher campos do formulário com medições do SQL
     */
    function populateFormWithMeasurements() {
        console.log('[MPPT_MANAGER] 📝 Preenchendo formulário com ' + STATE.measurements.length + ' medições...');

        STATE.measurements.forEach(function (measurement) {
            const inv = measurement.inverter_index;
            const mppt = measurement.mppt;
            const str = measurement.string_num;

            const metrics = ['voc', 'isc', 'vmp', 'imp', 'rins', 'irr', 'temp', 'rlo', 'notes', 'current'];

            metrics.forEach(function (metric) {
                const inputId = 'string_' + metric + '_' + inv + '_' + mppt + '_' + str;
                const input = document.getElementById(inputId);

                if (input && measurement[metric] !== null && measurement[metric] !== undefined) {
                    input.value = measurement[metric];
                    console.log('[MPPT_MANAGER] ✅ Preenchido ' + inputId + ' = ' + measurement[metric]);
                }
            });
        });

        console.log('[MPPT_MANAGER] ✅ Formulário preenchido com sucesso');
    }

    /**
     * Atachar listeners aos inputs MPPT
     */
    function attachMPPTListeners() {
        console.log('[MPPT_MANAGER] 🔌 Attachando listeners aos inputs...');

        const inputs = document.querySelectorAll('input[id^="string_"]');
        console.log('[MPPT_MANAGER] Encontrados ' + inputs.length + ' inputs MPPT');

        inputs.forEach(function (input) {
            // Input event com debounce
            input.addEventListener('input', function () {
                clearTimeout(CONFIG.DEBOUNCE_TIMERS[input.id]);
                CONFIG.DEBOUNCE_TIMERS[input.id] = setTimeout(function () {
                    autosaveField(input);
                }, CONFIG.AUTOSAVE_DELAY);
            });

            // Blur event - salva imediatamente
            input.addEventListener('blur', function () {
                clearTimeout(CONFIG.DEBOUNCE_TIMERS[input.id]);
                autosaveField(input);
            });
        });

        console.log('[MPPT_MANAGER] ✅ Listeners attachados');
    }

    /**
     * Autosave de um campo individual
     */
    function autosaveField(input) {
        if (!STATE.reportId) {
            console.log('[MPPT_MANAGER] ⏭️ Skipping autosave - no report_id');
            return;
        }

        const parsed = parseStringInputId(input.id);
        if (!parsed || !input.value) {
            return;
        }

        const payload = {
            report_id: STATE.reportId,
            inverter_index: parsed.inverter_index,
            mppt: parsed.mppt,
            string_num: parsed.string_num,
            field: parsed.metric,
            value: input.value
        };

        console.log('[MPPT_MANAGER] 💾 Autosaving: ' + input.id + ' = ' + input.value);

        fetch(CONFIG.API_BASE + '?action=autosave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[MPPT_MANAGER] ✅ Salvo: ' + input.id);
                    input.style.borderColor = '#28a745';
                    setTimeout(() => { input.style.borderColor = ''; }, 300);
                    STATE.isDirty = true;
                } else {
                    console.warn('[MPPT_MANAGER] ❌ Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('[MPPT_MANAGER] ❌ Erro de rede:', error);
            });
    }

    /**
     * Parse do ID do input para extrair componentes
     * Formato: string_{metric}_{inverter}_{mppt}_{string}
     */
    function parseStringInputId(inputId) {
        const match = inputId.match(/^string_(.+?)_(\d+)_(\d+)_(\d+)$/);
        if (!match) return null;

        return {
            metric: match[1],
            inverter_index: parseInt(match[2]),
            mppt: parseInt(match[3]),
            string_num: parseInt(match[4])
        };
    }

    /**
     * Salvar todas as medições (para Generate Report)
     */
    window.saveMPPTMeasurements = function () {
        console.log('[MPPT_MANAGER] 💾 Salvando todas as medições...');

        const inputs = document.querySelectorAll('input[id^="string_"]');
        let measurements = {};

        inputs.forEach(function (input) {
            if (!input.value) return;

            const parsed = parseStringInputId(input.id);
            if (!parsed) return;

            const key = parsed.inverter_index + '_' + parsed.mppt + '_' + parsed.string_num;

            if (!measurements[key]) {
                measurements[key] = {
                    report_id: STATE.reportId,
                    inverter_index: parsed.inverter_index,
                    mppt: parsed.mppt,
                    string_num: parsed.string_num,
                    voc: '', isc: '', vmp: '', imp: '',
                    rins: '', irr: '', temp: '', rlo: '', notes: '', current: ''
                };
            }

            measurements[key][parsed.metric] = input.value;
        });

        // Converter para array
        const data = Object.values(measurements);

        console.log('[MPPT_MANAGER] 📤 Enviando ' + data.length + ' medições para salvar...');

        // Salvar cada medição
        let saved = 0;
        data.forEach(function (measurement) {
            fetch(CONFIG.API_BASE + '?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(measurement)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) saved++;
                    console.log('[MPPT_MANAGER] ✅ Salva ' + saved + '/' + data.length);
                })
                .catch(error => {
                    console.error('[MPPT_MANAGER] ❌ Erro:', error);
                });
        });

        console.log('[MPPT_MANAGER] ✅ Medições salvas com sucesso');
        return true;
    };

    /**
     * Gerar relatório HTML com medições MPPT
     */
    window.generateMPPTReport = function () {
        console.log('[MPPT_MANAGER] 📊 Gerando relatório MPPT...');

        if (STATE.measurements.length === 0) {
            return '<p><em>Nenhuma medição MPPT registrada.</em></p>';
        }

        let html = '<h3>📊 Medições MPPT String</h3>';
        html += '<table style="width:100%; border-collapse: collapse; font-size: 12px;">';
        html += '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #333;">';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Inversor</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">MPPT</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">String</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Voc (V)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Isc (A)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Vmp (V)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Imp (A)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Rins (Ω)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Irr (W/m²)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Temp (°C)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Current (A)</th>';
        html += '<th style="border: 1px solid #ddd; padding: 8px;">Observações</th>';
        html += '</tr></thead><tbody>';

        STATE.measurements.forEach(function (m) {
            html += '<tr style="border-bottom: 1px solid #ddd;">';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' + m.inverter_index + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' + m.mppt + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' + m.string_num + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.voc || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.isc || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.vmp || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.imp || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.rins || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.irr || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.temp || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' + (m.current || '-') + '</td>';
            html += '<td style="border: 1px solid #ddd; padding: 8px;">' + (m.notes || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        console.log('[MPPT_MANAGER] ✅ Relatório gerado com ' + STATE.measurements.length + ' linhas');
        return html;
    };

    // Expor estado para debug
    window.MPPT_STATE = STATE;

})();
