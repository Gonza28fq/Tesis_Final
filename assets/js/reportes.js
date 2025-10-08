/**
 * =====================================================
 * MÓDULO DE REPORTES - JavaScript
 * Sistema de Gestión Comercial
 * =====================================================
 */

// =====================================================
// EXPORTACIÓN
// =====================================================

function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    window.open('exportar_excel.php?' + params.toString(), '_blank');
}

function imprimirReporte() {
    window.print();
}

// =====================================================
// VALIDACIÓN DE FILTROS
// =====================================================

function validarRangoFechas() {
    const desde = document.querySelector('[name="fecha_desde"]');
    const hasta = document.querySelector('[name="fecha_hasta"]');
    
    if (!desde || !hasta) return true;
    
    const fechaDesde = new Date(desde.value);
    const fechaHasta = new Date(hasta.value);
    
    if (fechaDesde > fechaHasta) {
        alert('⚠️ La fecha desde no puede ser mayor a la fecha hasta');
        return false;
    }
    
    return true;
}

// =====================================================
// FORMATEO
// =====================================================

function formatearMoneda(valor) {
    return '$' + parseFloat(valor).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// =====================================================
// ANIMACIONES
// =====================================================

function animarEntrada() {
    const elementos = document.querySelectorAll('.stat-card, .chart-card, .table-container');
    
    elementos.forEach((elemento, index) => {
        elemento.style.opacity = '0';
        elemento.style.transform = 'translateY(20px)';
        elemento.style.transition = 'all 0.4s ease-out';
        
        setTimeout(() => {
            elemento.style.opacity = '1';
            elemento.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// =====================================================
// INICIALIZACIÓN
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    // Animaciones
    animarEntrada();
    
    // Validar formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validarRangoFechas()) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-ocultar alertas
    const alertas = document.querySelectorAll('.alert-success');
    alertas.forEach(alerta => {
        setTimeout(() => {
            alerta.style.transition = 'opacity 0.3s';
            alerta.style.opacity = '0';
            setTimeout(() => alerta.remove(), 300);
        }, 5000);
    });
    
    console.log('✅ Módulo de Reportes inicializado');
});

// =====================================================
// ESTILOS CSS ADICIONALES
// =====================================================

const style = document.createElement('style');
style.textContent = `
    @media print {
        .filters, .btn, .header-actions {
            display: none !important;
        }
        .container {
            box-shadow: none !important;
        }
    }
`;
document.head.appendChild(style);