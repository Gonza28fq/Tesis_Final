// =============================================
// SISTEMA DE GESTIÓN COMERCIAL 2.0
// Scripts principales y manejo responsive
// =============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // =============================================
    // MANEJO DEL SIDEBAR
    // =============================================
    
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const mainContent = document.querySelector('.main-content');
    
    // Toggle sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth > 992) {
                // Desktop: colapsar sidebar
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            } else {
                // Mobile: mostrar/ocultar sidebar
                sidebar.classList.toggle('show');
                toggleOverlay();
            }
        });
    }
    
    // Restaurar estado del sidebar en desktop
    if (window.innerWidth > 992) {
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
        }
    }
    
    // Crear overlay para móvil
    function createOverlay() {
        if (!document.querySelector('.sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    }
    
    function toggleOverlay() {
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.classList.toggle('show');
        } else {
            createOverlay();
            setTimeout(() => {
                document.querySelector('.sidebar-overlay').classList.add('show');
            }, 10);
        }
    }
    
    // Ajustar sidebar en resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('show');
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.classList.remove('show');
            }
        } else {
            sidebar.classList.remove('collapsed');
        }
    });
    
    // =============================================
    // MENÚ DE USUARIO (DROPDOWN)
    // =============================================
    
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        userMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.querySelector('.dropdown-menu');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        });
        
        // Cerrar dropdown al hacer click fuera
        document.addEventListener('click', function() {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });
    }
    
    // =============================================
    // TOOLTIPS DE BOOTSTRAP
    // =============================================
    
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // =============================================
    // CONFIRMACIONES DE ELIMINACIÓN
    // =============================================
    
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete') || '¿Está seguro de eliminar este elemento?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // =============================================
    // AUTO-HIDE ALERTS
    // =============================================
    
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // =============================================
    // FORMATEO DE INPUTS DE MONEDA
    // =============================================
    
    const currencyInputs = document.querySelectorAll('input[data-type="currency"]');
    currencyInputs.forEach(input => {
        input.addEventListener('blur', function() {
            let value = this.value.replace(/[^0-9.]/g, '');
            if (value) {
                this.value = parseFloat(value).toFixed(2);
            }
        });
    });
    
    // =============================================
    // VALIDACIÓN DE FORMULARIOS
    // =============================================
    
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // =============================================
    // BÚSQUEDA EN TIEMPO REAL EN TABLAS
    // =============================================
    
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-table-search');
        const table = document.getElementById(tableId);
        
        if (table) {
            input.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    });
    
    // =============================================
    // SELECT2 INITIALIZATION (si está disponible)
    // =============================================
    
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
    
    // =============================================
    // DATEPICKER (si está disponible)
    // =============================================
    
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Establecer fecha máxima como hoy si no está definida
        if (!input.getAttribute('max')) {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('max', today);
        }
    });
    
    // =============================================
    // TABLAS RESPONSIVAS (agregar data-label)
    // =============================================
    
    function makeTablesResponsive() {
        const tables = document.querySelectorAll('.table-responsive table');
        tables.forEach(table => {
            const headers = table.querySelectorAll('thead th');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index].textContent);
                    }
                });
            });
        });
    }
    
    makeTablesResponsive();
    
    // =============================================
    // LOADING SPINNER PARA FORMULARIOS
    // =============================================
    
    const submitButtons = document.querySelectorAll('form button[type="submit"]');
    submitButtons.forEach(button => {
        button.closest('form').addEventListener('submit', function() {
            if (this.checkValidity()) {
                button.disabled = true;
                const originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
                
                // Restaurar después de 5 segundos (por si falla la carga)
                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                }, 5000);
            }
        });
    });
    
    // =============================================
    // CONTADOR DE CARACTERES EN TEXTAREAS
    // =============================================
    
    const textareasWithCounter = document.querySelectorAll('textarea[maxlength]');
    textareasWithCounter.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('small');
        counter.className = 'form-text text-muted';
        counter.textContent = `0 / ${maxLength} caracteres`;
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            counter.textContent = `${currentLength} / ${maxLength} caracteres`;
            
            if (currentLength >= maxLength * 0.9) {
                counter.classList.add('text-warning');
            } else {
                counter.classList.remove('text-warning');
            }
        });
    });
    
    // =============================================
    // ANIMACIÓN DE ENTRADA PARA CARDS
    // =============================================
    
    const cards = document.querySelectorAll('.card');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    cards.forEach(card => {
        observer.observe(card);
    });
    
    // =============================================
    // PREVENIR DOUBLE SUBMIT
    // =============================================
    
    const allForms = document.querySelectorAll('form');
    allForms.forEach(form => {
        let submitted = false;
        form.addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
            
            // Reset después de 3 segundos
            setTimeout(() => {
                submitted = false;
            }, 3000);
        });
    });
    
    // =============================================
    // FUNCIONES GLOBALES ÚTILES
    // =============================================
    
    // Formatear moneda
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS'
        }).format(amount);
    };
    
    // Formatear número
    window.formatNumber = function(number) {
        return new Intl.NumberFormat('es-AR').format(number);
    };
    
    // Formatear fecha
    window.formatDate = function(date) {
        return new Intl.DateTimeFormat('es-AR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).format(new Date(date));
    };
    
    // Copiar al portapapeles
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copiado al portapapeles', 'success');
        });
    };
    
    // Mostrar toast
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    };
    
    console.log('✅ Sistema de Gestión Comercial 2.0 - Scripts cargados correctamente');
});