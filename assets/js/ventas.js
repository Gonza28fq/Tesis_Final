/**
 * =====================================================
 * MÓDULO DE VENTAS - JavaScript CORREGIDO
 * Sistema de Gestión Comercial
 * =====================================================
 */

// Variables globales
let carrito = [];
let clienteSeleccionado = null;
let productoSeleccionado = null;
let timeoutBusqueda = null;

// =====================================================
// BÚSQUEDA DE CLIENTES
// =====================================================

function buscarClientes() {
    const input = document.getElementById('buscar-cliente');
    const resultados = document.getElementById('resultados-clientes');
    const busqueda = input.value.trim();
    
    // Limpiar timeout anterior
    if (timeoutBusqueda) {
        clearTimeout(timeoutBusqueda);
    }
    
    // Si la búsqueda está vacía, ocultar resultados
    if (busqueda.length < 2) {
        resultados.classList.remove('active');
        resultados.innerHTML = '';
        return;
    }
    
    // Mostrar "Buscando..."
    resultados.classList.add('active');
    resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#718096;">🔍 Buscando...</div>';
    
    // Esperar 500ms antes de buscar (debounce)
    timeoutBusqueda = setTimeout(() => {
        fetch(`buscar_cliente.php?q=${encodeURIComponent(busqueda)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                console.log('Respuesta del servidor:', data);
                
                if (data.success && data.clientes) {
                    mostrarResultadosClientes(data.clientes);
                } else {
                    resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#f56565;">⚠️ ' + (data.message || 'Error desconocido') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#f56565;">⚠️ Error al buscar clientes. Verifica que el archivo buscar_cliente.php existe.</div>';
            });
    }, 500);
}

function mostrarResultadosClientes(clientes) {
    const resultados = document.getElementById('resultados-clientes');
    
    if (!clientes || clientes.length === 0) {
        resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#a0aec0;">No se encontraron clientes</div>';
        return;
    }
    
    let html = '';
    clientes.forEach(cliente => {
        const nombreCompleto = `${cliente.nombre} ${cliente.apellido}`;
        const clienteData = {
            id: cliente.id_cliente,
            nombre_completo: nombreCompleto,
            email: cliente.email || 'Sin email',
            telefono: cliente.telefono || 'Sin teléfono',
            dni_cuit: cliente.dni_cuit || 'Sin DNI'
        };
        
        html += `
            <div class="search-item" onclick='seleccionarCliente(${JSON.stringify(clienteData)})'>
                <div style="font-weight:600;color:#2d3748;margin-bottom:4px;">${nombreCompleto}</div>
                <div style="font-size:13px;color:#718096;">
                    📧 ${clienteData.email} | 📱 ${clienteData.telefono} | 🆔 ${clienteData.dni_cuit}
                </div>
            </div>
        `;
    });
    
    resultados.innerHTML = html;
}

function seleccionarCliente(cliente) {
    clienteSeleccionado = cliente;
    
    // Actualizar la sección de cliente seleccionado
    const contenedor = document.getElementById('cliente-seleccionado');
    contenedor.innerHTML = `
        <div class="cliente-seleccionado">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:10px;">
                <div>
                    <div style="font-size:16px;font-weight:600;color:#2d3748;margin-bottom:5px;">
                        ✓ ${cliente.nombre_completo}
                    </div>
                    <div style="font-size:13px;color:#4a5568;">
                        📧 ${cliente.email}<br>
                        📱 ${cliente.telefono}<br>
                        🆔 DNI: ${cliente.dni_cuit}
                    </div>
                </div>
                <button type="button" class="btn btn-danger" onclick="quitarCliente()" style="padding:8px 15px;font-size:13px;">
                    ✕ Quitar
                </button>
            </div>
        </div>
    `;
    
    // Limpiar búsqueda
    document.getElementById('buscar-cliente').value = '';
    document.getElementById('resultados-clientes').classList.remove('active');
    
    // Notificación
    mostrarNotificacion('✓ Cliente seleccionado', 'success');
}

function quitarCliente() {
    clienteSeleccionado = null;
    document.getElementById('cliente-seleccionado').innerHTML = '';
    mostrarNotificacion('Cliente removido', 'info');
}

// =====================================================
// BÚSQUEDA DE PRODUCTOS
// =====================================================

function buscarProductos() {
    const input = document.getElementById('buscar-producto');
    const resultados = document.getElementById('resultados-productos');
    const busqueda = input.value.trim();
    
    // Limpiar timeout anterior
    if (timeoutBusqueda) {
        clearTimeout(timeoutBusqueda);
    }
    
    // Si la búsqueda está vacía, ocultar resultados
    if (busqueda.length < 2) {
        resultados.classList.remove('active');
        resultados.innerHTML = '';
        return;
    }
    
    // Mostrar "Buscando..."
    resultados.classList.add('active');
    resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#718096;">🔍 Buscando...</div>';
    
    // Esperar 500ms antes de buscar (debounce)
    timeoutBusqueda = setTimeout(() => {
        fetch(`buscar_producto.php?q=${encodeURIComponent(busqueda)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(productos => {
                mostrarResultadosProductos(productos);
            })
            .catch(error => {
                console.error('Error:', error);
                resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#f56565;">⚠️ Error al buscar productos</div>';
            });
    }, 500);
}

function mostrarResultadosProductos(productos) {
    const resultados = document.getElementById('resultados-productos');
    
    if (!productos || productos.length === 0) {
        resultados.innerHTML = '<div class="search-item" style="text-align:center;color:#a0aec0;">No se encontraron productos</div>';
        return;
    }
    
    let html = '';
    productos.forEach(producto => {
        // Verificar stock
        let stockBadge = '';
        if (producto.stock > 10) {
            stockBadge = '<span class="badge badge-success">Stock: ' + producto.stock + '</span>';
        } else if (producto.stock > 0) {
            stockBadge = '<span class="badge badge-warning">Stock: ' + producto.stock + '</span>';
        } else {
            stockBadge = '<span class="badge badge-danger">Sin stock</span>';
        }
        
        html += `
            <div class="search-item" onclick='seleccionarProducto(${JSON.stringify(producto)})' style="${producto.stock <= 0 ? 'opacity:0.5;cursor:not-allowed;' : ''}">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div style="flex:1;">
                        <div style="font-weight:600;color:#2d3748;margin-bottom:4px;">${producto.nombre}</div>
                        <div style="font-size:13px;color:#718096;">
                            Código: ${producto.codigo} | Categoría: ${producto.categoria || 'Sin categoría'} | ${stockBadge}
                        </div>
                    </div>
                    <div style="font-size:18px;font-weight:700;color:#48bb78;margin-left:15px;">
                        ${formatearMoneda(producto.precio)}
                    </div>
                </div>
            </div>
        `;
    });
    
    resultados.innerHTML = html;
}

function seleccionarProducto(producto) {
    if (producto.stock <= 0) {
        alert('⚠️ Producto sin stock disponible');
        return;
    }
    
    productoSeleccionado = producto;
    
    // Limpiar búsqueda y ocultar resultados
    document.getElementById('buscar-producto').value = producto.nombre;
    document.getElementById('resultados-productos').classList.remove('active');
}

function agregarProducto() {
    if (!productoSeleccionado) {
        alert('⚠️ Debe seleccionar un producto');
        return;
    }
    
    const cantidadInput = document.getElementById('cantidad-producto');
    const cantidad = parseInt(cantidadInput.value);
    
    if (isNaN(cantidad) || cantidad <= 0) {
        alert('⚠️ Ingrese una cantidad válida');
        return;
    }
    
    if (cantidad > productoSeleccionado.stock) {
        alert(`⚠️ Stock insuficiente. Disponible: ${productoSeleccionado.stock}`);
        return;
    }
    
    // Verificar si el producto ya está en el carrito
    const existe = carrito.find(item => item.id === productoSeleccionado.id);
    
    if (existe) {
        const nuevaCantidad = existe.cantidad + cantidad;
        if (nuevaCantidad > productoSeleccionado.stock) {
            alert(`⚠️ Stock insuficiente. Ya tiene ${existe.cantidad} en el carrito. Máximo disponible: ${productoSeleccionado.stock}`);
            return;
        }
        existe.cantidad = nuevaCantidad;
        existe.subtotal = existe.cantidad * existe.precio;
    } else {
        carrito.push({
            id: productoSeleccionado.id,
            nombre: productoSeleccionado.nombre,
            precio: parseFloat(productoSeleccionado.precio),
            cantidad: cantidad,
            stock: productoSeleccionado.stock,
            subtotal: cantidad * parseFloat(productoSeleccionado.precio)
        });
    }
    
    actualizarCarrito();
    
    // Limpiar
    productoSeleccionado = null;
    document.getElementById('buscar-producto').value = '';
    cantidadInput.value = '1';
    
    // Notificación
    mostrarNotificacion('✓ Producto agregado al carrito', 'success');
}

// =====================================================
// CARRITO DE COMPRAS
// =====================================================

function actualizarCarrito() {
    const contenedor = document.getElementById('carrito-items');
    const totalElement = document.getElementById('total-venta');
    
    if (carrito.length === 0) {
        contenedor.innerHTML = `
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <p>El carrito está vacío</p>
                <small>Busque y agregue productos para comenzar</small>
            </div>
        `;
        totalElement.textContent = '$0.00';
        return;
    }
    
    let html = '';
    let total = 0;
    
    carrito.forEach((item, index) => {
        total += item.subtotal;
        html += `
            <div class="carrito-item">
                <div class="item-info">
                    <div class="item-nombre">${item.nombre}</div>
                    <div class="item-detalles">
                        Precio unitario: ${formatearMoneda(item.precio)} | Subtotal: ${formatearMoneda(item.subtotal)}
                    </div>
                </div>
                <div class="item-cantidad">
                    <button class="cantidad-btn" onclick="cambiarCantidad(${index}, -1)">-</button>
                    <input type="number" value="${item.cantidad}" 
                           onchange="cambiarCantidadDirecta(${index}, this.value)"
                           min="1" max="${item.stock}"
                           class="cantidad-input">
                    <button class="cantidad-btn" onclick="cambiarCantidad(${index}, 1)">+</button>
                    <button class="btn btn-danger" onclick="eliminarDelCarrito(${index})" style="padding:8px 12px;margin-left:10px;">
                        🗑️
                    </button>
                </div>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    totalElement.textContent = formatearMoneda(total);
}

function cambiarCantidad(index, cambio) {
    const item = carrito[index];
    const nuevaCantidad = item.cantidad + cambio;
    
    if (nuevaCantidad <= 0) {
        eliminarDelCarrito(index);
        return;
    }
    
    if (nuevaCantidad > item.stock) {
        alert(`⚠️ Stock máximo disponible: ${item.stock}`);
        return;
    }
    
    item.cantidad = nuevaCantidad;
    item.subtotal = item.cantidad * item.precio;
    actualizarCarrito();
}

function cambiarCantidadDirecta(index, valor) {
    const item = carrito[index];
    const nuevaCantidad = parseInt(valor);
    
    if (isNaN(nuevaCantidad) || nuevaCantidad <= 0) {
        eliminarDelCarrito(index);
        return;
    }
    
    if (nuevaCantidad > item.stock) {
        alert(`⚠️ Stock máximo disponible: ${item.stock}`);
        item.cantidad = item.stock;
    } else {
        item.cantidad = nuevaCantidad;
    }
    
    item.subtotal = item.cantidad * item.precio;
    actualizarCarrito();
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
    mostrarNotificacion('Producto eliminado', 'info');
}

// =====================================================
// CONFIRMAR VENTA
// =====================================================

function confirmarVenta() {
    // Validaciones
    if (!clienteSeleccionado) {
        alert('⚠️ Debe seleccionar un cliente');
        document.getElementById('buscar-cliente').focus();
        return;
    }
    
    if (carrito.length === 0) {
        alert('⚠️ El carrito está vacío');
        return;
    }
    
    const tipoComprobante = document.getElementById('tipo-comprobante').value;
    const formaPago = document.getElementById('forma-pago').value;
    
    // Calcular total
    const total = carrito.reduce((sum, item) => sum + item.subtotal, 0);
    
    // Confirmar
    if (!confirm(`¿Confirmar venta por ${formatearMoneda(total)} a ${clienteSeleccionado.nombre_completo}?`)) {
        return;
    }
    
    // Preparar datos
    const datos = {
        id_cliente: clienteSeleccionado.id,
        tipo_comprobante: tipoComprobante,
        forma_pago: formaPago,
        productos: carrito.map(item => ({
            id_producto: item.id,
            cantidad: item.cantidad,
            precio_unitario: item.precio
        }))
    };
    
    console.log('Enviando venta:', datos);
    
    // Mostrar loading
    const btnConfirmar = event.target;
    const textoOriginal = btnConfirmar.innerHTML;
    btnConfirmar.innerHTML = '⏳ Procesando...';
    btnConfirmar.disabled = true;
    
    // Enviar al servidor
    fetch('procesar_venta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(resultado => {
        if (resultado.success) {
            mostrarNotificacion('✓ Venta registrada exitosamente', 'success');
            
            // Limpiar todo
            carrito = [];
            clienteSeleccionado = null;
            productoSeleccionado = null;
            actualizarCarrito();
            quitarCliente();
            
            // Preguntar si desea imprimir
            if (confirm('¿Desea ver el comprobante de la venta?')) {
                window.open(`ver_venta.php?id=${resultado.id_venta}`, '_blank');
            }
            
            // Recargar después de 2 segundos
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            alert('❌ Error: ' + (resultado.message || resultado.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión al procesar la venta');
    })
    .finally(() => {
        btnConfirmar.innerHTML = textoOriginal;
        btnConfirmar.disabled = false;
    });
}

// =====================================================
// UTILIDADES
// =====================================================

function formatearMoneda(valor) {
    return '$' + parseFloat(valor).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

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
        background: ${colores[tipo]};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 99999;
        animation: slideInRight 0.3s;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function mostrarFormNuevoCliente() {
    // Aquí puedes implementar un modal o redireccionar
    if (confirm('¿Desea ir al módulo de clientes para crear uno nuevo?')) {
        window.open('../clientes/index.php', '_blank');
    }
}

// =====================================================
// INICIALIZACIÓN
// =====================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Módulo de ventas inicializado');
    
    // Ocultar resultados al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) {
            document.getElementById('resultados-productos').classList.remove('active');
            document.getElementById('resultados-clientes').classList.remove('active');
        }
    });
    
    // Agregar estilos para animaciones
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100px); }
        }
    `;
    document.head.appendChild(style);
});