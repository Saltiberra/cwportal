// Shared container for inverters (attached to window) to avoid duplication between modules
window.invertersList = window.invertersList || [];

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function () {
    // Carregar inversores existentes se disponíveis
    if (window.existingInverters && Array.isArray(window.existingInverters)) {
        window.invertersList = window.existingInverters.map(inverter => {
            return {
                id: inverter.id || null,
                brand_id: inverter.brand_id || '',
                brand_name: inverter.brand || '',
                model_id: inverter.model_id || '',
                model_name: inverter.model || '',
                quantity: inverter.quantity || '1',
                status: inverter.status || 'new',
                status_text: inverter.status || 'New',
                location: inverter.location || '',
                serial_number: inverter.serial_number || '',
                datasheet_url: ''
            };
        });

        // Atualizar tabela e campo oculto
        updateInvertersTable();
        updateInvertersHiddenField();
        console.log('Inversores existentes carregados:', window.invertersList);
    }
});

/**
 * Função para adicionar inversor à tabela
 */
function addInverterToTableLegacy() {
    console.log('Função addInverterToTable chamada');

    // Obter valores do formulário
    const brandSelect = document.getElementById('new_inverter_brand');
    const modelSelect = document.getElementById('new_inverter_model');
    const statusSelect = document.getElementById('new_inverter_status');
    const locationInput = document.getElementById('new_inverter_location');
    const serialInput = document.getElementById('new_inverter_serial');

    // Verificar se os campos obrigatórios foram preenchidos
    if (!brandSelect.value || !modelSelect.value) {
        customModal.showWarning('Please fill in the inverter brand and model.');
        return;
    }

    // Obter o texto dos selects
    const brandName = brandSelect.options[brandSelect.selectedIndex].text;
    const modelName = modelSelect.options[modelSelect.selectedIndex].text;
    const statusText = statusSelect.options[statusSelect.selectedIndex].text;

    // Criar objeto do inversor
    const inverterData = {
        brand_id: brandSelect.value,
        brand_name: brandName,
        model_id: modelSelect.value,
        model_name: modelName,
        quantity: 1, // Default quantity since field was removed
        status: statusSelect.value,
        status_text: statusText,
        location: locationInput.value || '-',
        serial_number: serialInput ? serialInput.value || '-' : '-',
        datasheet_url: ''
    };

    // Adicionar à lista de inversores
    window.invertersList.push(inverterData);

    // Atualizar tabela
    updateInvertersTable();

    // Atualizar campo hidden com os dados
    updateInvertersHiddenField();

    // Clear form fields
    resetInverterForm();

    console.log('Inversor adicionado com sucesso:', inverterData);
}

/**
 * Função para atualizar a tabela com os inversores atuais
 */
