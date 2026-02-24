<?php
// =============================================
// modules/stock/transferir.php
// Transferir Stock entre Ubicaciones
// =============================================
require_once '../../config/constantes.php';      // 1️⃣ Primero
require_once '../../config/conexion.php';        // 2️⃣ Segundo
require_once '../../includes/funciones.php';     // 3️⃣ Tercero

iniciarSesion();  // 4️⃣ Cuarto
requierePermiso('stock', 'transferir');

$titulo_pagina = 'Transferir Stock';
$db = getDB();
$errores = [];

// Obtener ubicaciones
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE estado = 'activo' ORDER BY nombre_ubicacion")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto = (int)$_POST['id_producto'];
    $id_ubicacion_origen = (int)$_POST['id_ubicacion_origen'];
    $id_ubicacion_destino = (int)$_POST['id_ubicacion_destino'];
    $cantidad = (int)$_POST['cantidad'];
    $motivo = limpiarInput($_POST['motivo']);
    $observaciones = limpiarInput($_POST['observaciones']);
    
    // Validaciones
    if ($id_producto <= 0) {
        $errores[] = 'Debe seleccionar un producto';
    }
    
    if ($id_ubicacion_origen <= 0) {
        $errores[] = 'Debe seleccionar ubicación de origen';
    }
    
    if ($id_ubicacion_destino <= 0) {
        $errores[] = 'Debe seleccionar ubicación de destino';
    }
    
    if ($id_ubicacion_origen == $id_ubicacion_destino) {
        $errores[] = 'Las ubicaciones de origen y destino deben ser diferentes';
    }
    
    if ($cantidad <= 0) {
        $errores[] = 'La cantidad debe ser mayor a 0';
    }
    
    // Verificar stock disponible en origen
    if ($id_producto > 0 && $id_ubicacion_origen > 0) {
        $sql = "SELECT cantidad FROM stock WHERE id_producto = ? AND id_ubicacion = ?";
        $stmt = $db->query($sql, [$id_producto, $id_ubicacion_origen]);
        $stock_origen = $stmt->fetch();
        
        if (!$stock_origen || $stock_origen['cantidad'] < $cantidad) {
            $errores[] = 'No hay suficiente stock en la ubicación de origen (disponible: ' . ($stock_origen['cantidad'] ?? 0) . ')';
        }
    }
    
    // Si no hay errores, procesar
    if (empty($errores)) {
        try {
            $db->beginTransaction();
            
            // Restar de origen
            $sql = "UPDATE stock SET cantidad = cantidad - ? WHERE id_producto = ? AND id_ubicacion = ?";
            $db->query($sql, [$cantidad, $id_producto, $id_ubicacion_origen]);
            
            // Sumar a destino (o crear si no existe)
            $sql_check = "SELECT cantidad FROM stock WHERE id_producto = ? AND id_ubicacion = ?";
            $stmt = $db->query($sql_check, [$id_producto, $id_ubicacion_destino]);
            $stock_destino = $stmt->fetch();
            
            if ($stock_destino) {
                $sql = "UPDATE stock SET cantidad = cantidad + ? WHERE id_producto = ? AND id_ubicacion = ?";
                $db->execute($sql, [$cantidad, $id_producto, $id_ubicacion_destino]);
            } else {
                $sql = "INSERT INTO stock (id_producto, id_ubicacion, cantidad) VALUES (?, ?, ?)";
                $db->query($sql, [$id_producto, $id_ubicacion_destino, $cantidad]);
            }
            
            // Registrar movimiento
            $sql_mov = "INSERT INTO movimientos_stock 
                       (id_producto, id_ubicacion_origen, id_ubicacion_destino, tipo_movimiento, cantidad, motivo, id_usuario, observaciones)
                       VALUES (?, ?, ?, 'transferencia', ?, ?, ?, ?)";
            $db->query($sql_mov, [
                $id_producto, $id_ubicacion_origen, $id_ubicacion_destino,
                $cantidad, $motivo, $_SESSION['usuario_id'], $observaciones
            ]);
            
            $db->commit();
            
            // Obtener nombre del producto
            $sql_prod = "SELECT nombre FROM productos WHERE id_producto = ?";
            $prod = $db->query($sql_prod, [$id_producto])->fetch();
            
            registrarAuditoria('stock', 'transferir', 
                "Producto: {$prod['nombre']} - Cantidad: $cantidad");
            
            setAlerta('success', 'Transferencia realizada correctamente');
            redirigir('index.php');
            
        } catch (Exception $e) {
            $db->rollback();
            $errores[] = 'Error al realizar transferencia: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shuffle"></i> Transferir Stock</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Stock</a></li>
            <li class="breadcrumb-item active">Transferir</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i data-feather="alert-circle"></i> Errores encontrados:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <form method="POST" action="" id="form-transferencia">
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="package"></i> Seleccionar Producto
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Buscar Producto <span class="text-danger">*</span></label>
                        <input type="hidden" name="id_producto" id="id_producto" required>
                        <input type="text" class="form-control" id="buscar_producto" placeholder="Escribe el nombre o código del producto..." autocomplete="off">
                        <div id="resultados_busqueda" class="list-group mt-2" style="position: absolute; z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"></div>
                    </div>
                    
                    <div id="info_producto" style="display: none;" class="alert alert-info">
                        <strong>Producto seleccionado:</strong> <span id="prod_nombre"></span>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="shuffle"></i> Datos de Transferencia
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ubicación Origen <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_ubicacion_origen" id="id_ubicacion_origen" required>
                                <option value="">Seleccione origen...</option>
                                <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?php echo $ubi['id_ubicacion']; ?>"><?php echo $ubi['nombre_ubicacion']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="stock_origen" class="mt-2" style="display: none;">
                                <small>Stock disponible: <strong><span id="cant_origen">0</span></strong></small>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ubicación Destino <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_ubicacion_destino" required>
                                <option value="">Seleccione destino...</option>
                                <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?php echo $ubi['id_ubicacion']; ?>"><?php echo $ubi['nombre_ubicacion']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad a Transferir <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="cantidad" id="cantidad" min="1" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Motivo <span class="text-danger">*</span></label>
                            <select class="form-select" name="motivo" required>
                                <option value="">Seleccione motivo...</option>
                                <option value="Reubicación de inventario">Reubicación de inventario</option>
                                <option value="Balanceo de stock">Balanceo de stock</option>
                                <option value="Traslado a sucursal">Traslado a sucursal</option>
                                <option value="Reorganización">Reorganización</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" placeholder="Detalles adicionales de la transferencia..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i data-feather="info"></i> La transferencia restará stock de la ubicación origen y lo sumará a la ubicación destino automáticamente.
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                        
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="check"></i> Confirmar Transferencia
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let timeoutBusqueda;
let productoSeleccionado = null;

// Buscar productos
const inputBuscar = document.getElementById('buscar_producto');
const selectTipo = document.getElementById('tipo_busqueda');
const divResultados = document.getElementById('resultados_busqueda');

// Event listeners
if (inputBuscar) {
    inputBuscar.addEventListener('input', buscarProductos);
    
    if (selectTipo) {
        selectTipo.addEventListener('change', () => {
            if (inputBuscar.value.length >= 2) {
                buscarProductos();
            }
        });
    }
}

// Función de búsqueda
function buscarProductos() {
    clearTimeout(timeoutBusqueda);
    
    const query = inputBuscar.value.trim();
    
    if (query.length < 2) {
        divResultados.style.display = 'none';
        return;
    }
    
    divResultados.innerHTML = '<div class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div> Buscando...</div>';
    divResultados.style.display = 'block';
    
    timeoutBusqueda = setTimeout(() => {
        const tipo = selectTipo ? selectTipo.value : 'todos';
        const url = 'buscar_producto.php?q=' + encodeURIComponent(query) + '&tipo=' + tipo;
        
        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error('Error en servidor');
                return res.json();
            })
            .then(data => {
                console.log('Respuesta:', data);
                mostrarResultados(data);
            })
            .catch(err => {
                console.error('Error:', err);
                divResultados.innerHTML = '<div class="list-group-item list-group-item-danger">Error: ' + err.message + '</div>';
            });
    }, 300);
}

