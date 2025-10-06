/**
 * =====================================================
 * MÓDULO DE PRODUCTOS - JavaScript
 * Sistema de Gestión Comercial
 * =====================================================
 */

// =====================================================
// VALIDACIÓN DE FORMULARIOS
// =====================================================

/**
 * Validar formulario de producto (nuevo/editar)
 */
function validarFormularioProducto(form) {
    const errores = [];
    
    // Nombre
    const nombre = form.querySelector('[name="nombre"]');
    if (!nombre || nombre.value.trim() === '') {
        errores.push('El nombre del producto es obligatorio');
        marcarCampoError(nombre);
    }
    
    // Categoría
    const categoria = form.querySelector('[name="id_categoria"]');
    if (!categoria || categoria.value === '' || categoria.value === '0') {
        errores.push('Debe seleccionar una categoría');
        marcarCampoError(categoria);
    }
    
    // Proveedor
    const proveedor = form.querySelector('[name="id_proveedor"]');
    if (!proveedor || proveedor.value === '' || proveedor.value === '0') {
        errores.push('Debe seleccionar un proveedor');
        marcarCampoError(proveedor);
    }
    
    // Precio
    const precio = form.querySelector('[name="precio_unitario"]');
    if (!precio || parseFloat(precio.value) <= 0) {
        errores.push('El precio debe ser mayor a 0');
        marcarCampoError(precio);
    }
    
    // Stock mínimo
    const stockMin = form.querySelector('[name="stock_minimo"]');
    if (stockMin && parseInt(stockMin.value) < 0) {
        errores.push('El stock mínimo no puede ser negativo');
        marcarCampoError(stockMin);
    }
    
    if (errores.length > 0) {
        mostrarErrores(errores);
        return false;
    }
    
    return true;
}

/**
 * Marcar campo con error visual
 */
function marcarCampoError(campo) {
    if (campo) {
        campo.style.borderColor = '#fc8181';
        campo.addEventListener('input', function() {
            this.style.borderColor = '#e2e8f0';
        }, { once: true });
    }
}

/**
 * Mostrar errores en pantalla
 */
function mostrarErrores(errores) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-error';
    alertDiv.style.animation = 'slideDown 0.3s ease-out';
    
    let html = '<strong>⚠️ Por favor corrija los siguientes errores:</strong><ul>';
    errores.forEach(error => {
        html += `<li>${error}</li>`;
    });
    html += '</ul>';
    
    alertDiv.innerHTML = html;
    
    // Insertar al inicio del contenido
    const content = document.querySelector('.content');
    if (content) {
        content.insertBefore(alertDiv, content.firstChild);
        
        // Scroll hacia el error
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            alertDiv.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    }
}

// =====================================================
// BÚSQUEDA Y FILTROS
// =====================================================

/**
 * Búsqueda en tiempo real en tablas
 */
function inicializarBusquedaTabla(inputId, tablaId) {
    const input = document.getElementById(inputId);
    const tabla = document.getElementById(tablaId);
    
    if (!input || !tabla) return;
    
    input.addEventListener('keyup', function() {
        const filtro = this.value.toLowerCase();
        const filas = tabla.getElementsByTagName('tr');
        
        let visibles = 0;
        
        for (let i = 1; i < filas.length; i++) { // Empezar en 1 para saltar header
            const textoFila = filas[i].textContent.toLowerCase();
            
            if (textoFila.includes(filtro)) {
                filas[i].style.display = '';
                visibles++;
            } else {
                filas[i].style.display = 'none';
            }
        }
        
        // Mostrar mensaje si no hay resultados
        mostrarMensajeSinResultados(tabla, visibles === 0);
    });
}

/**
 * Mostrar mensaje cuando no hay resultados
 */
