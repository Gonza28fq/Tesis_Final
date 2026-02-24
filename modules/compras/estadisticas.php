<?php
// =============================================
// modules/compras/estadisticas.php
// Sistema de Estadísticas de Compras
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('compras', 'ver');

$titulo_pagina = 'Estadísticas de Compras';
$db = getDB();

// Obtener período seleccionado
$periodo = isset($_GET['periodo']) ? limpiarInput($_GET['periodo']) : 'mes';
$fecha_desde = isset($_GET['fecha_desde']) ? limpiarInput($_GET['fecha_desde']) : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? limpiarInput($_GET['fecha_hasta']) : date('Y-m-d');

// Establecer fechas según período
switch ($periodo) {
    case 'hoy':
        $fecha_desde = $fecha_hasta = date('Y-m-d');
        break;
    case 'semana':
        $fecha_desde = date('Y-m-d', strtotime('-7 days'));
        $fecha_hasta = date('Y-m-d');
        break;
    case 'mes':
        $fecha_desde = date('Y-m-01');
        $fecha_hasta = date('Y-m-d');
        break;
    case 'trimestre':
        $fecha_desde = date('Y-m-d', strtotime('-3 months'));
        $fecha_hasta = date('Y-m-d');
        break;
    case 'anio':
        $fecha_desde = date('Y-01-01');
        $fecha_hasta = date('Y-m-d');
        break;
}

// =============================================
// ESTADÍSTICAS GENERALES
// =============================================

