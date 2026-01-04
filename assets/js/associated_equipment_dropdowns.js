/**
 * associated_equipment_dropdowns.js
 * Script específico para garantir o funcionamento correto dos dropdowns na seção "Associated Equipment"
 */

console.log('[Associated Equipment] Dropdowns script loaded');

// Skip on reports page
if (window.REPORTS_PAGE) {
    console.log('[Associated Equipment] ⏭️ Skipping initialization on reports page');
} else {

    // Base URL para requisições AJAX já definida em dropdown-handler.js
    console.log('[Associated Equipment] BASE_URL disponível:', typeof window.BASE_URL, window.BASE_URL);

    // Expor funções globalmente para debug
    window.initAssociatedEquipmentDropdowns = initAssociatedEquipmentDropdowns;
    window.setupEquipmentDropdowns = setupEquipmentDropdowns;
    window.loadEquipmentBrands = loadEquipmentBrands;
    window.loadEquipmentModels = loadEquipmentModels;

    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScript);
    } else {
        initScript();
    }

    function initScript() {
        console.log('[Associated Equipment] DOM fully loaded');
        initAssociatedEquipmentDropdowns();
    }
} // End of !window.REPORTS_PAGE block

// Equipment dropdown change handlers
function attachEquipmentChangeHandlers(type) {
    // Load brands for this equipment type

    // Don't run on reports.php (only on pages with the form)
    if (!document.getElementById('commissioningForm')) {
        console.log('[Associated Equipment] Form not found - skipping initialization on this page');
        return;
    }

    // Check if elements exist before initializing
    let attempts = 0;
    const maxAttempts = 50; // Stop after 50 attempts (5 seconds)

    const checkElements = () => {
        attempts++;
        const elements = [
            'new_circuit_breaker_brand', 'new_circuit_breaker_model',
            'new_differential_brand', 'new_differential_model',
            'new_cable_brand', 'new_cable_model'
        ];

        const allExist = elements.every(id => document.getElementById(id));
        console.log('[Associated Equipment] Todos os elementos existem:', allExist, `(attempt ${attempts}/${maxAttempts})`);

        if (allExist) {
            console.log('[Associated Equipment] Inicializando dropdowns...');
            initAssociatedEquipmentDropdowns();
        } else if (attempts < maxAttempts) {
            setTimeout(checkElements, 100);
        } else {
            console.log('[Associated Equipment] Máximo de tentativas atingido, parando verificação');
        }
    };

    // Aguardar um pouco e verificar elementos
    setTimeout(checkElements, 100);

    // Também inicializar quando a aba "Associated Equipment" for mostrada
    const accessoriesTab = document.getElementById('accessories-tab');
    if (accessoriesTab) {
        // Usar tanto o evento Bootstrap quanto click como backup
        accessoriesTab.addEventListener('shown.bs.tab', function () {
            console.log('[Associated Equipment] Aba Associated Equipment mostrada (Bootstrap event)');
            initIfNeeded();
        });
        accessoriesTab.addEventListener('click', function () {
            console.log('[Associated Equipment] Aba Associated Equipment clicada');
            // Aguardar um pouco para o tab ser mostrado
            setTimeout(initIfNeeded, 100);
        });
    }

    function initIfNeeded() {
        const elements = [
            'new_circuit_breaker_brand', 'new_circuit_breaker_model',
            'new_differential_brand', 'new_differential_model',
            'new_cable_brand', 'new_cable_model'
        ];

        const allExist = elements.every(id => document.getElementById(id));
        if (allExist && !window.associatedEquipmentInitialized) {
            console.log('[Associated Equipment] Inicializando dropdowns na mudança de aba...');
            initAssociatedEquipmentDropdowns();
        }
    }
}

/**
 * Inicializar os dropdowns na seção "Associated Equipment"
 */
function initAssociatedEquipmentDropdowns() {
    // Evitar inicialização múltipla
    if (window.associatedEquipmentInitialized) {
        console.log('[Associated Equipment] Dropdowns já inicializados, pulando...');
        return;
    }

    console.log('[Associated Equipment] Configurando dropdowns');

    // Configurações para cada tipo de equipamento
    const equipmentTypes = [
        { type: 'circuit_breaker', brandId: 'new_circuit_breaker_brand', modelId: 'new_circuit_breaker_model' },
        { type: 'differential', brandId: 'new_differential_brand', modelId: 'new_differential_model' },
        { type: 'cable', brandId: 'new_cable_brand', modelId: 'new_cable_model' }
    ];

    // Configurar cada tipo de equipamento
    equipmentTypes.forEach(equipment => {
        setupEquipmentDropdowns(equipment.type, equipment.brandId, equipment.modelId);
    });

    // Marcar como inicializado
    window.associatedEquipmentInitialized = true;
}

