/**
 * clear_form_data.js - Funções para limpar todos os dados do formulário
 */

// Quando o documento estiver completamente carregado
document.addEventListener('DOMContentLoaded', function () {
    // Skip on reports page
    if (window.REPORTS_PAGE) {
        console.log('[Clear Form Data] ⏭️ Skipping initialization on reports page');
        return;
    }

    // Encontrar o botão de limpar dados
    const clearAllDataBtn = document.getElementById('clear-all-data-btn');

    if (clearAllDataBtn) {
        clearAllDataBtn.addEventListener('click', confirmClearAllData);
    }
});

/**
 * Exibe uma confirmação antes de limpar todos os dados
 */
function confirmClearAllData() {
    customConfirm('Are you sure you want to delete ALL data entered in all sections? This action cannot be undone.', 'Warning: Clear All Data')
        .then(confirmed => {
            if (confirmed) {
                clearAllFormData();
            }
        });
}

/**
 * Limpa todos os dados de todos os formulários e tabelas
 */
function clearAllFormData() {
    try {
        // 1. Limpar todos os campos de formulários
        clearAllFormFields();

        // 2. Limpar dados de módulos PV
        clearPVModulesData();

        // 3. Limpar dados de inversores
        clearInvertersData();

        // 4. Limpar dados de strings
        clearStringMeasurementsData();

        // 5. Limpar tabelas dinâmicas (Energy Meters, Punch List, etc.)
        clearDynamicTablesData();

        // 6. Limpar todo o localStorage relacionado ao formulário
        clearAllLocalStorage();

        // 7. Reinicializar variáveis globais que possam conter dados
        resetGlobalVariables();

        // 8. Mostrar mensagem de sucesso
        customAlert('All data has been successfully cleared!', 'success');

        // 9. Redirecionar para a primeira aba (General) após pequeno delay
        setTimeout(() => {
            const generalTab = document.querySelector('#myTab button[data-bs-target="#general-tab"]');
            if (generalTab) {
                const tab = new bootstrap.Tab(generalTab);
                tab.show();
            }
        }, 500);

        // 10. Oferecer opção para recarregar a página se necessário
        setTimeout(() => {
            // Verificar se o botão já existe para não duplicar
            if (document.getElementById('force-reload-btn')) {
                return; // Botão já existe, não criar novamente
            }

            const reloadBtn = document.createElement('div');
            reloadBtn.className = 'text-center mt-3';
            reloadBtn.id = 'force-reload-container'; // Adicionar ID ao container
            reloadBtn.innerHTML = `
                <button id="force-reload-btn" class="btn btn-warning">
                    <i class="fas fa-sync-alt me-2"></i>Force Clear and Reload
                </button>
                <p class="text-muted small mt-2">Use only if data still persists after clearing</p>
            `;

            const clearBtnContainer = document.getElementById('clear-all-data-btn').parentNode;
            clearBtnContainer.appendChild(reloadBtn);

            document.getElementById('force-reload-btn').addEventListener('click', function () {
                // Force reload of page state
                sessionStorage.setItem('force_reload', 'true');

                // Recarregar a página
                window.location.reload(true);
            });
        }, 600);

    } catch (error) {
        console.error('Error clearing data:', error);
        customAlert('An error occurred while trying to clear the data. Please try again.', 'error');
    }
}

/**
 * Limpa todos os campos de formulários (inputs, selects, textareas)
 */
function clearAllFormFields() {
    // Limpar todos os inputs de texto/número
    document.querySelectorAll('input[type="text"], input[type="number"], input[type="email"], input[type="date"], input[type="tel"]').forEach(input => {
        input.value = '';
    });

    // Limpar todas as áreas de texto
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.value = '';
    });

    // Limpar todos os selects
    document.querySelectorAll('select').forEach(select => {
        select.value = '';
    });

    // Limpar checkboxes e radios
    document.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
        input.checked = false;
    });
}

/**
 * Clear all PV module data
 */
function clearPVModulesData() {
    // Clear modules array
    if (typeof modulesList !== 'undefined') {
        modulesList = [];
        updateModulesTable();

        // Update hidden field
        const modulesDataField = document.getElementById('modules_data');
        if (modulesDataField) modulesDataField.value = JSON.stringify(modulesList);

        // Recalculate installed power
        if (typeof updateInstalledPower === 'function') {
            updateInstalledPower();
        }
    }
}

/**
 * Clear all inverter data
 */
