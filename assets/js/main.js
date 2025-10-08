/**
 * =====================================================
 * MAIN.JS - JavaScript Global del Sistema
 * Sistema de Gestión Comercial
 * =====================================================
 */

// =====================================================
// FUNCIONES GLOBALES - FORMATO
// =====================================================

/**
 * Formatear número como moneda
 */
function formatearMoneda(valor) {
    if (isNaN(valor) || valor === null || valor === undefined) return '$0.00';
    return '$' + parseFloat(valor).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Formatear fecha DD/MM/YYYY
 */
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const date = new Date(fecha);
    const dia = String(date.getDate()).padStart(2, '0');
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const anio = date.getFullYear();
    return `${dia}/${mes}/${anio}`;
}

/**
 * Formatear fecha y hora DD/MM/YYYY HH:mm
 */
function formatearFechaHora(fecha) {
    if (!fecha) return '-';
    const date = new Date(fecha);
    const dia = String(date.getDate()).padStart(2, '0');
    const mes = String(date.getMonth() + 1).padStart(2, '0');
    const anio = date.getFullYear();
    const hora = String(date.getHours()).padStart(2, '0');
    const minutos = String(date.getMinutes()).padStart(2, '0');
    return `${dia}/${mes}/${anio} ${hora}:${minutos}`;
}

// =====================================================
// NOTIFICACIONES TOAST
// =====================================================

/**
 * Mostrar notificación toast
 */
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
    const colores = {
        success: '#48bb78',
        error: '#f56565',
        warning: '#ed8936',
        info: '#4299e1'
    };
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.textContent = mensaje;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${colores[tipo] || colores.info};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 99999;
        font-size: 14px;
        font-weight: 500;
        animation: slideInRight 0.3s ease-out;
        max-width: 350px;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, duracion);
}

// =====================================================
// CONFIRMACIONES
// =====================================================

/**
 * Confirmar acción con mensaje personalizado
 */
