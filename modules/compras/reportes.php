<?php
// =============================================
// modules/compras/reportes.php
// Reportes y Estadísticas de Compras
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('reportes', 'compras');

$titulo_pagina = 'Reportes de Compras';
$db = getDB();

// Filtros de fecha
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$id_proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;

// Construir filtros
$where = ["c.estado = 'recibida'"]; // Solo compras recibidas
$params = [];

if (!empty($fecha_desde)) {
    $where[] = 'c.fecha_compra >= ?';
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where[] = 'c.fecha_compra <= ?';
    $params[] = $fecha_hasta;
}

if ($id_proveedor > 0) {
    $where[] = 'c.id_proveedor = ?';
    $params[] = $id_proveedor;
}

$where_clause = implode(' AND ', $where);

// ============================================
// KPIs PRINCIPALES
// ============================================
$sql_kpis = "SELECT 
            COUNT(*) as total_compras,
            COUNT(DISTINCT c.id_proveedor) as total_proveedores,
            SUM(c.total) as monto_total,
            AVG(c.total) as ticket_promedio,
            SUM((SELECT SUM(cantidad) FROM compras_detalle WHERE id_compra = c.id_compra)) as total_unidades
            FROM compras c
            WHERE $where_clause";
$kpis = $db->query($sql_kpis, $params)->fetch();

// ============================================
// COMPRAS POR PROVEEDOR
// ============================================
$sql_proveedores = "SELECT 
                    p.nombre_proveedor,
                    COUNT(c.id_compra) as cantidad_compras,
                    SUM(c.total) as monto_total,
                    AVG(c.total) as ticket_promedio
                    FROM compras c
                    INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
                    WHERE $where_clause
                    GROUP BY c.id_proveedor, p.nombre_proveedor
                    ORDER BY monto_total DESC
                    LIMIT 10";
$top_proveedores = $db->query($sql_proveedores, $params)->fetchAll();

// ============================================
// PRODUCTOS MÁS COMPRADOS
// ============================================
$sql_productos = "SELECT 
                 prod.codigo,
                 prod.nombre,
                 SUM(cd.cantidad) as total_cantidad,
                 SUM(cd.subtotal) as monto_total,
                 COUNT(DISTINCT cd.id_compra) as veces_comprado,
                 AVG(cd.precio_unitario) as precio_promedio
                 FROM compras c
                 INNER JOIN compras_detalle cd ON c.id_compra = cd.id_compra
                 INNER JOIN productos prod ON cd.id_producto = prod.id_producto
                 WHERE $where_clause
                 GROUP BY cd.id_producto, prod.codigo, prod.nombre
                 ORDER BY total_cantidad DESC
                 LIMIT 10";
$top_productos = $db->query($sql_productos, $params)->fetchAll();

// ============================================
// COMPRAS POR MES
// ============================================
$sql_mensual = "SELECT 
    DATE_FORMAT(c.fecha_compra, '%Y-%m') AS mes,
    DATE_FORMAT(c.fecha_compra, '%M %Y') AS mes_nombre,
    COUNT(*) AS cantidad,
    SUM(c.total) AS monto
FROM compras c
WHERE $where_clause
GROUP BY 
        DATE_FORMAT(c.fecha_compra, '%Y-%m'),
        DATE_FORMAT(c.fecha_compra, '%M %Y')
    ORDER BY mes DESC
    LIMIT 12";

$datos_mensuales = $db->query($sql_mensual, $params)->fetchAll();

// ============================================
// NOTAS DE PEDIDO - ESTADÍSTICAS
// ============================================
$sql_notas = "SELECT 
             COUNT(*) as total_notas,
             SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
             SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
             SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
             SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
             FROM notas_pedido
             WHERE fecha_solicitud BETWEEN ? AND ?";
$stats_notas = $db->query($sql_notas, [$fecha_desde, $fecha_hasta])->fetch();

// Tasa de conversión
$tasa_conversion = 0;
if ($stats_notas['aprobadas'] > 0) {
    $tasa_conversion = ($stats_notas['convertidas'] / $stats_notas['aprobadas']) * 100;
}

