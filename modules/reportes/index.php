<?php
// =============================================
// modules/reportes/index.php
// Dashboard Ejecutivo SIMPLIFICADO
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

$titulo_pagina = 'Dashboard Ejecutivo';
$db = getDB();

// Período seleccionado
$periodo = isset($_GET['periodo']) ? limpiarInput($_GET['periodo']) : 'mes';

// Calcular fechas según período
switch ($periodo) {
    case 'hoy':
        $fecha_desde = date('Y-m-d');
        $fecha_hasta = date('Y-m-d');
        $periodo_texto = 'Hoy';
        break;
    case 'semana':
        $fecha_desde = date('Y-m-d', strtotime('-7 days'));
        $fecha_hasta = date('Y-m-d');
        $periodo_texto = 'Última Semana';
        break;
    case 'mes':
        $fecha_desde = date('Y-m-01');
        $fecha_hasta = date('Y-m-d');
        $periodo_texto = 'Este Mes';
        break;
    case 'trimestre':
        $fecha_desde = date('Y-m-d', strtotime('-3 months'));
        $fecha_hasta = date('Y-m-d');
        $periodo_texto = 'Último Trimestre';
        break;
    case 'anio':
        $fecha_desde = date('Y-01-01');
        $fecha_hasta = date('Y-m-d');
        $periodo_texto = 'Este Año';
        break;
    default:
        $fecha_desde = date('Y-m-01');
        $fecha_hasta = date('Y-m-d');
        $periodo_texto = 'Este Mes';
}

// ============================================
// ESTADÍSTICAS GENERALES DE VENTAS
// ============================================
$sql_ventas = "SELECT 
    COUNT(*) as total_ventas,
    COALESCE(SUM(total), 0) as monto_ventas,
    COALESCE(AVG(total), 0) as ticket_promedio,
    COALESCE(SUM(descuento), 0) as total_descuentos,
    COALESCE(SUM(impuestos), 0) as total_impuestos
    FROM ventas 
    WHERE fecha_venta BETWEEN ? AND ? AND estado = 'completada'";
$stats_ventas = $db->query($sql_ventas, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);

// ============================================
// ESTADÍSTICAS DE COMPRAS
// ============================================
$sql_compras = "SELECT 
    COUNT(*) as total_compras,
    COALESCE(SUM(total), 0) as monto_compras,
    COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as compras_pendientes
    FROM compras 
    WHERE fecha_compra BETWEEN ? AND ?";
$stats_compras = $db->query($sql_compras, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);

// ============================================
// UTILIDAD BRUTA
// ============================================
$sql_utilidad = "SELECT 
    COALESCE(SUM(vd.cantidad * p.precio_costo), 0) as costo_total
    FROM ventas_detalle vd
    INNER JOIN ventas v ON vd.id_venta = v.id_venta
    INNER JOIN productos p ON vd.id_producto = p.id_producto
    WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado = 'completada'";
$costo_ventas = $db->query($sql_utilidad, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);
$utilidad_bruta = $stats_ventas['monto_ventas'] - $costo_ventas['costo_total'];
$margen_ganancia = $stats_ventas['monto_ventas'] > 0 ? ($utilidad_bruta / $stats_ventas['monto_ventas']) * 100 : 0;

// ============================================
// TOP 5 PRODUCTOS MÁS VENDIDOS
// ============================================
$sql_top_productos = "SELECT 
    p.codigo, 
    p.nombre, 
    SUM(vd.cantidad) as cantidad_vendida, 
    COALESCE(SUM(vd.subtotal), 0) as total_vendido
    FROM ventas_detalle vd
    INNER JOIN ventas v ON vd.id_venta = v.id_venta
    INNER JOIN productos p ON vd.id_producto = p.id_producto
    WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado = 'completada'
    GROUP BY vd.id_producto, p.codigo, p.nombre
    ORDER BY cantidad_vendida DESC
    LIMIT 5";
$top_productos = $db->query($sql_top_productos, [$fecha_desde, $fecha_hasta])->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// TOP 5 CLIENTES
// ============================================
$sql_top_clientes = "SELECT 
    c.nombre, 
    c.documento,
    COUNT(v.id_venta) as total_compras, 
    COALESCE(SUM(v.total), 0) as total_gastado
    FROM ventas v
    INNER JOIN clientes c ON v.id_cliente = c.id_cliente
    WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado = 'completada'
    GROUP BY v.id_cliente, c.nombre, c.documento
    ORDER BY total_gastado DESC
    LIMIT 5";