/**
 * Configurar dropdowns para um tipo específico de equipamento
 */
function setupEquipmentDropdowns(type, brandSelectId, modelSelectId) {
    console.log(`[Associated Equipment] Configurando dropdowns para ${type}`);

    // Obter referências aos elementos
    const brandSelect = document.getElementById(brandSelectId);
    const modelSelect = document.getElementById(modelSelectId);

    // Verificar se os elementos existem na página
    if (!brandSelect || !modelSelect) {
        console.log(`[Associated Equipment] Dropdowns para ${type} não encontrados`);
        return;
    }

    // Carregar as marcas para este tipo de equipamento
    loadEquipmentBrands(type, brandSelectId);

    // Remover listeners existentes para evitar duplicação
    const newBrandSelect = brandSelect.cloneNode(true);
    brandSelect.parentNode.replaceChild(newBrandSelect, brandSelect);

    // Adicionar evento para quando a marca for alterada
    newBrandSelect.addEventListener('change', function () {
        const brandId = this.value;
        console.log(`[Associated Equipment] ${type}: Marca alterada para ID ${brandId}`);

        if (brandId) {
            // Carregar os modelos para a marca selecionada
            loadEquipmentModels(type, brandId, modelSelectId);
        } else {
            // Limpar o dropdown de modelos
            clearModelSelect(modelSelectId);
        }
    });

    console.log(`[Associated Equipment] Event listener configured for #${brandSelectId}`);
}

/**
 * Load equipment brands
 */
function loadEquipmentBrands(type, selectId) {
    console.log(`[Associated Equipment] Loading brands for ${type}`);

    if (!window.BASE_URL) {
        console.error('[Associated Equipment] BASE_URL não definido!');
        return;
    }

    const select = document.getElementById(selectId);
    if (!select) return;

    // Limpar opções existentes
    select.innerHTML = '<option value="">Select Brand...</option>';

    // Verificar e ajustar BASE_URL se necessário (auto-detect)
    if (!window.BASE_URL || window.BASE_URL.includes('undefined')) {
        // Auto-detect BASE_URL based on current location
        let detectedURL = window.location.pathname;
        detectedURL = detectedURL.replace(/\/(comissionamento|reports|index)\.php.*$/, '/');
        detectedURL = detectedURL.replace(/\/+$/, '') + '/';
        if (detectedURL.split('/').filter(x => x).length === 0) {
            detectedURL = '/';
        }
        window.BASE_URL = window.location.origin + detectedURL;
        console.log(`[Associated Equipment] BASE_URL auto-detected: ${window.BASE_URL}`);
    }

    // URL para obter as marcas
    const url = `${window.BASE_URL}ajax/get_equipment_brands.php?type=${type}`;

    console.log(`[Associated Equipment] Fazendo requisição XMLHttpRequest para: ${url}`);

    // Usar XMLHttpRequest em vez de fetch para melhor compatibilidade
    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.timeout = 10000; // 10 segundos timeout

    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const brands = JSON.parse(xhr.responseText);
                console.log(`[Associated Equipment] Recebidas ${brands.length} marcas para ${type}:`, brands);

                // Adicionar opções ao select
                brands.forEach(brand => {
                    const option = document.createElement('option');
                    option.value = brand.id;
                    option.textContent = brand.brand_name;
                    select.appendChild(option);
                });

                console.log(`[Associated Equipment] Dropdown #${selectId} populado com marcas`);
            } catch (error) {
                console.error(`[Associated Equipment] Error parsing JSON for ${type}:`, error);
                select.innerHTML = '<option value="">Error loading brands</option>';
            }
        } else {
            console.error(`[Associated Equipment] Erro HTTP ${xhr.status} ao carregar marcas para ${type}`);
            select.innerHTML = '<option value="">Erro ao carregar marcas</option>';
        }
    };

    xhr.onerror = function () {
        console.error(`[Associated Equipment] Erro de rede ao carregar marcas para ${type}`);
        select.innerHTML = '<option value="">Erro de rede</option>';
    };

    xhr.ontimeout = function () {
        console.error(`[Associated Equipment] Timeout ao carregar marcas para ${type}`);
        select.innerHTML = '<option value="">Timeout</option>';
    };

    xhr.send();
}

