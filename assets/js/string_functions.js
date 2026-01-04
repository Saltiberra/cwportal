/**
 * string_functions.js
 * Funções para gerenciar as medições de strings dos inversores
 */

// Skip on reports page
if (window.REPORTS_PAGE) {
    console.log('[String Functions] ⏭️ Skipping initialization on reports page');
} else {

    // Adicionar estilos CSS personalizados para o design compacto
    const compactStringStyles = `
<style>
.string-measurement-table .form-control-sm {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
    height: 1.75rem;
}
.string-measurement-table .table th {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem;
}
.string-measurement-table .table td {
    padding: 0.125rem 0.25rem;
}
.string-measurement-table .card {
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    transition: box-shadow 0.15s ease-in-out;
}
.string-measurement-table .card:hover {
    box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1);
}
.string-measurement-table .card-header {
    font-size: 0.875rem;
}
@media (max-width: 768px) {
    .string-measurement-table .col-md-6 {
        margin-bottom: 1rem;
    }
    .string-measurement-table .table th,
    .string-measurement-table .table td {
        padding: 0.125rem;
        font-size: 0.75rem;
    }
}
</style>
`;

    // Injetar estilos no head do documento
    if (!document.querySelector('style[data-string-styles]')) {
        const styleElement = document.createElement('style');
        styleElement.setAttribute('data-string-styles', 'true');
        styleElement.innerHTML = compactStringStyles;
        document.head.appendChild(styleElement);
    }

    /*
     * MPPT table <-> card view toggle functions (mobile friendly)
     * The conversion moves the existing input elements into the card layout
     * and stores placeholders so we can restore them back to the original table.
     */
    function setupMPPTViewToggles(container) {
        if (!container) return;
        container.querySelectorAll('.string-measurement-table .card').forEach((mpptCard, i) => {
            // Avoid adding multiple toggles
            if (mpptCard.querySelector('.mppt-view-toggle')) return;

            const header = mpptCard.querySelector('.card-header');
            const toggle = document.createElement('button');
            toggle.className = 'btn btn-outline-secondary btn-sm mppt-view-toggle ms-2';
            toggle.setAttribute('type', 'button');
            toggle.setAttribute('aria-pressed', 'false');
            toggle.setAttribute('title', 'Toggle card/table view');
            toggle.setAttribute('aria-label', `Toggle MPPT ${i + 1} view`);
            toggle.setAttribute('role', 'switch');
            toggle.innerHTML = '<i class="fas fa-table"></i>';
            header.appendChild(toggle);

            toggle.addEventListener('click', function () {
                const currentView = mpptCard.dataset.view || (window.innerWidth <= 768 ? 'card' : 'table');
                if (currentView === 'table') {
                    convertTableToCardListInCard(mpptCard);
                } else {
                    restoreTableFromCardListInCard(mpptCard);
                }
            });

            // Default on small screens
            if (window.innerWidth <= 768) {
                convertTableToCardListInCard(mpptCard);
            }
        });
        // Apply preference from localStorage if present
        const pref = localStorage.getItem('mppt_view_preference');
        if (pref === 'table') toggleAllMPPTViews('table');
        if (pref === 'card') toggleAllMPPTViews('card');
    }

    function convertTableToCardListInCard(mpptCard) {
        if (!mpptCard) return;
        // If already in card view, do nothing
        if (mpptCard.dataset.view === 'card') return;

        const table = mpptCard.querySelector('table');
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Create card list container
        let cardList = mpptCard.querySelector('.mppt-card-list');
        if (!cardList) {
            cardList = document.createElement('div');
            cardList.className = 'mppt-card-list';
        }

        // For each row, create a small card giving each field a label/value
        Array.from(tbody.rows).forEach(row => {
            const card = document.createElement('div');
            card.className = 'mppt-string-card';

            // string index (first cell is row number)
            const idxCell = row.cells[0];
            const idxText = idxCell ? idxCell.textContent.trim() : '';
            const header = document.createElement('div');
            header.className = 'mb-2';
            header.innerHTML = `<div class="small text-muted">String</div><div class="fw-bold">${idxText}</div>`;
            card.appendChild(header);

            // Now iterate columns for actual inputs/notes: Voc(1), Isc(2), Vmp(3), Imp(4), Obs(5)
            const labels = ['Voc (V)', 'Isc (A)', 'Vmp (V)', 'Imp (A)', 'Obs'];
            for (let c = 1; c < row.cells.length; c++) {
                const cell = row.cells[c];
                const kv = document.createElement('div');
                kv.className = 'kv mb-1';
                const k = document.createElement('div');
                k.className = 'k small text-muted';
                k.textContent = labels[c - 1] || '';
                const v = document.createElement('div');
                v.className = 'v';
                // If cell has an input, move it into the card
                const input = cell.querySelector('input');
                if (input) {
                    // Create placeholder then move the input to the card
                    const placeholder = document.createElement('span');
                    placeholder.className = 'mppt-placeholder';
                    placeholder.dataset.placeholderFor = input.id;
                    cell.appendChild(placeholder);
                    v.appendChild(input);
                } else {
                    v.textContent = cell.textContent.trim();
                }

                kv.appendChild(k);
                kv.appendChild(v);
                card.appendChild(kv);
            }

            cardList.appendChild(card);
        });

        mpptCard.querySelector('.card-body').appendChild(cardList);
        table.style.display = 'none';
        mpptCard.dataset.view = 'card';
        const toggle = mpptCard.querySelector('.mppt-view-toggle i');
        const toggleBtn = mpptCard.querySelector('.mppt-view-toggle');
        if (toggleBtn) { toggleBtn.setAttribute('aria-pressed', 'true'); }
        if (toggle) { toggle.className = 'fas fa-table'; }
    }

    function restoreTableFromCardListInCard(mpptCard) {
        if (!mpptCard) return;
        if (mpptCard.dataset.view !== 'card') return;

        const table = mpptCard.querySelector('table');
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Move inputs back to placeholders
        const placeholders = mpptCard.querySelectorAll('.mppt-placeholder');
        placeholders.forEach(placeholder => {
            const inputId = placeholder.dataset.placeholderFor;
            if (!inputId) return;
            const input = document.getElementById(inputId);
            if (input) {
                placeholder.parentNode.insertBefore(input, placeholder);
            }
            placeholder.remove();
        });

        // Remove card-list container
        const cardList = mpptCard.querySelector('.mppt-card-list');
        if (cardList) cardList.remove();
        table.style.display = '';
        mpptCard.dataset.view = 'table';
        const toggleBtn2 = mpptCard.querySelector('.mppt-view-toggle');
        if (toggleBtn2) { toggleBtn2.setAttribute('aria-pressed', 'false'); }
        const toggle2 = mpptCard.querySelector('.mppt-view-toggle i');
        if (toggle2) { toggle2.className = 'fas fa-th-large'; }
    }

    /* Global toggle helpers */
    function toggleAllMPPTViews(view) {
        const container = document.getElementById('string-tables-container');
        if (!container) return;
        container.querySelectorAll('.string-measurement-table .card').forEach(mpptCard => {
            if (view === 'card') { convertTableToCardListInCard(mpptCard); } else { restoreTableFromCardListInCard(mpptCard); }
        });
        // Update global control state
        const globalBtn = document.getElementById('mppt-global-toggle');
        if (globalBtn) {
            globalBtn.setAttribute('aria-pressed', view === 'card' ? 'true' : 'false');
            const icon = globalBtn.querySelector('i');
            if (icon) icon.className = view === 'card' ? 'fas fa-list' : 'fas fa-th-large';
            const text = globalBtn.querySelector('span'); if (text) text.textContent = view === 'card' ? 'Card' : 'Table';
        }
        // Persist preference
        try { localStorage.setItem('mppt_view_preference', view); } catch (e) { }
    }

    function setupGlobalMPPTToggleListener() {
        const btn = document.getElementById('mppt-global-toggle');
        if (!btn) return;
        const container = document.getElementById('string-tables-container');
        const mpptsExist = container && container.querySelectorAll('.string-measurement-table .card').length > 0;
        btn.style.display = mpptsExist ? '' : 'none';
        btn.addEventListener('click', function () {
            const current = btn.getAttribute('aria-pressed') === 'true' ? 'card' : 'table';
            const next = current === 'card' ? 'table' : 'card';
            toggleAllMPPTViews(next);
        });
        // initialize state from localStorage
        const pref = localStorage.getItem('mppt_view_preference');
        if (pref === 'card') { btn.setAttribute('aria-pressed', 'true'); btn.querySelector('i').className = 'fas fa-list'; btn.querySelector('span').textContent = 'Card'; }
        if (pref === 'table') { btn.setAttribute('aria-pressed', 'false'); btn.querySelector('i').className = 'fas fa-th-large'; btn.querySelector('span').textContent = 'Table'; }
    }

    /**
     * Function to select the inverter for strings
     */
    function selectInverterForStrings() {
        // Check if the hidden element with inverter data exists and update the list
        const invertersDataField = document.getElementById('inverters_data');
        if (invertersDataField && invertersDataField.value) {
            try {
                invertersList = JSON.parse(invertersDataField.value);
                console.log('Lista de inversores atualizada:', invertersList);
            } catch (error) {
                console.error('Error parsing inverter data:', error);
            }
        }

        // Verificar se temos inversores
        if (invertersList.length === 0) {
            customModal.showWarning('Please add at least one inverter in the Equipment tab first.');
            document.getElementById('equipment-tab').click();
            return;
        }

        // Criar HTML do modal (mais compacto)
        let modalHtml = `
        <div class="modal fade" id="selectInverterModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0">
                            <i class="fas fa-plug text-primary me-2"></i>Select Inverter
                        </h6>
                        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th class="py-2">#</th>
                                        <th class="py-2">Brand</th>
                                        <th class="py-2">Model</th>
                                        <th class="py-2">S/N</th>
                                        <th class="py-2">Local</th>
                                        <th class="py-2">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>`;

        // Adicionar inversores à tabela (mais compacto)
        invertersList.forEach((inverter, index) => {
            modalHtml += `
            <tr>
                <td class="text-center py-2">${index + 1}</td>
                <td class="py-2">${inverter.brand_name}</td>
                <td class="py-2">${inverter.model_name}</td>
                <td class="py-2 small">${inverter.serial_number || '-'}</td>
                <td class="py-2 small">${inverter.location || '-'}</td>
                <td class="text-center py-2">
                    <button type="button" class="btn btn-primary btn-sm px-3 select-inverter-for-strings"
                        data-index="${index}" data-model-id="${inverter.model_id}"
                        title="Select this inverter">
                        <i class="fas fa-check"></i>
                    </button>
                </td>
            </tr>`;
        });

        modalHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <small class="text-muted me-auto">Total: ${invertersList.length} inverter(s)</small>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

        // Adicionar o modal ao documento
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer);

        // Inicializar o modal
        const modal = new bootstrap.Modal(document.getElementById('selectInverterModal'));
        modal.show();

        // Adicionar eventos aos botões de selecionar
        document.querySelectorAll('.select-inverter-for-strings').forEach(btn => {
            btn.addEventListener('click', function () {
                const modelId = this.getAttribute('data-model-id');
                const inverterIndex = this.getAttribute('data-index');
                const inverter = invertersList[inverterIndex];

                // Fechar o modal
                modal.hide();

                // Remover o elemento do modal após fechado
                document.getElementById('selectInverterModal').addEventListener('hidden.bs.modal', function () {
                    this.remove();
                });

                // Gerar a tabela de medição de strings
                generateStringMeasurementTable(modelId, 'string-tables-container', inverter);
            });
        });
    }

    /**
     * Function to generate the string measurement table based on inverter data
     *
     * @param {number} inverterId - ID do inversor selecionado
     * @param {string} targetContainerId - ID do elemento container para a tabela
     * @param {Object} inverterData - Objeto opcional com dados do inversor
     */
    function generateStringMeasurementTable(inverterId, targetContainerId, inverterData = null) {
        if (!inverterId) return;

        const container = document.getElementById(targetContainerId);
        if (!container) return;

        // Show loading indicator (more compact)
        container.innerHTML = '<div class="text-center p-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div><div class="mt-1 small text-muted">Loading inverter data...</div></div>';

        // Fazer requisição AJAX
        fetch(`ajax/get_inverter_data.php?inverter_id=${inverterId}`)
            .then(response => response.json())
            .then(data => {
                // Gerar a tabela de medição com design compacto
                let tableHtml = '';

                // Adicionar informações do inversor se fornecidas (mais compacto)
                if (inverterData) {
                    tableHtml += `
                <div class="alert alert-info alert-dismissible fade show mb-3 py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small">
                            <strong>Inversor:</strong> ${inverterData.brand_name} ${inverterData.model_name}
                            ${inverterData.serial_number ? ' | <strong>S/N:</strong> ' + inverterData.serial_number : ''}
                            ${inverterData.location ? ' | <strong>Location:</strong> ' + inverterData.location : ''}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-sm" id="change-inverter-btn">
                            <i class="fas fa-exchange-alt me-1"></i> Change
                        </button>
                    </div>
                </div>
                `;
                }

                // Armazenar o ID do modelo do inversor em um campo oculto
                tableHtml += `<input type="hidden" name="string_inverter_id" value="${inverterId}">`;

                // Layout compacto: usar grid para MPPTs
                tableHtml += '<div class="row g-2 string-measurement-table">';

                for (let i = 1; i <= data.mppts; i++) {
                    // Determinar a largura do card baseado no número de MPPTs (agrupar de 2 em 2)
                    const colClass = data.mppts === 1 ? 'col-12' :
                        data.mppts === 2 ? 'col-md-6' :
                            'col-md-6';

                    tableHtml += `
                <div class="${colClass}">
                    <div class="card h-100 border-primary">
                        <div class="card-header bg-primary text-white py-2">
                            <h6 class="card-title mb-0 text-center">
                                <i class="fas fa-bolt me-1"></i>MPPT ${i}
                            </h6>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="table-light">
                                        <tr class="text-center">
                                            <th class="py-1 px-2">&nbsp;</th>
                                            <th class="py-1 px-2">#</th>
                                            <th class="py-1 px-2">Voc<br><small class="text-muted">(V)</small></th>
                                            <th class="py-1 px-2">Isc<br><small class="text-muted">(A)</small></th>
                                            <th class="py-1 px-2">Vmp<br><small class="text-muted">(V)</small></th>
                                            <th class="py-1 px-2">Imp<br><small class="text-muted">(A)</small></th>
                                            <th class="py-1 px-2">Obs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;

                    // Adicionar linhas para cada string (mais compactas)
                    for (let j = 1; j <= data.strings_per_mppt; j++) {
                        tableHtml += `
                                    <tr>
                        <td class="text-center py-1 px-2 fw-bold">${j}</td>
                                        <td class="text-center py-1 px-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm copy-prev-row" title="Copy previous string values" aria-label="Copy previous row values">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" class="form-control form-control-sm text-center"
                                name="string_voc_${i}_${j}" id="string_voc_${i}_${j}" placeholder="0.00"
                                inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" aria-label="Voc string ${j} for MPPT ${i}">
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" class="form-control form-control-sm text-center"
                                name="string_isc_${i}_${j}" id="string_isc_${i}_${j}" placeholder="0.00"
                                inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" aria-label="Isc string ${j} for MPPT ${i}">
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" class="form-control form-control-sm text-center"
                                name="string_vmp_${i}_${j}" id="string_vmp_${i}_${j}" placeholder="0.00"
                                inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" aria-label="Vmp string ${j} for MPPT ${i}">
                        </td>
                        <td class="py-1 px-2">
                            <input type="number" step="0.01" class="form-control form-control-sm text-center"
                                name="string_imp_${i}_${j}" id="string_imp_${i}_${j}" placeholder="0.00"
                                inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" autocomplete="off" aria-label="Imp string ${j} for MPPT ${i}">
                        </td>
                        <td class="py-1 px-2">
                            <input type="text" class="form-control form-control-sm"
                                name="string_notes_${i}_${j}" id="string_notes_${i}_${j}" placeholder="...">
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
                </div>
                `;
                }

                tableHtml += '</div>';

                // Adicionar resumo compacto
                tableHtml += `
            <div class="mt-3 p-2 bg-light rounded">
                <div class="row text-center">
                    <div class="col-md-3">
                        <small class="text-muted">Total MPPTs</small>
                        <div class="h5 mb-0 text-primary">${data.mppts}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Strings/MPPT</small>
                        <div class="h5 mb-0 text-primary">${data.strings_per_mppt}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Total Strings</small>
                        <div class="h5 mb-0 text-primary">${data.mppts * data.strings_per_mppt}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Nominal Power</small>
                        <div class="h5 mb-0 text-primary">${data.nominal_power || 'N/A'} kW</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Max Output Current</small>
                        <div class="h5 mb-0 text-primary">${data.max_output_current || 'N/A'} A</div>
                    </div>
                </div>
            </div>
            `;

                // Atualizar o container
                container.innerHTML = tableHtml;
                // Initialize MPPT view toggles for card/table switch
                try { setupMPPTViewToggles(container); } catch (e) { console.error('MPPT view setup failed', e); }
                // Ensure a global toggle listener is hooked (if exists) after table generation
                try { setupGlobalMPPTToggleListener(); } catch (e) { console.error('Global MPPT toggle setup failed', e); }
                // Hook up copy-prev-row buttons
                try { setupCopyPrevRowButtons(container); } catch (e) { console.error('Copy prev row setup failed', e); }

                // Adicionar event listener ao botão de change inverter
                const changeInverterBtn = document.getElementById('change-inverter-btn');
                if (changeInverterBtn) {
                    changeInverterBtn.addEventListener('click', selectInverterForStrings);
                }
            })
            .catch(error => {
                console.error('Error loading inverter data:', error);
                container.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-danger">Error loading data: ${error.message}</small>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="selectInverterForStrings()">
                            <i class="fas fa-redo me-1"></i>Try Again
                        </button>
                    </div>
                </div>
            `;
            });
    }

    // Inicializar quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function () {
        // Button already has onclick="selectInverterForStrings()" defined in HTML,
        // so we don't need to add another event listener here

        // Initialize inverters list from saved data if it exists
        const invertersDataField = document.getElementById('inverters_data');
        if (invertersDataField && invertersDataField.value) {
            try {
                invertersList = JSON.parse(invertersDataField.value);
                console.log('Inverters loaded from hidden field:', invertersList);
            } catch (error) {
                console.error('Error parsing inverter data:', error);
            }
        }

        // Adicionar listener para o evento de atualização da lista de inversores
        document.addEventListener('invertersListUpdated', function (event) {
            console.log('Evento de atualização de inversores recebido:', event.detail.invertersList);
            // Atualizar a lista local de inversores
            invertersList = event.detail.invertersList;
            // Load the string measurement tables for all inverters
            console.log('[String Functions] Event listener triggering loadAllInverterStringTables()');
            loadAllInverterStringTables();
        });
        // Ensure the global MPPT toggle is initialized (no-op if not present)
        try { setupGlobalMPPTToggleListener(); } catch (e) { console.error('Global MPPT toggle initialization failed', e); }
    });

    /**
     * Restore and wire up string input persistence inside a container element.
     * Saves each input by its id into database draft key 'string_inputs'.
     * @param {HTMLElement} container
     */
    function restoreStringInputs(container) {
        if (!container) return;
        const skipLS = !!window.EDIT_MODE_SKIP_LOCALSTORAGE;

        // In edit mode, skip database draft loading
        if (skipLS) return;

        // Load from database draft
        fetch('ajax/load_string_inputs_draft.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.string_inputs) {
                    const storedObj = data.string_inputs;

                    // Only consider inputs that have an id
                    container.querySelectorAll('input').forEach(input => {
                        if (!input.id) return;
                        if (storedObj.hasOwnProperty(input.id)) {
                            input.value = storedObj[input.id];
                        }

                        // Attach listener to persist changes to database draft
                        input.addEventListener('input', function () {
                            if (skipLS) return;
                            try {
                                storedObj[input.id] = this.value;
                                fetch('ajax/save_string_inputs_draft.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        string_inputs: storedObj
                                    })
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (!data.success) {
                                            console.warn('Could not save string input to database draft:', data.error);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error saving string input to database draft:', error);
                                    });
                            } catch (err) {
                                console.warn('Could not save string input to database draft', err);
                            }
                        });
                    });
                }
            })
            .catch(err => {
                console.warn('Could not restore string inputs from database draft', err);
            });
    }

    /* Copy previous row values handler */
    function setupCopyPrevRowButtons(container) {
        if (!container) return;
        container.querySelectorAll('.copy-prev-row').forEach(btn => {
            // Avoid adding listener twice
            if (btn.dataset.copyHandler === '1') return;
            btn.dataset.copyHandler = '1';
            btn.addEventListener('click', function () {
                const row = btn.closest('tr');
                if (!row) return;
                const prev = row.previousElementSibling;
                if (!prev) {
                    // Nothing to copy
                    btn.classList.add('disabled');
                    setTimeout(() => btn.classList.remove('disabled'), 400);
                    return;
                }
                // Copy matching inputs from prev to current
                const prevInputs = prev.querySelectorAll('input');
                const curInputs = row.querySelectorAll('input');
                for (let i = 0; i < curInputs.length; i++) {
                    if (!curInputs[i] || !prevInputs[i]) continue;
                    curInputs[i].value = prevInputs[i].value;
                    // Trigger input event to ensure autosave & validations
                    try { curInputs[i].dispatchEvent(new Event('input', { bubbles: true })); } catch (err) { /* ignore */ }
                }
                // Brief highlight feedback
                row.classList.add('copy-row-highlight');
                setTimeout(() => row.classList.remove('copy-row-highlight'), 800);
            });
        });
    }

    /**
     * Function to automatically load string measurement tables for all inverters
     */
    function loadAllInverterStringTables() {
        console.log('[String Functions] Loading string tables for all inverters');

        // Debounce to avoid rapid consecutive renders
        if (window.__stringsRenderTimer) {
            clearTimeout(window.__stringsRenderTimer);
        }
        window.__stringsRenderTimer = setTimeout(() => {
            try {
                console.log('[String Functions] Initializing rendering of tables...');
                // Check if the hidden element with inverter data exists and update the list
                const invertersDataField = document.getElementById('inverters_data');
                if (invertersDataField && invertersDataField.value) {
                    try {
                        window.invertersList = JSON.parse(invertersDataField.value);
                        console.log('[String Functions] Hidden inverters_data parsed. count:', (window.invertersList || []).length);
                    } catch (error) {
                        console.error('[String Functions] Error parsing inverter data:', error);
                    }
                } else {
                    console.log('[String Functions] Campo hidden inverters_data vazio ou inexistente');
                }

                const container = document.getElementById('string-tables-container');
                if (!container) {
                    console.error('Container string-tables-container não encontrado');
                    return;
                }

                // Verificar se temos inversores
                if (!window.invertersList || window.invertersList.length === 0) {
                    container.innerHTML = `
                    <div class="text-center p-4">
                        <div class="card border-warning">
                            <div class="card-body py-4">
                                <i class="fas fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                                <h6 class="card-title mb-2">No Inverters Added</h6>
                                <p class="card-text text-muted small mb-3">Add inverters in the Equipment tab first.</p>
                                <button type="button" class="btn btn-primary btn-sm px-4" onclick="document.getElementById('equipment-tab').click()">
                                    <i class="fas fa-tools me-2"></i> Go to Equipment
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                    try { document.dispatchEvent(new CustomEvent('stringTablesReady', { detail: { when: Date.now(), count: 0, empty: true } })); } catch (_) { }
                    return;
                }

                // Show loading indicator
                container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading measurement tables...</div></div>';

                // Gerar tabelas para todos os inversores
                let allTablesHtml = '';
                let loadedCount = 0;

                window.invertersList.forEach((inverter, index) => {
                    console.log('[String Functions] Processing inverter', index);
                    console.log('[String Functions] model_id raw value:', inverter.model_id);
                    console.log('[String Functions] model_id type:', typeof inverter.model_id);
                    console.log('[String Functions] Full inverter object:', inverter);

                    // Convert model_id to number if it's a string
                    const modelId = inverter.model_id ? parseInt(inverter.model_id) : 0;
                    console.log('[String Functions] model_id converted:', modelId);

                    // Check if model_id is valid
                    if (!modelId || modelId <= 0) {
                        console.warn('[String Functions] Invalid model_id for inverter', index, '- skipping AJAX call');
                        allTablesHtml += `
                            <div class="card mb-3 border-warning">
                                <div class="card-header bg-warning text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bolt me-2"></i>
                                        ${inverter.brand_name} ${inverter.model_name} - S/N: ${inverter.serial_number || 'N/A'} - Location: ${inverter.location || 'N/A'}
                                        <span class="badge bg-light text-dark float-end">Inverter ${index + 1} of ${window.invertersList.length}</span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Model ID not available. Cannot load MPPT configuration.
                                    </div>
                                </div>
                            </div>
                        `;
                        loadedCount++;
                        if (loadedCount === window.invertersList.length) {
                            container.innerHTML = allTablesHtml;
                            restoreStringInputs(container);
                        }
                        return;
                    }

                    // Fazer requisição AJAX para cada inversor
                    fetch(`${window.BASE_URL}ajax/get_inverter_data.php?inverter_id=${modelId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data && !data.error) {
                                console.log('[String Functions] Data received for inverter', index, data);
                            } else {
                                console.warn('[String Functions] No data for inverter', index, data);
                            }
                            // Gerar HTML da tabela para este inversor
                            let tableHtml = generateInverterStringTableHtml(inverter, data, index);
                            allTablesHtml += tableHtml;
                            loadedCount++;
                            if (loadedCount === window.invertersList.length) {
                                container.innerHTML = allTablesHtml;
                                // Restore persistence in new tables
                                restoreStringInputs(container);
                                // Initialize MPPT per-card toggles and global toggle control
                                try { setupMPPTViewToggles(container); } catch (e) { console.error('MPPT view setup failed in loadAllInverterStringTables', e); }
                                try { setupGlobalMPPTToggleListener(); } catch (e) { console.error('Global MPPT toggle setup failed in loadAllInverterStringTables', e); }
                                // Hook up copy-prev-row buttons
                                try { setupCopyPrevRowButtons(container); } catch (e) { console.error('Copy prev row setup failed', e); }
                                // Reapply MPPT view toggles and global listener after all tables are rendered
                                try { setupMPPTViewToggles(container); } catch (e) { console.error('MPPT view setup failed in loadAllInverterStringTables', e); }
                                try { setupGlobalMPPTToggleListener(); } catch (e) { console.error('Global MPPT toggle setup failed in loadAllInverterStringTables', e); }
                                // Also reapply values from hidden JSON (SQL draft or last serialize)
                                try {
                                    const toApply = (function () {
                                        const hidden = document.getElementById('string_measurements_data');
                                        if (!hidden || !hidden.value) return null;
                                        let parsed;
                                        try { parsed = JSON.parse(hidden.value); } catch (e) { parsed = hidden.value; }
                                        if (!parsed) return null;
                                        if (!Array.isArray(parsed) && typeof parsed === 'object') return parsed;
                                        if (!Array.isArray(parsed)) return null;
                                        const idMap = {};
                                        const invs = window.invertersList || [];
                                        const idToIndex = (inverterId) => {
                                            for (let i = 0; i < invs.length; i++) { if (String(invs[i].model_id) === String(inverterId)) return i; }
                                            return 0;
                                        };
                                        parsed.forEach(item => {
                                            const invIdx = (item.inverter_index === null || typeof item.inverter_index === 'undefined')
                                                ? (item.inverter_id ? idToIndex(item.inverter_id) : 0)
                                                : item.inverter_index;
                                            const mppt = item.mppt || 1;
                                            const s = item.string_num || 1;
                                            if (item.voc !== undefined && item.voc !== '') idMap[`string_voc_${invIdx}_${mppt}_${s}`] = item.voc;
                                            if (item.isc !== undefined && item.isc !== '') idMap[`string_current_${invIdx}_${mppt}_${s}`] = item.isc;
                                            if (item.rins !== undefined && item.rins !== '') idMap[`string_rins_${invIdx}_${mppt}_${s}`] = item.rins;
                                            if (item.irr !== undefined && item.irr !== '') idMap[`string_irr_${invIdx}_${mppt}_${s}`] = item.irr;
                                            if (item.temp !== undefined && item.temp !== '') idMap[`string_temp_${invIdx}_${mppt}_${s}`] = item.temp;
                                            if (item.rlo !== undefined && item.rlo !== '') idMap[`string_rlo_${invIdx}_${mppt}_${s}`] = item.rlo;
                                        });
                                        return idMap;
                                    })();
                                    if (toApply && typeof window.restoreStringMeasurementsFromData === 'function') {
                                        window.restoreStringMeasurementsFromData(toApply);
                                    }
                                } catch (e) { console.warn('[String Functions] Could not apply hidden string data after render', e); }
                                // Also apply from existing report data if available
                                try {
                                    if (window.stringMeasurementsFromEquipment && Array.isArray(window.stringMeasurementsFromEquipment)) {
                                        const idMap = {};
                                        const invs = window.invertersList || [];
                                        const idToIndex = (inverterId) => {
                                            for (let i = 0; i < invs.length; i++) { if (String(invs[i].model_id) === String(inverterId)) return i; }
                                            return 0;
                                        };
                                        window.stringMeasurementsFromEquipment.forEach(item => {
                                            const invIdx = item.inverter_index !== undefined ? item.inverter_index : (item.inverter_id ? idToIndex(item.inverter_id) : 0);
                                            const mppt = item.mppt || 1;
                                            const s = item.string_num || 1;
                                            if (item.voc !== undefined && item.voc !== '') idMap[`string_voc_${invIdx}_${mppt}_${s}`] = item.voc;
                                            if (item.isc !== undefined && item.isc !== '') idMap[`string_current_${invIdx}_${mppt}_${s}`] = item.isc;
                                            if (item.rins !== undefined && item.rins !== '') idMap[`string_rins_${invIdx}_${mppt}_${s}`] = item.rins;
                                            if (item.irr !== undefined && item.irr !== '') idMap[`string_irr_${invIdx}_${mppt}_${s}`] = item.irr;
                                            if (item.temp !== undefined && item.temp !== '') idMap[`string_temp_${invIdx}_${mppt}_${s}`] = item.temp;
                                            if (item.rlo !== undefined && item.rlo !== '') idMap[`string_rlo_${invIdx}_${mppt}_${s}`] = item.rlo;
                                            if (item.notes !== undefined && item.notes !== '') idMap[`string_notes_${invIdx}_${mppt}_${s}`] = item.notes;
                                        });
                                        if (typeof window.restoreStringMeasurementsFromData === 'function') {
                                            window.restoreStringMeasurementsFromData(idMap);
                                        }
                                    }
                                } catch (e) { console.warn('[String Functions] Could not apply existing string data after render', e); }
                                // Dispatch readiness event for global loading overlay controller
                                try {
                                    document.dispatchEvent(new CustomEvent('stringTablesReady', { detail: { when: Date.now(), count: (window.invertersList || []).length } }));
                                } catch (_evtErr) { /* no-op */ }
                            }
                        })
                        .catch(err => {
                            console.error('[String Functions] Erro ao buscar dados do inversor', index, err);
                            loadedCount++;
                            if (loadedCount === window.invertersList.length) {
                                container.innerHTML = allTablesHtml || '<div class="alert alert-warning">Could not load inverter data.</div>';
                                try { document.dispatchEvent(new CustomEvent('stringTablesReady', { detail: { when: Date.now(), count: (window.invertersList || []).length, error: true } })); } catch (_) { }
                            }
                        });
                });
            } catch (err) {
                console.error('[String Functions] Falha ao renderizar tabelas:', err);
            }
        }, 50);

        // Verificar se o elemento hidden com dados dos inversores existe e atualizar a lista
        const invertersDataField = document.getElementById('inverters_data');
        if (invertersDataField && invertersDataField.value) {
            try {
                window.invertersList = JSON.parse(invertersDataField.value);
                console.log('Lista de inversores atualizada:', window.invertersList);
            } catch (error) {
                console.error('Error parsing inverter data:', error);
            }
        }

        const container = document.getElementById('string-tables-container');
        if (!container) {
            console.error('Container string-tables-container não encontrado');
            return;
        }

        // Verificar se temos inversores
        if (!window.invertersList || window.invertersList.length === 0) {
            container.innerHTML = `
            <div class="text-center p-4">
                <div class="card border-warning">
                    <div class="card-body py-4">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                        <h6 class="card-title mb-2">No Inverters Added</h6>
                        <p class="card-text text-muted small mb-3">Add inverters in the Equipment tab first.</p>
                        <button type="button" class="btn btn-primary btn-sm px-4" onclick="document.getElementById('equipment-tab').click()">
                            <i class="fas fa-tools me-2"></i> Go to Equipment
                        </button>
                    </div>
                </div>
            </div>
        `;
            try { document.dispatchEvent(new CustomEvent('stringTablesReady', { detail: { when: Date.now(), count: 0, empty: true } })); } catch (_) { }
            return;
        }

        // Show loading indicator
        container.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading measurement tables...</div></div>';

        // Gerar tabelas para todos os inversores
        let allTablesHtml = '';
        let loadedCount = 0;

        window.invertersList.forEach((inverter, index) => {
            // Fazer requisição AJAX para cada inversor
            fetch(`${window.BASE_URL}ajax/get_inverter_data.php?inverter_id=${inverter.model_id}`)
                .then(response => response.json())
                .then(data => {
                    // Gerar HTML da tabela para este inversor
                    let tableHtml = generateInverterStringTableHtml(inverter, data, index);
                    allTablesHtml += tableHtml;

                    loadedCount++;

                    // Quando todas as tabelas forem carregadas, atualizar o container
                    if (loadedCount === window.invertersList.length) {
                        container.innerHTML = allTablesHtml;

                        // Restore string inputs across all loaded tables
                        try { restoreStringInputs(container); } catch (err) { console.warn('Could not restore string inputs after loading all tables', err); }

                        // Also reapply values from hidden JSON (SQL draft or last serialize)
                        try {
                            const hidden = document.getElementById('string_measurements_data');
                            if (hidden && hidden.value) {
                                let parsed = null;
                                try { parsed = JSON.parse(hidden.value); } catch (e) { }
                                let toApply = null;
                                if (parsed && !Array.isArray(parsed) && typeof parsed === 'object') {
                                    toApply = parsed;
                                } else if (Array.isArray(parsed)) {
                                    const idMap = {};
                                    const invs = window.invertersList || [];
                                    const idToIndex = (inverterId) => {
                                        for (let i = 0; i < invs.length; i++) { if (String(invs[i].model_id) === String(inverterId)) return i; }
                                        return 0;
                                    };
                                    parsed.forEach(item => {
                                        const invIdx = (item.inverter_index === null || typeof item.inverter_index === 'undefined')
                                            ? (item.inverter_id ? idToIndex(item.inverter_id) : 0)
                                            : item.inverter_index;
                                        const mppt = item.mppt || 1;
                                        const s = item.string_num || 1;
                                        if (item.voc !== undefined && item.voc !== '') idMap[`string_voc_${invIdx}_${mppt}_${s}`] = item.voc;
                                        if (item.isc !== undefined && item.isc !== '') idMap[`string_current_${invIdx}_${mppt}_${s}`] = item.isc;
                                        if (item.rins !== undefined && item.rins !== '') idMap[`string_rins_${invIdx}_${mppt}_${s}`] = item.rins;
                                        if (item.irr !== undefined && item.irr !== '') idMap[`string_irr_${invIdx}_${mppt}_${s}`] = item.irr;
                                        if (item.temp !== undefined && item.temp !== '') idMap[`string_temp_${invIdx}_${mppt}_${s}`] = item.temp;
                                        if (item.rlo !== undefined && item.rlo !== '') idMap[`string_rlo_${invIdx}_${mppt}_${s}`] = item.rlo;
                                    });
                                    toApply = idMap;
                                }
                                if (toApply && typeof window.restoreStringMeasurementsFromData === 'function') {
                                    window.restoreStringMeasurementsFromData(toApply);
                                }
                            }
                        } catch (e) { console.warn('[String Functions] Could not apply hidden string data after render (2)', e); }

                        // Dispatch readiness event so loading overlay can hide
                        try { document.dispatchEvent(new CustomEvent('stringTablesReady', { detail: { when: Date.now(), count: (window.invertersList || []).length } })); } catch (_) { }

                        // Adicionar event listeners para os botões de mudança de inversor (se existirem)
                        document.querySelectorAll('.change-inverter-btn').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const inverterIndex = this.getAttribute('data-inverter-index');
                                // Aqui poderia abrir um modal para trocar o inversor, mas por enquanto apenas log
                                console.log('Change inverter:', inverterIndex);
                            });
                        });
                    }
                })
                .catch(error => {
                    console.error(`Error loading data for inverter ${inverter.model_name}:`, error);
                    loadedCount++;

                    // Mesmo com erro, mostrar mensagem para este inversor
                    let errorHtml = `
                    <div class="card mb-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error loading ${inverter.brand_name} ${inverter.model_name}
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-danger mb-0">Unable to load data for this inverter.</p>
                        </div>
                    </div>
                `;
                    allTablesHtml += errorHtml;

                    if (loadedCount === window.invertersList.length) {
                        container.innerHTML = allTablesHtml;
                        try { document.dispatchEvent(new CustomEvent('stringTablesReady', { detail: { when: Date.now(), count: (window.invertersList || []).length, error: true } })); } catch (_) { }
                    }
                });
        });
    }

    /**
     * Função auxiliar para gerar o HTML da tabela de strings para um inversor específico
     */
    function generateInverterStringTableHtml(inverter, inverterData, index) {
        let tableHtml = `
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-plug text-primary me-2"></i>
                    ${inverter.brand_name} ${inverter.model_name}
                    ${inverter.serial_number ? ` - S/N: ${inverter.serial_number}` : ''}
                    ${inverter.location ? ` - Location: ${inverter.location}` : ''}
                </h6>
                <small class="text-muted">Inverter ${index + 1} of ${invertersList.length}</small>
            </div>
            <div class="card-body">
    `;

        // Verificar se temos dados de MPPT
        if (inverterData && inverterData.mppts && inverterData.mppts > 0) {
            tableHtml += `<p class="small text-muted mb-3">This inverter has ${inverterData.mppts} MPPT(s)</p>`;

            // Gerar tabela para cada MPPT
            for (let mppt = 1; mppt <= inverterData.mppts; mppt++) {
                tableHtml += `
                <div class="mb-4">
                    <h6 class="text-primary mb-2">MPPT ${mppt}</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 40px;">&nbsp;</th>
                                    <th class="text-center" style="width: 60px;">String</th>
                                    <th class="text-center">VoC (V)</th>
                                    <th class="text-center">Current (A)</th>
                                    <th class="text-center">R.INS (MΩ)</th>
                                    <th class="text-center">Irr (W/m²)</th>
                                    <th class="text-center">Temp (°C)</th>
                                    <th class="text-center" style="width: 100px;">R.LO (Ω)</th>
                                    <th class="text-center" style="width: 150px;">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

                // Gerar linhas para cada string do MPPT
                const stringsPerMppt = inverterData.strings_per_mppt || 1;
                // Adicionar hidden para identificar inverter (por índice) — útil para backend
                tableHtml += `<input type="hidden" name="string_inverter_index[]" value="${index}">`;
                for (let string = 1; string <= stringsPerMppt; string++) {
                    // Incluir índice do inversor no name para evitar colisões quando houver múltiplos inversores
                    tableHtml += `
                    <tr>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm copy-prev-row" title="Copy previous string values" aria-label="Copy previous row values">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                        <td class="text-center">${string}</td>
                        <td><input id="string_voc_${index}_${mppt}_${string}" name="string_voc_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="0.00"></td>
                        <td><input id="string_current_${index}_${mppt}_${string}" name="string_current_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="0.00"></td>
                        <td><input id="string_rins_${index}_${mppt}_${string}" name="string_rins_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="0.00"></td>
                        <td><input id="string_irr_${index}_${mppt}_${string}" name="string_irr_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="0.00"></td>
                        <td><input id="string_temp_${index}_${mppt}_${string}" name="string_temp_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="0.0"></td>
                        <td><input id="string_rlo_${index}_${mppt}_${string}" name="string_rlo_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="0.00"></td>
                        <td><input id="string_notes_${index}_${mppt}_${string}" name="string_notes_${index}_${mppt}_${string}" type="text" class="form-control form-control-sm" placeholder="Notes"></td>
                    </tr>
                `;
                }

                tableHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            }
        } else {
            tableHtml += `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                MPPT data not available for this inverter model.
            </div>
        `;
        }

        tableHtml += `
            </div>
        </div>
    `;

        return tableHtml;
    }

    // --- Live serialization + SQL autosave wiring for String Measurements ---
    // Debounced saver to avoid spamming autosave endpoint
    let __stringAutosaveTimer = null;
    function __debouncedStringAutosave() {
        if (typeof window.saveFormDataToSQL !== 'function') return;
        if (__stringAutosaveTimer) clearTimeout(__stringAutosaveTimer);
        __stringAutosaveTimer = setTimeout(() => {
            try { window.saveFormDataToSQL(); } catch (e) { console.warn('[String Functions] Autosave call failed', e); }
        }, 700);
    }

    // Delegate input events for all string measurement inputs to keep hidden JSON updated
    document.addEventListener('input', function (e) {
        const t = e.target;
        if (!t || t.tagName !== 'INPUT') return;
        if (!t.id || !/^string_/.test(t.id)) return;
        try {
            const payload = serializeStringMeasurements();
            if (payload) {
                // Hidden updated inside serialize; now optionally autosave to SQL
                __debouncedStringAutosave();
            }
        } catch (err) { console.warn('[String Functions] Live serialize failed', err); }
    });

    /**
     * Serialize string measurement inputs into a JSON payload for backend consumption.
     * This builds an array of objects with keys: inverter_id, inverter_index, mppt, string_num, voc, isc, vmp, imp, rins, irr, temp, rlo, notes
     */
    function serializeStringMeasurements() {
        const inputs = document.querySelectorAll('input[name^="string_"]');
        if (!inputs || inputs.length === 0) return null;

        const assembled = {};
        inputs.forEach(input => {
            const name = input.name; // e.g. string_voc_0_1_2 or string_voc_1_2
            const parts = name.split('_');
            if (parts.length < 4) return;

            // Determine metric and positions
            // Possible forms: string_metric_mppt_string  OR string_metric_inv_mppt_string
            let metric, invIdx, mppt, str;
            if (parts.length === 4) {
                [, metric, mppt, str] = parts;
                invIdx = null;
            } else {
                [, metric, invIdx, mppt, str] = parts;
            }

            const key = (invIdx === null ? '0' : invIdx) + '_' + mppt + '_' + str;
            if (!assembled[key]) assembled[key] = { inverter_index: invIdx, mppt: mppt, string_num: str, metrics: {} };
            assembled[key].metrics[metric] = input.value;
        });

        // Try to map inverter_index to inverter_id using inverters_data hidden field
        let inverters = [];
        const invField = document.getElementById('inverters_data');
        if (invField && invField.value) {
            try { inverters = JSON.parse(invField.value); } catch (e) { inverters = []; }
        }

        const out = [];
        Object.keys(assembled).forEach(k => {
            const e = assembled[k];
            let inverterId = '';
            if (e.inverter_index !== null && inverters[e.inverter_index]) {
                inverterId = inverters[e.inverter_index].model_id || '';
            } else if (e.inverter_index !== null) {
                inverterId = 'INV' + String(Number(e.inverter_index) + 1).padStart(3, '0');
            }

            out.push({
                inverter_id: inverterId,
                inverter_index: e.inverter_index,
                mppt: e.mppt,
                string_num: e.string_num,
                voc: e.metrics['voc'] || e.metrics['Voc'] || '',
                isc: e.metrics['isc'] || e.metrics['current'] || '',
                vmp: e.metrics['vmp'] || '',
                imp: e.metrics['imp'] || '',
                rins: e.metrics['rins'] || '',
                irr: e.metrics['irr'] || '',
                temp: e.metrics['temp'] || '',
                rlo: e.metrics['rlo'] || '',
                notes: e.metrics['notes'] || ''
            });
        });

        if (out.length === 0) return null;

        // Ensure there's a hidden input to carry this payload
        let hidden = document.querySelector('input[name="string_measurements_data"]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'string_measurements_data';
            document.forms[0].appendChild(hidden);
        }
        hidden.value = JSON.stringify(out);
        return hidden.value;
    }

    // Make function globally accessible for autosave
    window.loadAllInverterStringTables = loadAllInverterStringTables;

    /**
     * Restore string measurement values from autosave data
     */
    function restoreStringMeasurementsFromData(data) {
        console.log('[String Functions] Restoring string measurements from data:', data);
        if (!data) return;

        try {
            const measurements = typeof data === 'string' ? JSON.parse(data) : data;
            console.log('[String Functions] Parsed measurements:', measurements);

            // Apply values to all string measurement inputs
            Object.keys(measurements).forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = measurements[inputId];
                    console.log(`[String Functions] Restored ${inputId} = ${measurements[inputId]}`);
                }
            });
        } catch (err) {
            console.error('[String Functions] Error restoring string measurements:', err);
        }
    }

    // Make function globally accessible
    window.restoreStringMeasurementsFromData = restoreStringMeasurementsFromData;

    // Attach serializer to form submit (on DOM ready)
    document.addEventListener('DOMContentLoaded', function () {
        // Find commissioning form by id or fallback to first form
        let form = document.getElementById('commissioning-form');
        if (!form) form = document.querySelector('form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            try {
                serializeStringMeasurements();
            } catch (err) {
                console.warn('Could not serialize string measurements before submit', err);
            }
            // allow submit to continue
        });
    });

} // End of !window.REPORTS_PAGE check