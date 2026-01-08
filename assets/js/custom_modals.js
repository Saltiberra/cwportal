/**
 * custom_modals.js
 * Sistema completo de modais para substituir popups nativos do browser
 */

// Classe para gerenciar modais personalizados
class CustomModalManager {
    constructor() {
        this.confirmCallback = null;
        this.init();
    }

    init() {
        // Inicializar event listeners quando o DOM estiver pronto
        document.addEventListener('DOMContentLoaded', () => {
            this.setupConfirmModal();
        });
    }

    setupConfirmModal() {
        const confirmYesBtn = document.getElementById('confirmModalYesBtn');
        if (confirmYesBtn) {
            confirmYesBtn.addEventListener('click', () => {
                if (this.confirmCallback) {
                    this.confirmCallback(true);
                    this.confirmCallback = null;
                }
                const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                modal.hide();
            });
        }

        // Clear callback when modal is closed without confirming
        const confirmModal = document.getElementById('confirmModal');
        if (confirmModal) {
            confirmModal.addEventListener('hidden.bs.modal', () => {
                if (this.confirmCallback) {
                    this.confirmCallback(false);
                    this.confirmCallback = null;
                }
            });
        }
    }

    // Substituir alert() - Modal informativo (usando warning modal)
    showInfo(message, title = 'Information') {
        return this.showWarning(message, title);
    }

    // Substituir alert() para avisos - Modal de aviso
    showWarning(message, title = 'Warning') {
        return new Promise((resolve) => {
            const modal = new bootstrap.Modal(document.getElementById('warningModal'));
            document.getElementById('warningModalLabel').innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${title}`;
            document.getElementById('warningModalMessage').innerHTML = `
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>${message}
                </div>
            `;
            modal.show();

            // Resolver promise quando modal for fechado
            document.getElementById('warningModal').addEventListener('hidden.bs.modal', () => {
                resolve();
            }, { once: true });
        });
    }

    // Modal de erro
    showError(message, title = 'Error') {
        return new Promise((resolve) => {
            const modal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('errorModalLabel').innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${title}`;
            document.getElementById('errorModalMessage').innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>${message}
                </div>
            `;
            modal.show();

            // Resolver promise quando modal for fechado
            document.getElementById('errorModal').addEventListener('hidden.bs.modal', () => {
                resolve();
            }, { once: true });
        });
    }

    // Modal de sucesso
    showSuccess(message, title = 'Success') {
        return new Promise((resolve) => {
            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successModalLabel').innerHTML = `<i class="fas fa-check-circle me-2"></i>${title}`;
            document.getElementById('successModalMessage').innerHTML = `
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i>${message}
                </div>
            `;
            modal.show();

            // Resolver promise quando modal for fechado
            document.getElementById('successModal').addEventListener('hidden.bs.modal', () => {
                resolve();
            }, { once: true });
        });
    }

    // Substituir confirm() - Modal de confirmação
    showConfirm(message, title = 'Confirmation') {
        return new Promise((resolve) => {
            this.confirmCallback = resolve;
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmModalLabel').innerHTML = `<i class="fas fa-question-circle me-2"></i>${title}`;
            document.getElementById('confirmModalMessage').innerHTML = `
                <div class="alert alert-primary mb-0">
                    <i class="fas fa-question-circle me-2"></i>${message}
                </div>
            `;
            modal.show();
        });
    }

    // Substituir prompt() - Modal de entrada (se necessário)
    showPrompt(message, defaultValue = '', title = 'Input') {
        return new Promise((resolve) => {
            // Criar modal dinamicamente para entrada
            const modalHtml = `
                <div class="modal fade" id="promptModal" tabindex="-1" aria-labelledby="promptModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-primary">
                                <h5 class="modal-title text-white" id="promptModalLabel">
                                    <i class="fas fa-edit me-2"></i>${title}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-primary mb-3">
                                    <i class="fas fa-edit me-2"></i>${message}
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="promptInput" value="${defaultValue}" placeholder="Type here...">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                                <button type="button" class="btn btn-primary" id="promptModalOkBtn">
                                    <i class="fas fa-check me-1"></i>OK
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Adicionar modal ao documento
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modal = new bootstrap.Modal(document.getElementById('promptModal'));
            const input = document.getElementById('promptInput');
            const okBtn = document.getElementById('promptModalOkBtn');

            // Focar no input quando modal abrir
            modal.show();
            setTimeout(() => input.focus(), 500);

            // Resolver promise quando OK for clicado
            okBtn.addEventListener('click', () => {
                const value = input.value;
                modal.hide();
                resolve(value);
            });

            // Resolver promise quando modal for fechado
            document.getElementById('promptModal').addEventListener('hidden.bs.modal', () => {
                resolve(null);
                // Remover modal do DOM
                document.getElementById('promptModal').remove();
            }, { once: true });
        });
    }
}

// Instância global do gerenciador de modais
const customModal = new CustomModalManager();

// Funções globais para substituir popups nativos
function customAlert(message, type = 'info', title = null) {
    switch(type) {
        case 'warning':
        case 'alert':
            return customModal.showWarning(message, title || 'Atenção');
        case 'error':
            return customModal.showError(message, title || 'Erro');
        case 'success':
            return customModal.showSuccess(message, title || 'Sucesso');
        default:
            return customModal.showInfo(message, title || 'Informação');
    }
}

function customConfirm(message, title = 'Confirmação') {
    return customModal.showConfirm(message, title);
}

function customPrompt(message, defaultValue = '', title = 'Input') {
    return customModal.showPrompt(message, defaultValue, title);
}

// Sobrescrever funções nativas globalmente (opcional - use com cuidado)
// window.alert = customAlert;
// window.confirm = customConfirm;
// window.prompt = customPrompt;