function updateInvertersTable() {
    const tableBody = document.getElementById('inverters-table-body');

    if (!tableBody) {
        console.error('Corpo da tabela de inversores não encontrado');
        return;
    }

    // Clear existing table
    tableBody.innerHTML = '';

    // Se não houver inversores, mostrar mensagem
    if (window.invertersList.length === 0) {
        const noDataRow = document.createElement('tr');
        noDataRow.innerHTML = '<td colspan="7" class="text-center">No inverters added yet</td>';
        tableBody.appendChild(noDataRow);
        return;
    }

    // Adicionar cada inversor à tabela
    window.invertersList.forEach((inverter, index) => {
        const row = document.createElement('tr');

        // Determinar botões de datasheet
        let datasheetBtn = inverter.datasheet_url
            ? `<a href="${inverter.datasheet_url}" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-file-pdf"></i></a>`
            : '-';

        // Criar HTML da linha
        row.innerHTML = `
            <td>${inverter.brand_name}</td>
            <td>${inverter.model_name}</td>
            <td>${inverter.quantity}</td>
            <td>${inverter.status_text}</td>
            <td>${inverter.serial_number || '-'}</td>
            <td>${inverter.location}</td>
            <td class="text-center">${datasheetBtn}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-primary edit-inverter" data-index="${index}">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger delete-inverter" data-index="${index}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(row);
    });

    // Adicionar eventos aos botões
    addInverterTableButtonEvents();
}

/**
 * Função para adicionar eventos aos botões da tabela de inversores
 */
function addInverterTableButtonEvents() {
    // Eventos para botões de editar
    document.querySelectorAll('.edit-inverter').forEach(button => {
        button.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            editInverter(index);
        });
    });

    // Eventos para botões de deletar
    document.querySelectorAll('.delete-inverter').forEach(button => {
        button.addEventListener('click', function () {
            const index = parseInt(this.getAttribute('data-index'));
            deleteInverter(index);
        });
    });
}

/**
 * Função para editar um inversor
 */
function editInverter(index) {
    const inverter = window.invertersList[index];
    if (!inverter) return;

    // Preencher o formulário com os dados do inversor
    document.getElementById('new_inverter_brand').value = inverter.brand_id;

    // Carregar os modelos da marca e depois selecionar o correto
    fetchModels('inverter', inverter.brand_id, 'new_inverter_model');

    // Aguardar um pouco para o dropdown ser preenchido e então selecionar o modelo
    setTimeout(() => {
        const modelDropdown = document.getElementById('new_inverter_model');
        if (modelDropdown) modelDropdown.value = inverter.model_id;
    }, 500);

    document.getElementById('new_inverter_status').value = inverter.status;
    document.getElementById('new_inverter_location').value = inverter.location;

    // Preencher número de série se o campo existir
    const serialInput = document.getElementById('new_inverter_serial');
    if (serialInput && inverter.serial_number) {
        serialInput.value = inverter.serial_number;
    }

    // Don't remove the inverter when entering edit mode — keep its position so its numbering (Inverter 1, Inverter 2...) is preserved
    // Set global editing index (main.js uses this when saving to update the item in place)
    if (typeof window.editingInverterIndex === 'undefined') window.editingInverterIndex = -1;
    window.editingInverterIndex = index;

    // Update submit button to indicate update action (also ensure validation runs)
    const submitBtn = document.getElementById('submit-inverter-btn');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Update Inverter';
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-warning');
    }
    if (typeof validateAssociatedEquipment === 'function') {
        setTimeout(() => validateAssociatedEquipment(), 150);
    }

    // No removal; simply reflect the UI that we're in edit mode
    updateInvertersTable();
    updateInvertersHiddenField();
}

/**
 * Função para deletar um inversor
 */
function deleteInverter(index) {
    customModal.showConfirm('Are you sure you want to remove this inverter?', 'Confirm Deletion')
        .then(confirmed => {
            if (!confirmed) return;

            const inv = window.invertersList[index];
            if (inv && (inv.id || inv.inverter_id)) {
                // Persist deletion to server
                fetch((window.BASE_URL || '') + 'ajax/delete_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table: 'report_equipment', id: inv.id || inv.inverter_id })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            window.invertersList.splice(index, 1);
                            updateInvertersTable();
                            updateInvertersHiddenField();
                            showToast('Inverter removed', 'success');
                        } else {
                            showToast(data.error || 'Failed to remove inverter', 'danger');
                        }
                    }).catch(err => {
                        console.error('Failed to delete inverter on server:', err);
                        showToast('Failed to remove inverter', 'danger');
                    });
            } else {
                // local only
                window.invertersList.splice(index, 1);
                updateInvertersTable();
                updateInvertersHiddenField();
            }
        });
}

/**
 * Função para atualizar o campo hidden com os dados dos inversores
 */
function updateInvertersHiddenField() {
    const hiddenField = document.getElementById('inverters_data');
    if (hiddenField) {
        hiddenField.value = JSON.stringify(window.invertersList);

        // Disparar um evento personalizado para notificar que a lista de inversores mudou
        const event = new CustomEvent('invertersListUpdated', {
            detail: { invertersList: window.invertersList }
        });
        document.dispatchEvent(event);
    }
}

/**
 * Function to clear the inverter form
 */
function resetInverterForm() {
    document.getElementById('new_inverter_model').innerHTML = '<option value="">Select Model...</option>';
    document.getElementById('new_inverter_brand').value = '';
    document.getElementById('new_inverter_status').value = 'new';
    document.getElementById('new_inverter_location').value = '';

    const serialInput = document.getElementById('new_inverter_serial');
    if (serialInput) serialInput.value = '';
}