/**
 * Carregar modelos de equipamentos baseado na marca selecionada
 */
function loadEquipmentModels(type, brandId, selectId) {
    console.log(`[Associated Equipment] Carregando modelos para ${type}, marca ID: ${brandId}`);

    if (!window.BASE_URL) {
        console.error('[Associated Equipment] BASE_URL não definido!');
        return;
    }

    const select = document.getElementById(selectId);
    if (!select) {
        console.error(`[Associated Equipment] Elemento #${selectId} não encontrado no DOM`);
        return;
    }

    // Indicar carregamento
    select.innerHTML = '<option value="">Loading models...</option>';

    // Verificar e ajustar BASE_URL se necessário
    if (!window.BASE_URL) {
        window.BASE_URL = window.location.origin + '/ComissionamentoV2/';
        console.log(`[Associated Equipment] BASE_URL não estava definido, definido como: ${window.BASE_URL}`);
    }

    // URL para obter os modelos
    const url = `${window.BASE_URL}ajax/get_equipment_models.php?type=${type}&brand_id=${brandId}`;

    console.log(`[Associated Equipment] Fazendo requisição XMLHttpRequest para: ${url}`);

    // Usar XMLHttpRequest em vez de fetch para melhor compatibilidade
    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.timeout = 10000; // 10 segundos timeout

    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const models = JSON.parse(xhr.responseText);
                console.log(`[Associated Equipment] Dados recebidos para ${type}:`, models);

                // Limpar o select
                select.innerHTML = '<option value="">Select Model...</option>';

                // Verificar formato dos dados e processar conforme necessário
                let processedModels = models;

                // Se recebeu um objeto com erro
                if (models && models.error) {
                    throw new Error(`Erro do servidor: ${models.error}`);
                }

                // Adicionar os modelos ao select
                if (Array.isArray(processedModels) && processedModels.length > 0) {
                    processedModels.forEach(model => {
                        // Verificar formato do modelo (pode ser string ou objeto)
                        if (typeof model === 'object') {
                            // Se for objeto, procurar propriedades id e model_name/name
                            const id = model.id || model.model_id;
                            const name = model.model_name || model.name || model.model;

                            if (id && name) {
                                const option = document.createElement('option');
                                option.value = id;
                                option.textContent = name;
                                select.appendChild(option);
                            }
                        } else {
                            // Se for string, usar como nome e valor
                            const option = document.createElement('option');
                            option.value = model;
                            option.textContent = model;
                            select.appendChild(option);
                        }
                    });

                    console.log(`[Associated Equipment] Adicionados ${processedModels.length} modelos ao dropdown #${selectId}`);

                    // Disparar evento change para notificar outros scripts
                    const event = new Event('change');
                    select.dispatchEvent(event);
                } else {
                    console.log(`[Associated Equipment] Nenhum modelo encontrado para esta marca`);
                    select.innerHTML = '<option value="">No models available</option>';
                }
            } catch (error) {
                console.error(`[Associated Equipment] Error parsing JSON for ${type}:`, error);
                select.innerHTML = '<option value="">Error loading models</option>';
            }
        } else {
            console.error(`[Associated Equipment] Erro HTTP ${xhr.status} ao carregar modelos para ${type}`);
            select.innerHTML = '<option value="">Error loading models</option>';
        }
    };

    xhr.onerror = function () {
        console.error(`[Associated Equipment] Erro de rede ao carregar modelos para ${type}`);
        select.innerHTML = '<option value="">Network error</option>';
    };

    xhr.ontimeout = function () {
        console.error(`[Associated Equipment] Timeout ao carregar modelos para ${type}`);
        select.innerHTML = '<option value="">Timeout</option>';
    };

    xhr.send();
}

/**
 * Limpar um select de modelos
 */
function clearModelSelect(selectId) {
    const select = document.getElementById(selectId);
    if (select) {
        select.innerHTML = '<option value="">Select Model...</option>';
    }
}