function confirmarAccion(mensaje, callback) {
    if (confirm(mensaje)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}

/**
 * Confirmar eliminación
 */
function confirmarEliminar(nombre, url) {
    const mensaje = `¿Está seguro de que desea eliminar "${nombre}"?\n\nEsta acción no se puede deshacer.`;
    if (confirm(mensaje)) {
        window.location.href = url;
    }
}

// =====================================================
// VALIDACIONES
// =====================================================

/**
 * Validar email
 */
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validar teléfono (Argentina)
 */
function validarTelefono(telefono) {
    const regex = /^[\d\s\-\+\(\)]{7,20}$/;
    return regex.test(telefono);
}

/**
 * Validar CUIT/CUIL (Argentina)
 */
function validarCUIT(cuit) {
    cuit = cuit.replace(/[-]/g, '');
    if (cuit.length !== 11) return false;
    return /^\d{11}$/.test(cuit);
}

/**
 * Validar que campo no esté vacío
 */
function validarRequerido(valor) {
    return valor !== null && valor !== undefined && valor.toString().trim() !== '';
}

// =====================================================
// MANEJO DE FORMULARIOS
// =====================================================

/**
 * Marcar campo con error
 */
function marcarError(campo, mensaje = '') {
    if (!campo) return;
    
    campo.style.borderColor = '#f56565';
    
    // Remover mensaje de error anterior si existe
    const errorAnterior = campo.parentElement.querySelector('.error-message');
    if (errorAnterior) errorAnterior.remove();
    
    // Agregar mensaje si se proporcionó
    if (mensaje) {
        const errorMsg = document.createElement('small');
        errorMsg.className = 'error-message';
        errorMsg.textContent = mensaje;
        errorMsg.style.cssText = 'color: #f56565; font-size: 12px; margin-top: 5px; display: block;';
        campo.parentElement.appendChild(errorMsg);
    }
    
    // Quitar error al escribir
    campo.addEventListener('input', function() {
        this.style.borderColor = '#e2e8f0';
        const error = this.parentElement.querySelector('.error-message');
        if (error) error.remove();
    }, { once: true });
}

/**
 * Limpiar errores del formulario
 */
function limpiarErrores(formulario) {
    const campos = formulario.querySelectorAll('input, select, textarea');
    campos.forEach(campo => {
        campo.style.borderColor = '#e2e8f0';
    });
    
    const mensajes = formulario.querySelectorAll('.error-message');
    mensajes.forEach(msg => msg.remove());
}

// =====================================================
// LOADING Y BLOQUEO
// =====================================================

/**
 * Mostrar loading overlay
 */
function mostrarLoading(mensaje = 'Cargando...') {
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.innerHTML = `
        <div style="text-align: center;">
            <div class="spinner"></div>
            <p style="margin-top: 15px; color: white; font-size: 16px;">${mensaje}</p>
        </div>
    `;
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 99998;
    `;
    
    document.body.appendChild(overlay);
}

/**
 * Ocultar loading overlay
 */
function ocultarLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.remove();
}

/**
 * Bloquear botón (prevenir doble click)
 */
function bloquearBoton(boton, segundos = 2) {
    if (!boton) return;
    
    const textoOriginal = boton.textContent;
    boton.disabled = true;
    boton.style.opacity = '0.6';
    boton.style.cursor = 'not-allowed';
    
    setTimeout(() => {
        boton.disabled = false;
        boton.style.opacity = '1';
        boton.style.cursor = 'pointer';
        boton.textContent = textoOriginal;
    }, segundos * 1000);
}

// =====================================================
// UTILIDADES
// =====================================================

/**
 * Copiar texto al portapapeles
 */
function copiarAlPortapapeles(texto) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            mostrarNotificacion('✓ Copiado al portapapeles', 'success');
        }).catch(() => {
            mostrarNotificacion('⚠️ Error al copiar', 'error');
        });
    } else {
        // Fallback para navegadores antiguos
        const input = document.createElement('input');
        input.value = texto;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        mostrarNotificacion('✓ Copiado al portapapeles', 'success');
    }
}

/**
 * Imprimir contenido específico
 */
function imprimirContenido(selector) {
    const contenido = document.querySelector(selector);
    if (!contenido) return;
    
    const ventanaImpresion = window.open('', '', 'width=800,height=600');
    ventanaImpresion.document.write('<html><head><title>Imprimir</title>');
    ventanaImpresion.document.write('<style>body{font-family:Arial;padding:20px;}</style>');
    ventanaImpresion.document.write('</head><body>');
    ventanaImpresion.document.write(contenido.innerHTML);
    ventanaImpresion.document.write('</body></html>');
    ventanaImpresion.document.close();
    ventanaImpresion.print();
}

/**
 * Detectar si es dispositivo móvil
 */
function esMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

/**
 * Scroll suave a elemento
 */
function scrollSuave(selector) {
    const elemento = document.querySelector(selector);
    if (elemento) {
        elemento.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// =====================================================
// BÚSQUEDA EN TIEMPO REAL
// =====================================================

/**
 * Filtrar tabla en tiempo real
 */
function inicializarBusquedaTabla(inputSelector, tableSelector) {
    const input = document.querySelector(inputSelector);
    const tabla = document.querySelector(tableSelector);
    
    if (!input || !tabla) return;
    
    input.addEventListener('keyup', function() {
        const filtro = this.value.toLowerCase();
        const filas = tabla.querySelectorAll('tbody tr');
        let visibles = 0;
        
        filas.forEach(fila => {
            const texto = fila.textContent.toLowerCase();
            if (texto.includes(filtro)) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });
        
        // Mostrar mensaje si no hay resultados
        mostrarMensajeSinResultados(tabla, visibles === 0);
    });
}

/**
 * Mostrar/ocultar mensaje sin resultados
 */
function mostrarMensajeSinResultados(tabla, mostrar) {
    let mensaje = tabla.querySelector('.no-results-message');
    
    if (mostrar && !mensaje) {
        mensaje = document.createElement('tr');
        mensaje.className = 'no-results-message';
        mensaje.innerHTML = '<td colspan="100%" style="text-align:center;padding:40px;color:#a0aec0;">No se encontraron resultados</td>';
        tabla.querySelector('tbody').appendChild(mensaje);
    } else if (!mostrar && mensaje) {
        mensaje.remove();
    }
}

// =====================================================
// AUTO-OCULTAR ALERTAS
// =====================================================

/**
 * Auto-ocultar alertas de éxito
 */
function autoOcultarAlertas() {
    const alertas = document.querySelectorAll('.alert-success, .alert-info');
    alertas.forEach(alerta => {
        setTimeout(() => {
            alerta.style.transition = 'opacity 0.5s';
            alerta.style.opacity = '0';
            setTimeout(() => alerta.remove(), 500);
        }, 5000);
    });
}

// =====================================================
// ANIMACIONES
// =====================================================

/**
 * Animar entrada de elementos
 */
function animarEntrada(selector = '.stat-card, .form-section, .table-container') {
    const elementos = document.querySelectorAll(selector);
    
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
// INICIALIZACIÓN AL CARGAR LA PÁGINA
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-ocultar alertas
    autoOcultarAlertas();
    
    // Animar entrada de elementos
    animarEntrada();
    
    // Prevenir doble submit en formularios
    const formularios = document.querySelectorAll('form');
    formularios.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                bloquearBoton(submitBtn, 3);
            }
        });
    });
    
    // Confirmar antes de salir si hay cambios sin guardar
    let cambiosSinGuardar = false;
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('change', () => {
            cambiosSinGuardar = true;
        });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (cambiosSinGuardar) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    // Marcar que se guardó al hacer submit
    formularios.forEach(form => {
        form.addEventListener('submit', () => {
            cambiosSinGuardar = false;
        });
    });
    
    console.log('✅ Sistema inicializado correctamente');
});

// =====================================================
// ESTILOS CSS GLOBALES
// =====================================================

const style = document.createElement('style');
style.textContent = `
    /* Animaciones */
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100px); }
    }
    
    /* Spinner de carga */
    .spinner {
        border: 4px solid rgba(255,255,255,0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Scroll suave */
    html {
        scroll-behavior: smooth;
    }
    
    /* Selección de texto */
    ::selection {
        background: #667eea;
        color: white;
    }
`;
document.head.appendChild(style);