function mostrarMensajeSinResultados(tabla, mostrar) {
    let mensaje = tabla.querySelector('.no-results-message');
    
    if (mostrar && !mensaje) {
        mensaje = document.createElement('tr');
        mensaje.className = 'no-results-message';
        mensaje.innerHTML = '<td colspan="100%" style="text-align: center; padding: 40px; color: #a0aec0;">No se encontraron resultados</td>';
        tabla.querySelector('tbody').appendChild(mensaje);
    } else if (!mostrar && mensaje) {
        mensaje.remove();
    }
}

// =====================================================
// CONFIRMACIONES
// =====================================================

/**
 * Confirmar eliminación/desactivación
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
 * Confirmar desactivar producto
 */
function confirmarDesactivarProducto(id, nombre) {
    const mensaje = `¿Está seguro de que desea desactivar el producto "${nombre}"?\n\nEsta acción se puede revertir posteriormente.`;
    
    return confirmarAccion(mensaje, function() {
        window.location.href = `eliminar.php?id=${id}`;
    });
}

/**
 * Confirmar eliminar categoría
 */
function confirmarEliminarCategoria(id, nombre, totalProductos) {
    if (totalProductos > 0) {
        alert(`No se puede eliminar la categoría "${nombre}" porque tiene ${totalProductos} productos asociados.\n\nPrimero debe reasignar o eliminar esos productos.`);
        return false;
    }
    
    const mensaje = `¿Está seguro de que desea eliminar la categoría "${nombre}"?`;
    return confirmarAccion(mensaje);
}

// =====================================================
// FORMATO DE NÚMEROS Y MONEDAS
// =====================================================

/**
 * Formatear campos de precio mientras se escribe
 */
function inicializarFormatoPrecio() {
    const camposPrecio = document.querySelectorAll('input[type="number"][step="0.01"]');
    
    camposPrecio.forEach(campo => {
        campo.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
        
        // Prevenir valores negativos
        campo.addEventListener('input', function() {
            if (parseFloat(this.value) < 0) {
                this.value = 0;
            }
        });
    });
}

/**
 * Formatear número como moneda
 */
function formatearMoneda(valor) {
    return '$' + parseFloat(valor).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// =====================================================
// CÓDIGO DE PRODUCTO
// =====================================================

/**
 * Generar código automático si está vacío
 */
function generarCodigoAutomatico() {
    const campoCodigo = document.querySelector('[name="codigo_producto"]');
    const campoNombre = document.querySelector('[name="nombre"]');
    
    if (!campoCodigo || !campoNombre) return;
    
    // Solo generar si está vacío
    campoNombre.addEventListener('blur', function() {
        if (campoCodigo.value.trim() === '' && this.value.trim() !== '') {
            // Generar código basado en nombre + timestamp
            const timestamp = Date.now().toString().slice(-4);
            const nombreCorto = this.value.trim()
                .substring(0, 3)
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '');
            
            campoCodigo.value = `PROD-${nombreCorto}${timestamp}`;
            campoCodigo.style.borderColor = '#48bb78';
            
            setTimeout(() => {
                campoCodigo.style.borderColor = '#e2e8f0';
            }, 2000);
        }
    });
}

// =====================================================
// ALERTAS DE STOCK
// =====================================================

/**
 * Verificar y mostrar alertas de stock bajo
 */
function verificarStockBajo() {
    const filas = document.querySelectorAll('tbody tr');
    
    filas.forEach(fila => {
        const celdaStock = fila.cells[5]; // Columna de stock
        const celdaMinimo = fila.cells[6]; // Columna de stock mínimo
        
        if (!celdaStock || !celdaMinimo) return;
        
        const stockActual = parseInt(celdaStock.textContent);
        const stockMinimo = parseInt(celdaMinimo.textContent);
        
        if (stockActual <= stockMinimo) {
            celdaStock.style.color = '#e53e3e';
            celdaStock.style.fontWeight = '700';
            celdaStock.innerHTML = `⚠️ ${stockActual}`;
        } else if (stockActual <= stockMinimo * 1.5) {
            celdaStock.style.color = '#dd6b20';
            celdaStock.style.fontWeight = '600';
        }
    });
}

// =====================================================
// EXPORT A EXCEL
// =====================================================

