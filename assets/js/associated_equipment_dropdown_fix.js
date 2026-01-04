/**
 * SOLUÇÃO PARA PROBLEMAS DOS DROPDOWNS
 * 
 * Este script é carregado diretamente no index.php para forçar a inicialização
 * correta dos dropdowns de Associated Equipment quando a aba é ativada.
 */

// Aguardar o DOM estar pronto
document.addEventListener('DOMContentLoaded', function () {
    console.log('[Dropdown Fix] Inicializando solução para dropdowns Associated Equipment');

    // Skip on reports page
    if (window.REPORTS_PAGE) {
        console.log('[Dropdown Fix] ⏭️ Skipping initialization on reports page');
        return;
    }

    // Verificar se a aba de Associated Equipment existe
    const accessoriesTab = document.getElementById('accessories-tab');
    if (!accessoriesTab) {
        console.log('[Dropdown Fix] Aba accessories-tab não encontrada');
        return;
    }

    // Adicionar um listener para quando a aba for ativada
    accessoriesTab.addEventListener('click', function () {
        console.log('[Dropdown Fix] Aba accessories-tab clicada, inicializando em 100ms...');

        // Esperar um pouco para a aba ser ativada antes de inicializar os dropdowns
        setTimeout(function () {
            // Definir BASE_URL auto-detectando
            if (!window.BASE_URL) {
                let detectedURL = window.location.pathname;
                detectedURL = detectedURL.replace(/\/(comissionamento|reports|index)\.php.*$/, '/');
                detectedURL = detectedURL.replace(/\/+$/, '') + '/';
                if (detectedURL.split('/').filter(x => x).length === 0) {
                    detectedURL = '/';
                }
                window.BASE_URL = window.location.origin + detectedURL;
                console.log('[Dropdown Fix] BASE_URL auto-detected:', window.BASE_URL);
            }

            console.log('[Dropdown Fix] Carregando Circuit Breaker brands...');
            loadEquipmentBrandsDirectly('circuit_breaker', 'new_circuit_breaker_brand', 'new_circuit_breaker_model');

            console.log('[Dropdown Fix] Carregando Differential brands...');
            loadEquipmentBrandsDirectly('differential', 'new_differential_brand', 'new_differential_model');

            console.log('[Dropdown Fix] Carregando Cable brands...');
            loadEquipmentBrandsDirectly('cable', 'new_cable_brand', 'new_cable_model');
        }, 100);
    });

    // Também usar o evento Bootstrap se disponível
    accessoriesTab.addEventListener('shown.bs.tab', function () {
        console.log('[Dropdown Fix] Evento shown.bs.tab acionado');

        // Definir BASE_URL auto-detectando
        if (!window.BASE_URL) {
            let detectedURL = window.location.pathname;
            detectedURL = detectedURL.replace(/\/(comissionamento|reports|index)\.php.*$/, '/');
            detectedURL = detectedURL.replace(/\/+$/, '') + '/';
            if (detectedURL.split('/').filter(x => x).length === 0) {
                detectedURL = '/';
            }
            window.BASE_URL = window.location.origin + detectedURL;
            console.log('[Dropdown Fix] BASE_URL auto-detected:', window.BASE_URL);
        }

        loadEquipmentBrandsDirectly('circuit_breaker', 'new_circuit_breaker_brand', 'new_circuit_breaker_model');
        loadEquipmentBrandsDirectly('differential', 'new_differential_brand', 'new_differential_model');
        loadEquipmentBrandsDirectly('cable', 'new_cable_brand', 'new_cable_model');
    });
});

// Função para carregar marcas diretamente via AJAX
function loadEquipmentBrandsDirectly(type, brandSelectId, modelSelectId) {
    const brandSelect = document.getElementById(brandSelectId);
    if (!brandSelect) {
        console.error(`[Dropdown Fix] Elemento #${brandSelectId} não encontrado`);
        return;
    }

    // Carregar as marcas via AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `${window.BASE_URL}ajax/get_equipment_brands.php?type=${type}`, true);

    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const brands = JSON.parse(xhr.responseText);
                console.log(`[Dropdown Fix] ${brands.length} marcas carregadas para ${type}`);

                // Limpar opções existentes
                brandSelect.innerHTML = '<option value="">Select Brand...</option>';

                // Adicionar as marcas
                brands.forEach(brand => {
                    const option = document.createElement('option');
                    option.value = brand.id;
                    option.textContent = brand.brand_name;
                    brandSelect.appendChild(option);
                });

                // Configurar evento change para carregar modelos
                brandSelect.addEventListener('change', function () {
                    const brandId = this.value;
                    if (brandId) {
                        loadEquipmentModelsDirectly(type, brandId, modelSelectId);
                    } else {
                        // Limpar dropdown de modelos
                        const modelSelect = document.getElementById(modelSelectId);
                        if (modelSelect) {
                            modelSelect.innerHTML = '<option value="">Select Model...</option>';
                        }
                    }
                });

            } catch (error) {
                console.error(`[Dropdown Fix] Error processing JSON for ${type}:`, error);
            }
        } else {
            console.error(`[Dropdown Fix] Erro HTTP ${xhr.status} ao carregar marcas para ${type}`);
        }
    };

    xhr.onerror = function () {
        console.error(`[Dropdown Fix] Erro de rede ao carregar marcas para ${type}`);
    };

    xhr.send();
}

// Função para carregar modelos diretamente via AJAX
function loadEquipmentModelsDirectly(type, brandId, modelSelectId) {
    const modelSelect = document.getElementById(modelSelectId);
    if (!modelSelect) {
        console.error(`[Dropdown Fix] Elemento #${modelSelectId} não encontrado`);
        return;
    }

    // Indicar carregamento
    modelSelect.innerHTML = '<option value="">Loading models...</option>';

    // Carregar os modelos via AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `${window.BASE_URL}ajax/get_equipment_models.php?type=${type}&brand_id=${brandId}`, true);

    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const models = JSON.parse(xhr.responseText);
                console.log(`[Dropdown Fix] ${models.length} modelos carregados para ${type}, marca ID ${brandId}`);

                // Limpar opções existentes
                modelSelect.innerHTML = '<option value="">Select Model...</option>';

                // Adicionar os modelos
                models.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model.id;
                    option.textContent = model.model_name;
                    modelSelect.appendChild(option);
                });

            } catch (error) {
                console.error(`[Dropdown Fix] Error processing JSON for ${type} models:`, error);
                modelSelect.innerHTML = '<option value="">Error loading models</option>';
            }
        } else {
            console.error(`[Dropdown Fix] Erro HTTP ${xhr.status} ao carregar modelos para ${type}`);
            modelSelect.innerHTML = '<option value="">Error loading models</option>';
        }
    };

    xhr.onerror = function () {
        console.error(`[Dropdown Fix] Erro de rede ao carregar modelos para ${type}`);
        modelSelect.innerHTML = '<option value="">Network error</option>';
    };

    xhr.send();
}