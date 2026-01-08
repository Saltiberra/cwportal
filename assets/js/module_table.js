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

    // Função unificada para buscar marcas (Converted to XHR for better compatibility)
    function fetchBrands(type, targetDropdownId) {
        let baseUrl = window.BASE_URL || '/';
        const dropdown = document.getElementById(targetDropdownId);
        
        if (!dropdown) {
            console.error(`Components #${targetDropdownId} not found`);
            return;
        }

        // Use direct PHP file which is proven to work in other scripts
        // instead of dropdown_handler.php which seems to trigger firewall/errors with fetch
        let url = `${baseUrl}ajax/get_equipment_brands.php?type=${type}`;

        console.log(`Fetching brands for ${type} to populate ${targetDropdownId} via XHR`);

        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.timeout = 10000;

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    let data = JSON.parse(xhr.responseText);
                    // Handle response wrapper if present
                    if (data.data) data = data.data;
                    
                    populateBrandDropdown(data, targetDropdownId, type);
                } catch (e) {
                    console.error('Error parsing brands JSON:', e);
                    dropdown.innerHTML = '<option value="">Error loading data</option>';
                }
            } else {
                console.error(`HTTP Error ${xhr.status} loading brands`);
            }
        };

        xhr.onerror = function() {
            console.error('Network error loading brands');
        };

        xhr.send();
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

    // Função unificada para buscar modelos (Converted to XHR)
    function fetchModels(type, brandId, targetDropdownId) {
        let baseUrl = window.BASE_URL || '/';
        const dropdown = document.getElementById(targetDropdownId);

        if (!dropdown) return;

        let url = `${baseUrl}ajax/get_equipment_models.php?type=${type}&brand_id=${brandId}`;

        console.log(`Fetching models for ${type} (brand ID: ${brandId}) to populate ${targetDropdownId} via XHR`);
        
        dropdown.innerHTML = '<option value="">Loading...</option>';

        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.timeout = 10000;

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    let data = JSON.parse(xhr.responseText);
                    populateModelDropdown(data, targetDropdownId);
                } catch (e) {
                     console.error('Error parsing models JSON:', e);
                     dropdown.innerHTML = '<option value="">Error loading models</option>';
                }
            } else {
                console.error(`HTTP Error ${xhr.status} loading models`);
            }
        };
        
        xhr.onerror = function() {
             console.error('Network error loading models');
             dropdown.innerHTML = '<option value="">Network Error</option>';
        };

        xhr.send();
    }    // Função auxiliar para popular dropdown de modelos
    function populateModelDropdown(data, targetDropdownId) {
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
