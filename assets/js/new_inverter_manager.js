/**
 * new// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Inverter Manager] DOM carregado, inicializando gerenciador de inversores');
    
    // O botão submit-inverter-btn é gerenciado por main.js, não adicionar listener aqui
    
    // Inicializar dropdowns se necessário
 * Script responsável por gerenciar a adição e exibição de inversores
 */

// Skip on reports page
if (window.REPORTS_PAGE) {
    console.log('[Inverter Manager] ⏭️ Skipping initialization on reports page');
} else {

    // Declaração global para a lista de inversores
    if (!window.invertersList) {
        window.invertersList = [];
        console.log('[Inverter Manager] Lista de inversores inicializada');
    }

    // Inicialização quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function () {
        console.log('[Inverter Manager] DOM carregado, inicializando gerenciador de inversores');

        // O botão submit-inverter-btn é gerenciado por main.js, não adicionar listener aqui

        // Inicializar dropdowns - verificar se já foi inicializado
        if (typeof window.initEquipmentDropdowns === 'function' && !window.dropdownsInitialized) {
            console.log('[Inverter Manager] Usando função global initEquipmentDropdowns');
            window.initEquipmentDropdowns();
            window.dropdownsInitialized = true;
        } else if (!window.dropdownsInitialized) {
            console.log('[Inverter Manager] Função global não disponível ou já inicializada, usando setup local');
            setupDropdowns();

            // Load brands for dropdowns (namespaced to avoid conflicts)
            nim_loadEquipmentBrands('inverter', 'new_inverter_brand');
            nim_loadEquipmentBrands('circuit_breaker', 'new_circuit_breaker_brand');
            nim_loadEquipmentBrands('differential', 'new_differential_brand');
            nim_loadEquipmentBrands('cable', 'new_cable_brand');
            // Defensive cleanup: dedupe options if global function available
            try { if (typeof dedupeSelectOptions === 'function') dedupeSelectOptions('new_inverter_brand'); } catch (e) { /* ignore */ }
            window.dropdownsInitialized = true;
        } else {
            console.log('[Inverter Manager] Dropdowns já inicializados, pulando...');
        }

        // Atualizar a visualização inicial
        updateInvertersDisplay();
    });

    /**
     * Submeter um novo inversor
     */
    function submitInverter() {
        console.log('[Inverter Manager] Processando submissão de inversor');

        // Coletar dados do formulário
        const brandSelect = document.getElementById('new_inverter_brand');
        const modelSelect = document.getElementById('new_inverter_model');
        const statusSelect = document.getElementById('new_inverter_status');
        const serialInput = document.getElementById('new_inverter_serial');
        const locationInput = document.getElementById('new_inverter_location');
        const maxCurrentInput = document.getElementById('new_inverter_max_current');

        // Circuit breaker fields
        const circuitBreakerBrandSelect = document.getElementById('new_circuit_breaker_brand');
        const circuitBreakerModelSelect = document.getElementById('new_circuit_breaker_model');
        const circuitBreakerRatedCurrentInput = document.getElementById('new_circuit_breaker_rated_current');

        // Differential fields
        const differentialBrandSelect = document.getElementById('new_differential_brand');
        const differentialModelSelect = document.getElementById('new_differential_model');
        const differentialRatedCurrentInput = document.getElementById('new_differential_rated_current');
        const differentialCurrentInput = document.getElementById('new_differential_current');

        // Cable fields
        const cableBrandSelect = document.getElementById('new_cable_brand');
        const cableModelInput = document.getElementById('new_cable_model');
        const cableSizeInput = document.getElementById('new_cable_size');
        const cableInsulationInput = document.getElementById('new_cable_insulation');

        // Obter valores
        const brandId = brandSelect.value;
        const brandText = brandSelect.options[brandSelect.selectedIndex]?.text || '';
        const modelId = modelSelect.value;
        const modelText = modelSelect.options[modelSelect.selectedIndex]?.text || '';
        const quantity = 1; // Default quantity since field was removed
        const status = statusSelect.value;
        const statusText = statusSelect.options[statusSelect.selectedIndex]?.text || '';
        const serialNumber = serialInput.value;
        const location = locationInput.value;
        const maxCurrent = maxCurrentInput.value;

        console.log('[Inverter Manager] Valores coletados:', {
            brandId, modelId, quantity, maxCurrent, serialNumber, location
        });
        if (!brandId || !modelId) {
            if (typeof customAlert === 'function') {
                customAlert('Please select the inverter brand and model.', 'warning', 'Required Fields');
            } else {
                alert('Please select the inverter brand and model.');
            }
            return;
        }

        // Criar objeto do inversor
        const inverterData = {
            brand_id: brandId,
            brand_name: brandText,
            model_id: modelId,
            model_name: modelText,
            quantity: quantity,
            status: status,
            status_text: statusText,
            serial_number: serialNumber || '',
            location: location || '',
            max_output_current: maxCurrent || '',

            // Circuit breaker data
            circuit_breaker_brand_name: circuitBreakerBrandSelect.options[circuitBreakerBrandSelect.selectedIndex]?.text || '',
            circuit_breaker_model_id: circuitBreakerModelSelect.value || '',
            circuit_breaker_model_name: circuitBreakerModelSelect.options[circuitBreakerModelSelect.selectedIndex]?.text || '',
            circuit_breaker_rated_current: circuitBreakerRatedCurrentInput ? circuitBreakerRatedCurrentInput.value : '',

            // Differential data
            differential_brand_id: differentialBrandSelect.value || '',
            differential_brand_name: differentialBrandSelect.options[differentialBrandSelect.selectedIndex]?.text || '',
            differential_model_id: differentialModelSelect.value || '',
            differential_model_name: differentialModelSelect.options[differentialModelSelect.selectedIndex]?.text || '',
            differential_rated_current: differentialRatedCurrentInput ? differentialRatedCurrentInput.value : '',
            differential_current: differentialCurrentInput ? differentialCurrentInput.value : '',

            // Cable data
            cable_brand_id: cableBrandSelect.value || '',
            cable_brand_name: cableBrandSelect.options[cableBrandSelect.selectedIndex]?.text || '',
            cable_model_name: cableModelInput ? cableModelInput.value || '' : '',
            cable_size: cableSizeInput.value || '',
            cable_insulation: cableInsulationInput ? cableInsulationInput.value : '',

            // Other properties
            datasheet_url: ''
        };

        console.log('[Inverter Manager] Dados do inversor coletados:', inverterData);
        window.invertersList.push(inverterData);

        // Atualizar o campo oculto para submissão do formulário
        const hiddenField = document.getElementById('inverters_data');
        if (hiddenField) {
            hiddenField.value = JSON.stringify(window.invertersList);
        }

        // Clear the form
        resetInverterForm();

        // Atualizar a exibição
        updateInvertersDisplay();

        // Mostrar mensagem de sucesso
        if (typeof customAlert === 'function') {
            customAlert('Inverter added successfully!', 'success', 'Success');
        } else {
            alert('Inverter added successfully!');
        }

        // Scroll to the section of added inverters
        const invertersSection = document.querySelector('.mt-4.pt-2');
        if (invertersSection) {
            invertersSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * Clear the inverter form
     */
    function resetInverterForm() {
        // Campos básicos
        document.getElementById('new_inverter_brand').value = '';
        document.getElementById('new_inverter_model').innerHTML = '<option value="">Select Model...</option>';
        document.getElementById('new_inverter_status').value = 'new';
        document.getElementById('new_inverter_serial').value = '';
        document.getElementById('new_inverter_location').value = '';
        document.getElementById('new_inverter_max_current').value = '';

        // Circuit breaker
        document.getElementById('new_circuit_breaker_brand').value = '';
        document.getElementById('new_circuit_breaker_model').innerHTML = '<option value="">Select Model...</option>';
        if (document.getElementById('new_circuit_breaker_rated_current')) {
            document.getElementById('new_circuit_breaker_rated_current').value = '';
        }

        // Differential
        document.getElementById('new_differential_brand').value = '';
        document.getElementById('new_differential_model').innerHTML = '<option value="">Select Model...</option>';
        if (document.getElementById('new_differential_rated_current')) {
            document.getElementById('new_differential_rated_current').value = '';
        }
        if (document.getElementById('new_differential_current')) {
            document.getElementById('new_differential_current').value = '';
        }

        // Cable
        document.getElementById('new_cable_brand').value = '';
        if (document.getElementById('new_cable_model')) {
            document.getElementById('new_cable_model').value = '';
        }
        document.getElementById('new_cable_size').value = '';
        if (document.getElementById('new_cable_insulation')) {
            document.getElementById('new_cable_insulation').value = '';
        }
    }

    /**
     * Atualizar a exibição de inversores
     */
    function updateInvertersDisplay() {
        console.log('[Inverter Manager] Atualizando exibição de inversores');

        // Obter o container de inversores
        const invertersContainer = document.getElementById('inverters-container');
        const noInvertersMessage = document.getElementById('no-inverters-message');
        const inverterCountBadge = document.getElementById('inverter-count-badge');

        if (!invertersContainer) {
            console.error('[Inverter Manager] Container de inversores não encontrado!');
            return;
        }

        // Clear existing cards, preserving the "no inverters" message
        const existingCards = invertersContainer.querySelectorAll('.inverter-card');
        existingCards.forEach(card => card.remove());

        // Atualizar o badge de contagem, se existir
        if (inverterCountBadge) {
            inverterCountBadge.textContent = `(${window.invertersList.length})`;
        }

        // Verificar se há inversores para exibir
        if (!window.invertersList || window.invertersList.length === 0) {
            // Mostrar mensagem "no inversores"
            if (noInvertersMessage) {
                noInvertersMessage.classList.remove('d-none');
            }
            return;
        }

        // Ocultar a mensagem "no inversores"
        if (noInvertersMessage) {
            noInvertersMessage.classList.add('d-none');
        }

        // Adicionar um card para cada inversor
        window.invertersList.forEach((inverter, index) => {
            // Criar o elemento do card
            const cardCol = document.createElement('div');
            cardCol.className = 'col inverter-card';

            // Determinar o estado do botão de datasheet
            const hasDatasheet = inverter.datasheet_url && inverter.datasheet_url.trim() !== '';
            let datasheetBtn;

            if (hasDatasheet) {
                datasheetBtn = `
                <a href="${inverter.datasheet_url}" target="_blank" class="btn btn-sm btn-info me-1" title="View Datasheet">
                    <i class="fas fa-file-pdf"></i> View Datasheet
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary edit-datasheet" data-index="${index}" data-type="inverter" title="Edit Datasheet URL">
                    <i class="fas fa-edit"></i>
                </button>`;
            } else {
                datasheetBtn = `
                <button type="button" class="btn btn-sm btn-outline-info me-1 add-datasheet" data-index="${index}" data-type="inverter" title="Add Datasheet URL">
                    <i class="fas fa-link"></i> Add Datasheet
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary search-datasheet" data-index="${index}" data-type="inverter" title="Search Datasheet Online">
                    <i class="fas fa-search"></i>
                </button>`;
            }

            // HTML do card
            cardCol.innerHTML = `
            <div class="card h-100 border-primary">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fs-5">
                        ${inverter.brand_name} ${inverter.model_name}
                    </h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-danger delete-inverter" data-index="${index}" title="Delete Inverter">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="row g-0">
                        <div class="col-md-6">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <strong><i class="fas fa-hashtag me-2"></i>Quantity:</strong> ${inverter.quantity}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-info-circle me-2"></i>Status:</strong> ${inverter.status_text}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-bolt me-2"></i>Max Output Current:</strong> ${inverter.max_output_current || 'N/A'}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-barcode me-2"></i>Serial Number:</strong> ${inverter.serial_number || 'N/A'}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-map-marker-alt me-2"></i>Location:</strong> ${inverter.location || 'N/A'}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <strong><i class="fas fa-shield-alt me-2"></i>Circuit Breaker:</strong><br>
                                    ${inverter.circuit_breaker_brand_name && inverter.circuit_breaker_model_name ?
                    `${inverter.circuit_breaker_brand_name} ${inverter.circuit_breaker_model_name}${inverter.circuit_breaker_rated_current ? ` (${inverter.circuit_breaker_rated_current}A)` : ''}` : 'N/A'}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-plug me-2"></i>Differential:</strong><br>
                                    ${inverter.differential_brand_name && inverter.differential_model_name ?
                    `${inverter.differential_brand_name} ${inverter.differential_model_name}${inverter.differential_rated_current ? ` (${inverter.differential_rated_current}A` : ''}${inverter.differential_current ? `, ${inverter.differential_current}mA)` : (inverter.differential_rated_current ? ')' : '')}` : 'N/A'}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-flash me-2"></i>Differential Current:</strong><br>
                                    ${inverter.differential_current ? `${inverter.differential_current} mA` : 'N/A'}
                                </div>
                                <div class="list-group-item">
                                    <strong><i class="fas fa-charging-station me-2"></i>Cable:</strong><br>
                                    ${(inverter.cable_brand_name || inverter.cable_model_name || inverter.cable_size) ?
                    `${inverter.cable_brand_name || ''} ${inverter.cable_model_name ? inverter.cable_model_name + ' ' : ''}${inverter.cable_size ? inverter.cable_size + ' mm2' : ''}${inverter.cable_insulation ? ` (${inverter.cable_insulation})` : ''}`.trim() : 'N/A'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    ${datasheetBtn}
                </div>
            </div>
        `;

            // Adicionar ao container
            invertersContainer.appendChild(cardCol);

            // Highlight para o card recém-adicionado
            if (index === window.invertersList.length - 1) {
                const card = cardCol.querySelector('.card');
                card.style.boxShadow = '0 0 15px rgba(13, 110, 253, 0.7)';
                setTimeout(() => {
                    card.style.boxShadow = '';
                    // Adicionar transição para suavizar
                    card.style.transition = 'box-shadow 0.5s ease-out';
                }, 2000);
            }
        });

        // Adicionar event listeners para os botões
        addCardButtonEventListeners();
    }

    /**
     * Adicionar event listeners para os botões dos cards
     */
    function addCardButtonEventListeners() {
        // Botões de exclusão de inversor
        document.querySelectorAll('.delete-inverter').forEach(btn => {
            btn.addEventListener('click', function () {
                const index = parseInt(this.getAttribute('data-index'));
                deleteInverter(index);
            });
        });

        // Botões de datasheet
        document.querySelectorAll('.add-datasheet').forEach(btn => {
            btn.addEventListener('click', function () {
                const index = parseInt(this.getAttribute('data-index'));
                addDatasheet(index);
            });
        });

        document.querySelectorAll('.search-datasheet').forEach(btn => {
            btn.addEventListener('click', function () {
                const index = parseInt(this.getAttribute('data-index'));
                searchDatasheet(index);
            });
        });
    }

    /**
     * Excluir um inversor
     */
    function deleteInverter(index) {
        console.log('[Inverter Manager] Excluindo inversor com índice', index);

        const proceedDeleteLocal = () => {
            // Remover da lista
            window.window.invertersList.splice(index, 1);
            // Atualizar o campo oculto
            const hiddenField = document.getElementById('inverters_data');
            if (hiddenField) {
                hiddenField.value = JSON.stringify(window.invertersList);
            }
            // Atualizar a exibição
            updateInvertersDisplay();
            if (typeof customAlert === 'function') {
                customAlert('Inverter deleted successfully', 'success', 'Success');
            }
            // Trigger an autosave of the form/state if available
            if (typeof saveFormDataToSQL === 'function') {
                try { saveFormDataToSQL(true); } catch (e) { saveFormDataToSQL(); }
            } else if (typeof window.triggerAutosave === 'function') {
                try { window.triggerAutosave(); } catch (e) { }
            }
        };

        const proceedDelete = () => {
            const inverter = window.invertersList[index];
            const hasDbId = inverter && (inverter.id || inverter.inverter_id);

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
                            proceedDeleteLocal();
                        } else {
                            console.error('Failed to delete inverter on server:', data);
                            if (typeof customAlert === 'function') {
                                customAlert('Could not delete inverter on server: ' + (data && data.error ? data.error : 'Unknown error'), 'danger', 'Error');
                            } else {
                                alert('Could not delete inverter on server');
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error calling delete endpoint:', err);
                        if (typeof customAlert === 'function') {
                            customAlert('Error deleting inverter: ' + err.message, 'danger', 'Error');
                        } else {
                            alert('Error deleting inverter: ' + err.message);
                        }
                    });
            } else {
                // No DB id — just remove locally
                proceedDeleteLocal();
            }
        };

        if (typeof customConfirm === 'function') {
            customConfirm('Are you sure you want to delete this inverter?', 'Delete Inverter').then(confirmed => {
                if (confirmed) proceedDelete();
            });
        } else if (confirm('Are you sure you want to delete this inverter?')) {
            proceedDelete();
        }
    }

    /**
     * Adicionar datasheet a um inversor
     */
    function addDatasheet(index) {
        console.log('[Inverter Manager] Adicionando datasheet para inversor', index);

        const handleUrl = (url) => {
            if (url && url.trim() !== '') {
                window.invertersList[index].datasheet_url = url.trim();
                updateInvertersDisplay();
                if (typeof customAlert === 'function') {
                    customAlert('Datasheet added successfully!', 'success', 'Success');
                }
            }
        };

        if (typeof customPrompt === 'function') {
            customPrompt('Enter the datasheet URL:', '', 'Add Datasheet').then(handleUrl);
        } else {
            const url = prompt('Enter datasheet URL:');
            handleUrl(url);
        }
    }

    /**
     * Pesquisar datasheet online
     */
    function searchDatasheet(index) {
        console.log('[Inverter Manager] Pesquisando datasheet para inversor', index);

        const inverter = window.invertersList[index];
        const searchTerm = `${inverter.brand_name} ${inverter.model_name} datasheet pdf`;

        window.open(`https://www.google.com/search?q=${encodeURIComponent(searchTerm)}`, '_blank');
    }

    /**
     * Configure event listeners for dropdowns
     */
    function setupDropdowns() {
        console.log('[Inverter Manager] Configurando dropdowns');

        // Event listener para o dropdown de marca do inversor
        const inverterBrandSelect = document.getElementById('new_inverter_brand');
        if (inverterBrandSelect) {
            inverterBrandSelect.addEventListener('change', function () {
                console.log('[Inverter Manager] Marca de inversor alterada:', this.value);
                if (this.value) {
                    callLoadEquipmentModels('inverter', this.value, 'new_inverter_model');
                } else {
                    clearSelect('new_inverter_model');
                }
            });
        } else {
            console.error('[Inverter Manager] Dropdown de marca de inversor não encontrado');
        }

        // Event listener para o dropdown de marca do disjuntor
        const circuitBreakerBrandSelect = document.getElementById('new_circuit_breaker_brand');
        if (circuitBreakerBrandSelect) {
            circuitBreakerBrandSelect.addEventListener('change', function () {
                console.log('[Inverter Manager] Marca de disjuntor alterada:', this.value);
                if (this.value) {
                    callLoadEquipmentModels('circuit_breaker', this.value, 'new_circuit_breaker_model');
                } else {
                    clearSelect('new_circuit_breaker_model');
                }
            });
        } else {
            console.error('[Inverter Manager] Dropdown de marca de disjuntor não encontrado');
        }

        // Event listener para o dropdown de marca do diferencial
        const differentialBrandSelect = document.getElementById('new_differential_brand');
        if (differentialBrandSelect) {
            differentialBrandSelect.addEventListener('change', function () {
                console.log('[Inverter Manager] Marca de diferencial alterada:', this.value);
                if (this.value) {
                    callLoadEquipmentModels('differential', this.value, 'new_differential_model');
                } else {
                    clearSelect('new_differential_model');
                }
            });
        } else {
            console.error('[Inverter Manager] Dropdown de marca de diferencial não encontrado');
        }

        // Event listener para o dropdown de modelo do inversor
        const inverterModelSelect = document.getElementById('new_inverter_model');
        if (inverterModelSelect) {
            inverterModelSelect.addEventListener('change', function () {
                console.log('[Inverter Manager] Modelo de inversor alterado:', this.value);
                if (this.value) {
                    // Buscar dados do modelo selecionado para popular o campo max_current
                    loadInverterModelData(this.value);
                } else {
                    // Clear max_current field if no model selected
                    document.getElementById('new_inverter_max_current').value = '';
                }
            });
        } else {
            console.error('[Inverter Manager] Dropdown de modelo de inversor não encontrado');
        }
    }

    /**
     * Carregar dados do modelo de inversor selecionado
     */
    function loadInverterModelData(modelId) {
        console.log('[Inverter Manager] Carregando dados do modelo de inversor:', modelId);

        fetch(`${window.BASE_URL}ajax/get_inverter_data.php?inverter_id=${modelId}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.max_output_current) {
                    // Popular o campo max_current com o valor do banco
                    document.getElementById('new_inverter_max_current').value = data.max_output_current;
                    console.log('[Inverter Manager] Campo max_current populado com:', data.max_output_current);
                    console.log('[Inverter Manager] Valor atual do campo:', document.getElementById('new_inverter_max_current').value);
                } else {
                    console.warn('[Inverter Manager] Dados do modelo não encontrados ou max_output_current vazio');
                    document.getElementById('new_inverter_max_current').value = '';
                }
            })
            .catch(error => {
                console.error('[Inverter Manager] Error loading model data:', error);
                document.getElementById('new_inverter_max_current').value = '';
            });
    }

    /**
     * Clear dropdown options
     */
    function clearSelect(selectId) {
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Select Model...</option>';
        } else {
            console.error(`[Inverter Manager] Select #${selectId} não encontrado`);
        }
    }

    /**
     * Load equipment brands
     */
    function nim_loadEquipmentBrands(type, targetSelectId) {
        console.log(`[Inverter Manager] Carregando marcas do tipo ${type} para #${targetSelectId}`);

        const targetSelect = document.getElementById(targetSelectId);
        if (!targetSelect) {
            console.error(`[Inverter Manager] Select #${targetSelectId} não encontrado`);
            return;
        }

        // Clear current options
        targetSelect.innerHTML = '<option value="">Select Brand...</option>';

        // URL da requisição
        const url = `ajax/get_equipment_brands.php?type=${type}`;

        // Make AJAX request
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log(`[Inverter Manager] ${data.length} marcas recebidas para ${type}`);

                // Add options to select – deduplicate by id and name
                const uniqueMap = new Map();
                data.forEach(b => {
                    const key = String(b.id) || String(b.brand_name).toLowerCase();
                    if (!uniqueMap.has(key)) uniqueMap.set(key, b);
                });
                const uniqueBrands = Array.from(uniqueMap.values());
                uniqueBrands.forEach(brand => {
                    const exists = Array.from(targetSelect.options).some(o => String(o.value) === String(brand.id) || o.textContent === brand.brand_name);
                    if (!exists) {
                        const option = document.createElement('option');
                        option.value = brand.id;
                        option.textContent = brand.brand_name;
                        targetSelect.appendChild(option);
                    }
                });
            })
            .catch(error => {
                console.error(`[Inverter Manager] Error loading brands:`, error);
            });
    }

    /**
     * Wrapper seguro para carregar modelos: usa a função global se existir,
     * caso contrário utiliza um fallback local namespaced (evita colisões)
     */
    function callLoadEquipmentModels(type, brandId, targetSelectId) {
        if (typeof window.loadEquipmentModels === 'function') {
            return window.loadEquipmentModels(type, brandId, targetSelectId);
        }
        return nim_loadEquipmentModels(type, brandId, targetSelectId);
    }

    // Fallback local namespaced para evitar colisão com outras implementações
    function nim_loadEquipmentModels(type, brandId, targetSelectId) {
        console.log('[Inverter Manager] Fallback nim_loadEquipmentModels acionado');
        return new Promise((resolve, reject) => {
            const modelSelect = document.getElementById(targetSelectId);
            if (!modelSelect) {
                reject('Select element not found');
                return;
            }
            modelSelect.innerHTML = '<option value="">Select Model...</option>';
            const endpoint = `ajax/get_equipment_models.php?type=${encodeURIComponent(type)}&brand_id=${encodeURIComponent(brandId)}`;
            fetch(endpoint)
                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                .then(data => {
                    if (Array.isArray(data)) {
                        data.forEach(model => {
                            const option = document.createElement('option');
                            option.value = model.id;
                            option.textContent = model.model_name;
                            modelSelect.appendChild(option);
                        });
                    }
                    resolve();
                })
                .catch(err => {
                    console.error('[Inverter Manager] Erro no fallback de modelos:', err);
                    reject(err);
                });
        });
    }

} // End of !window.REPORTS_PAGE check
