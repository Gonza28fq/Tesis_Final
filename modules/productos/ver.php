<?php
// =============================================
// modules/productos/ver.php
// Ver Detalles del Producto
require_once '../../config/constantes.php';      // 1️⃣ Primero
require_once '../../config/conexion.php';        // 2️⃣ Segundo
require_once '../../includes/funciones.php';     // 3️⃣ Tercero

iniciarSesion();  // 4️⃣ Cuarto
requierePermiso('productos', 'ver');

$titulo_pagina = 'Detalles del Producto';
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_producto == 0) {
    setAlerta('error', 'Producto no válido');
    redirigir('index.php');
}

$db = getDB();

// Obtener datos del producto
$sql = "SELECT p.*, 
        c.nombre_categoria,
        prov.nombre_proveedor, prov.telefono as proveedor_telefono, prov.email as proveedor_email
        FROM productos p
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        LEFT JOIN proveedores prov ON p.id_proveedor = prov.id_proveedor
        WHERE p.id_producto = ?";

$stmt = $db->query($sql, [$id_producto]);
$producto = $stmt->fetch();

if (!$producto) {
    setAlerta('error', 'Producto no encontrado');
    redirigir('index.php');
}

// Obtener stock por ubicación
$sql_stock = "SELECT u.nombre_ubicacion, s.cantidad, u.tipo
              FROM stock s
              INNER JOIN ubicaciones u ON s.id_ubicacion = u.id_ubicacion
              WHERE s.id_producto = ?
              ORDER BY u.nombre_ubicacion";
$stock_ubicaciones = $db->query($sql_stock, [$id_producto])->fetchAll();

$stock_total = array_sum(array_column($stock_ubicaciones, 'cantidad'));

// Obtener precios por lista
$sql_precios = "SELECT pp.*, lp.nombre_lista, lp.porcentaje_incremento
                FROM productos_precios pp
                INNER JOIN listas_precios lp ON pp.id_lista_precio = lp.id_lista_precio
                WHERE pp.id_producto = ?
                ORDER BY lp.nombre_lista";
$precios_listas = $db->query($sql_precios, [$id_producto])->fetchAll();

// Obtener últimos movimientos de stock
$sql_movimientos = "SELECT ms.*, u.usuario, 
                    uo.nombre_ubicacion as ubicacion_origen,
                    ud.nombre_ubicacion as ubicacion_destino
                    FROM movimientos_stock ms
                    INNER JOIN usuarios u ON ms.id_usuario = u.id_usuario
                    LEFT JOIN ubicaciones uo ON ms.id_ubicacion_origen = uo.id_ubicacion
                    LEFT JOIN ubicaciones ud ON ms.id_ubicacion_destino = ud.id_ubicacion
                    WHERE ms.id_producto = ?
                    ORDER BY ms.fecha_movimiento DESC
                    LIMIT 10";
