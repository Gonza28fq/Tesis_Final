<?php
// =============================================
// modules/notas_pedido/nueva.php
// Crear Nueva Nota de Pedido
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('notas_pedido', 'crear');

$titulo_pagina = 'Nueva Nota de Pedido';
$db = getDB();
$pdo = $db->getConexion();
$errores = [];

// Obtener proveedores activos
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();

// Obtener productos activos con información de stock Y PROVEEDOR
$productos = $db->query("SELECT p.*, 
                        c.nombre_categoria,
                        prov.nombre_proveedor,
                        prov.id_proveedor,
                        (SELECT COALESCE(SUM(cantidad), 0) FROM stock WHERE id_producto = p.id_producto) as stock_actual
                        FROM productos p 
                        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria 
                        LEFT JOIN proveedores prov ON p.id_proveedor = prov.id_proveedor
                        WHERE p.estado = 'activo' 
                        ORDER BY p.nombre")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_proveedor = (int)$_POST['id_proveedor'];
    $fecha_solicitud = $_POST['fecha_solicitud'];
    $fecha_necesidad = !empty($_POST['fecha_necesidad']) ? $_POST['fecha_necesidad'] : null;
    $observaciones = limpiarInput($_POST['observaciones'] ?? '');
    
    // Productos seleccionados
    $productos_seleccionados = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $precios_estimados = $_POST['precios_estimados'] ?? [];
    $observaciones_productos = $_POST['observaciones_producto'] ?? [];
    
    // Validaciones
    if ($id_proveedor <= 0) {
        $errores[] = 'Debe seleccionar un proveedor';
    }
    
    if (empty($fecha_solicitud)) {
        $errores[] = 'La fecha de solicitud es obligatoria';
    }
    
    if (empty($productos_seleccionados)) {
        $errores[] = 'Debe agregar al menos un producto';
    }
    
    // Validar productos
    foreach ($productos_seleccionados as $id_producto) {
        if (!isset($cantidades[$id_producto]) || $cantidades[$id_producto] <= 0) {
            $errores[] = 'Todas las cantidades deben ser mayores a 0';
            break;
        }
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // Generar número de nota
            $sql_ultimo = "SELECT numero_nota FROM notas_pedido ORDER BY id_nota_pedido DESC LIMIT 1";
            $stmt_ultimo = $db->query($sql_ultimo);
            $ultimo = $stmt_ultimo->fetch();
            
            if ($ultimo) {
                $ultimo_num = (int)str_replace('NP-', '', $ultimo['numero_nota']);
                $nuevo_num = $ultimo_num + 1;
            } else {
                $nuevo_num = 1;
            }
            $numero_nota = 'NP-' . str_pad($nuevo_num, 6, '0', STR_PAD_LEFT);
            
            // Insertar nota de pedido
            $sql = "INSERT INTO notas_pedido 
                    (numero_nota, id_proveedor, id_usuario_solicitante, fecha_solicitud, 
                     fecha_necesidad, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, ?, 'pendiente')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $numero_nota,
                $id_proveedor,
                $_SESSION['usuario_id'],
                $fecha_solicitud,
                $fecha_necesidad,
                $observaciones
            ]);
            
            $id_nota = $pdo->lastInsertId();
            
            // Insertar detalle
            $sql_detalle = "INSERT INTO notas_pedido_detalle 
                           (id_nota_pedido, id_producto, cantidad, precio_estimado, observaciones)
                           VALUES (?, ?, ?, ?, ?)";
            
            foreach ($productos_seleccionados as $id_producto) {
                $cantidad = (int)$cantidades[$id_producto];
                $precio = !empty($precios_estimados[$id_producto]) ? (float)$precios_estimados[$id_producto] : 0;
                $obs_prod = limpiarInput($observaciones_productos[$id_producto] ?? '');
                
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([$id_nota, $id_producto, $cantidad, $precio, $obs_prod]);
            }
            
            $pdo->commit();
            
            // Registrar auditoría
            registrarAuditoria('notas_pedido', 'crear', "Nota de pedido $numero_nota creada");
            
            setAlerta('success', 'Nota de pedido creada correctamente. Pendiente de aprobación.');
            redirigir('modules/notas_pedido/ver.php?id=' . $id_nota);
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="file-text"></i> Nueva Nota de Pedido</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Notas de Pedido</a></li>
            <li class="breadcrumb-item active">Nueva</li>
        </ol>
    </nav>
</div>

