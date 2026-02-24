/**
 * Sistema de Gestión Comercial
 * Módulo de Compras - JavaScript
 */

// Variables globales
let proveedorSeleccionado = null;
let productoSeleccionado = null;
let carrito = [];
let timeoutBusqueda = null;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
    cargarUbicaciones();
    actualizarCarrito();
});

/**
 * Inicializar todos los event listeners
 */
function inicializarEventos() {
    // Búsqueda de proveedor
    const inputProveedor = document.getElementById('buscar-proveedor');
    if (inputProveedor) {
        inputProveedor.addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                timeoutBusqueda = setTimeout(() => buscarProveedor(query), 300);
            } else {
                document.getElementById('resultados-proveedores').classList.remove('active');
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
 * Cargar ubicaciones de stock
 */
async function cargarUbicaciones() {
    try {
        const response = await fetch('cargar_ubicaciones.php');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('ubicacion-stock');
            select.innerHTML = '<option value="">Seleccione ubicación...</option>';
            
            data.ubicaciones.forEach(ubicacion => {
                const option = document.createElement('option');
                option.value = ubicacion.id_ubicacion;
                option.textContent = ubicacion.nombre;
                select.appendChild(option);
            });
            
            // Seleccionar la primera por defecto si existe
            if (data.ubicaciones.length > 0) {
                select.selectedIndex = 1;
            }
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Buscar proveedor en la base de datos
 */
async function buscarProveedor(query) {
    try {
        const response = await fetch(`buscar_proveedor.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success) {
            mostrarResultadosProveedores(data.proveedores);
        } else {
            mostrarAlerta('error', data.message || 'Error al buscar proveedores');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al buscar proveedores');
    }
}

/**
 * Mostrar resultados de búsqueda de proveedores
 */
function mostrarResultadosProveedores(proveedores) {
    const contenedor = document.getElementById('resultados-proveedores');
    
    if (proveedores.length === 0) {
        contenedor.innerHTML = '<div class="search-item">No se encontraron proveedores</div>';
        contenedor.classList.add('active');
        return;
    }
    
    let html = '';
    proveedores.forEach(proveedor => {
        html += `
            <div class="search-item" onclick='seleccionarProveedor(${JSON.stringify(proveedor)})'>
                <strong>${proveedor.nombre}</strong><br>
                <small>${proveedor.email || ''} ${proveedor.cuit ? '- CUIT: ' + proveedor.cuit : ''}</small>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    contenedor.classList.add('active');
}

/**
 * Seleccionar un proveedor
 */
function seleccionarProveedor(proveedor) {
    proveedorSeleccionado = proveedor;
    document.getElementById('buscar-proveedor').value = '';
    document.getElementById('resultados-proveedores').classList.remove('active');
    
    const contenedor = document.getElementById('proveedor-seleccionado');
    contenedor.innerHTML = `
        <div class="proveedor-seleccionado">
            <strong>Proveedor seleccionado:</strong><br>
            ${proveedor.nombre}<br>
            <small>
                ${proveedor.contacto ? 'Contacto: ' + proveedor.contacto : ''}
                ${proveedor.email ? ' - ' + proveedor.email : ''}
                ${proveedor.cuit ? '<br>CUIT: ' + proveedor.cuit : ''}
            </small>
            <button type="button" class="btn btn-danger" onclick="quitarProveedor()" style="margin-top: 10px;">
                ✕ Quitar proveedor
            </button>
        </div>
    `;
    
    mostrarAlerta('success', 'Proveedor seleccionado correctamente');
}

/**
 * Quitar proveedor seleccionado
 */
function quitarProveedor() {
    proveedorSeleccionado = null;
    document.getElementById('proveedor-seleccionado').innerHTML = '';
}

/**
 * Buscar producto en la base de datos
 */
async function buscarProducto(query) {
    try {
        const response = await fetch(`buscar_producto_compra.php?q=${encodeURIComponent(query)}`);
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
        html += `
            <div class="search-item" onclick='seleccionarProducto(${JSON.stringify(producto)})'>
                <strong>${producto.nombre}</strong><br>
                <small>Stock actual: ${producto.stock_total} - ${producto.nombre_categoria}</small>
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
    
    // Si tiene precio de venta, sugerir un precio de compra
    if (producto.precio_unitario) {
        const precioSugerido = (parseFloat(producto.precio_unitario) * 0.6).toFixed(2);
        document.getElementById('precio-compra').value = precioSugerido;
    }
    
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
    const precioCompra = parseFloat(document.getElementById('precio-compra').value);
    const ubicacion = document.getElementById('ubicacion-stock').value;
    
    if (cantidad <= 0) {
        mostrarAlerta('error', 'La cantidad debe ser mayor a 0');
        return;
    }
    
    if (!precioCompra || precioCompra <= 0) {
        mostrarAlerta('error', 'Debe ingresar un precio de compra válido');
        return;
    }
    
    if (!ubicacion) {
        mostrarAlerta('error', 'Debe seleccionar una ubicación de stock');
        return;
    }
    
    // Obtener nombre de la ubicación
    const selectUbicacion = document.getElementById('ubicacion-stock');
    const nombreUbicacion = selectUbicacion.options[selectUbicacion.selectedIndex].text;
    
    // Verificar si el producto ya está en el carrito
    const indexExistente = carrito.findIndex(item => item.id_producto === productoSeleccionado.id_producto && item.id_ubicacion === ubicacion);
    
    if (indexExistente !== -1) {
        carrito[indexExistente].cantidad += cantidad;
        carrito[indexExistente].subtotal = carrito[indexExistente].cantidad * carrito[indexExistente].precio_compra;
    } else {
        const precioVenta = parseFloat(productoSeleccionado.precio_unitario);
        let alertaPrecio = null;
        
        // Verificar si el precio de compra es mayor al de venta
        if (precioCompra > precioVenta) {
            alertaPrecio = 'warning';
        }
        
        // Calcular diferencia con último precio de compra (si existe)
        let diferenciaPrecio = null;
        if (productoSeleccionado.ultimo_precio_compra) {
            const precioAnterior = parseFloat(productoSeleccionado.ultimo_precio_compra);
            const diferencia = ((precioCompra - precioAnterior) / precioAnterior * 100).toFixed(2);
            diferenciaPrecio = {
                anterior: precioAnterior,
                diferencia: diferencia
            };
        }
        
        carrito.push({
            id_producto: productoSeleccionado.id_producto,
            nombre: productoSeleccionado.nombre,
            precio_compra: precioCompra,
            precio_venta: precioVenta,
            cantidad: cantidad,
            subtotal: cantidad * precioCompra,
            id_ubicacion: ubicacion,
            nombre_ubicacion: nombreUbicacion,
            alerta_precio: alertaPrecio,
            diferencia_precio: diferenciaPrecio
        });
    }
    
    // Limpiar selección
    productoSeleccionado = null;
    document.getElementById('buscar-producto').value = '';
    document.getElementById('cantidad-producto').value = '1';
    document.getElementById('precio-compra').value = '';
    
    actualizarCarrito();
    mostrarAlerta('success', 'Producto agregado al ingreso');
}

/**
 * Actualizar visualización del carrito
 */
/**
 * Actualizar visualización del carrito
 */
function actualizarCarrito() {
    const contenedor = document.getElementById('carrito-items');
    
    if (carrito.length === 0) {
        contenedor.innerHTML = `
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <p>No hay productos agregados</p>
                <small>Busque y agregue productos para comenzar</small>
            </div>
        `;
        document.getElementById('total-compra').textContent = '$0.00';
        return;
    }
    
    let html = '';
    let total = 0;
    
    carrito.forEach((item, index) => {
        total += item.subtotal;
        
        let alertaPrecio = '';
        if (item.alerta_precio === 'warning') {
            alertaPrecio = '<span class="badge badge-warning">⚠️ Precio > Venta</span>';
        }
        
        let infoDiferencia = '';
        if (item.diferencia_precio) {
            const esAumento = item.diferencia_precio.diferencia > 0;
            const clase = esAumento ? 'aumento' : 'reduccion';
            const simbolo = esAumento ? '↑' : '↓';
            
            infoDiferencia = `
                <div class="precio-info">
                    <span class="precio-anterior">Anterior: $${formatearNumero(item.diferencia_precio.anterior)}</span>
                    <span class="precio-diferencia ${clase}">${simbolo} ${Math.abs(item.diferencia_precio.diferencia)}%</span>
                </div>
            `;
        }
        
        html += `
            <div class="carrito-item">
                <div class="item-info">
                    <div class="item-nombre">${item.nombre} ${alertaPrecio}</div>
                    <div class="item-detalles">
                        Ubicación: ${item.nombre_ubicacion}<br>
                        $${formatearNumero(item.precio_compra)} × ${item.cantidad} = $${formatearNumero(item.subtotal)}
                    </div>
                    ${infoDiferencia}
                </div>
                <div class="item-inputs">
                    <div>
                        <label style="font-size: 12px; color: #718096;">Cantidad</label>
                        <input type="number" class="input-small" value="${item.cantidad}" 
                               onchange="cambiarCantidad(${index}, this.value)" min="1">
                    </div>
                    <div>
                        <label style="font-size: 12px; color: #718096;">Precio</label>
                        <input type="number" class="input-small" value="${item.precio_compra}" 
                               onchange="cambiarPrecio(${index}, this.value)" min="0.01" step="0.01">
                    </div>
                    <button class="btn btn-danger" onclick="quitarProducto(${index})" style="align-self: flex-end;">✕</button>
                </div>
            </div>
        `;
    });
    
    contenedor.innerHTML = html;
    // ✅ LÍNEA CORREGIDA:
    document.getElementById('total-compra').textContent = '$' + formatearNumero(total);
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
    
    carrito[index].cantidad = nuevaCantidad;
    carrito[index].subtotal = nuevaCantidad * carrito[index].precio_compra;
    
    actualizarCarrito();
}

/**
 * Cambiar precio directamente desde input
 */
function cambiarPrecio(index, nuevoPrecio) {
    nuevoPrecio = parseFloat(nuevoPrecio);
    
    if (isNaN(nuevoPrecio) || nuevoPrecio <= 0) {
        mostrarAlerta('error', 'Precio inválido');
        actualizarCarrito();
        return;
    }
    
    carrito[index].precio_compra = nuevoPrecio;
    carrito[index].subtotal = carrito[index].cantidad * nuevoPrecio;
    
    // Verificar si ahora el precio es mayor al de venta
    if (nuevoPrecio > carrito[index].precio_venta) {
        carrito[index].alerta_precio = 'warning';
    } else {
        carrito[index].alerta_precio = null;
    }
    
    actualizarCarrito();
}

/**
 * Quitar producto del carrito
 */
function quitarProducto(index) {
    if (confirm('¿Está seguro de quitar este producto del ingreso?')) {
        carrito.splice(index, 1);
        actualizarCarrito();
        mostrarAlerta('success', 'Producto eliminado del ingreso');
    }
}

/**
 * Confirmar y procesar la compra
 */
async function confirmarCompra() {
    // Validaciones
    if (!proveedorSeleccionado) {
        mostrarAlerta('error', 'Debe seleccionar un proveedor');
        return;
    }
    
    if (carrito.length === 0) {
        mostrarAlerta('error', 'El carrito está vacío');
        return;
    }
    
    const numeroComprobante = document.getElementById('numero-comprobante').value.trim();
    const fechaIngreso = document.getElementById('fecha-ingreso').value;
    const observaciones = document.getElementById('observaciones').value.trim();
    
    if (!fechaIngreso) {
        mostrarAlerta('error', 'Debe seleccionar una fecha');
        return;
    }
    
    if (!confirm('¿Confirmar el ingreso de mercadería?')) {
        return;
    }
    
    // Preparar datos
    const datosCompra = {
        id_proveedor: proveedorSeleccionado.id_proveedor,
        numero_comprobante: numeroComprobante,
        fecha: fechaIngreso,
        observaciones: observaciones,
        productos: carrito
    };
    
    // Mostrar loading
    const btnConfirmar = event.target;
    const textoOriginal = btnConfirmar.innerHTML;
    btnConfirmar.innerHTML = '<span class="loading"></span> Procesando...';
    btnConfirmar.disabled = true;
    
    try {
        const response = await fetch('procesar_compra.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosCompra)
        });
        
        const data = await response.json();
        
        if (data.success) {
            mostrarAlerta('success', 'Ingreso registrado exitosamente');
            
            // Preguntar si desea ver el detalle
            if (confirm('¿Desea ver el detalle del ingreso?')) {
                window.location.href = `historial.php?id_ingreso=${data.id_ingreso}`;
            } else {
                // Limpiar formulario
                limpiarFormulario();
            }
        } else {
            mostrarAlerta('error', data.message || 'Error al procesar el ingreso');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al procesar el ingreso');
    } finally {
        btnConfirmar.innerHTML = textoOriginal;
        btnConfirmar.disabled = false;
    }
}

/**
 * Limpiar formulario después de una compra exitosa
 */
function limpiarFormulario() {
    proveedorSeleccionado = null;
    productoSeleccionado = null;
    carrito = [];
    
    document.getElementById('buscar-proveedor').value = '';
    document.getElementById('buscar-producto').value = '';
    document.getElementById('numero-comprobante').value = '';
    document.getElementById('fecha-ingreso').value = new Date().toISOString().split('T')[0];
    document.getElementById('observaciones').value = '';
    document.getElementById('cantidad-producto').value = '1';
    document.getElementById('precio-compra').value = '';
    document.getElementById('proveedor-seleccionado').innerHTML = '';
    
    actualizarCarrito();
}

/**
 * Mostrar formulario para crear nuevo proveedor
 */
function mostrarFormNuevoProveedor() {
    const modal = `
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px;" id="modal-proveedor">
            <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;">
                <h2 style="margin-bottom: 20px;">Nuevo Proveedor</h2>
                <form id="form-nuevo-proveedor" onsubmit="guardarNuevoProveedor(event)">
                    <div class="form-group">
                        <label>Nombre / Razón Social *</label>
                        <input type="text" id="nuevo-nombre" required>
                    </div>
                    <div class="form-group">
                        <label>CUIT</label>
                        <input type="text" id="nuevo-cuit" placeholder="20-12345678-9">
                    </div>
                    <div class="form-group">
                        <label>Persona de Contacto</label>
                        <input type="text" id="nuevo-contacto">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="nuevo-email">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" id="nuevo-telefono">
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" id="nuevo-direccion">
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
 * Guardar nuevo proveedor
 */
async function guardarNuevoProveedor(event) {
    event.preventDefault();
    
    const datosProveedor = {
        nombre: document.getElementById('nuevo-nombre').value,
        cuit: document.getElementById('nuevo-cuit').value,
        contacto: document.getElementById('nuevo-contacto').value,
        email: document.getElementById('nuevo-email').value,
        telefono: document.getElementById('nuevo-telefono').value,
        direccion: document.getElementById('nuevo-direccion').value
    };
    
    try {
        const response = await fetch('buscar_proveedor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosProveedor)
        });
        
        const data = await response.json();
        
        if (data.success) {
            seleccionarProveedor(data.proveedor);
            cerrarModal();
            mostrarAlerta('success', 'Proveedor creado exitosamente');
        } else {
            mostrarAlerta('error', data.message || 'Error al crear proveedor');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('error', 'Error de conexión al crear proveedor');
    }
}

/**
 * Cerrar modal
 */
function cerrarModal() {
    const modal = document.getElementById('modal-proveedor');
    if (modal) {
        modal.remove();
    }
}

/**
 * Mostrar alerta
 */
function mostrarAlerta(tipo, mensaje) {
    const alert = document.getElementById('alert');
    alert.className = `alert alert-${tipo === 'error' ? 'error' : tipo === 'warning' ? 'warning' : 'success'} show`;
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