/**
 * module_table.js
 * Script unificado para gerenciar dropdowns de equipamentos e tabelas
 */

// Skip on reports page
if (window.REPORTS_PAGE) {
    console.log('[Module Table] ⏭️ Skipping initialization on reports page');
} else {

    // Variáveis globais para armazenar as listas
    // modulesList agora está definida no arquivo main.js
    // invertersList agora está definida no arquivo inverter_functions.js

    // Função unificada para buscar marcas
    function fetchBrands(type, targetDropdownId) {
        // Determinar a URL base do projeto - detectar do caminho atual
        let baseUrl = '';
        const pathParts = window.location.pathname.split('/').filter(p => p);
        if (pathParts.length > 0 && pathParts[0] !== 'ajax') {
            baseUrl = '/' + pathParts[0] + '/';
        }

        // URL primária para buscar marcas
        let url = `${baseUrl}ajax/dropdown_handler.php?action=getBrands&type=${type}`;

        // URL de fallback (compatível com outros arquivos)
        let fallbackUrl = `${baseUrl}ajax/get_equipment_brands.php?type=${type}`;

        console.log(`Fetching brands for ${type} to populate ${targetDropdownId}`);

        // Primeiro tentar com dropdown_handler.php
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Primary endpoint failed, trying fallback');
                }
                return response.json();
            })
            .then(responseData => {
                // dropdown_handler.php returns {success, options, type}
                // options array has {id, name} format
                const data = responseData.options || responseData;
                populateBrandDropdown(data, targetDropdownId, type);
            })
            .catch(error => {
                console.warn(`Primary endpoint error: ${error.message}. Trying fallback URL...`);

                // Tentar fallback
                fetch(fallbackUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Fallback HTTP error: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        // Processar dados (pode ter formatos diferentes)
                        const data = response.data || response;
                        populateBrandDropdown(data, targetDropdownId, type);
                    })
                    .catch(fallbackError => {
                        console.error('Error loading brands from both endpoints:', fallbackError);
                        // Don't show toast - the dropdown will display the error state
                    });
            });
    }

    // Função auxiliar para popular dropdown de marcas
    function populateBrandDropdown(data, targetDropdownId, type) {
        const dropdown = document.getElementById(targetDropdownId);
        if (dropdown && Array.isArray(data)) {
            dropdown.innerHTML = '<option value="">Select Brand...</option>';
            data.forEach(brand => {
                // Support both {id, brand_name} and {id, name} formats
                const brandName = brand.brand_name || brand.name;
                dropdown.innerHTML += `<option value="${brand.id}">${brandName}</option>`;
            });
            console.log(`${data.length} brands loaded for ${type}`);
        } else {
            console.error('Invalid data format or dropdown not found:', data);
        }
    }

    // Função unificada para buscar modelos
    function fetchModels(type, brandId, targetDropdownId) {
        // Determinar a URL base do projeto - detectar do caminho atual
        let baseUrl = '';
        const pathParts = window.location.pathname.split('/').filter(p => p);
        if (pathParts.length > 0 && pathParts[0] !== 'ajax') {
            baseUrl = '/' + pathParts[0] + '/';
        }

        // URL primária para buscar modelos
        let url = `${baseUrl}ajax/dropdown_handler.php?action=getModels&type=${type}&brand_id=${brandId}`;

        // URL de fallback (usada por main.js)
        let fallbackUrl = `${baseUrl}ajax/get_equipment_models.php?type=${type}&brand_id=${brandId}`;

        console.log(`Fetching models for ${type} (brand ID: ${brandId}) to populate ${targetDropdownId}`);

        // Primeiro tentar com dropdown_handler.php
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Primary endpoint failed, trying fallback');
                }
                return response.json();
            })
            .then(responseData => {
                // dropdown_handler.php returns {success, options, type}
                const data = responseData.options || responseData;
                populateModelDropdown(data, targetDropdownId, type, brandId);
            })
            .catch(error => {
                console.warn(`Primary endpoint error: ${error.message}. Trying fallback URL...`);

                // Tentar fallback
                fetch(fallbackUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Fallback HTTP error: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        // Processar dados (pode ter formatos diferentes)
                        const data = response.data || response;
                        populateModelDropdown(data, targetDropdownId, type, brandId);
                    })
                    .catch(fallbackError => {
                        console.error('Error loading models from both endpoints:', fallbackError);
                        // Don't show toast - the dropdown will display the error state

                        // Habilitar dropdown mesmo com erro
                        const dropdown = document.getElementById(targetDropdownId);
                        if (dropdown) dropdown.disabled = false;
                    });
            });
    }

    // Função auxiliar para popular dropdown de modelos
    function populateModelDropdown(data, targetDropdownId, type, brandId) {
        const dropdown = document.getElementById(targetDropdownId);
        if (dropdown && Array.isArray(data)) {
            dropdown.innerHTML = '<option value="">Select Model...</option>';
            data.forEach(model => {
                // Support both {id, model_name} and {id, name} formats
                const modelName = model.model_name || model.name;
                dropdown.innerHTML += `<option value="${model.id}">${modelName}</option>`;
            });
            console.log(`${data.length} models loaded for ${type} brand ${brandId}`);
            dropdown.disabled = false;
        } else {
            console.error('Invalid data format or dropdown not found:', data);
        }
    }

    // Função para inicializar todos os dropdowns
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar todos os dropdowns - PV Modules, Disjuntores e Diferenciais
        // Nota: Inversores são tratados separadamente em inverter_dropdown_fix.js
        setupAllDropdowns();
    });

    // Function to configure all dropdowns
    function setupAllDropdowns() {
        // PV Modules são tratados em um arquivo separado (pv_module_dropdown_fix.js)
        // para evitar conflitos e garantir o funcionamento do dropdown de modelos

        // Inversores são tratados em um arquivo separado (inverter_dropdown_fix.js)
        // para evitar conflitos

        // Setup Disjuntores
        setupEquipmentDropdowns('circuit_breaker', 'new_circuit_breaker_brand', 'new_circuit_breaker_model');

        // Setup Diferenciais
        setupEquipmentDropdowns('differential', 'new_differential_brand', 'new_differential_model');
    }

    // Função genérica para configurar dropdowns de equipamentos
    function setupEquipmentDropdowns(type, brandDropdownId, modelDropdownId) {
        // Carregar marcas
        fetchBrands(type, brandDropdownId);

        // Configurar event listener para carregar modelos quando marca for selecionada
        const brandDropdown = document.getElementById(brandDropdownId);
        if (brandDropdown) {
            brandDropdown.addEventListener('change', function () {
                const brandId = this.value;
                if (brandId) {
                    fetchModels(type, brandId, modelDropdownId);
                } else {
                    // Limpar dropdown de modelos se nenhuma marca for selecionada
                    const modelDropdown = document.getElementById(modelDropdownId);
                    if (modelDropdown) {
                        modelDropdown.innerHTML = '<option value="">Select Model...</option>';
                    }
                }
            });
        }
    }

} // End of !window.REPORTS_PAGE check