// Proveedores para filtro
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="bar-chart"></i> Reportes de Compras</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Compras</a></li>
            <li class="breadcrumb-item active">Reportes</li>
        </ol>
    </nav>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Proveedor</label>
                <select class="form-select" name="proveedor">
                    <option value="0">Todos</option>
                    <?php foreach ($proveedores as $prov): ?>
                    <option value="<?php echo $prov['id_proveedor']; ?>" 
                            <?php echo $id_proveedor == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i data-feather="search"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs Principales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Compras</h6>
                        <h3 class="mb-0"><?php echo number_format($kpis['total_compras']); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i data-feather="shopping-bag" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Monto Total</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($kpis['monto_total']); ?></h3>
                    </div>
                    <div class="text-success">
                        <i data-feather="dollar-sign" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Ticket Promedio</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($kpis['ticket_promedio']); ?></h3>
                    </div>
                    <div class="text-info">
                        <i data-feather="trending-up" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Unidades</h6>
                        <h3 class="mb-0"><?php echo number_format($kpis['total_unidades']); ?></h3>
                    </div>
                    <div class="text-warning">
                        <i data-feather="package" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas de Notas de Pedido -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i data-feather="file-text"></i> Estadísticas de Notas de Pedido</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <div class="text-center">
                    <h3 class="mb-1"><?php echo number_format($stats_notas['total_notas']); ?></h3>
                    <small class="text-muted">Total Notas</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <h3 class="mb-1 text-warning"><?php echo number_format($stats_notas['pendientes']); ?></h3>
                    <small class="text-muted">Pendientes</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <h3 class="mb-1 text-success"><?php echo number_format($stats_notas['aprobadas']); ?></h3>
                    <small class="text-muted">Aprobadas</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <h3 class="mb-1 text-danger"><?php echo number_format($stats_notas['rechazadas']); ?></h3>
                    <small class="text-muted">Rechazadas</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <h3 class="mb-1 text-info"><?php echo number_format($stats_notas['convertidas']); ?></h3>
                    <small class="text-muted">Convertidas</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <h3 class="mb-1 text-primary"><?php echo number_format($tasa_conversion, 1); ?>%</h3>
                    <small class="text-muted">Tasa Conversión</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Proveedores -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i data-feather="users"></i> Top 10 Proveedores</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Proveedor</th>
                                <th class="text-center">Compras</th>
                                <th class="text-end">Monto Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_proveedores)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    No hay datos disponibles
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($top_proveedores as $prov): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($prov['nombre_proveedor']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Ticket promedio: <?php echo formatearMoneda($prov['ticket_promedio']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $prov['cantidad_compras']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($prov['monto_total']); ?></strong>
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
    
    <!-- Top Productos -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i data-feather="box"></i> Top 10 Productos Comprados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_productos)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    No hay datos disponibles
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($top_productos as $prod): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($prod['codigo']); ?></code>
                                        <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Comprado <?php echo $prod['veces_comprado']; ?> veces - 
                                            Precio promedio: <?php echo formatearMoneda($prod['precio_promedio']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo number_format($prod['total_cantidad']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($prod['monto_total']); ?></strong>
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

<!-- Compras por Mes -->
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i data-feather="calendar"></i> Compras por Mes (Últimos 12 meses)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($datos_mensuales)): ?>
            <p class="text-center text-muted py-4">No hay datos disponibles</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Mes</th>
                        <th class="text-center">Cantidad Compras</th>
                        <th class="text-end">Monto Total</th>
                        <th class="text-end">Promedio</th>
                        <th width="200">Gráfico</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $max_monto = max(array_column($datos_mensuales, 'monto'));
                    foreach ($datos_mensuales as $mes): 
                        $porcentaje = ($mes['monto'] / $max_monto) * 100;
                        $promedio = $mes['cantidad'] > 0 ? $mes['monto'] / $mes['cantidad'] : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo $mes['mes_nombre']; ?></strong></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?php echo number_format($mes['cantidad']); ?></span>
                        </td>
                        <td class="text-end">
                            <strong><?php echo formatearMoneda($mes['monto']); ?></strong>
                        </td>
                        <td class="text-end">
                            <?php echo formatearMoneda($promedio); ?>
                        </td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $porcentaje; ?>%" 
                                     aria-valuenow="<?php echo $porcentaje; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo number_format($porcentaje, 0); ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTALES</td>
                        <td class="text-center">
                            <?php echo number_format(array_sum(array_column($datos_mensuales, 'cantidad'))); ?>
                        </td>
                        <td class="text-end">
                            <?php echo formatearMoneda(array_sum(array_column($datos_mensuales, 'monto'))); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>