/**
 * Sistema de Gestión Comercial
 * Módulo de Ventas - JavaScript
 */

// Variables globales
let clienteSeleccionado = null;
let productoSeleccionado = null;
let carrito = [];
let timeoutBusqueda = null;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
    actualizarCarrito();
});

/**
 * Inicializar todos los event listeners
 */
function inicializarEventos() {
    // Búsqueda de cliente
    const inputCliente = document.getElementById('buscar-cliente');
    if (inputCliente) {
        inputCliente.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                timeoutBusqueda = setTimeout(() => buscarCliente(query), 300);
            } else {
                document.getElementById('resultados-clientes').classList.remove('active');
            }
        });
    }

    // Búsqueda de producto
    const inputProducto = document.getElementById('buscar-producto');
    if (inputProducto) {
        inputProducto.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                timeoutBusqueda = setTimeout(() => buscarProducto(query), 300);
            } else {
                document.getElementById('resultados-productos').classList.remove('active');
            }
        });
    }

    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) {
            document.querySelectorAll('.search-results').forEach(el => {
                el.classList.remove('active');
            });
        }
    });

    // Enter en cantidad para agregar producto
    const cantidadInput = document.getElementById('cantidad-producto');
    if (cantidadInput) {
        cantidadInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                agregarProducto();
            }
        });
    }
}

/**
 * Buscar cliente en la base de datos
 */
