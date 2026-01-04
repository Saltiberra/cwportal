/**
 * dropdown-handler.js
 * Script especializado para garantir o funcionamento correto dos dropdowns
 */

// Skip on reports page
if (window.REPORTS_PAGE) {
    console.log('[Dropdown Handler] ⏭️ Skipping initialization on reports page');
} else {

    // Base URL para requisições AJAX (ajusta automaticamente baseado no ambiente)
    // Usar caminho absoluto incluindo origem para evitar problemas
    // Auto-detect BASE_URL based on current location
    // Works both in /ComissionamentoV2/ subdirectory and at root
    let BASE_URL = window.location.pathname;

    // Remove trailing slashes and page names to get base path
    BASE_URL = BASE_URL.replace(/\/(comissionamento|reports|index)\.php.*$/, '/');
    BASE_URL = BASE_URL.replace(/\/+$/, '') + '/'; // Ensure single trailing slash

    // If only one level (root), just use /
    if (BASE_URL.split('/').filter(x => x).length === 0) {
        BASE_URL = '/';
    }

    BASE_URL = window.location.origin + BASE_URL;

    // Tornar BASE_URL global para outros scripts
    window.BASE_URL = BASE_URL;
    console.log('[Dropdown Handler] BASE_URL auto-detected:', window.BASE_URL);

    // Função para carregar modelos de equipamento baseado na marca selecionada
    function loadEquipmentModels(type, brandId, targetSelectId) {
        console.log(`Carregando modelos do tipo: ${type}, marca ID: ${brandId}, para select: #${targetSelectId}`);

        // Verificar se foi selecionada uma marca
        if (!brandId) {
            console.error('Nenhuma marca selecionada');
            clearSelect(targetSelectId);
            return;
        }

        // Todos os tipos usam o mesmo endpoint, apenas passamos o tipo como parâmetro
        const endpoint = 'ajax/get_equipment_models.php';

        // Construir URL completa com tipo e brand_id
        const url = `${BASE_URL}${endpoint}?type=${type}&brand_id=${brandId}`;

        console.log(`Fazendo requisição AJAX para: ${url} (tipo: ${type}, brand_id: ${brandId})`);
        console.log(`Fazendo requisição AJAX para: ${url}`);

        // Fazer requisição AJAX
        fetch(url)
            .then(response => {
                console.log('Resposta recebida com status:', response.status);
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.error) {
                    throw new Error(data.error);
                }
                populateModelsDropdown(targetSelectId, data);
            })
            .catch(error => {
                console.error('Error loading models:', error);
                // Limpar o select
                clearSelect(targetSelectId);

                // Adicionar opção de erro
                const select = document.getElementById(targetSelectId);
                if (select) {
                    const option = document.createElement('option');
                    option.value = "";
                    option.textContent = "Error loading models";
                    select.appendChild(option);
                }

                // Mostrar erro no console para depuração
                console.error(`Falha ao carregar modelos para ${type}, brand ID ${brandId}:`, error);
            });
    }

    // Função para limpar um select
    function clearSelect(selectId) {
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Select Model...</option>';
        } else {
            console.error(`Elemento select #${selectId} não encontrado`);
        }
    }

    // Função para popular dropdown de modelos
    function populateModelsDropdown(selectId, data) {
        const select = document.getElementById(selectId);
        if (!select) {
            console.error(`Elemento select #${selectId} não encontrado`);
            return;
        }

        // Limpar opções existentes
        select.innerHTML = '<option value="">Select Model...</option>';

        // Verificar se temos dados
        if (!data || data.length === 0) {
            console.log('Nenhum modelo encontrado para essa marca');
            return;
        }

        // Verificar o formato dos dados e adaptar
        let models = data;

        // Se os dados estiverem em formato de objeto com chave 'models', extrair
        if (data.models && Array.isArray(data.models)) {
            models = data.models;
            console.log('Dados extraídos do formato envelope');
        }

        // Se os dados estiverem em formato de objeto com outras chaves, tentar extrair
        if (!Array.isArray(models)) {
            const values = Object.values(data);
            if (values.length > 0 && Array.isArray(values[0])) {
                models = values[0];
                console.log('Dados extraídos de formato objeto');
            }
        }

        // Popular select com dados
        models.forEach(model => {
            // Verificar formato do modelo (pode ser string ou objeto)
            if (typeof model === 'object') {
                // Se for objeto, procurar propriedades id e model_name/name
                const id = model.id || model.model_id;
                const name = model.model_name || model.name || model.model;

                if (id && name) {
                    // Para inversores, incluir a potência nominal no nome
                    let displayName = name;
                    if (model.nominal_power) {
                        displayName = `${name} (${model.nominal_power}kW)`;
                    }

                    const option = new Option(displayName, id);
                    // Armazenar power_options se disponível (para PV modules)
                    if (model.power_options) {
                        option.setAttribute('data-power-options', model.power_options);
                    }
                    // Armazenar potência nominal se disponível (para inversores)
                    if (model.nominal_power) {
                        option.setAttribute('data-nominal-power', model.nominal_power);
                    }
                    select.add(option);
                }
            } else {
                // Se for string, usar como nome e valor
                const option = new Option(model, model);
                select.add(option);
            }
        });

        console.log(`Dropdown #${selectId} populado com ${models.length} modelos`);
    }

    // Inicialização quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function () {
        console.log('Inicializando handler de dropdowns...');

        // Verificar se temos os elementos necessários na página
        const moduleBrandSelect = document.getElementById('new_module_brand');

        if (moduleBrandSelect) {
            console.log('Encontrado select de marca de módulos');

            // Adicionar evento change se não existir
            if (!moduleBrandSelect.getAttribute('onchange')) {
                moduleBrandSelect.addEventListener('change', function () {
                    loadEquipmentModels('pv_module', this.value, 'new_module_model');
                });
                console.log('Adicionado evento change ao select de marca');
            }
        } else {
            console.log('Select de marca de módulos não encontrado');
        }

        // Outros inicializadores aqui...
        // Final dedupe pass for all brand selects (defensive)
        setTimeout(function () { dedupeAllBrandSelects(); }, 400);

        // Attach dedupe-on-open handlers to critical selects so duplicates are removed
        // right before the user opens the dropdown (covers late appends)
        ['new_inverter_brand', 'new_module_brand'].forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            // Use mousedown so it's cleaned before the native dropdown opens
            el.addEventListener('mousedown', function () { try { dedupeSelectOptions(id); } catch (e) { } });
            // Also handle keyboard focus
            el.addEventListener('focus', function () { try { dedupeSelectOptions(id); } catch (e) { } });
        });
    });

    // Função de diagnóstico para testar AJAX
    function testAjaxEndpoint(url, params = {}) {
        console.log(`Testando endpoint AJAX: ${url}`);

        // Construir URL com parâmetros
        const fullUrl = new URL(BASE_URL + url);
        Object.keys(params).forEach(key => fullUrl.searchParams.append(key, params[key]));

        console.log(`URL completa: ${fullUrl}`);

        // Fazer requisição de teste
        fetch(fullUrl)
            .then(response => {
                console.log(`Status da resposta: ${response.status}`);
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                return data;
            })
            .catch(error => {
                console.error('Erro no teste AJAX:', error);
            });
    }

    // Deduplicate options in a select element (keep first occurrence)
    function dedupeSelectOptions(selectId) {
        try {
            const sel = document.getElementById(selectId);
            if (!sel) return;
            const seen = new Set();
            // Iterate backwards to remove duplicates safely while keeping first occurrence
            for (let i = sel.options.length - 1; i >= 0; i--) {
                const opt = sel.options[i];
                const key = (opt.value && opt.value !== '') ? 'v:' + String(opt.value) : 't:' + String(opt.textContent).trim().toLowerCase();
                if (seen.has(key)) {
                    sel.remove(i);
                    console.debug(`dedupeSelectOptions: removed duplicate option from #${selectId}: ${opt.textContent} (${opt.value})`);
                } else {
                    seen.add(key);
                }
            }
        } catch (e) { console.warn('dedupeSelectOptions error', e); }
    }

    // Deduplicate across all brand selects on the page
    function dedupeAllBrandSelects() {
        try {
            const selects = document.querySelectorAll('select[id$="_brand"]');
            selects.forEach(s => {
                if (s && s.id) dedupeSelectOptions(s.id);
            });
        } catch (e) { console.warn('dedupeAllBrandSelects error', e); }
    }

    /**
     * Inicializar dropdowns para equipamentos - função que pode ser chamada de outros scripts
     */
    function initEquipmentDropdowns() {
        // Verificar se já foi inicializado
        if (window.dropdownsInitialized) {
            console.log('Dropdowns já inicializados, pulando initEquipmentDropdowns...');
            return;
        }

        console.log('Inicializando dropdowns de equipamentos...');

        // Configurações para cada tipo de equipamento (excluindo pv_module e associated equipment que são gerenciados por scripts específicos)
        const dropdowns = [
            { type: 'inverter', brandId: 'new_inverter_brand', modelId: 'new_inverter_model' },
            { type: 'circuit_breaker', brandId: 'protection_circuit_brand', modelId: 'protection_circuit_model' },
            { type: 'circuit_breaker', brandId: 'new_circuit_breaker_brand', modelId: 'new_circuit_breaker_model' },
            { type: 'meter', brandId: 'meter_brand', modelId: 'meter_model' }
        ];

        // Configurar cada dropdown
        dropdowns.forEach(dropdown => {
            const brandSelect = document.getElementById(dropdown.brandId);
            if (!brandSelect) {
                console.warn(`Dropdown de marca não encontrado: #${dropdown.brandId}`);
                return;
            }

            console.log(`Configurando listener para ${dropdown.type} (#${dropdown.brandId})`);

            // Remover listeners antigos para evitar duplicação
            const newBrandSelect = brandSelect.cloneNode(true);
            brandSelect.parentNode.replaceChild(newBrandSelect, brandSelect);

            // Adicionar novo listener
            newBrandSelect.addEventListener('change', function () {
                console.log(`[${dropdown.type}] Marca alterada para ID: ${this.value}`);
                if (this.value) {
                    loadEquipmentModels(dropdown.type, this.value, dropdown.modelId);
                } else {
                    clearSelect(dropdown.modelId);
                    // Limpar campo max_current se for inversor
                    if (dropdown.type === 'inverter') {
                        const maxCurrentField = document.getElementById('new_inverter_max_current');
                        if (maxCurrentField) {
                            maxCurrentField.value = '';
                        }
                    }
                }
            });

            // Carregar marcas iniciais
            loadBrands(dropdown.type, dropdown.brandId);
        });

        // Configurar event listeners específicos para modelos
        dropdowns.forEach(dropdown => {
            const modelSelect = document.getElementById(dropdown.modelId);
            if (modelSelect) {
                // Remover listeners antigos
                const newModelSelect = modelSelect.cloneNode(true);
                modelSelect.parentNode.replaceChild(newModelSelect, modelSelect);

                // Adicionar listener específico baseado no tipo
                if (dropdown.type === 'inverter') {
                    newModelSelect.addEventListener('change', function () {
                        console.log(`[Inverter] Modelo alterado para ID: ${this.value}`);
                        if (this.value) {
                            // Buscar dados do modelo selecionado
                            loadInverterModelData(this.value);
                        } else {
                            // Limpar campo max_current
                            const maxCurrentField = document.getElementById('new_inverter_max_current');
                            if (maxCurrentField) {
                                maxCurrentField.value = '';
                            }
                        }
                    });
                }
            }
        });

        // Carregar marcas para os dropdowns (excluindo pv_module e associated equipment)
        console.log('Carregando marcas para todos os dropdowns...');
        loadBrands('inverter', 'new_inverter_brand');
        loadBrands('circuit_breaker', 'protection_circuit_brand');
        loadBrands('circuit_breaker', 'new_circuit_breaker_brand');
        loadBrands('meter', 'meter_brand');

        // Marcar como inicializado
        window.dropdownsInitialized = true;
    }

    /**
     * Carregar marcas para um select
     */
    function loadBrands(type, targetId) {
        console.log(`Carregando marcas para ${type} no select #${targetId}`);
        const select = document.getElementById(targetId);
        if (!select) {
            console.error(`Select #${targetId} não encontrado para carregar marcas de ${type}`);
            return;
        }

        // Limpar opções
        select.innerHTML = '<option value="">Select Brand...</option>';

        // URL para requisição AJAX
        const url = `${BASE_URL}ajax/get_equipment_brands.php?type=${type}`;
        console.log(`Buscando marcas em: ${url}`);

        // Fazer requisição AJAX
        fetch(url)
            .then(response => {
                console.log(`Resposta para marcas de ${type}: status ${response.status}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log(`Recebidas ${data.length} marcas para ${type}:`, data);
                if (Array.isArray(data)) {
                    // Deduplicate brands by id and brand_name (defensive)
                    const uniqueMap = new Map();
                    data.forEach(b => {
                        const key = String(b.id) || String(b.brand_name).toLowerCase();
                        if (!uniqueMap.has(key)) uniqueMap.set(key, b);
                    });
                    const uniqueBrands = Array.from(uniqueMap.values());

                    // Append only unique brands
                    uniqueBrands.forEach(brand => {
                        const option = document.createElement('option');
                        option.value = brand.id;
                        option.textContent = brand.brand_name;
                        select.appendChild(option);
                    });
                    console.log(`Adicionadas ${uniqueBrands.length} opções (únicas) ao dropdown #${targetId}`);
                    // Final cleanup: dedupe select options in case other scripts appended the same entries
                    dedupeSelectOptions(targetId);
                    // Also run a global dedupe for brand selects
                    dedupeAllBrandSelects();
                    // Debug: Log actual DOM options to detect duplicates
                    try {
                        const opts = Array.from(select.options).map(o => ({ v: o.value, t: o.textContent }));
                        console.debug(`#${targetId} options post-populate:`, opts);
                    } catch (e) { console.warn('Failed to log select options', e); }
                } else {
                    console.error(`Formato de dados inválido para marcas de ${type}:`, data);
                }
            })
            .catch(error => {
                console.error(`Error loading ${type} brands:`, error);
            });
    }

    // Expor funções para uso global
    window.loadEquipmentModels = loadEquipmentModels;
    window.testAjaxEndpoint = testAjaxEndpoint;
    window.initEquipmentDropdowns = initEquipmentDropdowns;

    // Listen for cross-tab model updates (admin page creates a model and sets localStorage)
    window.addEventListener('storage', function (e) {
        if (!e.key) return;
        if (e.key === 'cw_models_updated') {
            try {
                const payload = JSON.parse(e.newValue);
                if (!payload || !payload.type) return;
                if (payload.type === 'pv_module' && payload.brand_id && payload.model_id) {
                    // Find any selects for pv_module models and reload if brand matches
                    const selects = document.querySelectorAll('select[id^="new_module_model"]');
                    selects.forEach(sel => {
                        const brandSelectId = sel.id.replace('model', 'brand');
                        const brandSelect = document.getElementById(brandSelectId);
                        const brandVal = brandSelect ? brandSelect.value : null;
                        if (String(brandVal) === String(payload.brand_id)) {
                            try { loadEquipmentModels('pv_module', payload.brand_id, sel.id); } catch (err) { console.warn('cw: failed to reload models on storage event', err); }
                        }
                    });
                }
                if (payload.type === 'meter' && payload.brand_id && payload.model_id) {
                    // Find any selects for meter models and reload if brand matches
                    const selects = document.querySelectorAll('select[id$="_meter_model"], select[id^="meter_model"]');
                    selects.forEach(sel => {
                        const brandSelectId = sel.id.replace('_model', '_brand').replace('meter_model', 'meter_brand');
                        const brandSelect = document.getElementById(brandSelectId);
                        const brandVal = brandSelect ? brandSelect.value : null;
                        if (String(brandVal) === String(payload.brand_id)) {
                            try { loadEquipmentModels('meter', payload.brand_id, sel.id); } catch (err) { console.warn('cw: failed to reload models on storage event', err); }
                        }
                    });
                }
            } catch (err) {
                console.warn('cw: invalid storage payload', err);
            }
            return;
        }
        if (e.key === 'cw_brands_updated') {
            try {
                const payload = JSON.parse(e.newValue);
                if (!payload || !payload.type) return;
                // For pv_module brand changes, reload any brand select for pv_module
                if (payload.type === 'pv_module' && payload.brand_id) {
                    const selects = document.querySelectorAll('select[id^="new_module_brand"], select[id^="pvModelsBrandFilter"], select[id^="new_module_brand_"]');
                    selects.forEach(sel => {
                        if (!sel) return;
                        // Append option if not present
                        const exists = Array.from(sel.options).some(o => String(o.value) === String(payload.brand_id));
                        if (!exists) {
                            const opt = document.createElement('option');
                            opt.value = payload.brand_id;
                            opt.textContent = payload.brand_name || 'New Brand';
                            sel.appendChild(opt);
                        }
                        // Optionally select the new brand if select is the main add brand select
                        if (sel.id === 'new_module_brand' || sel.id === 'pvModelsBrandFilter') {
                            sel.value = payload.brand_id;
                            sel.dispatchEvent(new Event('change'));
                        }
                    });
                    // Dedupe brand selects after update
                    selects.forEach(sel => dedupeSelectOptions(sel.id));
                    // Global cleanup
                    dedupeAllBrandSelects();
                }
                // For circuit_breaker brand changes, reload circuit breaker brand selects
                if (payload.type === 'circuit_breaker' && payload.brand_id) {
                    const selects = document.querySelectorAll('select[id$="_circuit_brand"], select[id^="new_circuit_breaker_brand"]');
                    selects.forEach(sel => {
                        if (!sel) return;
                        // Reload the brands for this select
                        loadBrands('circuit_breaker', sel.id);
                        // Optionally select the new brand
                        if (payload.brand_id) {
                            // Wait a bit for load to complete, then select
                            setTimeout(() => {
                                sel.value = payload.brand_id;
                                sel.dispatchEvent(new Event('change'));
                            }, 100);
                        }
                    });
                }
                // For meter brand changes, reload meter brand selects
                if (payload.type === 'meter' && payload.brand_id) {
                    const selects = document.querySelectorAll('select[id$="_meter_brand"], select[id^="meter_brand"]');
                    selects.forEach(sel => {
                        if (!sel) return;
                        // Reload the brands for this select
                        loadBrands('meter', sel.id);
                        // Optionally select the new brand
                        if (payload.brand_id) {
                            // Wait a bit for load to complete, then select
                            setTimeout(() => {
                                sel.value = payload.brand_id;
                                sel.dispatchEvent(new Event('change'));
                            }, 100);
                        }
                    });
                }
            } catch (err) { console.warn('cw: invalid brand storage payload', err); }
            return;
        }
        try {
            const payload = JSON.parse(e.newValue);
            if (!payload || !payload.type) return;
            if (payload.type === 'pv_module' && payload.brand_id) {
                // Find any selects for pv_module models and reload if brand matches
                const selects = document.querySelectorAll('select[id^="new_module_model"]');
                selects.forEach(sel => {
                    const brandSelectId = sel.id.replace('model', 'brand');
                    const brandSelect = document.getElementById(brandSelectId);
                    const brandVal = brandSelect ? brandSelect.value : null;
                    if (String(brandVal) === String(payload.brand_id)) {
                        try { loadEquipmentModels('pv_module', payload.brand_id, sel.id); } catch (err) { console.warn('cw: failed to reload models on storage event', err); }
                    }
                });
            }
            if (payload.type === 'circuit_breaker' && payload.brand_id) {
                // Find any selects for circuit_breaker models and reload if brand matches
                const selects = document.querySelectorAll('select[id$="_circuit_model"], select[id^="new_circuit_breaker_model"]');
                selects.forEach(sel => {
                    const brandSelectId = sel.id.replace('_model', '_brand').replace('new_circuit_breaker_model', 'new_circuit_breaker_brand');
                    const brandSelect = document.getElementById(brandSelectId);
                    const brandVal = brandSelect ? brandSelect.value : null;
                    if (String(brandVal) === String(payload.brand_id)) {
                        try { loadEquipmentModels('circuit_breaker', payload.brand_id, sel.id); } catch (err) { console.warn('cw: failed to reload models on storage event', err); }
                    }
                });
            }
        } catch (err) {
            console.warn('cw: invalid storage payload', err);
        }
    });

    // Also support BroadcastChannel for more reliable cross-tab messaging
    try {
        if (typeof BroadcastChannel !== 'undefined') {
            window.__cwModelsChannel = new BroadcastChannel('cw_models');
            window.__cwModelsChannel.addEventListener('message', function (ev) {
                try {
                    const payload = ev.data;
                    if (!payload || payload.type !== 'pv_module') return;
                    // If message contains model_id, reload models for the brand
                    if (payload.model_id) {
                        const selects = document.querySelectorAll('select[id^="new_module_model"]');
                        selects.forEach(sel => {
                            const brandSelectId = sel.id.replace('model', 'brand');
                            const brandSelect = document.getElementById(brandSelectId);
                            const brandVal = brandSelect ? brandSelect.value : null;
                            if (String(brandVal) === String(payload.brand_id)) {
                                try { loadEquipmentModels('pv_module', payload.brand_id, sel.id); } catch (err) { console.warn('cw: failed to reload models on broadcast', err); }
                            }
                        });
                    } else if (payload.brand_id) {
                        // Brand added - update brand selects
                        const selects = document.querySelectorAll('select[id^="new_module_brand"], select[id^="pvModelsBrandFilter"], select[id^="new_module_brand_"]');
                        selects.forEach(sel => {
                            if (!sel) return;
                            const exists = Array.from(sel.options).some(o => String(o.value) === String(payload.brand_id));
                            if (!exists) {
                                const opt = document.createElement('option');
                                opt.value = payload.brand_id;
                                opt.textContent = payload.brand_name || 'New Brand';
                                sel.appendChild(opt);
                            }
                            if (sel.id === 'new_module_brand' || sel.id === 'pvModelsBrandFilter') {
                                sel.value = payload.brand_id;
                                sel.dispatchEvent(new Event('change'));
                            }
                        });
                        // Dedupe brand selects after broadcast update
                        selects.forEach(sel => dedupeSelectOptions(sel.id));
                        // Global cleanup
                        dedupeAllBrandSelects();
                    }
                    selects.forEach(sel => {
                        const brandSelectId = sel.id.replace('model', 'brand');
                        const brandSelect = document.getElementById(brandSelectId);
                        const brandVal = brandSelect ? brandSelect.value : null;
                        if (String(brandVal) === String(payload.brand_id)) {
                            try { loadEquipmentModels('pv_module', payload.brand_id, sel.id); } catch (err) { console.warn('cw: failed to reload models on broadcast', err); }
                        }
                    });
                } catch (err) { console.warn('cw: invalid broadcast payload', err); }
            });
        }
    } catch (e) { /* ignore */ }

    // When the page regains visibility (user switches tab), attempt to reload relevant selects
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            // reload pv_module selects for currently selected brands
            const selects = document.querySelectorAll('select[id^="new_module_model"]');
            selects.forEach(sel => {
                const brandSelectId = sel.id.replace('model', 'brand');
                const brandSelect = document.getElementById(brandSelectId);
                const brandVal = brandSelect ? brandSelect.value : null;
                if (brandVal) {
                    try { loadEquipmentModels('pv_module', brandVal, sel.id); } catch (err) { /* ignore */ }
                }
            });
        }
    });

    /**
     * Carregar dados do modelo de inversor selecionado
     */
    function loadInverterModelData(modelId) {
        console.log('[Dropdown Handler] Carregando dados do modelo de inversor:', modelId);

        const url = `${BASE_URL}ajax/get_inverter_data.php?inverter_id=${modelId}`;
        console.log('[Dropdown Handler] URL da requisição:', url);

        fetch(url)
            .then(response => {
                console.log('[Dropdown Handler] Status da resposta:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('[Dropdown Handler] Dados recebidos:', data);
                if (data && data.max_output_current) {
                    // Popular o campo max_current com o valor do banco
                    const maxCurrentField = document.getElementById('new_inverter_max_current');
                    if (maxCurrentField) {
                        maxCurrentField.value = data.max_output_current;
                        console.log('[Dropdown Handler] Campo max_current populado com:', data.max_output_current);
                    }
                } else {
                    console.warn('[Dropdown Handler] Dados do modelo não encontrados ou max_output_current vazio');
                    const maxCurrentField = document.getElementById('new_inverter_max_current');
                    if (maxCurrentField) {
                        maxCurrentField.value = '';
                    }
                }
            })
            .catch(error => {
                console.error('[Dropdown Handler] Error loading model data:', error);
                const maxCurrentField = document.getElementById('new_inverter_max_current');
                if (maxCurrentField) {
                    maxCurrentField.value = '';
                }
            });
    }

    // Expor a função globalmente
    window.loadInverterModelData = loadInverterModelData;

} // End of !window.REPORTS_PAGE check