<!-- Alertas -->
<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <strong><i data-feather="alert-triangle"></i> Errores:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="" id="form-nota">
    <div class="row">
        <!-- Columna Principal -->
        <div class="col-lg-8">
            <!-- Información General -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i data-feather="info"></i> Información General</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_proveedor" id="select_proveedor" required>
                                <option value="0">Seleccione un proveedor...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>">
                                    <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                <i data-feather="info" width="12"></i> 
                                Se mostrarán solo los productos de este proveedor
                            </small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha Solicitud <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_solicitud" id="fecha_solicitud"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha Necesidad <span class="text-danger">*</span></label>
                            <input type="date"
                                class="form-control"
                                name="fecha_necesidad"
                                id="fecha_necesidad"
                                min="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo date('Y-m-d'); ?>"
                                required>
                            <small class="form-text text-muted">Igual o posterior a la fecha de solicitud</small>
                        </div>

                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones Generales</label>
                        <textarea class="form-control" name="observaciones" rows="3" 
                                  placeholder="Observaciones o instrucciones especiales..."></textarea>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i data-feather="info"></i>
                        <strong>Información:</strong> La nota quedará en estado "Pendiente" hasta que sea aprobada por el supervisor o jefe de compras.
                    </div>
                </div>
            </div>
            
            <!-- Productos -->
            <div class="card">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i data-feather="box"></i> Productos Solicitados <span class="text-danger">*</span></h5>
                    <button type="button" class="btn btn-sm btn-light" id="btn-agregar-productos" disabled>
                        <i data-feather="plus-circle"></i> Agregar Producto
                    </button>
                </div>
                <div class="card-body">
                    <div id="mensaje-seleccionar-proveedor" class="alert alert-warning">
                        <i data-feather="alert-triangle"></i>
                        <strong>Seleccione primero un proveedor</strong> para poder agregar productos
                    </div>
                    
                    <div class="table-responsive" id="tabla-productos-container" style="display: none;">
                        <table class="table" id="tabla-productos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th width="80">Stock</th>
                                    <th width="100">Cantidad</th>
                                    <th width="120">Precio Est.</th>
                                    <th width="150">Observaciones</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="productos-seleccionados">
                                <tr id="empty-row">
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i data-feather="inbox" width="48"></i>
                                        <p class="mt-2">No hay productos agregados</p>
                                        <small>Use el botón "Agregar Producto" para comenzar</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panel Lateral -->
        <div class="col-lg-4">
            <!-- Resumen -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i data-feather="bar-chart-2"></i> Resumen</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-7">Total Productos:</dt>
                        <dd class="col-sm-5 text-end" id="total-productos">0</dd>
                        
                        <dt class="col-sm-7">Total Unidades:</dt>
                        <dd class="col-sm-5 text-end" id="total-unidades">0</dd>
                        
                        <dt class="col-12"><hr></dt>
                        
                        <dt class="col-sm-7">Monto Estimado:</dt>
                        <dd class="col-sm-5 text-end fw-bold text-primary" id="monto-estimado">$ 0,00</dd>
                        
                        <dt class="col-12 mt-2">
                            <small class="text-muted">
                                <i data-feather="info" width="14"></i>
                                El monto estimado es referencial y puede variar en la compra real.
                            </small>
                        </dt>
                    </dl>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i data-feather="save"></i> Guardar Nota
                        </button>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Ayuda -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i data-feather="help-circle"></i> Ayuda</h6>
                </div>
                <div class="card-body">
                    <small>
                        <ul class="mb-0">
                            <li><strong>Primero seleccione un proveedor</strong></li>
                            <li>Solo se mostrarán productos de ese proveedor</li>
                            <li>Agregue todos los productos necesarios</li>
                            <li>Indique la cantidad requerida de cada uno</li>
                            <li>El precio estimado es opcional</li>
                            <li>La nota será revisada antes de convertirse en compra</li>
                        </ul>
                    </small>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal Agregar Productos -->