async function buscarCliente(query) {
    try {
        const response = await fetch(`buscar_cliente.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            mostrarResultadosClientes(data.clientes);
        } else {
            mostrarAlerta('error', data.message || 'Error al buscar clientes');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al buscar clientes');
    }
}

/**
 * Mostrar resultados de búsqueda de clientes
 */
function mostrarResultadosClientes(clientes) {
    const contenedor = document.getElementById('resultados-clientes');
    
    if (clientes.length === 0) {
        contenedor.innerHTML = '<div class="search-item">No se encontraron clientes</div>';
        contenedor.classList.add('active');
        return;
    }
    
    let html = '';
    clientes.forEach(cliente => {
        html += `
            <div class="search-item" onclick='seleccionarCliente(${JSON.stringify(cliente)})'>
                <strong>${cliente.nombre} ${cliente.apellido}</strong><br>
                <small>${cliente.email || ''} ${cliente.dni_cuit ? '- ' + cliente.dni_cuit : ''}</small>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    contenedor.classList.add('active');
}

/**
 * Seleccionar un cliente
 */
function seleccionarCliente(cliente) {
    clienteSeleccionado = cliente;
    document.getElementById('buscar-cliente').value = '';
    document.getElementById('resultados-clientes').classList.remove('active');
    
    const contenedor = document.getElementById('cliente-seleccionado');
    contenedor.innerHTML = `
        <div class="cliente-seleccionado">
            <strong>Cliente seleccionado:</strong><br>
            ${cliente.nombre} ${cliente.apellido}<br>
            <small>${cliente.email || ''} ${cliente.telefono ? '- Tel: ' + cliente.telefono : ''}</small>
            <button type="button" class="btn btn-danger" onclick="quitarCliente()" style="margin-top: 10px;">
                ✕ Quitar cliente
            </button>
        </div>
    `;
    
    mostrarAlerta('success', 'Cliente seleccionado correctamente');
}

/**
 * Quitar cliente seleccionado
 */
function quitarCliente() {
    clienteSeleccionado = null;
    document.getElementById('cliente-seleccionado').innerHTML = '';
}

/**
 * Buscar producto en la base de datos
 */
async function buscarProducto(query) {
    try {
        const response = await fetch(`buscar_producto.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            mostrarResultadosProductos(data.productos);
        } else {
            mostrarAlerta('error', data.message || 'Error al buscar productos');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al buscar productos');
    }
}

/**
 * Mostrar resultados de búsqueda de productos
 */
function mostrarResultadosProductos(productos) {
    const contenedor = document.getElementById('resultados-productos');
    
    if (productos.length === 0) {
        contenedor.innerHTML = '<div class="search-item">No se encontraron productos</div>';
        contenedor.classList.add('active');
        return;
    }
    
    let html = '';
    productos.forEach(producto => {
        const stockBadge = producto.stock_total > 0 
            ? `<span class="badge badge-success">Stock: ${producto.stock_total}</span>`
            : `<span class="badge badge-danger">Sin stock</span>`;
        
        html += `
            <div class="search-item" onclick='seleccionarProducto(${JSON.stringify(producto)})'>
                <strong>${producto.nombre}</strong> ${stockBadge}<br>
                <small>Precio: $${formatearNumero(producto.precio_unitario)} - ${producto.nombre_categoria}</small>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    contenedor.classList.add('active');
}

/**
 * Seleccionar un producto
 */
function seleccionarProducto(producto) {
    productoSeleccionado = producto;
    document.getElementById('buscar-producto').value = producto.nombre;
    document.getElementById('resultados-productos').classList.remove('active');
    
    // Focus en cantidad
    document.getElementById('cantidad-producto').focus();
    document.getElementById('cantidad-producto').select();
}

/**
 * Agregar producto al carrito
 */
function agregarProducto() {
    if (!productoSeleccionado) {
        mostrarAlerta('error', 'Debe seleccionar un producto');
        return;
    }
    
    const cantidad = parseInt(document.getElementById('cantidad-producto').value);
    
    if (cantidad <= 0) {
        mostrarAlerta('error', 'La cantidad debe ser mayor a 0');
        return;
    }
    
    if (cantidad > productoSeleccionado.stock_total) {
        mostrarAlerta('error', `Stock insuficiente. Disponible: ${productoSeleccionado.stock_total}`);
        return;
    }
    
    // Verificar si el producto ya está en el carrito
    const indexExistente = carrito.findIndex(item => item.id_producto === productoSeleccionado.id_producto);
    
    if (indexExistente !== -1) {
        const nuevaCantidad = carrito[indexExistente].cantidad + cantidad;
        
        if (nuevaCantidad > productoSeleccionado.stock_total) {
            mostrarAlerta('error', `Stock insuficiente. Ya tiene ${carrito[indexExistente].cantidad} en el carrito. Disponible: ${productoSeleccionado.stock_total}`);
            return;
        }
        
        carrito[indexExistente].cantidad = nuevaCantidad;
        carrito[indexExistente].subtotal = nuevaCantidad * carrito[indexExistente].precio_unitario;
    } else {
        carrito.push({
            id_producto: productoSeleccionado.id_producto,
            nombre: productoSeleccionado.nombre,
            precio_unitario: parseFloat(productoSeleccionado.precio_unitario),
            cantidad: cantidad,
            subtotal: cantidad * parseFloat(productoSeleccionado.precio_unitario),
            stock_disponible: productoSeleccionado.stock_total
        });
    }
    
    // Limpiar selección
    productoSeleccionado = null;
    document.getElementById('buscar-producto').value = '';
    document.getElementById('cantidad-producto').value = '1';
    
    actualizarCarrito();
    mostrarAlerta('success', 'Producto agregado al carrito');
}

/**
 * Actualizar visualización del carrito
 */
function actualizarCarrito() {
    const contenedor = document.getElementById('carrito-items');
    
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
        document.getElementById('total-venta').textContent = '$0.00';
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
                        ${formatearNumero(item.precio_unitario)} × ${item.cantidad} = ${formatearNumero(item.subtotal)}
                    </div>
                </div>
                <div class="item-cantidad">
                    <button class="cantidad-btn" onclick="modificarCantidad(${index}, -1)">-</button>
                    <input type="number" class="cantidad-input" value="${item.cantidad}" 
                           onchange="cambiarCantidad(${index}, this.value)" min="1" max="${item.stock_disponible}">
                    <button class="cantidad-btn" onclick="modificarCantidad(${index}, 1)">+</button>
                    <button class="btn btn-danger" onclick="quitarProducto(${index})" style="margin-left: 10px;">✕</button>
                </div>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    document.getElementById('total-venta').textContent = ' + formatearNumero(total)';
}

/**
 * Modificar cantidad de un producto en el carrito
 */
function modificarCantidad(index, cambio) {
    const item = carrito[index];
    const nuevaCantidad = item.cantidad + cambio;
    
    if (nuevaCantidad <= 0) {
        quitarProducto(index);
        return;
    }
    
    if (nuevaCantidad > item.stock_disponible) {
        mostrarAlerta('error', `Stock insuficiente. Máximo: ${item.stock_disponible}`);
        return;
    }
    
    carrito[index].cantidad = nuevaCantidad;
    carrito[index].subtotal = nuevaCantidad * item.precio_unitario;
    
    actualizarCarrito();
}

/**
 * Cambiar cantidad directamente desde input
 */
function cambiarCantidad(index, nuevaCantidad) {
    nuevaCantidad = parseInt(nuevaCantidad);
    
    if (isNaN(nuevaCantidad) || nuevaCantidad <= 0) {
        mostrarAlerta('error', 'Cantidad inválida');
        actualizarCarrito();
        return;
    }
    
    const item = carrito[index];
    
    if (nuevaCantidad > item.stock_disponible) {
        mostrarAlerta('error', `Stock insuficiente. Máximo: ${item.stock_disponible}`);
        actualizarCarrito();
        return;
    }
    
    carrito[index].cantidad = nuevaCantidad;
    carrito[index].subtotal = nuevaCantidad * item.precio_unitario;
    
    actualizarCarrito();
}

/**
 * Quitar producto del carrito
 */
function quitarProducto(index) {
    if (confirm('¿Está seguro de quitar este producto del carrito?')) {
        carrito.splice(index, 1);
        actualizarCarrito();
        mostrarAlerta('success', 'Producto eliminado del carrito');
    }
}

/**
 * Confirmar y procesar la venta
 */
async function confirmarVenta() {
    // Validaciones
    if (!clienteSeleccionado) {
        mostrarAlerta('error', 'Debe seleccionar un cliente');
        return;
    }
    
    if (carrito.length === 0) {
        mostrarAlerta('error', 'El carrito está vacío');
        return;
    }
    
    if (!confirm('¿Confirmar la venta?')) {
        return;
    }
    
    // Preparar datos
    const tipoComprobante = document.getElementById('tipo-comprobante').value;
    const formaPago = document.getElementById('forma-pago').value;
    
    const datosVenta = {
        id_cliente: clienteSeleccionado.id_cliente,
        tipo_comprobante: tipoComprobante,
        forma_pago: formaPago,
        productos: carrito
    };
    
    // Mostrar loading
    const btnConfirmar = event.target;
    const textoOriginal = btnConfirmar.innerHTML;
    btnConfirmar.innerHTML = '<span class="loading"></span> Procesando...';
    btnConfirmar.disabled = true;
    
    try {
        const response = await fetch('procesar_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosVenta)
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarAlerta('success', 'Venta registrada exitosamente');
            
            // Preguntar si desea imprimir comprobante
            if (confirm('¿Desea imprimir el comprobante?')) {
                window.open(`generar_pdf.php?id_venta=${data.id_venta}`, '_blank');
            }
            
            // Limpiar formulario
            limpiarFormulario();
        } else {
            mostrarAlerta('error', data.message || 'Error al procesar la venta');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al procesar la venta');
    } finally {
        btnConfirmar.innerHTML = textoOriginal;
        btnConfirmar.disabled = false;
    }
}

/**
 * Limpiar formulario después de una venta exitosa
 */
function limpiarFormulario() {
    clienteSeleccionado = null;
    productoSeleccionado = null;
    carrito = [];
    
    document.getElementById('buscar-cliente').value = '';
    document.getElementById('buscar-producto').value = '';
    document.getElementById('cantidad-producto').value = '1';
    document.getElementById('cliente-seleccionado').innerHTML = '';
    document.getElementById('tipo-comprobante').value = 'B';
    document.getElementById('forma-pago').value = 'efectivo';
    
    actualizarCarrito();
}

/**
 * Mostrar formulario para crear nuevo cliente
 */
function mostrarFormNuevoCliente() {
    const modal = `
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px;" id="modal-cliente">
            <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;">
                <h2 style="margin-bottom: 20px;">Nuevo Cliente</h2>
                <form id="form-nuevo-cliente" onsubmit="guardarNuevoCliente(event)">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" id="nuevo-nombre" required>
                    </div>
                    <div class="form-group">
                        <label>Apellido *</label>
                        <input type="text" id="nuevo-apellido" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" id="nuevo-email" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" id="nuevo-telefono">
                    </div>
                    <div class="form-group">
                        <label>DNI/CUIT</label>
                        <input type="text" id="nuevo-dni">
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" id="nuevo-direccion">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Cliente</label>
                        <select id="nuevo-tipo">
                            <option value="consumidor_final">Consumidor Final</option>
                            <option value="responsable_inscripto">Responsable Inscripto</option>
                            <option value="monotributista">Monotributista</option>
                            <option value="exento">Exento</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">Guardar</button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarModal()" style="flex: 1;">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modal);
}

/**
 * Guardar nuevo cliente
 */
async function guardarNuevoCliente(event) {
    event.preventDefault();
    
    const datosCliente = {
        nombre: document.getElementById('nuevo-nombre').value,
        apellido: document.getElementById('nuevo-apellido').value,
        email: document.getElementById('nuevo-email').value,
        telefono: document.getElementById('nuevo-telefono').value,
        dni_cuit: document.getElementById('nuevo-dni').value,
        direccion: document.getElementById('nuevo-direccion').value,
        tipo_cliente: document.getElementById('nuevo-tipo').value
    };
    
    try {
        const response = await fetch('buscar_cliente.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosCliente)
        });
        
        const data = await response.json();
        
        if (data.success) {
            seleccionarCliente(data.cliente);
            cerrarModal();
            mostrarAlerta('success', 'Cliente creado exitosamente');
        } else {
            mostrarAlerta('error', data.message || 'Error al crear cliente');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al crear cliente');
    }
}

/**
 * Cerrar modal
 */
function cerrarModal() {
    const modal = document.getElementById('modal-cliente');
    if (modal) {
        modal.remove();
    }
}

/**
 * Mostrar alerta
 */
function mostrarAlerta(tipo, mensaje) {
    const alert = document.getElementById('alert');
    alert.className = `alert alert-${tipo === 'error' ? 'error' : 'success'} show`;
    alert.textContent = mensaje;
    
    setTimeout(() => {
        alert.classList.remove('show');
    }, 5000);
}

/**
 * Formatear número con separadores de miles
 */
function formatearNumero(numero) {
    return parseFloat(numero).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}