$movimientos = $db->query($sql_movimientos, [$id_producto])->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="package"></i> Detalles del Producto</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item active">Ver</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Información General -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i data-feather="info"></i> Información General</span>
                <?php if (tienePermiso('productos', 'editar')): ?>
                <a href="editar.php?id=<?php echo $id_producto; ?>" class="btn btn-sm btn-warning">
                    <i data-feather="edit"></i> Editar
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Código:</strong>
                    </div>
                    <div class="col-md-9">
                        <code class="fs-5"><?php echo $producto['codigo']; ?></code>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Nombre:</strong>
                    </div>
                    <div class="col-md-9">
                        <h5 class="mb-0"><?php echo $producto['nombre']; ?></h5>
                    </div>
                </div>
                
                <?php if (!empty($producto['descripcion'])): ?>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Descripción:</strong>
                    </div>
                    <div class="col-md-9">
                        <?php echo nl2br($producto['descripcion']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Categoría:</strong>
                    </div>
                    <div class="col-md-9">
                        <?php echo $producto['nombre_categoria'] ?? '<span class="text-muted">Sin categoría</span>'; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Proveedor:</strong>
                    </div>
                    <div class="col-md-9">
                        <?php if ($producto['nombre_proveedor']): ?>
                            <?php echo $producto['nombre_proveedor']; ?>
                            <?php if ($producto['proveedor_telefono']): ?>
                                <br><small class="text-muted"><i data-feather="phone" width="14"></i> <?php echo $producto['proveedor_telefono']; ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Sin proveedor</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Unidad de Medida:</strong>
                    </div>
                    <div class="col-md-9">
                        <?php echo ucfirst($producto['unidad_medida']); ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Estado:</strong>
                    </div>
                    <div class="col-md-9">
                        <span class="badge bg-<?php echo $producto['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($producto['estado']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Opciones:</strong>
                    </div>
                    <div class="col-md-9">
                        <?php if ($producto['requiere_serie']): ?>
                        <span class="badge bg-info me-1">Requiere Serie</span>
                        <?php endif; ?>
                        <?php if ($producto['requiere_lote']): ?>
                        <span class="badge bg-info">Requiere Lote</span>
                        <?php endif; ?>
                        <?php if (!$producto['requiere_serie'] && !$producto['requiere_lote']): ?>
                        <span class="text-muted">Sin opciones especiales</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Precios por Lista -->
        <?php if (!empty($precios_listas)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="dollar-sign"></i> Precios por Lista
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Lista de Precios</th>
                                <th>Incremento</th>
                                <th>Precio</th>
                                <th>Vigencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($precios_listas as $precio): ?>
                            <tr>
                                <td><strong><?php echo $precio['nombre_lista']; ?></strong></td>
                                <td>
                                    <?php if ($precio['porcentaje_incremento'] > 0): ?>
                                        <span class="badge bg-success">+<?php echo $precio['porcentaje_incremento']; ?>%</span>
                                    <?php else: ?>
                                        <span class="text-muted">Base</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong class="text-primary"><?php echo formatearMoneda($precio['precio']); ?></strong></td>
                                <td>
                                    <?php if ($precio['fecha_vigencia_desde']): ?>
                                        Desde: <?php echo formatearFecha($precio['fecha_vigencia_desde']); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stock por Ubicación -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="archive"></i> Stock por Ubicación
            </div>
            <div class="card-body">
                <?php if (empty($stock_ubicaciones)): ?>
                    <p class="text-muted text-center py-3">No hay stock registrado</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Ubicación</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stock_ubicaciones as $stock): ?>
                            <tr>
                                <td><?php echo $stock['nombre_ubicacion']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst($stock['tipo']); ?></span></td>
                                <td>
                                    <?php
                                    $clase = 'success';
                                    if ($stock['cantidad'] <= $producto['stock_minimo']) {
                                        $clase = 'danger';
                                    } elseif ($stock['cantidad'] <= $producto['stock_minimo'] * 1.5) {
                                        $clase = 'warning';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $clase; ?>"><?php echo $stock['cantidad']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="2"><strong>TOTAL:</strong></td>
                                <td><strong><?php echo $stock_total; ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Últimos Movimientos -->
        <?php if (!empty($movimientos)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="activity"></i> Últimos Movimientos de Stock
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Motivo</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td><?php echo formatearFechaHora($mov['fecha_movimiento']); ?></td>
                                <td>
                                    <?php
                                    $badges_tipo = [
                                        'entrada' => 'success',
                                        'salida' => 'danger',
                                        'transferencia' => 'info',
                                        'ajuste' => 'warning',
                                        'devolucion' => 'secondary'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $badges_tipo[$mov['tipo_movimiento']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($mov['tipo_movimiento']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $mov['cantidad']; ?></strong></td>
                                <td><?php echo $mov['motivo'] ?? '-'; ?></td>
                                <td><small><?php echo $mov['usuario']; ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?php echo MODULES_URL; ?>stock/movimientos.php?producto=<?php echo $id_producto; ?>" class="btn btn-sm btn-outline-primary">
                    Ver todos los movimientos →
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Panel Lateral Derecho -->
    <div class="col-md-4">
        <!-- Precios -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="dollar-sign"></i> Precios
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted">Precio de Costo</label>
                    <h4 class="mb-0"><?php echo formatearMoneda($producto['precio_costo']); ?></h4>
                </div>
                
                <div class="mb-3">
                    <label class="text-muted">Precio Base</label>
                    <h3 class="mb-0 text-primary"><?php echo formatearMoneda($producto['precio_base']); ?></h3>
                </div>
                
                <?php if ($producto['precio_costo'] > 0): ?>
                <div>
                    <label class="text-muted">Margen de Ganancia</label>
                    <?php
                    $margen = (($producto['precio_base'] - $producto['precio_costo']) / $producto['precio_costo']) * 100;
                    $clase_margen = $margen >= 30 ? 'success' : ($margen >= 15 ? 'warning' : 'danger');
                    ?>
                    <h4 class="mb-0 text-<?php echo $clase_margen; ?>">
                        <?php echo number_format($margen, 2); ?>%
                    </h4>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stock -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="package"></i> Control de Stock
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted">Stock Total</label>
                    <?php
                    $clase_stock = 'success';
                    if ($stock_total <= $producto['stock_minimo']) {
                        $clase_stock = 'danger';
                    } elseif ($stock_total <= $producto['stock_minimo'] * 1.5) {
                        $clase_stock = 'warning';
                    }
                    ?>
                    <h3 class="mb-0 text-<?php echo $clase_stock; ?>"><?php echo $stock_total; ?></h3>
                </div>
                
                <div class="mb-3">
                    <label class="text-muted">Stock Mínimo</label>
                    <h5 class="mb-0"><?php echo $producto['stock_minimo']; ?></h5>
                </div>
                
                <div>
                    <label class="text-muted">Stock Máximo</label>
                    <h5 class="mb-0"><?php echo $producto['stock_maximo']; ?></h5>
                </div>
                
                <?php if ($stock_total <= $producto['stock_minimo']): ?>
                <div class="alert alert-danger mt-3 mb-0">
                    <strong><i data-feather="alert-triangle" width="16"></i> Stock Crítico</strong>
                    <p class="mb-0 mt-1">El stock está por debajo del mínimo establecido.</p>
                </div>
                <?php elseif ($stock_total <= $producto['stock_minimo'] * 1.5): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong><i data-feather="alert-circle" width="16"></i> Stock Bajo</strong>
                    <p class="mb-0 mt-1">El stock está cerca del mínimo establecido.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Auditoría -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="clock"></i> Información de Registro
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">Fecha de Creación</small>
                    <p class="mb-0"><?php echo formatearFechaHora($producto['fecha_creacion']); ?></p>
                </div>
                
                <div>
                    <small class="text-muted">Última Modificación</small>
                    <p class="mb-0"><?php echo formatearFechaHora($producto['fecha_modificacion']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Acciones -->
        <div class="card">
            <div class="card-header">
                <i data-feather="zap"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (tienePermiso('productos', 'editar')): ?>
                    <a href="editar.php?id=<?php echo $id_producto; ?>" class="btn btn-warning">
                        <i data-feather="edit"></i> Editar Producto
                    </a>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('stock', 'ajustar')): ?>
                    <a href="<?php echo MODULES_URL; ?>stock/ajustar.php?producto=<?php echo $id_producto; ?>" class="btn btn-primary">
                        <i data-feather="package"></i> Ajustar Stock
                    </a>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('compras', 'crear')): ?>
                    <a href="<?php echo MODULES_URL; ?>compras/nueva.php?producto=<?php echo $id_producto; ?>" class="btn btn-info">
                        <i data-feather="shopping-bag"></i> Registrar Compra
                    </a>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-secondary">
                        <i data-feather="arrow-left"></i> Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$custom_js = <<<'JS'
<script>
feather.replace();
</script>
JS;

include '../../includes/footer.php';
?>