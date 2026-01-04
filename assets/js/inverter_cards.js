console.log('[Inverter Cards] Script carregado');

// Skip on reports page
if (window.REPORTS_PAGE) {
    console.log('[Inverter Cards] ⏭️ Skipping initialization on reports page');
} else {

    // Verificar se o objeto invertersList existe
    if (window.invertersList) {
        console.log('[Inverter Cards] invertersList encontrado com', window.invertersList.length, 'items');
    } else {
        console.log('[Inverter Cards] AVISO: window.invertersList não foi encontrado!');
        // Garantir que invertersList existe mesmo que não tenha sido criado antes
        window.invertersList = window.invertersList || [];
    }

    // Inicializar cards quando a página for carregada
    document.addEventListener('DOMContentLoaded', function () {
        console.log('[Inverter Cards] DOM carregado, inicializando cards');

        // Registrar a função para ser chamada após 1 segundo para garantir que tudo foi carregado
        setTimeout(function () {
            // Se updateInvertersTable já foi executada, também chamar updateInvertersCards
            if (window.invertersList && window.invertersList.length > 0) {
                console.log('[Inverter Cards] Existem inversores, atualizando cards');
                window.updateInvertersCards();
            } else {
                console.log('[Inverter Cards] Nenhum inversor para mostrar no carregamento inicial');
            }

            // Verificar se o container de inversores existe
            const invertersContainer = document.getElementById('inverters-container');
            if (!invertersContainer) {
                console.error('[Inverter Cards] Container de inversores não encontrado no DOM!');
            } else {
                console.log('[Inverter Cards] Container de inversores encontrado no DOM');
            }
        }, 1000); // Delay de 1 segundo para garantir que tudo está carregado
    });

    /**
     * Update inverters display with cards layout
     * This function is declared as a global function to be accessible from other scripts
     */
    window.updateInvertersCards = function () {
        console.log('[Inverter Cards] Atualizando cards de inversores', window.invertersList);

        const invertersContainer = document.getElementById('inverters-container');
        const noInvertersMessage = document.getElementById('no-inverters-message');
        const inverterCountBadge = document.getElementById('inverter-count-badge');

        console.log('[Inverter Cards] Container:', invertersContainer);

        if (!invertersContainer) {
            console.error('[Inverter Cards] Container de inversores não encontrado');
            return;
        }

        // Update counter badge
        if (inverterCountBadge) {
            const count = window.invertersList ? window.invertersList.length : 0;
            inverterCountBadge.textContent = `(${count})`;
        }

        // Clear existing cards but keep the no-inverters-message element
        const cardsToRemove = invertersContainer.querySelectorAll('.inverter-card');
        cardsToRemove.forEach(card => card.remove());

        if (!window.invertersList || window.invertersList.length === 0) {
            // Show no data message
            console.log('[Inverter Cards] Nenhum inversor para mostrar');
            if (noInvertersMessage) noInvertersMessage.classList.remove('d-none');
            return;
        }

        // Hide no data message
        if (noInvertersMessage) noInvertersMessage.classList.add('d-none');

        // Add a card for each inverter
        window.invertersList.forEach((inverter, index) => {
            // Create card for visual display
            const cardCol = document.createElement('div');
            cardCol.className = 'col inverter-card';

            // Determine datasheet button state
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

            // Create the card with inverter details
            cardCol.innerHTML = `
            <div class="card h-100 ${inverter.from_database ? 'border-info' : 'border-primary'}">
                <div class="card-header ${inverter.from_database ? 'bg-info' : 'bg-primary'} text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fs-5">
                        ${inverter.brand_name} ${inverter.model_name}
                        ${inverter.from_database ? '<span class="badge bg-light text-dark ms-2">Existing</span>' : ''}
                    </h5>
                    <div>
                        <!-- Editar: mais visível (fundo claro + texto escuro) -->
                        <button type="button" class="btn btn-sm btn-edit-inverter edit-inverter me-1" data-index="${index}" title="Editar Inverter" aria-label="Editar Inverter">
                            <i class="fas fa-edit"></i>
                        </button>
                        <!-- Eliminar: destaque em vermelho, com margem para separar -->
                        <button type="button" class="btn btn-sm btn-danger btn-delete-inverter delete-inverter me-1" data-index="${index}" title="Eliminar Inverter" aria-label="Eliminar Inverter">
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
                    `${inverter.differential_brand_name} ${inverter.differential_model_name}${inverter.differential_rated_current ? ` (${inverter.differential_rated_current}A${inverter.differential_current ? `, ${inverter.differential_current}mA` : ''})` : (inverter.differential_current ? ` (${inverter.differential_current}mA)` : '')}` : 'N/A'}
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
                    
                    <!-- Validation Status Section -->
                    ${inverter.validation_message ? `
                    <div class="mt-2 mx-3 mb-2">
                        <div class="alert alert-${inverter.validation_status === 'warning' ? 'warning' : inverter.validation_status === 'success' ? 'success' : 'info'} alert-dismissible fade show p-2 small" role="alert">
                            <small>${inverter.validation_message}</small>
                        </div>
                    </div>
                    ` : ''}
                </div>
                <div class="card-footer">
                    ${datasheetBtn}
                </div>
            </div>
        `;

            invertersContainer.appendChild(cardCol);
        });

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
                const inverter = window.invertersList[index];

                if (inverter) {
                    // Direct search - open Google search in new tab
                    const searchTerm = `${inverter.brand_name} ${inverter.model_name} datasheet pdf`;
                    window.open(`https://www.google.com/search?q=${encodeURIComponent(searchTerm)}`, '_blank');
                }
            });
        });

        // Edit inverter from card: populate main form and set editing index (do not remove the item yet)
        document.querySelectorAll('.edit-inverter').forEach(btn => {
            btn.addEventListener('click', function (e) {
                // Prevent default to avoid unexpected navigation
                if (e && e.preventDefault) e.preventDefault();

                const index = parseInt(this.getAttribute('data-index'));
                const inverter = window.invertersList[index];
                if (!inverter) return;

                console.log('[Inverter Cards] Editing inverter from card index', index, inverter);

                // Ensure main form tab is visible
                const basicTab = document.getElementById('basic-tab');

                // No automatic scrolling when entering edit mode (user requested no page scroll)
                function doScrollToInvDetails() {
                    // Intentionally left blank: scrolling disabled
                    return;
                }

                if (basicTab) {
                    const tab = new bootstrap.Tab(basicTab);
                    // If the basic tab is not active, wait for it to be shown then scroll
                    // Show basic tab but DO NOT scroll the page
                    if (!basicTab.classList.contains('active')) {
                        // Save current scroll position
                        const currentScrollY = window.scrollY;
                        const onShown = function () {
                            try {
                                // Restore scroll position after tab switch to prevent unwanted scrolling
                                setTimeout(() => window.scrollTo({ top: currentScrollY, behavior: 'instant' }), 0);
                            } finally {
                                basicTab.removeEventListener('shown.bs.tab', onShown);
                            }
                        };
                        basicTab.addEventListener('shown.bs.tab', onShown);
                        tab.show();
                    } else {
                        // Already active - no scrolling
                    }
                }

                // Set global editing index used by main.addInverterToTable
                if (typeof window.editingInverterIndex !== 'undefined') {
                    window.editingInverterIndex = index;
                } else {
                    window.editingInverterIndex = index;
                }

                // Populate basic fields
                const brandEl = document.getElementById('new_inverter_brand');
                const modelEl = document.getElementById('new_inverter_model');
                const statusEl = document.getElementById('new_inverter_status');
                const serialEl = document.getElementById('new_inverter_serial');
                const locationEl = document.getElementById('new_inverter_location');
                const maxCurEl = document.getElementById('new_inverter_max_current');

                if (brandEl) brandEl.value = inverter.brand_id || '';

                // Load models for the brand then select
                if (inverter.brand_id && modelEl) {
                    loadEquipmentModels('inverter', inverter.brand_id, 'new_inverter_model').then(() => {
                        setTimeout(() => {
                            if (modelEl) modelEl.value = inverter.model_id || '';
                        }, 200);
                    }).catch(() => {
                        setTimeout(() => { if (modelEl) modelEl.value = inverter.model_id || ''; }, 300);
                    });
                }

                if (statusEl) statusEl.value = inverter.status || 'new';
                if (serialEl) serialEl.value = inverter.serial_number || '';
                if (locationEl) locationEl.value = inverter.location || '';
                if (maxCurEl) maxCurEl.value = inverter.max_output_current || '';

                // Populate associated equipment fields if present
                try {
                    if (document.getElementById('new_circuit_breaker_brand')) document.getElementById('new_circuit_breaker_brand').value = inverter.circuit_breaker_brand_id || '';
                    if (document.getElementById('new_circuit_breaker_model')) {
                        loadEquipmentModels('circuit_breaker', inverter.circuit_breaker_brand_id || '', 'new_circuit_breaker_model').then(() => {
                            setTimeout(() => { document.getElementById('new_circuit_breaker_model').value = inverter.circuit_breaker_model_id || ''; }, 200);
                        }).catch(() => { });
                    }
                    if (document.getElementById('new_circuit_breaker_rated_current')) document.getElementById('new_circuit_breaker_rated_current').value = inverter.circuit_breaker_rated_current || '';

                    if (document.getElementById('new_differential_brand')) document.getElementById('new_differential_brand').value = inverter.differential_brand_id || '';
                    if (document.getElementById('new_differential_model')) {
                        loadEquipmentModels('differential', inverter.differential_brand_id || '', 'new_differential_model').then(() => {
                            setTimeout(() => { document.getElementById('new_differential_model').value = inverter.differential_model_id || ''; }, 200);
                        }).catch(() => { });
                    }
                    if (document.getElementById('new_differential_rated_current')) document.getElementById('new_differential_rated_current').value = inverter.differential_rated_current || '';
                    if (document.getElementById('new_differential_current')) document.getElementById('new_differential_current').value = inverter.differential_current || '';

                    if (document.getElementById('new_cable_brand')) document.getElementById('new_cable_brand').value = inverter.cable_brand_id || '';
                    if (document.getElementById('new_cable_model')) {
                        loadEquipmentModels('cable', inverter.cable_brand_id || '', 'new_cable_model').then(() => {
                            setTimeout(() => { document.getElementById('new_cable_model').value = inverter.cable_model_id || ''; }, 200);
                        }).catch(() => { });
                    }
                    if (document.getElementById('new_cable_size')) document.getElementById('new_cable_size').value = inverter.cable_size || '';
                    if (document.getElementById('new_cable_insulation')) document.getElementById('new_cable_insulation').value = inverter.cable_insulation || '';
                } catch (e) {
                    console.warn('Could not populate associated equipment fields during edit', e);
                }

                // Update submit button to indicate update action
                const submitBtn = document.getElementById('submit-inverter-btn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Update Inverter';
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-warning');
                }

                // Run validation to enable the button if required fields are present
                if (typeof validateAssociatedEquipment === 'function') {
                    setTimeout(() => validateAssociatedEquipment(), 150);
                }

                // Scroll to the Inverter Details container to focus the user on the inverter form
                const invDetails = document.getElementById('inverter-details');
                if (invDetails) {
                    // Wait for tab to become visible and layout to stabilise, then perform an offset-aware scroll
                    setTimeout(() => {
                        try {
                            // Compute header offset dynamically (sum heights of visible fixed/sticky headers)
                            let headerOffset = 0;
                            const headerSelectors = ['.fixed-top', '.sticky-top', 'header', '.navbar', '.page-header'];
                            headerSelectors.forEach(sel => {
                                document.querySelectorAll(sel).forEach(el => {
                                    if (el && el.offsetHeight && el.offsetParent !== null) headerOffset += el.offsetHeight;
                                });
                            });

                            // Add small extra padding but cap it
                            headerOffset = Math.min(headerOffset + 14, window.innerHeight * 0.4);

                            const rect = invDetails.getBoundingClientRect();
                            const target = Math.max(0, window.scrollY + rect.top - headerOffset);
                            window.scrollTo({ top: target, behavior: 'smooth' });
                        } catch (err) {
                            invDetails.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 350);
                } else {
                    // Fallback to the form top if the container is not found
                    const formEl = document.getElementById('commissioningForm');
                    if (formEl) formEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

} // End of !window.REPORTS_PAGE check