function clearInvertersData() {
    // Clear inverters array
    if (typeof window.invertersList !== 'undefined') {
        window.invertersList = [];

        // Update inverters table if function exists
        if (typeof updateInvertersTable === 'function') {
            updateInvertersTable();
        }

        // Atualizar container de cards de inversores
        const inverterCardsContainer = document.getElementById('inverter-cards-container');
        if (inverterCardsContainer) {
            inverterCardsContainer.innerHTML = '';
        }

        // Clear string measurements data associated with inverters
        clearStringMeasurementsData();

        // Update hidden field
        const invertersDataField = document.getElementById('inverters_data');
        if (invertersDataField) invertersDataField.value = JSON.stringify([]);

        // Dispatch inverters update event for other components
        const event = new CustomEvent('invertersListUpdated', {
            detail: { invertersList: [] }
        });
        document.dispatchEvent(event);
    }
}

/**
 * Clear string measurement data
 */
function clearStringMeasurementsData() {
    // Clear string tables container
    const stringTablesContainer = document.getElementById('string-tables-container');
    if (stringTablesContainer) {
        stringTablesContainer.innerHTML = '<div class="alert alert-info">Please select an inverter to configure string measurements.</div>';
    }
}

/**
 * Clear dynamic table data
 */
function clearDynamicTablesData() {
    // Limpar Energy Meters
    if (typeof energyMetersList !== 'undefined') {
        energyMetersList = [];
        if (typeof updateEnergyMetersTable === 'function') {
            updateEnergyMetersTable();
        }
    }

    // Limpar Punch List
    if (typeof punchListItems !== 'undefined') {
        punchListItems = [];
        if (typeof updatePunchListTable === 'function') {
            updatePunchListTable();
        }
    }

    // Limpar tabela de Communications
    if (typeof communicationDevices !== 'undefined') {
        communicationDevices = [];
        if (typeof updateCommunicationsTable === 'function') {
            updateCommunicationsTable();
        }
    }

    // Limpar tabela de Amperometric Clamp Measurements
    if (typeof ampClampMeasurements !== 'undefined') {
        ampClampMeasurements = [];
        if (typeof updateAmpClampTable === 'function') {
            updateAmpClampTable();
        }
    }

    // Limpar outros campos hidden que guardam dados JSON
    document.querySelectorAll('input[type="hidden"][name$="_data"]').forEach(hiddenInput => {
        hiddenInput.value = '';
    });
}

/**
 * Reset all global variables to their initial states
 */
function resetGlobalVariables() {
    // Reset module variables
    if (typeof modulesList !== 'undefined') {
        modulesList = [];
    }
    if (typeof window.modulesList !== 'undefined') {
        window.modulesList = [];
    }
    if (typeof editingModuleIndex !== 'undefined') {
        editingModuleIndex = -1;
    }

    // Reset inverter variables
    if (typeof window.invertersList !== 'undefined') {
        window.invertersList = [];
    }
    if (typeof window.invertersList !== 'undefined') {
        window.invertersList = [];
    }
    if (typeof editingInverterIndex !== 'undefined') {
        editingInverterIndex = -1;
    }

    // Reset other common variables
    if (typeof window.existingModules !== 'undefined') {
        window.existingModules = [];
    }
    if (typeof window.existingInverters !== 'undefined') {
        window.existingInverters = [];
    }

    // Reset dynamic tables variables
    if (typeof energyMetersList !== 'undefined') {
        energyMetersList = [];
    }
    if (typeof punchListItems !== 'undefined') {
        punchListItems = [];
    }
    if (typeof communicationDevices !== 'undefined') {
        communicationDevices = [];
    }
    if (typeof ampClampMeasurements !== 'undefined') {
        ampClampMeasurements = [];
    }

    // Reset any other global variables as needed
    console.log('All global variables have been reset to initial state');

    // Force reload of page state on next reload 
    sessionStorage.setItem('force_reload', 'true');
}

/**
 * Completely clear all stored data in database (drafts)
 */
function clearAllLocalStorage() {
    // Limpar dados específicos do comissionamento no banco de dados
    const keysToRemove = [
        'form_data',
        'modules',
        'layouts',
        'protection',
        'protection_cables',
        'clamp_measurements',
        'punch_list',
        'energy_meters',
        'telemetry_credentials',
        'telemetry_meters',
        'communications',
        'earth_resistance',
        'notes',
        'epc',
        'representative',
        'string_inputs'
    ];

    // Remover todas as chaves identificadas do banco de dados
    const clearPromises = keysToRemove.map(key => {
        return fetch((window.BASE_URL || "") + `ajax/clear_draft.php?key=${key}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`Cleared database draft for ${key}`);
                } else {
                    console.warn(`Could not clear database draft for ${key}:`, data.error);
                }
            })
            .catch(error => {
                console.error(`Error clearing database draft for ${key}:`, error);
            });
    });

    // Wait for all clear operations to complete
    Promise.all(clearPromises).then(() => {
        console.log(`Cleared ${keysToRemove.length} database draft items`);
    });
}