/**
 * Exportar tabla a Excel
 */
function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    const url = 'exportar_productos.php?' + params.toString();
    
    // Mostrar mensaje de procesamiento
    const btn = event.target;
    const textoOriginal = btn.textContent;
    btn.textContent = '⏳ Generando...';
    btn.disabled = true;
    
    // Abrir en nueva pestaña
    window.open(url, '_blank');
    
    // Restaurar botón después de 2 segundos
    setTimeout(() => {
        btn.textContent = textoOriginal;
        btn.disabled = false;
    }, 2000);
}

// =====================================================
// TOOLTIPS Y AYUDA
// =====================================================

/**
 * Inicializar tooltips simples
 */
function inicializarTooltips() {
    const elementos = document.querySelectorAll('[data-tooltip]');
    
    elementos.forEach(elemento => {
        elemento.style.position = 'relative';
        elemento.style.cursor = 'help';
        
        elemento.addEventListener('mouseenter', function() {
            const texto = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip-custom';
            tooltip.textContent = texto;
            tooltip.style.cssText = `
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #2d3748;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 1000;
                margin-bottom: 5px;
            `;
            this.appendChild(tooltip);
        });
        
        elemento.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.tooltip-custom');
            if (tooltip) tooltip.remove();
        });
    });
}

// =====================================================
// AUTO-GUARDADO (LOCAL STORAGE)
// =====================================================

/**
 * Guardar borrador del formulario
 * NOTA: NO usar localStorage en artifacts de Claude
 * Esta función es para uso en entorno de producción
 */
function guardarBorrador(formId) {
    // Esta función debe ser implementada en el entorno de producción
    // donde localStorage esté disponible
    console.log('Guardar borrador - implementar en producción');
}

// =====================================================
// ANIMACIONES
// =====================================================

/**
 * Agregar animación de entrada a elementos
 */
function animarEntrada() {
    const elementos = document.querySelectorAll('.stat-card, .form-card, .table-container');
    
    elementos.forEach((elemento, index) => {
        elemento.style.opacity = '0';
        elemento.style.transform = 'translateY(20px)';
        elemento.style.transition = 'all 0.3s ease-out';
        
        setTimeout(() => {
            elemento.style.opacity = '1';
            elemento.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// =====================================================
// INICIALIZACIÓN
// =====================================================

/**
 * Inicializar todas las funcionalidades al cargar la página
 */
document.addEventListener('DOMContentLoaded', function() {
    // Animaciones de entrada
    animarEntrada();
    
    // Formato de precios
    inicializarFormatoPrecio();
    
    // Generación automática de código
    generarCodigoAutomatico();
    
    // Verificar stock bajo
    verificarStockBajo();
    
    // Tooltips
    inicializarTooltips();
    
    // Auto-ocultar alertas de éxito
    const alertas = document.querySelectorAll('.alert-success');
    alertas.forEach(alerta => {
        setTimeout(() => {
            alerta.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => alerta.remove(), 300);
        }, 5000);
    });
    
    // Validación de formularios
    const formProducto = document.querySelector('form[action=""]');
    if (formProducto) {
        formProducto.addEventListener('submit', function(e) {
            if (!validarFormularioProducto(this)) {
                e.preventDefault();
            }
        });
    }
    
    console.log('✅ Módulo de Productos inicializado correctamente');
});

// =====================================================
// UTILIDADES ADICIONALES
// =====================================================

/**
 * Copiar texto al portapapeles
 */
function copiarAlPortapapeles(texto) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(texto).then(() => {
            mostrarNotificacion('✓ Copiado al portapapeles', 'success');
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
 * Mostrar notificación toast
 */
function mostrarNotificacion(mensaje, tipo = 'info') {
    const colores = {
        success: '#48bb78',
        error: '#f56565',
        warning: '#ed8936',
        info: '#4299e1'
    };
    
    const toast = document.createElement('div');
    toast.textContent = mensaje;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${colores[tipo] || colores.info};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Agregar animaciones CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
`;
document.head.appendChild(style);