$stats_generales = $db->query("
    SELECT 
        COUNT(*) as total_compras,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as compras_pendientes,
        SUM(CASE WHEN estado = 'recibida' THEN 1 ELSE 0 END) as compras_recibidas,
        SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as compras_canceladas,
        COALESCE(SUM(CASE WHEN estado = 'recibida' THEN total ELSE 0 END), 0) as monto_total,
        COALESCE(AVG(CASE WHEN estado = 'recibida' THEN total ELSE NULL END), 0) as ticket_promedio,
        COUNT(DISTINCT id_proveedor) as total_proveedores
    FROM compras
    WHERE fecha_compra BETWEEN ? AND ?
", [$fecha_desde, $fecha_hasta])->fetch();

// Total de productos comprados
$total_productos_comprados = $db->query("
    SELECT COALESCE(SUM(cd.cantidad), 0) as total
    FROM compras_detalle cd
    INNER JOIN compras c ON cd.id_compra = c.id_compra
    WHERE c.fecha_compra BETWEEN ? AND ?
        AND c.estado = 'recibida'
", [$fecha_desde, $fecha_hasta])->fetch()['total'] ?? 0;

// =============================================
// EVOLUCIÓN DE COMPRAS POR MES
// =============================================

$compras_por_mes = $db->query("
    SELECT 
        DATE_FORMAT(fecha_compra, '%Y-%m') as mes,
        DATE_FORMAT(MIN(fecha_compra), '%b %Y') as mes_nombre,
        COUNT(*) as cantidad_compras,
        SUM(CASE WHEN estado = 'recibida' THEN total ELSE 0 END) as monto_total
    FROM compras
    WHERE fecha_compra BETWEEN DATE_SUB(?, INTERVAL 11 MONTH) AND ?
    GROUP BY DATE_FORMAT(fecha_compra, '%Y-%m')
    ORDER BY mes ASC
", [$fecha_hasta, $fecha_hasta])->fetchAll();

// =============================================
// TOP 10 PROVEEDORES
// =============================================

$top_proveedores = $db->query("
    SELECT 
        p.nombre_proveedor,
        p.cuit,
        COUNT(c.id_compra) as total_compras,
        SUM(CASE WHEN c.estado = 'recibida' THEN c.total ELSE 0 END) as monto_total,
        AVG(CASE WHEN c.estado = 'recibida' THEN c.total ELSE NULL END) as ticket_promedio,
        MAX(c.fecha_compra) as ultima_compra
    FROM compras c
    INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
    WHERE c.fecha_compra BETWEEN ? AND ?
    GROUP BY c.id_proveedor
    ORDER BY monto_total DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// TOP 10 PRODUCTOS MÁS COMPRADOS
// =============================================

$top_productos = $db->query("
    SELECT 
        prod.codigo,
        prod.nombre,
        cat.nombre_categoria,
        SUM(cd.cantidad) as total_comprado,
        COUNT(DISTINCT c.id_compra) as num_compras,
        SUM(cd.subtotal) as monto_total,
        AVG(cd.precio_unitario) as precio_promedio
    FROM compras_detalle cd
    INNER JOIN compras c ON cd.id_compra = c.id_compra
    INNER JOIN productos prod ON cd.id_producto = prod.id_producto
    LEFT JOIN categorias_producto cat ON prod.id_categoria = cat.id_categoria
    WHERE c.fecha_compra BETWEEN ? AND ?
        AND c.estado = 'recibida'
    GROUP BY cd.id_producto
    ORDER BY total_comprado DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// COMPRAS POR ESTADO
// =============================================

$compras_por_estado = $db->query("
    SELECT 
        estado,
        COUNT(*) as cantidad,
        SUM(total) as monto
    FROM compras
    WHERE fecha_compra BETWEEN ? AND ?
    GROUP BY estado
    ORDER BY cantidad DESC
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// ANÁLISIS DE TIEMPO DE PROCESAMIENTO
// =============================================

$tiempo_procesamiento = $db->query("
    SELECT 
        AVG(DATEDIFF(
            COALESCE(
                (SELECT MIN(ms.fecha_movimiento)
                 FROM movimientos_stock ms
                 WHERE ms.referencia = CONCAT('COMPRA-', np.id_nota_pedido)
                 AND ms.tipo_movimiento = 'entrada'),
                np.fecha_aprobacion
            ),
            np.fecha_solicitud
        )) AS dias_promedio
    FROM notas_pedido np
    WHERE np.estado = 'convertida'
      AND np.fecha_solicitud BETWEEN ? AND ?
", [$fecha_desde, $fecha_hasta])->fetch()['dias_promedio'] ?? 0;


// =============================================
// COMPRAS CON Y SIN NOTA DE PEDIDO
// =============================================

$origen_compras = $db->query("
    SELECT 
        CASE 
            WHEN id_nota_pedido IS NOT NULL THEN 'Con Nota de Pedido'
            ELSE 'Compra Directa'
        END as origen,
        COUNT(*) as cantidad,
        SUM(total) as monto
    FROM compras
    WHERE fecha_compra BETWEEN ? AND ?
        AND estado = 'recibida'
    GROUP BY origen
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// NOTAS DE PEDIDO PENDIENTES
// =============================================

$notas_pendientes = $db->query("
    SELECT 
        np.numero_nota,
        np.fecha_solicitud,
        p.nombre_proveedor,
        u.nombre_completo as solicitante,
        (SELECT COUNT(*) FROM notas_pedido_detalle WHERE id_nota_pedido = np.id_nota_pedido) as productos,
        DATEDIFF(CURDATE(), np.fecha_solicitud) as dias_pendiente
    FROM notas_pedido np
    INNER JOIN proveedores p ON np.id_proveedor = p.id_proveedor
    INNER JOIN usuarios u ON np.id_usuario_solicitante = u.id_usuario
    WHERE np.estado IN ('pendiente', 'aprobada')
    ORDER BY np.fecha_solicitud ASC
    LIMIT 10
")->fetchAll();

// =============================================
// ÚLTIMAS COMPRAS RECIBIDAS
// =============================================

$ultimas_compras = $db->query("
    SELECT 
        c.id_compra,
        c.numero_compra,
        c.fecha_compra,
        p.nombre_proveedor,
        c.total,
        (SELECT COUNT(*) FROM compras_detalle WHERE id_compra = c.id_compra) AS productos
    FROM compras c
    INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
    WHERE c.estado = 'recibida'
      AND c.fecha_compra BETWEEN ? AND ?
    ORDER BY c.fecha_compra DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();


// =============================================
// COMPARACIÓN CON PERÍODO ANTERIOR
// =============================================

$dias_periodo = (strtotime($fecha_hasta) - strtotime($fecha_desde)) / 86400;
$fecha_desde_anterior = date('Y-m-d', strtotime($fecha_desde . " -$dias_periodo days"));
$fecha_hasta_anterior = date('Y-m-d', strtotime($fecha_hasta . " -$dias_periodo days"));

$periodo_anterior = $db->query("
    SELECT 
        COUNT(*) as total_compras,
        COALESCE(SUM(CASE WHEN estado = 'recibida' THEN total ELSE 0 END), 0) as monto_total
    FROM compras
    WHERE fecha_compra BETWEEN ? AND ?
", [$fecha_desde_anterior, $fecha_hasta_anterior])->fetch();

// Calcular variaciones
$variacion_compras = $periodo_anterior['total_compras'] > 0 
    ? (($stats_generales['total_compras'] - $periodo_anterior['total_compras']) / $periodo_anterior['total_compras']) * 100 
    : 0;

$variacion_monto = $periodo_anterior['monto_total'] > 0 
    ? (($stats_generales['monto_total'] - $periodo_anterior['monto_total']) / $periodo_anterior['monto_total']) * 100 
    : 0;

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="bar-chart-2"></i> Estadísticas de Compras</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Compras</a></li>
            <li class="breadcrumb-item active">Estadísticas</li>
        </ol>
    </nav>
</div>

<!-- Filtros de Período -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="calendar"></i> Período de Análisis
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Período Predefinido</label>
                <select class="form-select" name="periodo" id="periodo" onchange="toggleFechas()">
                    <option value="hoy" <?php echo $periodo == 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                    <option value="semana" <?php echo $periodo == 'semana' ? 'selected' : ''; ?>>Última Semana</option>
                    <option value="mes" <?php echo $periodo == 'mes' ? 'selected' : ''; ?>>Este Mes</option>
                    <option value="trimestre" <?php echo $periodo == 'trimestre' ? 'selected' : ''; ?>>Último Trimestre</option>
                    <option value="anio" <?php echo $periodo == 'anio' ? 'selected' : ''; ?>>Este Año</option>
                    <option value="personalizado" <?php echo $periodo == 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                </select>
            </div>
            
            <div class="col-md-3" id="campo-desde">
                <label class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
            </div>
            
            <div class="col-md-3" id="campo-hasta">
                <label class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i data-feather="search"></i> Aplicar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas Generales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Compras</h6>
                        <h3 class="mb-0"><?php echo number_format($stats_generales['total_compras']); ?></h3>
                        <small class="<?php echo $variacion_compras >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i data-feather="<?php echo $variacion_compras >= 0 ? 'trending-up' : 'trending-down'; ?>" width="14"></i>
                            <?php echo number_format(abs($variacion_compras), 1); ?>% vs anterior
                        </small>
                    </div>
                    <div class="text-primary">
                        <i data-feather="shopping-bag" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Monto Total</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($stats_generales['monto_total']); ?></h3>
                        <small class="<?php echo $variacion_monto >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i data-feather="<?php echo $variacion_monto >= 0 ? 'trending-up' : 'trending-down'; ?>" width="14"></i>
                            <?php echo number_format(abs($variacion_monto), 1); ?>% vs anterior
                        </small>
                    </div>
                    <div class="text-success">
                        <i data-feather="dollar-sign" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Ticket Promedio</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($stats_generales['ticket_promedio']); ?></h3>
                        <small class="text-muted"><?php echo number_format($stats_generales['total_proveedores']); ?> proveedores</small>
                    </div>
                    <div class="text-info">
                        <i data-feather="trending-up" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Productos Comprados</h6>
                        <h3 class="mb-0"><?php echo number_format($total_productos_comprados); ?></h3>
                        <small class="text-muted">Unidades totales</small>
                    </div>
                    <div class="text-warning">
                        <i data-feather="package" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos de Evolución -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="trending-up"></i> Evolución de Compras (Últimos 12 Meses)
            </div>
            <div class="card-body">
                <div style="position: relative; height: 320px;">
                    <canvas id="chartEvolucion"></canvas>
                </div>
            </div>
        </div>
    </div>

    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="pie-chart"></i> Compras por Estado
            </div>
            <div class="card-body">
                <div style="position: relative; height: 260px;">
                    <canvas id="chartEstados"></canvas>
                </div>

                <div class="mt-3">
                    <?php foreach ($compras_por_estado as $estado): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo ucfirst($estado['estado']); ?>:</span>
                        <strong><?php echo number_format($estado['cantidad']); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Información Adicional -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="git-branch"></i> Origen de Compras
            </div>
            <div style="height: 300px;">
                <canvas id="chartOrigen"></canvas>
            </div>

        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="clock"></i> Tiempo de Procesamiento
            </div>
            <div class="card-body text-center">
                <div class="py-4">
                    <i data-feather="clock" width="48" class="text-info"></i>
                    <h2 class="mt-3"><?php echo number_format($tiempo_procesamiento, 1); ?> días</h2>
                    <p class="text-muted">Promedio desde compra hasta recepción</p>
                </div>
                
                <?php if ($tiempo_procesamiento > 7): ?>
                <div class="alert alert-warning mb-0">
                    <small>
                        <i data-feather="alert-triangle" width="14"></i>
                        El tiempo de procesamiento supera los 7 días recomendados
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top 10 Proveedores -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="award"></i> Top 10 Proveedores (<?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Proveedor</th>
                        <th>CUIT</th>
                        <th class="text-end">Compras</th>
                        <th class="text-end">Monto Total</th>
                        <th class="text-end">Ticket Promedio</th>
                        <th>Última Compra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_proveedores)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay datos de proveedores en el período seleccionado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($top_proveedores as $index => $prov): ?>
                        <tr>
                            <td>
                                <?php if ($index < 3): ?>
                                <span class="badge bg-<?php echo ['warning', 'secondary', 'info'][$index]; ?>">
                                    <?php echo $index + 1; ?>°
                                </span>
                                <?php else: ?>
                                <?php echo $index + 1; ?>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($prov['nombre_proveedor']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($prov['cuit'] ?? 'N/A'); ?></code></td>
                            <td class="text-end"><?php echo number_format($prov['total_compras']); ?></td>
                            <td class="text-end text-success"><strong><?php echo formatearMoneda($prov['monto_total']); ?></strong></td>
                            <td class="text-end"><?php echo formatearMoneda($prov['ticket_promedio']); ?></td>
                            <td><?php echo formatearFecha($prov['ultima_compra']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top 10 Productos y Notas Pendientes -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="box"></i> Top 10 Productos Más Comprados
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_productos)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    No hay datos en el período
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($top_productos as $prod): ?>
                                <tr>
                                    <td>
                                        <small><code><?php echo htmlspecialchars($prod['codigo']); ?></code></small><br>
                                        <strong><?php echo htmlspecialchars(substr($prod['nombre'], 0, 40)); ?></strong>
                                        <?php if ($prod['nombre_categoria']): ?>
                                        <br><span class="badge bg-info"><?php echo htmlspecialchars($prod['nombre_categoria']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo number_format($prod['total_comprado']); ?></strong>
                                        <br><small class="text-muted"><?php echo $prod['num_compras']; ?> compras</small>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($prod['monto_total']); ?></strong>
                                        <br><small class="text-muted">Prom: <?php echo formatearMoneda($prod['precio_promedio']); ?></small>
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
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="file-text"></i> Notas de Pedido Pendientes
                <?php if (count($notas_pendientes) > 0): ?>
                <span class="badge bg-warning"><?php echo count($notas_pendientes); ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <?php if (empty($notas_pendientes)): ?>
                            <tr>
                                <td class="text-center text-muted py-4">
                                    <i data-feather="check-circle" width="32"></i>
                                    <p class="mb-0 mt-2">No hay notas de pedido pendientes</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($notas_pendientes as $nota): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo htmlspecialchars($nota['numero_nota']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($nota['nombre_proveedor']); ?></small>
                                                <br><small><?php echo $nota['productos']; ?> productos</small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $nota['dias_pendiente'] > 7 ? 'danger' : 'warning'; ?>">
                                                    <?php echo $nota['dias_pendiente']; ?> días
                                                </span>
                                                <br><small class="text-muted"><?php echo formatearFecha($nota['fecha_solicitud']); ?></small>
                                            </div>
                                        </div>
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

<!-- Últimas Compras Recibidas -->
<div class="card">
    <div class="card-header">
        <i data-feather="check-circle"></i> Últimas Compras Recibidas
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>N° Compra</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th class="text-end">Productos</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimas_compras)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No hay compras recibidas en el período
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ultimas_compras as $compra): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($compra['numero_compra']); ?></code></td>
                            <td><?php echo formatearFecha($compra['fecha_compra']); ?></td>
                            <td><?php echo htmlspecialchars($compra['nombre_proveedor']); ?></td>
                            <td class="text-end"><?php echo number_format($compra['productos']); ?></td>
                            <td class="text-end"><strong><?php echo formatearMoneda($compra['total']); ?></strong></td>
                            <td class="text-end">
                                <?php if (!empty($compra['id_compra'])): ?>
                                    <a href="ver.php?id=<?php echo intval($compra['id_compra']); ?>" class="btn btn-sm btn-info">
                                        <i data-feather="eye" width="14"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">–</span>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Evolución
const ctxEvolucion = document.getElementById('chartEvolucion');
if (ctxEvolucion) {
    new Chart(ctxEvolucion, {
        type: 'line',
        data: {
            labels: [
                <?php foreach ($compras_por_mes as $mes): ?>
                '<?php echo $mes['mes_nombre']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Monto Total',
                data: [
                    <?php foreach ($compras_por_mes as $mes): ?>
                    <?php echo $mes['monto_total']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Cantidad de Compras',
                data: [
                    <?php foreach ($compras_por_mes as $mes): ?>
                    <?php echo $mes['cantidad_compras']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Monto ($)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Cantidad'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Gráfico de Estados
const ctxEstados = document.getElementById('chartEstados');
if (ctxEstados) {
    new Chart(ctxEstados, {
        type: 'doughnut',
        data: {
            labels: [
                <?php foreach ($compras_por_estado as $estado): ?>
                '<?php echo ucfirst($estado['estado']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($compras_por_estado as $estado): ?>
                    <?php echo $estado['cantidad']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Gráfico de Origen
const ctxOrigen = document.getElementById('chartOrigen');
if (ctxOrigen) {
    new Chart(ctxOrigen, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($origen_compras as $origen): ?>
                '<?php echo $origen['origen']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Cantidad',
                data: [
                    <?php foreach ($origen_compras as $origen): ?>
                    <?php echo $origen['cantidad']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#0d6efd',
                borderWidth: 1
            }, {
                label: 'Monto',
                data: [
                    <?php foreach ($origen_compras as $origen): ?>
                    <?php echo $origen['monto']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#28a745',
                borderWidth: 1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Cantidad'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Monto ($)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Control de campos de fecha
function toggleFechas() {
    const periodo = document.getElementById('periodo').value;
    const campoDesde = document.getElementById('campo-desde');
    const campoHasta = document.getElementById('campo-hasta');
    
    if (periodo === 'personalizado') {
        campoDesde.style.display = 'block';
        campoHasta.style.display = 'block';
    } else {
        campoDesde.style.display = 'none';
        campoHasta.style.display = 'none';
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    toggleFechas();
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>