<?php
// =============================================
// modules/ventas/nueva.php
// Registrar Nueva Venta
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('ventas', 'crear');

$titulo_pagina = 'Nueva Venta';
$db = getDB();
$pdo = $db->getConexion();
$errores = [];

// Obtener punto de venta por defecto (el primero activo)
$punto_venta = $db->query("SELECT * FROM puntos_venta WHERE estado = 'activo' ORDER BY id_punto_venta LIMIT 1")->fetch();

if (!$punto_venta) {
    setAlerta('danger', 'No hay puntos de venta activos');
    redirigir('index.php');
}

// Obtener clientes activos
$clientes = $db->query("SELECT c.*, tc.nombre_tipo as tipo_cliente_nombre, lp.id_lista_precio, lp.nombre_lista
                       FROM clientes c
                       INNER JOIN tipos_cliente tc ON c.id_tipo_cliente = tc.id_tipo_cliente
                       LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
                       WHERE c.estado = 'activo'
                       ORDER BY c.nombre")->fetchAll();

// Obtener productos activos con stock
$productos = $db->query("SELECT p.*, c.nombre_categoria,
                        (SELECT COALESCE(SUM(cantidad), 0) FROM stock WHERE id_producto = p.id_producto) as stock_disponible
                        FROM productos p
                        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
                        WHERE p.estado = 'activo'
                        ORDER BY p.nombre")->fetchAll();

// Obtener tipos de comprobante
$tipos_comprobante = $db->query("SELECT * FROM tipos_comprobante WHERE estado = 'activo' ORDER BY codigo")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = (int)$_POST['id_cliente'];
    $id_tipo_comprobante = (int)$_POST['id_tipo_comprobante'];
    $fecha_venta = $_POST['fecha_venta'];
    $forma_pago = limpiarInput($_POST['forma_pago'] ?? '');
    $observaciones = limpiarInput($_POST['observaciones'] ?? '');
    
    // Productos
    $productos_venta = json_decode($_POST['productos_json'] ?? '[]', true);
    $descuento_general = (float)($_POST['descuento_general'] ?? 0);
    
    // Validaciones
    if ($id_cliente <= 0) {
        $errores[] = 'Debe seleccionar un cliente';
    }
    
    if (empty($forma_pago)) {
    $errores[] = 'Debe seleccionar una forma de pago';
    }
    
    if ($id_tipo_comprobante <= 0) {
        $errores[] = 'Debe seleccionar un tipo de comprobante';
    }
    
    if (empty($fecha_venta)) {
        $errores[] = 'La fecha de venta es obligatoria';
    }
    
    if (empty($productos_venta)) {
        $errores[] = 'Debe agregar al menos un producto';
    }
    
    if (empty($forma_pago)) {
        $errores[] = 'Debe seleccionar una forma de pago';
    }
    // Validar límite de crédito si es cuenta corriente
    if ($forma_pago === 'Cuenta Corriente') {
        $sql_cliente = "SELECT limite_credito, credito_utilizado, nombre 
                        FROM clientes WHERE id_cliente = ?";
        $cliente = $db->query($sql_cliente, [$id_cliente])->fetch();
        
        if ($cliente) {
            // Calcular total de la venta
            $subtotal_temp = 0;
            foreach ($productos_venta as $prod) {
                $subtotal_temp += $prod['subtotal'];
            }
            $descuento_temp = (float)($_POST['descuento_general'] ?? 0);
            $subtotal_con_desc = $subtotal_temp - $descuento_temp;
            $impuestos_temp = $subtotal_con_desc * 0.21;
            $total_venta = $subtotal_con_desc + $impuestos_temp;
            
            // Verificar disponibilidad de crédito
            $credito_disponible = $cliente['limite_credito'] - $cliente['credito_utilizado'];
            
            if ($total_venta > $credito_disponible) {
                $errores[] = "Límite de crédito insuficiente. Disponible: " . 
                            formatearMoneda($credito_disponible) . 
                            " - Requerido: " . formatearMoneda($total_venta);
            }
        }
    }
    // Validar stock disponible
    foreach ($productos_venta as $prod) {
        $sql_stock = "SELECT COALESCE(SUM(cantidad), 0) as stock FROM stock WHERE id_producto = ?";
        $stock = $db->query($sql_stock, [$prod['id_producto']])->fetch();
        
        if ($stock['stock'] < $prod['cantidad']) {
            $errores[] = "Stock insuficiente para producto: {$prod['nombre']}. Disponible: {$stock['stock']}";
        }
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // ============================================
            // 1. GENERAR NÚMERO DE VENTA
            // ============================================
            $sql_ultimo = "SELECT numero_venta FROM ventas ORDER BY id_venta DESC LIMIT 1";
            $ultimo = $db->query($sql_ultimo)->fetch();
            
            if ($ultimo) {
                $ultimo_num = (int)str_replace('VT-', '', $ultimo['numero_venta']);
                $nuevo_num = $ultimo_num + 1;
            } else {
                $nuevo_num = 1;
            }
            $numero_venta = 'VT-' . str_pad($nuevo_num, 6, '0', STR_PAD_LEFT);
            
            // ============================================
            // 2. GENERAR NÚMERO DE COMPROBANTE
            // ============================================
            $sql_ultimo_comp = "SELECT numero_comprobante FROM ventas 
                               WHERE id_punto_venta = ? AND id_tipo_comprobante = ?
                               ORDER BY id_venta DESC LIMIT 1";
            $ultimo_comp = $db->query($sql_ultimo_comp, [$punto_venta['id_punto_venta'], $id_tipo_comprobante])->fetch();
            
            if ($ultimo_comp && $ultimo_comp['numero_comprobante']) {
                $partes = explode('-', $ultimo_comp['numero_comprobante']);
                $ultimo_num_comp = (int)$partes[1];
                $nuevo_num_comp = $ultimo_num_comp + 1;
            } else {
                $nuevo_num_comp = 1;
            }
            
            $numero_comprobante = str_pad($punto_venta['numero_punto'], 4, '0', STR_PAD_LEFT) . '-' . 
                                 str_pad($nuevo_num_comp, 8, '0', STR_PAD_LEFT);
            
            // ============================================
            // 3. CALCULAR TOTALES
            // ============================================
            $subtotal = 0;
            foreach ($productos_venta as $prod) {
                $subtotal += $prod['subtotal'];
            }
            
            $descuento_total = $descuento_general;
            $subtotal_con_descuento = $subtotal - $descuento_total;
            
            // IVA 21%
            $impuestos = $subtotal_con_descuento * 0.21;
            $total = $subtotal_con_descuento + $impuestos;
            
            // ============================================
            // 4. GENERAR CAE (SIMULADO)
            // ============================================
            $cae = date('Ymd') . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT) . '01';
            $vencimiento_cae = date('Y-m-d', strtotime('+10 days'));
            
            // ============================================
            // 5. INSERTAR VENTA
            // ============================================
            $sql = "INSERT INTO ventas 
                    (numero_venta, id_cliente, id_usuario, id_punto_venta, id_tipo_comprobante,
                     numero_comprobante, cae, vencimiento_cae, fecha_venta, subtotal, descuento,
                     impuestos, total, estado, forma_pago, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completada', ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $numero_venta,
                $id_cliente,
                $_SESSION['usuario_id'],
                $punto_venta['id_punto_venta'],
                $id_tipo_comprobante,
                $numero_comprobante,
                $cae,
                $vencimiento_cae,
                $fecha_venta,
                $subtotal,
                $descuento_total,
                $impuestos,
                $total,
                $forma_pago,
                $observaciones
            ]);
            
            $id_venta = $pdo->lastInsertId();
            
            
            // ============================================
            // 6. INSERTAR DETALLE Y ACTUALIZAR STOCK
            // ============================================
            $sql_detalle = "INSERT INTO ventas_detalle 
                           (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal)
                           VALUES (?, ?, ?, ?, ?, ?)";
            
            foreach ($productos_venta as $prod) {
                // Insertar detalle
                $stmt_detalle = $pdo->prepare($sql_detalle);
                $stmt_detalle->execute([
                    $id_venta,
                    $prod['id_producto'],
                    $prod['cantidad'],
                    $prod['precio_unitario'],
                    $prod['descuento'],
                    $prod['subtotal']
                ]);
                
                // Actualizar stock - Buscar ubicación con stock disponible
                $sql_stock_ubicacion = "SELECT id_ubicacion, cantidad 
                                       FROM stock 
                                       WHERE id_producto = ? AND cantidad >= ?
                                       ORDER BY cantidad DESC
                                       LIMIT 1";
                $ubicacion_stock = $db->query($sql_stock_ubicacion, [$prod['id_producto'], $prod['cantidad']])->fetch();
                
                if ($ubicacion_stock) {
                    // Descontar stock
                    $sql_update_stock = "UPDATE stock 
                                        SET cantidad = cantidad - ?
                                        WHERE id_producto = ? AND id_ubicacion = ?";
                    $stmt = $pdo->prepare($sql_update_stock);
                    $stmt->execute([$prod['cantidad'], $prod['id_producto'], $ubicacion_stock['id_ubicacion']]);
                    
                    // Registrar movimiento
                    $sql_movimiento = "INSERT INTO movimientos_stock 
                                      (id_producto, id_ubicacion_origen, tipo_movimiento, cantidad,
                                       motivo, referencia, id_usuario, observaciones)
                                      VALUES (?, ?, 'salida', ?, 'Venta', ?, ?, ?)";
                    $stmt = $pdo->prepare($sql_movimiento);
                    $stmt->execute([
                        $prod['id_producto'],
                        $ubicacion_stock['id_ubicacion'],
                        $prod['cantidad'],
                        $numero_venta,
                        $_SESSION['usuario_id'],
                        "Venta a cliente - {$numero_comprobante}"
                    ]);
                    
                    // Verificar stock mínimo
                    $sql_producto = "SELECT p.nombre, p.stock_minimo,
                                    (SELECT COALESCE(SUM(cantidad), 0) FROM stock WHERE id_producto = p.id_producto) as stock_actual
                                    FROM productos p WHERE id_producto = ?";
                    $prod_info = $db->query($sql_producto, [$prod['id_producto']])->fetch();
                    
                    if ($prod_info['stock_actual'] <= $prod_info['stock_minimo']) {
                        registrarAuditoria(
                            'stock',
                            'alerta_minimo',
                            "Producto bajo stock mínimo: {$prod_info['nombre']} - Stock actual: {$prod_info['stock_actual']} - Mínimo: {$prod_info['stock_minimo']}"
                        );
                    }
                }
            }
            // ============================================
            // 7. ACTUALIZAR CRÉDITO SI ES CUENTA CORRIENTE
            // ============================================
            if ($forma_pago === 'Cuenta Corriente') {
                $sql_update_credito = "UPDATE clientes 
                                    SET credito_utilizado = credito_utilizado + ?
                                    WHERE id_cliente = ?";
                $stmt = $pdo->prepare($sql_update_credito);
                $stmt->execute([$total, $id_cliente]);
                
                registrarAuditoria(
                    'clientes',
                    'credito',
                    "Crédito utilizado actualizado - Cliente ID: $id_cliente - Monto: " . formatearMoneda($total)
                );
            }

            $pdo->commit();
            
            // Registrar auditoría
            registrarAuditoria('ventas', 'crear', "Venta $numero_venta creada - Total: " . formatearMoneda($total));
            
            setAlerta('success', 'Venta registrada exitosamente');
            redirigir('modules/ventas/ver.php?id=' . $id_venta);
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shopping-cart"></i> Nueva Venta</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Ventas</a></li>
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

<form method="POST" action="" id="form-venta">
    <input type="hidden" name="productos_json" id="productos_json">
    
    <div class="row">
        <!-- Columna Principal -->
        <div class="col-lg-8">
            <!-- Información del Cliente -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i data-feather="user"></i> Cliente y Comprobante</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_cliente" id="id_cliente" required>
                                <option value="0">Seleccione un cliente...</option>
                                <?php foreach ($clientes as $cli): ?>
                                <option value="<?php echo $cli['id_cliente']; ?>"
                                        data-tipo-cliente="<?php echo $cli['id_tipo_cliente']; ?>"
                                        data-lista-precio="<?php echo $cli['id_lista_precio']; ?>"
                                        data-cuit="<?php echo $cli['cuit_cuil']; ?>"
                                        data-nombre-lista="<?php echo htmlspecialchars($cli['nombre_lista'] ?? 'Base'); ?>"
                                        data-limite-credito="<?php echo $cli['limite_credito']; ?>"
                                        data-credito-utilizado="<?php echo $cli['credito_utilizado'] ?? 0; ?>">
                                    <?php echo htmlspecialchars($cli['nombre']); ?> - <?php echo $cli['documento']; ?>
                                    (<?php echo $cli['tipo_cliente_nombre']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tipo Comprobante <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_tipo_comprobante" id="id_tipo_comprobante" required>
                                <option value="0">Seleccione...</option>
                                <?php foreach ($tipos_comprobante as $tc): ?>
                                <option value="<?php echo $tc['id_tipo_comprobante']; ?>"
                                        data-codigo="<?php echo $tc['codigo']; ?>"
                                        data-requiere-cuit="<?php echo $tc['requiere_cuit'] ? '1' : '0'; ?>">
                                    <?php echo htmlspecialchars($tc['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Se asigna automáticamente</small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha Venta <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_venta" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0" id="info-cliente" style="display: none;">
                                <small>
                                    <strong>Lista de precios:</strong> <span id="nombre-lista"></span><br>
                                    <strong>Punto de venta:</strong> <?php echo htmlspecialchars($punto_venta['nombre']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Productos -->
            <div class="card">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i data-feather="box"></i> Productos</h5>
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalProductos">
                        <i data-feather="plus-circle"></i> Agregar Producto
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="tabla-productos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th width="80">Stock</th>
                                    <th width="100">Cantidad</th>
                                    <th width="120">Precio Unit.</th>
                                    <th width="100">Desc. %</th>
                                    <th width="120">Subtotal</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="productos-venta">
                                <tr id="empty-row">
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i data-feather="inbox" width="48"></i>
                                        <p class="mt-2">No hay productos agregados</p>
                                        <small>Seleccione un cliente primero</small>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong id="total-subtotal">$ 0,00</strong></td>
                                    <td></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="5" class="text-end">
                                        <strong>Descuento:</strong>
                                        <input type="number" class="form-control form-control-sm d-inline-block ms-2" 
                                               style="width: 100px;" name="descuento_general" id="descuento_general"
                                               value="0" min="0" step="0.01">
                                    </td>
                                    <td><strong id="total-descuento">$ 0,00</strong></td>
                                    <td></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="5" class="text-end">IVA (21%):</td>
                                    <td id="total-iva">$ 0,00</td>
                                    <td></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="5" class="text-end"><strong class="fs-5">TOTAL:</strong></td>
                                    <td><strong class="fs-5 text-primary" id="total-final">$ 0,00</strong></td>
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
            <!-- Forma de Pago -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i data-feather="credit-card"></i> Forma de Pago</h5>
                </div>
                <div class="card-body">
                    <select class="form-select form-select-lg" name="forma_pago" required>
                        <option value="">Seleccione...</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Tarjeta Débito">Tarjeta Débito</option>
                        <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Cuenta Corriente">Cuenta Corriente</option>
                    </select>
                </div>
            </div>
            
            <!-- Resumen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i data-feather="bar-chart-2"></i> Resumen</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7">Total Productos:</dt>
                        <dd class="col-5 text-end" id="resumen-productos">0</dd>
                        
                        <dt class="col-7">Total Unidades:</dt>
                        <dd class="col-5 text-end" id="resumen-unidades">0</dd>
                        
                        <dt class="col-12"><hr></dt>
                        
                        <dt class="col-7">Subtotal:</dt>
                        <dd class="col-5 text-end" id="resumen-subtotal">$ 0,00</dd>
                        
                        <dt class="col-7">Descuento:</dt>
                        <dd class="col-5 text-end" id="resumen-descuento">$ 0,00</dd>
                        
                        <dt class="col-7">IVA:</dt>
                        <dd class="col-5 text-end" id="resumen-iva">$ 0,00</dd>
                        
                        <dt class="col-7 fw-bold fs-5">Total:</dt>
                        <dd class="col-5 text-end fw-bold text-primary fs-5" id="resumen-total">$ 0,00</dd>
                    </dl>
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i data-feather="message-square"></i> Observaciones</h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="observaciones" rows="3" 
                              placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i data-feather="check-circle"></i> Registrar Venta
                        </button>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                    </div>
                    
                    <div class="alert alert-warning mt-3 mb-0">
                        <small>
                            <i data-feather="alert-circle"></i>
                            Al registrar la venta se actualizará el stock automáticamente
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal Agregar Productos -->
<div class="modal fade" id="modalProductos" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seleccionar Productos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="buscar-producto" 
                               placeholder="Buscar por código o nombre...">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="filtro-stock">
                            <option value="">Todos</option>
                            <option value="disponible">Solo con stock</option>
                            <option value="bajo">Stock bajo</option>
                        </select>
                    </div>
                </div>
                
                <div class="alert alert-info" id="alerta-sin-cliente" style="display: none;">
                    <i data-feather="info"></i>
                    Debe seleccionar un cliente primero para ver los precios correctos
                </div>
                
                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table table-hover table-sm">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Stock</th>
                                <th>Precio</th>
                                <th width="80"></th>
                            </tr>
                        </thead>
                        <tbody id="lista-productos">
                            <?php foreach ($productos as $prod): 
                                $sin_stock = $prod['stock_disponible'] == 0;
                                $stock_bajo = $prod['stock_disponible'] > 0 && $prod['stock_disponible'] <= $prod['stock_minimo'];
                            ?>
                            <tr data-id="<?php echo $prod['id_producto']; ?>"
                                data-sin-stock="<?php echo $sin_stock ? '1' : '0'; ?>"
                                data-stock-bajo="<?php echo $stock_bajo ? '1' : '0'; ?>">
                                <td><code><?php echo htmlspecialchars($prod['codigo']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                                    <?php if ($prod['nombre_categoria']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($prod['nombre_categoria']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $sin_stock ? 'danger' : ($stock_bajo ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($prod['stock_disponible']); ?>
                                    </span>
                                </td>
                                <td class="precio-producto" data-precio-base="<?php echo $prod['precio_base']; ?>">
                                    <span class="precio-mostrar">-</span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success btn-agregar"
                                            data-id="<?php echo $prod['id_producto']; ?>"
                                            data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                            data-stock="<?php echo $prod['stock_disponible']; ?>"
                                            data-precio-base="<?php echo $prod['precio_base']; ?>"
                                            <?php echo $sin_stock ? 'disabled' : ''; ?>>
                                        <i class="bi bi-plus-circle"></i>
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
let productosVenta = [];
let listaPrecios = 0;
let clienteSeleccionado = false;

// Cambio de cliente
// Cambio de cliente
document.getElementById('id_cliente').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    
    if (this.value > 0) {
        const tipoCliente = parseInt(option.dataset.tipoCliente);
        listaPrecios = parseInt(option.dataset.listaPrecio) || 0;
        const nombreLista = option.dataset.nombreLista;
        const cuit = option.dataset.cuit;
        
        clienteSeleccionado = true;
        
        // Asignar tipo de comprobante automáticamente
        asignarTipoComprobante(tipoCliente, cuit);
        
        // Mostrar info
        const limiteCredito = parseFloat(option.dataset.limiteCredito) || 0;
        const creditoUtilizado = parseFloat(option.dataset.creditoUtilizado) || 0;
        const creditoDisponible = limiteCredito - creditoUtilizado;

        
        document.getElementById('info-cliente').innerHTML = `
            <small>
                <strong>Lista de precios:</strong> ${nombreLista}<br>
                <strong>Punto de venta:</strong> <?php echo htmlspecialchars($punto_venta['nombre']); ?><br>
                <strong>Límite de crédito:</strong> $ ${limiteCredito.toLocaleString('es-AR', {minimumFractionDigits: 2})}<br>
                <strong>Crédito utilizado:</strong> $ ${creditoUtilizado.toLocaleString('es-AR', {minimumFractionDigits: 2})}<br>
                <strong>Crédito disponible:</strong> <span class="text-${creditoDisponible > 0 ? 'success' : 'danger'}">
                    $ ${creditoDisponible.toLocaleString('es-AR', {minimumFractionDigits: 2})}
                </span>
            </small>
        `;
        document.getElementById('info-cliente').style.display = 'block';
        document.getElementById('alerta-sin-cliente').style.display = 'none';
        
        // Actualizar precios en modal
        actualizarPreciosModal();
        
    } else {
        clienteSeleccionado = false;
        document.getElementById('info-cliente').style.display = 'none';
        document.getElementById('alerta-sin-cliente').style.display = 'block';
    }
});
function asignarTipoComprobante(tipoCliente, cuit) {
    const select = document.getElementById('id_tipo_comprobante');
    
    // Mapeo: 1=CF, 2=RI, 3=MT, 4=EX
    let tipoAsignar = '';
    
    switch(tipoCliente) {
        case 2: // Responsable Inscripto
            tipoAsignar = 'FA'; // Factura A
            if (!cuit) {
                alert('El cliente requiere CUIT para Factura A');
            }
            break;
        case 1: // Consumidor Final
        case 3: // Monotributista
            tipoAsignar = 'FB'; // Factura B
            break;
        case 4: // Exento
            tipoAsignar = 'FC'; // Factura C
            break;
    }
    
    // Seleccionar en el combo
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].dataset.codigo === tipoAsignar) {
            select.selectedIndex = i;
            break;
        }
    }
}

function actualizarPreciosModal() {
    if (!clienteSeleccionado) return;
    
    // Aquí se deberían cargar los precios según la lista del cliente
    // Por simplicidad, usamos el precio base
    document.querySelectorAll('.precio-producto').forEach(td => {
        const precioBase = parseFloat(td.dataset.precioBase);
        td.querySelector('.precio-mostrar').textContent = '$ ' + precioBase.toLocaleString('es-AR', {minimumFractionDigits: 2});
    });
}

// Buscar producto
document.getElementById('buscar-producto').addEventListener('input', function() {
    filtrarProductos();
});

document.getElementById('filtro-stock').addEventListener('change', function() {
    filtrarProductos();
});

function filtrarProductos() {
    const busqueda = document.getElementById('buscar-producto').value.toLowerCase();
    const filtroStock = document.getElementById('filtro-stock').value;
    
    document.querySelectorAll('#lista-productos tr').forEach(tr => {
        const texto = tr.textContent.toLowerCase();
        const cumpleBusqueda = texto.includes(busqueda);
        
        let cumpleFiltro = true;
        if (filtroStock === 'disponible') {
            cumpleFiltro = tr.dataset.sinStock !== '1';
        } else if (filtroStock === 'bajo') {
            cumpleFiltro = tr.dataset.stockBajo === '1';
        }
        
        tr.style.display = (cumpleBusqueda && cumpleFiltro) ? '' : 'none';
    });
}

// Agregar producto
document.querySelectorAll('.btn-agregar').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!clienteSeleccionado) {
            alert('Debe seleccionar un cliente primero');
            return;
        }
        
        const id = this.dataset.id;
        const codigo = this.dataset.codigo;
        const nombre = this.dataset.nombre;
        const stock = parseInt(this.dataset.stock);
        const precioBase = parseFloat(this.dataset.precioBase);
        
        // Verificar si ya está agregado
        if (productosVenta.find(p => p.id_producto === id)) {
            alert('Este producto ya está en la venta');
            return;
        }
        
        // Agregar a array
        const producto = {
            id_producto: id,
            codigo: codigo,
            nombre: nombre,
            stock_disponible: stock,
            cantidad: 1,
            precio_unitario: precioBase,
            descuento: 0,
            subtotal: precioBase
        };
        
        productosVenta.push(producto);
        
        // Agregar a tabla
        agregarProductoATabla(producto);
        
        // Recalcular
        calcularTotales();
        
        // Cerrar modal
        bootstrap.Modal.getInstance(document.getElementById('modalProductos')).hide();
    });
});

