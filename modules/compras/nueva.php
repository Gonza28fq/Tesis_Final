<?php
// =============================================
// modules/compras/nueva.php
// Crear Nueva Compra (Directa o desde Nota)
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('compras', 'crear');

$titulo_pagina = 'Nueva Compra';
$db = getDB();
$pdo = $db->getConexion();
$errores = [];

// Verificar si viene desde una nota de pedido
$id_nota_pedido = isset($_GET['nota']) ? (int)$_GET['nota'] : 0;
$nota_pedido = null;
$detalle_nota = [];

if ($id_nota_pedido > 0) {
    // Cargar nota de pedido
    $sql_nota = "SELECT np.*, p.nombre_proveedor 
                FROM notas_pedido np
                INNER JOIN proveedores p ON np.id_proveedor = p.id_proveedor
                WHERE np.id_nota_pedido = ? AND np.estado = 'aprobada'";
    $nota_pedido = $db->query($sql_nota, [$id_nota_pedido])->fetch();
    
    if (!$nota_pedido) {
        setAlerta('danger', 'Nota de pedido no encontrada o no está aprobada');
        redirigir('../notas_pedido/index.php');
    }
    
    // Cargar detalle de la nota
    $sql_detalle = "SELECT npd.*, p.codigo, p.nombre, p.precio_costo 
                   FROM notas_pedido_detalle npd
                   INNER JOIN productos p ON npd.id_producto = p.id_producto
                   WHERE npd.id_nota_pedido = ?";
    $detalle_nota = $db->query($sql_detalle, [$id_nota_pedido])->fetchAll();
}

// Obtener proveedores activos
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();

// Obtener productos activos CON INFORMACIÓN DE PROVEEDOR
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
    $id_nota_pedido_form = !empty($_POST['id_nota_pedido']) ? (int)$_POST['id_nota_pedido'] : null;
    $fecha_compra = $_POST['fecha_compra'];
    $tipo_comprobante = limpiarInput($_POST['tipo_comprobante'] ?? '');
    $numero_factura = limpiarInput($_POST['numero_factura'] ?? '');
    $fecha_factura = !empty($_POST['fecha_factura']) ? $_POST['fecha_factura'] : null;
    $observaciones = limpiarInput($_POST['observaciones'] ?? '');
    
    // Productos
    $productos_seleccionados = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $precios = $_POST['precios'] ?? [];
    
    // Validaciones
    if ($id_proveedor <= 0) {
        $errores[] = 'Debe seleccionar un proveedor';
    }
    
    if (empty($fecha_compra)) {
        $errores[] = 'La fecha de compra es obligatoria';
    }
    
    if (empty($productos_seleccionados)) {
        $errores[] = 'Debe agregar al menos un producto';
    }
    // Si por algún motivo no llega el número de factura desde el form, generarlo igual
    if (empty($numero_factura)) {
        $sql2 = "SELECT numero_compra FROM compras ORDER BY id_compra DESC LIMIT 1";
        $stmt2 = $pdo->query($sql2);
        $ultimo = $stmt2->fetch();

        if ($ultimo) {
            $ultimo_num = (int)str_replace('CP-', '', $ultimo['numero_compra']);
            $nuevo_num = $ultimo_num + 1;
        } else {
            $nuevo_num = 1;
        }

        $numero_factura = '0001-' . str_pad($nuevo_num, 8, '0', STR_PAD_LEFT);
    }

    
    foreach ($productos_seleccionados as $id_producto) {
        if (!isset($cantidades[$id_producto]) || $cantidades[$id_producto] <= 0) {
            $errores[] = 'Todas las cantidades deben ser mayores a 0';
            break;
        }
        if (!isset($precios[$id_producto]) || $precios[$id_producto] <= 0) {
            $errores[] = 'Todos los precios deben ser mayores a 0';
            break;
        }
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // Generar número de compra
            $sql_ultimo = "SELECT numero_compra FROM compras ORDER BY id_compra DESC LIMIT 1";
            $stmt_ultimo = $db->query($sql_ultimo);
            $ultimo = $stmt_ultimo->fetch();
            
            if ($ultimo) {
                $ultimo_num = (int)str_replace('CP-', '', $ultimo['numero_compra']);
                $nuevo_num = $ultimo_num + 1;
            } else {
                $nuevo_num = 1;
            }
            $numero_compra = 'CP-' . str_pad($nuevo_num, 6, '0', STR_PAD_LEFT);
            
            // Calcular totales
            $subtotal = 0;
            $impuestos = 0;
            
            foreach ($productos_seleccionados as $id_producto) {
                $cantidad = (int)$cantidades[$id_producto];
                $precio = (float)$precios[$id_producto];
                $subtotal += $cantidad * $precio;
            }
            
            // Calcular IVA (asumiendo 21%)
            $impuestos = $subtotal * 0.21;
            $total = $subtotal + $impuestos;
            
            // Insertar compra
            $sql = "INSERT INTO compras 
                (numero_compra, id_proveedor, id_nota_pedido, id_usuario, fecha_compra,
                tipo_comprobante, numero_factura, fecha_factura, subtotal, impuestos,
                total, estado, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $numero_compra,
                $id_proveedor,
                $id_nota_pedido_form,
                $_SESSION['usuario_id'],
                $fecha_compra,
                $tipo_comprobante,
                $numero_factura,
                $fecha_factura,
                $subtotal,
                $impuestos,
                $total,
                trim('pendiente'),
                $observaciones
            ]);

            $id_compra = $pdo->lastInsertId();
            
            // Insertar detalle
            $sql_detalle = "INSERT INTO compras_detalle 
                           (id_compra, id_producto, cantidad, precio_unitario, subtotal)
                           VALUES (?, ?, ?, ?, ?)";
            
            foreach ($productos_seleccionados as $id_producto) {
                $cantidad = (int)$cantidades[$id_producto];
                $precio = (float)$precios[$id_producto];
                $subtotal_linea = $cantidad * $precio;
                
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([$id_compra, $id_producto, $cantidad, $precio, $subtotal_linea]);
            }
            
            // Si proviene de nota de pedido, actualizar su estado
            if ($id_nota_pedido_form) {
                $sql_nota = "UPDATE notas_pedido SET estado = ? WHERE id_nota_pedido = ?";
                $stmt = $pdo->prepare($sql_nota);
                $stmt->execute(['completada', $id_nota_pedido_form]);
            }
            
            $pdo->commit();
            
            // Registrar auditoría
            $origen = $id_nota_pedido_form ? " (desde nota de pedido)" : " (compra directa)";
            registrarAuditoria('compras', 'crear', "Compra $numero_compra creada$origen");
            
            setAlerta('success', 'Compra registrada correctamente. Pendiente de recepción.');
            redirigir('modules/compras/ver.php?id=' . $id_compra);
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shopping-bag"></i> 
        <?php echo $nota_pedido ? 'Nueva Compra desde Nota de Pedido' : 'Nueva Compra Directa'; ?>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Compras</a></li>
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