// Mostrar resultados
function mostrarResultados(data) {
    if (!data.success || !data.productos || data.productos.length === 0) {
        divResultados.innerHTML = '<div class="list-group-item text-center text-muted">No se encontraron productos</div>';
        return;
    }
    
    let html = '';
    data.productos.forEach(prod => {
        html += `
            <a href="#" class="list-group-item list-group-item-action" onclick="seleccionarProducto(${prod.id_producto}, '${escape(prod.nombre)}', '${escape(prod.codigo)}', ${prod.stock_total}, '${escape(prod.nombre_categoria || 'Sin categoría')}'); return false;">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${prod.nombre}</strong><br>
                        <small class="text-muted">
                            <span class="badge bg-secondary">${prod.codigo}</span>
                            ${prod.nombre_categoria ? '<span class="badge bg-info ms-1">' + prod.nombre_categoria + '</span>' : ''}
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary">${prod.stock_total}</span>
                    </div>
                </div>
            </a>
        `;
    });
    
    divResultados.innerHTML = html;
}
// Función escape para cadenas
function escape(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Seleccionar producto
function seleccionarProducto(id, nombre, codigo, stock, categoria) {
    productoSeleccionadoId = id;
    
    document.getElementById('id_producto').value = id;
    document.getElementById('buscar_producto').value = nombre;
    document.getElementById('prod_nombre').textContent = nombre;
    document.getElementById('prod_codigo').textContent = codigo;
    document.getElementById('prod_categoria').textContent = categoria;
    document.getElementById('prod_stock').textContent = stock;
    
    document.getElementById('info_producto').style.display = 'block';
    divResultados.style.display = 'none';
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Mostrar stock por ubicaciones
function mostrarStockUbicaciones(data) {
    let html = `
        <div class="mb-3">
            <h6><strong>${data.producto.nombre}</strong></h6>
            <p class="text-muted mb-0">Código: ${data.producto.codigo}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Ubicación</th>
                        <th>Tipo</th>
                        <th class="text-end">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (data.ubicaciones && data.ubicaciones.length > 0) {
        data.ubicaciones.forEach(ubi => {
            html += `
                <tr>
                    <td><strong>${ubi.nombre_ubicacion}</strong></td>
                    <td><span class="badge bg-info">${ubi.tipo}</span></td>
                    <td class="text-end"><span class="badge bg-primary">${ubi.cantidad}</span></td>
                </tr>
            `;
        });
        html += `
                </tbody>
                <tfoot class="table-info">
                    <tr>
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td class="text-end"><strong>${data.stock_total}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        `;
    } else {
        html += '<tr><td colspan="3" class="text-center text-muted py-4">No hay stock registrado</td></tr></tbody></table>';
    }
    
    document.getElementById('contenido_stock_ubicacion').innerHTML = html;
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('#buscar_producto') && !e.target.closest('#resultados_busqueda')) {
        document.getElementById('resultados_busqueda').style.display = 'none';
    }
});
// Inicializar iconos si está disponible
if (typeof feather !== 'undefined') {
    feather.replace();
}

feather.replace();
</script>


<?php include '../../includes/footer.php';
?>