<div class="modal fade" id="modalProductos" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i data-feather="box"></i> Seleccionar Productos
                    <span id="proveedor-seleccionado-modal" class="badge bg-light text-dark ms-2"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="buscar-producto" 
                               placeholder="Buscar por código o nombre...">
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="filtro-stock">
                            <option value="">Todos los productos</option>
                            <option value="bajo">Solo con stock bajo (menor al mínimo)</option>
                            <option value="sin">Solo sin stock</option>
                        </select>
                    </div>
                </div>
                
                <div id="productos-disponibles-info" class="alert alert-info">
                    <i data-feather="info"></i>
                    Mostrando productos del proveedor seleccionado
                </div>
                
                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table table-hover table-sm">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Proveedor</th>
                                <th>Stock Actual</th>
                                <th>Stock Mín.</th>
                                <th>Precio Costo</th>
                                <th width="80"></th>
                            </tr>
                        </thead>
                        <tbody id="lista-productos">
                            <?php foreach ($productos as $prod): 
                                $stock_bajo = $prod['stock_actual'] < $prod['stock_minimo'];
                                $sin_stock = $prod['stock_actual'] == 0;
                            ?>
                            <tr data-id="<?php echo $prod['id_producto']; ?>" 
                                data-proveedor="<?php echo $prod['id_proveedor'] ?? '0'; ?>"
                                data-stock-bajo="<?php echo $stock_bajo ? '1' : '0'; ?>"
                                data-sin-stock="<?php echo $sin_stock ? '1' : '0'; ?>">
                                <td><code><?php echo htmlspecialchars($prod['codigo']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                                    <?php if ($prod['nombre_categoria']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($prod['nombre_categoria']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($prod['nombre_proveedor'] ?? 'Sin proveedor'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $sin_stock ? 'danger' : ($stock_bajo ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($prod['stock_actual']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($prod['stock_minimo']); ?></span>
                                </td>
                                <td><?php echo formatearMoneda($prod['precio_costo']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success btn-agregar" 
                                            data-id="<?php echo $prod['id_producto']; ?>"
                                            data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                            data-stock="<?php echo $prod['stock_actual']; ?>"
                                            data-precio="<?php echo $prod['precio_costo']; ?>">
                                        <i data-feather="plus"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    
let productosAgregados = [];
let proveedorSeleccionado = 0;
setInterval(() => {
    document.getElementById("fecha_necesidad").removeAttribute("max");
}, 200);


// Cuando cambia el proveedor
document.getElementById('select_proveedor').addEventListener('change', function() {
    proveedorSeleccionado = parseInt(this.value);
    const btnAgregar = document.getElementById('btn-agregar-productos');
    const mensajeSeleccionar = document.getElementById('mensaje-seleccionar-proveedor');
    const tablaContainer = document.getElementById('tabla-productos-container');
    
    if (proveedorSeleccionado > 0) {
        btnAgregar.disabled = false;
        mensajeSeleccionar.style.display = 'none';
        tablaContainer.style.display = 'block';
        
        // Actualizar nombre en modal
        const nombreProveedor = this.options[this.selectedIndex].text;
        document.getElementById('proveedor-seleccionado-modal').textContent = nombreProveedor;
    } else {
        btnAgregar.disabled = true;
        mensajeSeleccionar.style.display = 'block';
        tablaContainer.style.display = 'none';
    }
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
// Sincronizar fecha de necesidad con fecha de solicitud
document.getElementById('fecha_solicitud').addEventListener('change', function() {
    const fechaSolicitud = this.value;
    const inputFechaNecesidad = document.getElementById('fecha_necesidad');
    
    // Actualizar el mínimo de fecha necesidad
    inputFechaNecesidad.min = fechaSolicitud;
    
    // Si la fecha de necesidad es anterior a la nueva fecha de solicitud, actualizarla
    if (inputFechaNecesidad.value < fechaSolicitud) {
        inputFechaNecesidad.value = fechaSolicitud;
    }
});
// Abrir modal
document.getElementById('btn-agregar-productos').addEventListener('click', function() {
    if (proveedorSeleccionado > 0) {
        const modal = new bootstrap.Modal(document.getElementById('modalProductos'));
        modal.show();
        
        // Filtrar productos del proveedor
        filtrarProductosPorProveedor();
    }
});

function filtrarProductosPorProveedor() {
    let productosVisibles = 0;

    document.querySelectorAll('#lista-productos tr').forEach(tr => {
        const provProducto = parseInt(tr.dataset.proveedor || '0');

        if (proveedorSeleccionado === 0 || provProducto === proveedorSeleccionado) {
            tr.style.display = '';
            productosVisibles++;
        } else {
            tr.style.display = 'none';
        }
    });

    // Evita error si el elemento no existe — clave para que no se corte todo el script
    const infoDiv = document.getElementById('productos-disponibles-info');
    if (!infoDiv) return;

    if (productosVisibles === 0) {
        infoDiv.className = 'alert alert-warning';
        infoDiv.innerHTML = '<i data-feather="alert-triangle"></i> Este proveedor no tiene productos asociados';
    } else {
        infoDiv.className = 'alert alert-info';
        infoDiv.innerHTML = `<i data-feather="info"></i> Mostrando ${productosVisibles} producto(s) del proveedor seleccionado`;
    }

    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}


// Buscar producto
document.getElementById('buscar-producto').addEventListener('input', function() {
    filtrarProductos();
});

// Filtro de stock
document.getElementById('filtro-stock').addEventListener('change', function() {
    filtrarProductos();
});

function filtrarProductos() {
    const busqueda = document.getElementById('buscar-producto').value.toLowerCase();
    const filtroStock = document.getElementById('filtro-stock').value;
    
    document.querySelectorAll('#lista-productos tr').forEach(tr => {
        const provProducto = parseInt(tr.dataset.proveedor || '0');
        const texto = tr.textContent.toLowerCase();
        
        // Filtro de proveedor (principal)
        const cumpleProveedor = (proveedorSeleccionado === 0 || provProducto === proveedorSeleccionado);
        
        // Filtro de búsqueda
        const cumpleBusqueda = texto.includes(busqueda);
        
        // Filtro de stock
        let cumpleFiltro = true;
        if (filtroStock === 'bajo') {
            cumpleFiltro = tr.dataset.stockBajo === '1';
        } else if (filtroStock === 'sin') {
            cumpleFiltro = tr.dataset.sinStock === '1';
        }
        
        tr.style.display = (cumpleProveedor && cumpleBusqueda && cumpleFiltro) ? '' : 'none';
    });
}

// Agregar producto
document.querySelectorAll('.btn-agregar').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const codigo = this.dataset.codigo;
        const nombre = this.dataset.nombre;
        const stock = this.dataset.stock;
        const precio = parseFloat(this.dataset.precio);
        
        if (productosAgregados.includes(id)) {
            alert('Este producto ya fue agregado');
            return;
        }
        
        productosAgregados.push(id);
        
        const tbody = document.getElementById('productos-seleccionados');
        document.getElementById('empty-row')?.remove();
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <strong>${codigo}</strong> - ${nombre}
                <input type="hidden" name="productos[]" value="${id}">
            </td>
            <td>
                <span class="badge bg-${stock == 0 ? 'danger' : 'secondary'}">${stock}</span>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm cantidad" 
                       name="cantidades[${id}]" value="1" min="1" required>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm precio" 
                       name="precios_estimados[${id}]" value="${precio.toFixed(2)}" 
                       step="0.01" min="0" placeholder="Opcional">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" 
                       name="observaciones_producto[${id}]" placeholder="Obs...">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProducto(this, '${id}')">
                    <i data-feather="trash-2"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
        
        // Agregar eventos
        tr.querySelector('.cantidad').addEventListener('input', actualizarResumen);
        tr.querySelector('.precio').addEventListener('input', actualizarResumen);
        
        actualizarResumen();
        
        // Reemplazar iconos
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Cerrar modal
        bootstrap.Modal.getInstance(document.getElementById('modalProductos')).hide();
    });
});


function eliminarProducto(btn, id) {
    if (confirm('¿Eliminar este producto?')) {
        btn.closest('tr').remove();
        productosAgregados = productosAgregados.filter(p => p !== id);
        
        if (productosAgregados.length === 0) {
            document.getElementById('productos-seleccionados').innerHTML = `
                <tr id="empty-row">
                    <td colspan="6" class="text-center text-muted py-4">
                        <i data-feather="inbox" width="48"></i>
                        <p class="mt-2">No hay productos agregados</p>
                        <small>Use el botón "Agregar Producto" para comenzar</small>
                    </td>
                </tr>
            `;
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        }
        
        actualizarResumen();
    }
}

function actualizarResumen() {
    let totalProductos = 0;
    let totalUnidades = 0;
    let montoTotal = 0;
    
    document.querySelectorAll('#productos-seleccionados tr:not(#empty-row)').forEach(tr => {
        totalProductos++;
        
        const cantidad = parseInt(tr.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(tr.querySelector('.precio').value) || 0;
        
        totalUnidades += cantidad;
        montoTotal += cantidad * precio;
    });
    
    document.getElementById('total-productos').textContent = totalProductos;
    document.getElementById('total-unidades').textContent = totalUnidades;
    document.getElementById('monto-estimado').textContent = '$ ' + montoTotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
}

// Inicializar Feather Icons
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

</script>

<?php include '../../includes/footer.php'; ?>