<!-- Alerta si viene de nota de pedido -->
<?php if ($nota_pedido): ?>
<div class="alert alert-info">
    <strong><i data-feather="info"></i> Convirtiendo Nota de Pedido:</strong>
    <span class="badge bg-info"><?php echo $nota_pedido['numero_nota']; ?></span>
    - <?php echo htmlspecialchars($nota_pedido['nombre_proveedor']); ?>
</div>
<?php endif; ?>

<form method="POST" action="" id="form-compra">
    <?php if ($nota_pedido): ?>
    <input type="hidden" name="id_nota_pedido" value="<?php echo $nota_pedido['id_nota_pedido']; ?>">
    <?php endif; ?>
    
    <div class="row">
        <!-- Columna Principal -->
        <div class="col-lg-8">
            <!-- Información General -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i data-feather="file-text"></i> Información de la Compra</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_proveedor" id="select_proveedor" required 
                                    <?php echo $nota_pedido ? 'disabled' : ''; ?>>
                                <option value="0">Seleccione un proveedor...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>"
                                        <?php echo ($nota_pedido && $prov['id_proveedor'] == $nota_pedido['id_proveedor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($nota_pedido): ?>
                            <input type="hidden" name="id_proveedor" value="<?php echo $nota_pedido['id_proveedor']; ?>">
                            <?php else: ?>
                            <small class="form-text text-muted">
                                <i data-feather="info" width="12"></i> 
                                Se mostrarán solo los productos de este proveedor
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha Compra <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_compra" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tipo Comprobante <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_comprobante" required>
                                <option value="">Sin comprobante</option>
                                <option value="Factura A" selected>Factura A</option>
                                <option value="Factura B">Factura B</option>
                                <option value="Factura C">Factura C</option>
                                <option value="Remito">Remito</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Número de Factura <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_factura_display"
                                placeholder="0001-00000001" readonly 
                                style="background-color: #e9ecef;">
                            <input type="hidden" name="numero_factura" id="numero_factura">
                            <small class="form-text text-muted">
                                <i data-feather="info" width="12"></i> Se genera automáticamente
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha Factura <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_factura" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="2" 
                                  placeholder="Observaciones adicionales..."><?php echo $nota_pedido ? "Generada desde nota: {$nota_pedido['numero_nota']}" : ''; ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Productos -->
            <div class="card">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i data-feather="box"></i> Productos</h5>
                    <?php if (!$nota_pedido): ?>
                    <button type="button" class="btn btn-sm btn-light" id="btn-agregar-productos" disabled>
                        <i data-feather="plus-circle"></i> Agregar Producto
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$nota_pedido): ?>
                    <div id="mensaje-seleccionar-proveedor" class="alert alert-warning">
                        <i data-feather="alert-triangle"></i>
                        <strong>Seleccione primero un proveedor</strong> para poder agregar productos
                    </div>
                    
                    <div class="table-responsive" id="tabla-productos-container" style="display: none;">
                    <?php else: ?>
                    <div class="table-responsive">
                    <?php endif; ?>
                        <table class="table" id="tabla-productos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th width="100">Cantidad</th>
                                    <th width="120">Precio Unit.</th>
                                    <th width="120">Subtotal</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="productos-seleccionados">
                                <?php if ($nota_pedido && !empty($detalle_nota)): ?>
                                    <?php foreach ($detalle_nota as $item): ?>
                                    <tr data-id="<?php echo $item['id_producto']; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['codigo']); ?></strong> - 
                                            <?php echo htmlspecialchars($item['nombre']); ?>
                                            <input type="hidden" name="productos[]" value="<?php echo $item['id_producto']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm cantidad" 
                                                   name="cantidades[<?php echo $item['id_producto']; ?>]" 
                                                   value="<?php echo $item['cantidad']; ?>" min="1" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm precio" 
                                                   name="precios[<?php echo $item['id_producto']; ?>]" 
                                                   value="<?php echo $item['precio_estimado'] > 0 ? $item['precio_estimado'] : $item['precio_costo']; ?>" 
                                                   step="0.01" min="0.01" required>
                                        </td>
                                        <td class="subtotal-producto">$ 0,00</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="eliminarProducto(this, '<?php echo $item['id_producto']; ?>')">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr id="empty-row">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i data-feather="inbox" width="48"></i>
                                        <p class="mt-2">No hay productos agregados</p>
                                        <?php if (!$nota_pedido): ?>
                                        <small>Use el botón "Agregar Producto" para comenzar</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="3" class="text-end">Subtotal:</td>
                                    <td id="total-subtotal">$ 0,00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end">IVA (21%):</td>
                                    <td id="total-iva">$ 0,00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary fw-bold">
                                    <td colspan="3" class="text-end">TOTAL:</td>
                                    <td id="total-final" class="fs-5">$ 0,00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
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
                        <dd class="col-sm-5 text-end" id="resumen-productos">0</dd>
                        
                        <dt class="col-sm-7">Total Unidades:</dt>
                        <dd class="col-sm-5 text-end" id="resumen-unidades">0</dd>
                        
                        <dt class="col-12"><hr></dt>
                        
                        <dt class="col-sm-7">Subtotal:</dt>
                        <dd class="col-sm-5 text-end" id="resumen-subtotal">$ 0,00</dd>
                        
                        <dt class="col-sm-7">IVA:</dt>
                        <dd class="col-sm-5 text-end" id="resumen-iva">$ 0,00</dd>
                        
                        <dt class="col-sm-7 fw-bold">Total:</dt>
                        <dd class="col-sm-5 text-end fw-bold text-primary fs-5" id="resumen-total">$ 0,00</dd>
                    </dl>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i data-feather="save"></i> Registrar Compra
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
                            <?php if (!$nota_pedido): ?>
                            <li><strong>Primero seleccione un proveedor</strong></li>
                            <li>Solo se mostrarán productos de ese proveedor</li>
                            <?php endif; ?>
                            <li>Verifique las cantidades y precios</li>
                            <li>Complete los datos del comprobante</li>
                            <li>La compra quedará pendiente de recepción</li>
                        </ul>
                    </small>
                </div>
            </div>
        </div>
    </div>
</form>

<?php if (!$nota_pedido): ?>
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
<?php endif; ?>

<script>
let productosAgregados = <?php echo $nota_pedido ? json_encode(array_column($detalle_nota, 'id_producto')) : '[]'; ?>;
let proveedorSeleccionado = <?php echo $nota_pedido ? $nota_pedido['id_proveedor'] : '0'; ?>;

<?php if (!$nota_pedido): ?>
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

// Abrir modal
document.getElementById('btn-agregar-productos').addEventListener('click', function() {
    if (proveedorSeleccionado > 0) {
        const modal = new bootstrap.Modal(document.getElementById('modalProductos'));
        modal.show();
        
        // Filtrar productos del proveedor
        filtrarProductosPorProveedor();
    }
});

// Filtrar productos por proveedor seleccionado
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
    
    // Mensaje si no hay productos
    const infoDiv = document.getElementById('productos-disponibles-info');
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
<?php endif; ?>

// Agregar producto
document.querySelectorAll('.btn-agregar').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const codigo = this.dataset.codigo;
        const nombre = this.dataset.nombre;
        const precio = parseFloat(this.dataset.precio);
        
        if (productosAgregados.includes(id)) {
            alert('Este producto ya fue agregado');
            return;
        }
        
        productosAgregados.push(id);
        
        const tbody = document.getElementById('productos-seleccionados');
        document.getElementById('empty-row')?.remove();
        
        const tr = document.createElement('tr');
        tr.dataset.id = id;
        tr.innerHTML = `
            <td>
                <strong>${codigo}</strong> - ${nombre}
                <input type="hidden" name="productos[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm cantidad" 
                       name="cantidades[${id}]" value="1" min="1" required>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm precio" 
                       name="precios[${id}]" value="${precio.toFixed(2)}" step="0.01" min="0.01" required>
            </td>
            <td class="subtotal-producto">$ 0,00</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProducto(this, '${id}')">
                    <i data-feather="trash-2"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
        
        // Agregar eventos
        tr.querySelector('.cantidad').addEventListener('input', calcularTotales);
        tr.querySelector('.precio').addEventListener('input', calcularTotales);
        
        calcularTotales();
        
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Cerrar modal
        <?php if (!$nota_pedido): ?>
        bootstrap.Modal.getInstance(document.getElementById('modalProductos')).hide();
        <?php endif; ?>
    });
});

function eliminarProducto(btn, id) {
    if (confirm('¿Eliminar este producto?')) {
        btn.closest('tr').remove();
        productosAgregados = productosAgregados.filter(p => p !== id);
        
        if (productosAgregados.length === 0) {
            document.getElementById('productos-seleccionados').innerHTML = `
                <tr id="empty-row">
                    <td colspan="5" class="text-center text-muted py-4">
                        <i data-feather="inbox" width="48"></i>
                        <p class="mt-2">No hay productos agregados</p>
                        <?php if (!$nota_pedido): ?>
                        <small>Use el botón "Agregar Producto" para comenzar</small>
                        <?php endif; ?>
                    </td>
                </tr>
            `;
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        }
        
        calcularTotales();
    }
}

function calcularTotales() {
    let subtotal = 0;
    let totalProductos = 0;
    let totalUnidades = 0;
    
    document.querySelectorAll('#productos-seleccionados tr[data-id]').forEach(tr => {
        const cantidad = parseFloat(tr.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(tr.querySelector('.precio').value) || 0;
        const subtotalLinea = cantidad * precio;
        
        tr.querySelector('.subtotal-producto').textContent = '$ ' + subtotalLinea.toLocaleString('es-AR', {minimumFractionDigits: 2});
        
        subtotal += subtotalLinea;
        totalProductos++;
        totalUnidades += cantidad;
    });
    
    const iva = subtotal * 0.21;
    const total = subtotal + iva;
    
    // Actualizar tabla
    document.getElementById('total-subtotal').textContent = '$ ' + subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('total-iva').textContent = '$ ' + iva.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('total-final').textContent = '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.addEventListener("DOMContentLoaded", generarNumeroFactura);
    document.querySelector('select[name="tipo_comprobante"]').addEventListener("change", generarNumeroFactura);

    // Actualizar resumen
    document.getElementById('resumen-productos').textContent = totalProductos;
    document.getElementById('resumen-unidades').textContent = totalUnidades;
    document.getElementById('resumen-subtotal').textContent = '$ ' + subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('resumen-iva').textContent = '$ ' + iva.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('resumen-total').textContent = '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
}

// Calcular al cargar
document.addEventListener('DOMContentLoaded', function() {
    // Agregar eventos a productos precargados
    document.querySelectorAll('.cantidad, .precio').forEach(input => {
        input.addEventListener('input', calcularTotales);
    });
    
    calcularTotales();
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
// Generar número de factura automático cuando se selecciona el tipo de comprobante
document.querySelector('select[name="tipo_comprobante"]').addEventListener('change', function() {
    generarNumeroFactura();
});

// También generar al cargar si viene de nota de pedido
<?php if ($nota_pedido): ?>
document.addEventListener('DOMContentLoaded', function() {
    generarNumeroFactura();
});
<?php endif; ?>

function generarNumeroFactura() {
    const tipoComprobante = document.querySelector('select[name="tipo_comprobante"]').value;
    
    if (tipoComprobante && tipoComprobante !== '') {
        // Obtener el último número de compra para generar el correlativo
        fetch('generar_numero_factura.php')
            .then(response => response.json())
            .then(data => {
                if (data.numero) {
                    // Actualizar ambos campos: el visible (display) y el oculto (real)
                    document.getElementById('numero_factura_display').value = data.numero;
                    document.getElementById('numero_factura').value = data.numero;
                }
            })
            .catch(error => {
                console.error('Error al generar número:', error);
            });
    }
}
</script>
<?php include '../../includes/footer.php'; ?>