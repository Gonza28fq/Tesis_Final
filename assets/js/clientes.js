/**
 * Sistema de Gestión Comercial
 * Módulo de Clientes - JavaScript
 */

/**
 * Ver detalle del cliente
 */
async function verDetalle(idCliente) {
    try {
        const response = await fetch(`detalle_cliente.php?id=${idCliente}`);
        const data = await response.json();
        
        if (data.success) {
            mostrarModal(data.html);
        } else {
            alert('Error al cargar el detalle');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
}

/**
 * Mostrar modal
 */
function mostrarModal(html) {
    const modal = document.createElement('div');
    modal.id = 'modal-detalle';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        border-radius: 15px;
        max-width: 1000px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    `;
    
    modalContent.innerHTML = html;
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Cerrar al hacer clic fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            cerrarModal();
        }
    });
}

/**
 * Cerrar modal
 */
function cerrarModal() {
    const modal = document.getElementById('modal-detalle');
    if (modal) {
        modal.remove();
    }
}

/**
 * Exportar a Excel
 */
function exportarExcel() {
    const table = document.getElementById('tablaClientes');
    if (!table) {
        alert('No hay datos para exportar');
        return;
    }
    
    // Construir URL con filtros actuales
    const params = new URLSearchParams(window.location.search);
    window.open('exportar_clientes.php?' + params.toString(), '_blank');
}

/**
 * Formatear número
 */
function formatearNumero(numero) {
    return parseFloat(numero).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}