$top_clientes = $db->query($sql_top_clientes, [$fecha_desde, $fecha_hasta])->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// VENTAS POR VENDEDOR
// ============================================
$sql_vendedores = "SELECT 
    u.nombre_completo, 
    COUNT(v.id_venta) as total_ventas, 
    COALESCE(SUM(v.total), 0) as total_vendido
    FROM ventas v
    INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
    WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado = 'completada'
    GROUP BY v.id_usuario, u.nombre_completo
    ORDER BY total_vendido DESC";
$ventas_vendedores = $db->query($sql_vendedores, [$fecha_desde, $fecha_hasta])->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// STOCK CRÍTICO
// ============================================
$sql_stock_critico = "SELECT 
    p.codigo, 
    p.nombre, 
    p.stock_minimo,
    COALESCE(SUM(s.cantidad), 0) as stock_actual
    FROM productos p
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    WHERE p.estado = 'activo'
    GROUP BY p.id_producto, p.codigo, p.nombre, p.stock_minimo
    HAVING stock_actual <= p.stock_minimo
    ORDER BY stock_actual ASC
    LIMIT 5";
$stock_critico = $db->query($sql_stock_critico)->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// NOTAS DE PEDIDO PENDIENTES
// ============================================
$sql_notas_pendientes = "SELECT COUNT(*) as total FROM notas_pedido WHERE estado = 'pendiente'";
$notas_pendientes = $db->query($sql_notas_pendientes)->fetch(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="bar-chart-2"></i> Dashboard Ejecutivo</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
</div>

<!-- Selector de Período -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="btn-group mb-2" role="group">
                <a href="?periodo=hoy" class="btn btn-<?php echo $periodo == 'hoy' ? 'primary' : 'outline-primary'; ?>">
                    <i data-feather="calendar"></i> Hoy
                </a>
                <a href="?periodo=semana" class="btn btn-<?php echo $periodo == 'semana' ? 'primary' : 'outline-primary'; ?>">
                    Semana
                </a>
                <a href="?periodo=mes" class="btn btn-<?php echo $periodo == 'mes' ? 'primary' : 'outline-primary'; ?>">
                    Mes
                </a>
                <a href="?periodo=trimestre" class="btn btn-<?php echo $periodo == 'trimestre' ? 'primary' : 'outline-primary'; ?>">
                    Trimestre
                </a>
                <a href="?periodo=anio" class="btn btn-<?php echo $periodo == 'anio' ? 'primary' : 'outline-primary'; ?>">
                    Año
                </a>
            </div>
            
            <div class="mb-2">
                <span class="badge bg-info fs-6">
                    <i data-feather="calendar"></i>
                    <?php echo $periodo_texto; ?>: <?php echo formatearFecha($fecha_desde); ?> al <?php echo formatearFecha($fecha_hasta); ?>
                </span>
            </div>
            
            <div class="btn-group mb-2">
                <a href="financiero.php" class="btn btn-outline-success">
                    <i data-feather="dollar-sign"></i> Rep. Financiero
                </a>
                <a href="stock.php" class="btn btn-outline-info">
                    <i data-feather="package"></i> Rep. Stock
                </a>
                <a href="programar.php" class="btn btn-outline-primary">
                    <i data-feather="package"></i> Programar
                </a>
                <a href="enviar_reportes.php" class="btn btn-outline-secondary">
                    <i data-feather="package"></i> Enviar Reporte
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alertas de Stock Crítico -->
<?php if (count($stock_critico) > 0): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <strong><i data-feather="alert-triangle"></i> Alerta de Stock:</strong>
    Hay <?php echo count($stock_critico); ?> producto(s) bajo stock mínimo.
    <a href="stock.php" class="alert-link">Ver detalles</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- KPIs Principales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Ventas Realizadas</h6>
                        <h2 class="mb-0"><?php echo number_format($stats_ventas['total_ventas']); ?></h2>
                        <small class="text-muted">Ticket: <?php echo formatearMoneda($stats_ventas['ticket_promedio']); ?></small>
                    </div>
                    <div class="text-primary">
                        <i data-feather="shopping-cart" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Monto Vendido</h6>
                        <h2 class="mb-0 text-success"><?php echo formatearMoneda($stats_ventas['monto_ventas']); ?></h2>
                        <small class="text-muted">IVA: <?php echo formatearMoneda($stats_ventas['total_impuestos']); ?></small>
                    </div>
                    <div class="text-success">
                        <i data-feather="dollar-sign" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Utilidad Bruta</h6>
                        <h2 class="mb-0 text-info"><?php echo formatearMoneda($utilidad_bruta); ?></h2>
                        <small class="text-muted">Margen: <?php echo number_format($margen_ganancia, 1); ?>%</small>
                    </div>
                    <div class="text-info">
                        <i data-feather="trending-up" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Compras</h6>
                        <h2 class="mb-0 text-warning"><?php echo formatearMoneda($stats_compras['monto_compras']); ?></h2>
                        <small class="text-muted">Pendientes: <?php echo $stats_compras['compras_pendientes']; ?></small>
                    </div>
                    <div class="text-warning">
                        <i data-feather="shopping-bag" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top 5 y Estadísticas -->
<div class="row mb-4">
    <!-- Top 5 Productos -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white">
                <i data-feather="package"></i> Top 5 Productos Más Vendidos
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_productos)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <i data-feather="inbox"></i>
                                    <p>No hay datos</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $pos = 1; foreach ($top_productos as $prod): ?>
                                <tr>
                                    <td><strong><?php echo $pos++; ?></strong></td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($prod['codigo']); ?></small><br>
                                        <?php echo htmlspecialchars(substr($prod['nombre'], 0, 30)); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo number_format($prod['cantidad_vendida']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($prod['total_vendido']); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top 5 Clientes -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <i data-feather="users"></i> Top 5 Mejores Clientes
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th class="text-center">Compras</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_clientes)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">
                                    <i data-feather="inbox"></i>
                                    <p>No hay datos</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $pos = 1; foreach ($top_clientes as $cliente): ?>
                                <tr>
                                    <td><strong><?php echo $pos++; ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($cliente['nombre'], 0, 25)); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($cliente['documento']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $cliente['total_compras']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($cliente['total_gastado']); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ventas por Vendedor -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <i data-feather="user-check"></i> Rendimiento por Vendedor
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Vendedor</th>
                                <th class="text-center">Ventas</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventas_vendedores)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    <i data-feather="inbox"></i>
                                    <p>No hay datos</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($ventas_vendedores as $vendedor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vendedor['nombre_completo']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $vendedor['total_ventas']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($vendedor['total_vendido']); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alertas de Gestión -->
<div class="row">
    <!-- Stock Crítico -->
    <div class="col-lg-6 mb-4">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i data-feather="alert-triangle"></i> Stock Crítico (Bajo Mínimo)
            </div>
            <div class="card-body">
                <?php if (empty($stock_critico)): ?>
                    <div class="text-center text-success py-3">
                        <i data-feather="check-circle" width="48"></i>
                        <p class="mb-0">Todo el stock está en niveles óptimos</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Actual</th>
                                    <th class="text-center">Mínimo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock_critico as $item): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['codigo']); ?></small><br>
                                        <?php echo htmlspecialchars($item['nombre']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?php echo $item['stock_actual']; ?></span>
                                    </td>
                                    <td class="text-center"><?php echo $item['stock_minimo']; ?></td>
                                    <td>
                                        <span class="badge bg-warning">Reponer</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Pendientes de Gestión -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i data-feather="clipboard"></i> Pendientes de Gestión
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="<?php echo MODULES_URL; ?>compras/notas_pedido.php?estado=pendiente" 
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i data-feather="file-text"></i>
                            <strong>Notas de Pedido Pendientes</strong>
                        </div>
                        <span class="badge bg-warning rounded-pill"><?php echo $notas_pendientes['total']; ?></span>
                    </a>
                    
                    <a href="<?php echo MODULES_URL; ?>compras/index.php?estado=pendiente" 
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i data-feather="shopping-bag"></i>
                            <strong>Compras Pendientes de Recepción</strong>
                        </div>
                        <span class="badge bg-warning rounded-pill"><?php echo $stats_compras['compras_pendientes']; ?></span>
                    </a>
                    
                    <a href="stock.php" 
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i data-feather="package"></i>
                            <strong>Productos Bajo Stock Mínimo</strong>
                        </div>
                        <span class="badge bg-danger rounded-pill"><?php echo count($stock_critico); ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
feather.replace();
</script>

<?php include '../../includes/footer.php'; ?>