function agregarProductoATabla(producto) {
    const tbody = document.getElementById('productos-venta');
    document.getElementById('empty-row')?.remove();
    
    const tr = document.createElement('tr');
    tr.dataset.idProducto = producto.id_producto;
    tr.innerHTML = `
        <td>
            <strong>${producto.codigo}</strong> - ${producto.nombre}
        </td>
        <td>
            <span class="badge bg-${producto.stock_disponible === 0 ? 'danger' : 'secondary'}">
                ${producto.stock_disponible}
            </span>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm cantidad-input" 
                   value="${producto.cantidad}" min="1" max="${producto.stock_disponible}" required>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm precio-input" 
                   value="${producto.precio_unitario.toFixed(2)}" min="0" step="0.01" required>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm descuento-input" 
                   value="${producto.descuento}" min="0" max="100" step="0.01">
        </td>
        <td class="subtotal-producto">
            $ ${producto.subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2})}
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProducto('${producto.id_producto}')">
                <i data-feather="trash-2"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(tr);
    
    // Eventos
    tr.querySelector('.cantidad-input').addEventListener('input', function() {
        actualizarProducto(producto.id_producto);
    });
    
    tr.querySelector('.precio-input').addEventListener('input', function() {
        actualizarProducto(producto.id_producto);
    });
    
    tr.querySelector('.descuento-input').addEventListener('input', function() {
        actualizarProducto(producto.id_producto);
    });
    
    feather.replace();
}

function actualizarProducto(idProducto) {
    const tr = document.querySelector(`tr[data-id-producto="${idProducto}"]`);
    const producto = productosVenta.find(p => p.id_producto === idProducto);
    
    if (!tr || !producto) return;
    
    const cantidad = parseInt(tr.querySelector('.cantidad-input').value) || 0;
    const precio = parseFloat(tr.querySelector('.precio-input').value) || 0;
    const descuentoPorcentaje = parseFloat(tr.querySelector('.descuento-input').value) || 0;
    
    // Validar stock
    if (cantidad > producto.stock_disponible) {
        alert(`Stock insuficiente. Disponible: ${producto.stock_disponible}`);
        tr.querySelector('.cantidad-input').value = producto.stock_disponible;
        return;
    }
    
    // Calcular
    const subtotalSinDesc = cantidad * precio;
    const descuentoMonto = subtotalSinDesc * (descuentoPorcentaje / 100);
    const subtotal = subtotalSinDesc - descuentoMonto;
    
    // Actualizar objeto
    producto.cantidad = cantidad;
    producto.precio_unitario = precio;
    producto.descuento = descuentoMonto;
    producto.subtotal = subtotal;
    
    // Actualizar visual
    tr.querySelector('.subtotal-producto').textContent = '$ ' + subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    
    calcularTotales();
}

function eliminarProducto(idProducto) {
    if (confirm('¿Eliminar este producto?')) {
        // Eliminar de array
        productosVenta = productosVenta.filter(p => p.id_producto !== idProducto);
        
        // Eliminar de tabla
        const tr = document.querySelector(`tr[data-id-producto="${idProducto}"]`);
        tr?.remove();
        
        // Verificar si está vacío
        if (productosVenta.length === 0) {
            document.getElementById('productos-venta').innerHTML = `
                <tr id="empty-row">
                    <td colspan="7" class="text-center text-muted py-4">
                        <i data-feather="inbox" width="48"></i>
                        <p class="mt-2">No hay productos agregados</p>
                        <small>Seleccione un cliente primero</small>
                    </td>
                </tr>
            `;
            feather.replace();
        }
        
        calcularTotales();
    }
}

// Descuento general
document.getElementById('descuento_general').addEventListener('input', function() {
    calcularTotales();
});

function calcularTotales() {
    let subtotal = 0;
    let totalProductos = 0;
    let totalUnidades = 0;
    
    productosVenta.forEach(prod => {
        subtotal += prod.subtotal;
        totalProductos++;
        totalUnidades += prod.cantidad;
    });
    
    const descuentoGeneral = parseFloat(document.getElementById('descuento_general').value) || 0;
    const subtotalConDescuento = subtotal - descuentoGeneral;
    
    // IVA 21%
    const iva = subtotalConDescuento * 0.21;
    const total = subtotalConDescuento + iva;
    
    // Actualizar tabla footer
    document.getElementById('total-subtotal').textContent = '$ ' + subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('total-descuento').textContent = '$ ' + descuentoGeneral.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('total-iva').textContent = '$ ' + iva.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('total-final').textContent = '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
    
    // Actualizar resumen
    document.getElementById('resumen-productos').textContent = totalProductos;
    document.getElementById('resumen-unidades').textContent = totalUnidades;
    document.getElementById('resumen-subtotal').textContent = '$ ' + subtotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('resumen-descuento').textContent = '$ ' + descuentoGeneral.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('resumen-iva').textContent = '$ ' + iva.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('resumen-total').textContent = '$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
}

// Submit form
document.getElementById('form-venta').addEventListener('submit', function(e) {
    e.preventDefault();
// Validar límite de crédito si es cuenta corriente
const formaPago = document.querySelector('select[name="forma_pago"]').value;
if (formaPago === 'Cuenta Corriente') {
    const option = document.querySelector('#id_cliente option:checked');
    const limiteCredito = parseFloat(option.dataset.limiteCredito) || 0;
    const creditoUtilizado = parseFloat(option.dataset.creditoUtilizado) || 0;
    const creditoDisponible = limiteCredito - creditoUtilizado;
    
    // Calcular total
    let subtotal = 0;
    productosVenta.forEach(prod => {
        subtotal += prod.subtotal;
    });
    const descuentoGeneral = parseFloat(document.getElementById('descuento_general').value) || 0;
    const subtotalConDescuento = subtotal - descuentoGeneral;
    const iva = subtotalConDescuento * 0.21;
    const total = subtotalConDescuento + iva;
    
    if (total > creditoDisponible) {
        alert('CRÉDITO INSUFICIENTE\n\n' +
              'Crédito disponible: $ ' + creditoDisponible.toLocaleString('es-AR', {minimumFractionDigits: 2}) + '\n' +
              'Total de la venta: $ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        return;
    }
}
    
    if (productosVenta.length === 0) {
        alert('Debe agregar al menos un producto');
        return;
    }
    
    if (!clienteSeleccionado) {
        alert('Debe seleccionar un cliente');
        return;
    }
    
    // Serializar productos
    document.getElementById('productos_json').value = JSON.stringify(productosVenta);
    
    // Confirmar
    if (confirm('¿Confirmar la venta?\n\nSe actualizará el stock y se generará el comprobante.')) {
        this.submit();
    }
});

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>