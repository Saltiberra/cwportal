/**
 * dropdown-persistence-fix.js
 * Correção para preservar seleções dos dropdowns após carregamento dinâmico
 * Versão simplificada sem localStorage - apenas interceptação de mudanças
 */

(function () {
    'use strict';

    // Skip on reports page
    if (window.REPORTS_PAGE) {
        console.log('[Dropdown Persistence] ⏭️ Skipping initialization on reports page');
        return;
    }

    console.log('[Dropdown Persistence] Inicializando versão simplificada (sem localStorage)...');

    // Interceptar mudanças nos dropdowns principais
    function setupDropdownInterceptors() {
        const dropdownIds = [
            'new_module_brand',
            'new_module_model',
            'new_inverter_brand',
            'new_inverter_model',
            'new_circuit_breaker_brand',
            'new_circuit_breaker_model',
            'new_differential_brand',
            'new_differential_model',
            'meter_brand',
            'meter_model',
            'energy_meter_brand',
            'energy_meter_model',
            'comm_equipment',
            'comm_model'
        ];

        dropdownIds.forEach(dropdownId => {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                // Apenas log quando mudar (sem salvar)
                dropdown.addEventListener('change', function () {
                    console.debug('[Dropdown Persistence] Changed', dropdownId, this.value);
                });
            }
        });
    }

    // Modificar função loadEquipmentBrands para preservar seleções atuais
    // Wrap loadEquipmentBrands if present - still allow original behavior
    const originalLoadEquipmentBrands = window.loadEquipmentBrands;
    if (typeof originalLoadEquipmentBrands === 'function') {
        window.loadEquipmentBrands = function (type, targetElement) {
            // Salvar seleção atual temporariamente (apenas na memória)
            const dropdown = document.getElementById(targetElement);
            const currentValue = dropdown ? dropdown.value : null;

            const result = originalLoadEquipmentBrands.apply(this, arguments);

            // After original completes, tentar restaurar valor anterior se existir
            const attemptRestore = () => setTimeout(() => {
                if (currentValue && dropdown) {
                    const optionExists = Array.from(dropdown.options).some(opt => opt.value === currentValue);
                    if (optionExists) {
                        dropdown.value = currentValue;
                        dropdown.dispatchEvent(new Event('change', { bubbles: true }));
                        console.info(`[Dropdown Persistence] Restored ${targetElement} = ${currentValue}`);
                    }
                }
            }, 50);

            if (result && typeof result.then === 'function') {
                return result.then(data => {
                    attemptRestore();
                    return data;
                });
            } else {
                attemptRestore();
                return result;
            }
        };
    }

    // Modificar função loadEquipmentModels para preservar seleções atuais
    const originalLoadEquipmentModels = window.loadEquipmentModels;
    if (typeof originalLoadEquipmentModels === 'function') {
        window.loadEquipmentModels = function (type, brandId, targetElement) {
            const dropdown = document.getElementById(targetElement);
            const currentValue = dropdown ? dropdown.value : null;

            const result = originalLoadEquipmentModels.apply(this, arguments);

            const attemptRestore = () => setTimeout(() => {
                if (currentValue && dropdown) {
                    const optionExists = Array.from(dropdown.options).some(opt => opt.value === currentValue);
                    if (optionExists) {
                        dropdown.value = currentValue;
                        dropdown.dispatchEvent(new Event('change', { bubbles: true }));
                        console.info(`[Dropdown Persistence] Restored ${targetElement} = ${currentValue}`);
                    }
                }
            }, 50);

            if (result && typeof result.then === 'function') {
                return result.then(data => {
                    attemptRestore();
                    return data;
                });
            } else {
                attemptRestore();
                return result;
            }
        };
    }

    // Modificar função loadSavedFormData para trabalhar melhor com dropdowns dinâmicos
    const originalLoadSavedFormData = window.loadSavedFormData;
    if (typeof originalLoadSavedFormData === 'function') {
        window.loadSavedFormData = function () {
            const result = originalLoadSavedFormData.apply(this, arguments);
            // Não há restauração de seleções salvas, apenas executar função original
            return result;
        };
    }

    // Função para forçar restauração de seleções (placeholder - não faz nada na versão simplificada)
    window.forceRestoreDropdownSelections = function () {
        console.log('[Dropdown Persistence] Versão simplificada - nenhuma seleção para restaurar');
    };

    // Inicialização quando DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function () {
        console.log('[Dropdown Persistence] Inicializando correção de persistência (versão simplificada)...');

        // Configurar interceptadores
        setupDropdownInterceptors();

        // Setup MutationObserver para detectar mudanças nos dropdowns (sem persistência)
        const observer = new MutationObserver(mutations => {
            mutations.forEach(m => {
                if (m.type === 'childList' && m.target && m.target.tagName === 'SELECT') {
                    console.debug('[Dropdown Persistence] Options changed for select:', m.target.id);
                }
            });
        });

        // Observe all select elements currently in DOM and future ones added under body
        document.querySelectorAll('select').forEach(s => observer.observe(s, { childList: true, subtree: false }));

        const bodyObserver = new MutationObserver(mutations => {
            mutations.forEach(m => {
                m.addedNodes.forEach(node => {
                    if (node && node.querySelectorAll) {
                        node.querySelectorAll('select').forEach(s => observer.observe(s, { childList: true, subtree: false }));
                    }
                    if (node && node.tagName === 'SELECT') {
                        observer.observe(node, { childList: true, subtree: false });
                    }
                });
            });
        });
        bodyObserver.observe(document.body, { childList: true, subtree: true });

        // Adicionar listener para mudanças de aba
        const tabElements = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabElements.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function () {
                console.log('[Dropdown Persistence] Aba mudou - versão simplificada não restaura seleções');
            });
        });

        console.log('[Dropdown Persistence] Correção inicializada com sucesso (versão simplificada)!');
    });

    // Tornar funções globais para debug (versões simplificadas)
    window.saveDropdownSelection = function (dropdownId, value) {
        console.debug('[Dropdown Persistence] Save ignored (no localStorage):', dropdownId, value);
    };
    window.restoreDropdownSelection = function (dropdownId) {
        console.debug('[Dropdown Persistence] Restore ignored (no localStorage):', dropdownId);
        return false;
    };
    window.restoreAllPendingSelections = function () {
        console.log('[Dropdown Persistence] Restore all ignored (no localStorage)');
    };

})();