<?php
// =============================================
// modules/ventas/estadisticas.php
// Sistema de Estadísticas de Ventas
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('ventas', 'ver');

$titulo_pagina = 'Estadísticas de Ventas';
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
        COUNT(*) as total_ventas,
        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as ventas_completadas,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as ventas_pendientes,
        SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as ventas_canceladas,
        SUM(CASE WHEN estado = 'devuelta' THEN 1 ELSE 0 END) as ventas_devueltas,
        COALESCE(SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END), 0) as monto_total,
        COALESCE(AVG(CASE WHEN estado = 'completada' THEN total ELSE NULL END), 0) as ticket_promedio,
        COUNT(DISTINCT id_cliente) as total_clientes,
        COUNT(DISTINCT id_usuario) as total_vendedores
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ?
", [$fecha_desde, $fecha_hasta])->fetch();

// Total de productos vendidos
$total_productos_vendidos = $db->query("
    SELECT COALESCE(SUM(vd.cantidad), 0) as total
    FROM ventas_detalle vd
    INNER JOIN ventas v ON vd.id_venta = v.id_venta
    WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.estado = 'completada'
", [$fecha_desde, $fecha_hasta])->fetch()['total'] ?? 0;

// =============================================
// EVOLUCIÓN DE VENTAS POR DÍA/MES
// =============================================

// Determinar agrupación según el período
$dias_periodo = (strtotime($fecha_hasta) - strtotime($fecha_desde)) / 86400;

if ($dias_periodo <= 31) {
    // Por día si es menos de un mes
    $ventas_evolucion = $db->query("
        SELECT 
            DATE_FORMAT(fecha_venta, '%Y-%m-%d') as periodo,
            DATE_FORMAT(fecha_venta, '%d/%m') as periodo_nombre,
            COUNT(*) as cantidad_ventas,
            SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END) as monto_total
        FROM ventas
        WHERE fecha_venta BETWEEN ? AND ?
        GROUP BY 
            DATE_FORMAT(fecha_venta, '%Y-%m-%d'),
            DATE_FORMAT(fecha_venta, '%d/%m')
        ORDER BY periodo ASC
    ", [$fecha_desde, $fecha_hasta])->fetchAll();
}
 else {
    // Por mes si es más de un mes
    $ventas_evolucion = $db->query("
        SELECT 
            DATE_FORMAT(fecha_venta, '%Y-%m') as periodo,
            DATE_FORMAT(MIN(fecha_venta), '%b %Y') as periodo_nombre,
            COUNT(*) as cantidad_ventas,
            SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END) as monto_total
        FROM ventas
        WHERE fecha_venta BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(fecha_venta, '%Y-%m')
        ORDER BY periodo ASC
    ", [$fecha_desde, $fecha_hasta])->fetchAll();
}

// =============================================
// TOP 10 CLIENTES
// =============================================

$top_clientes = $db->query("
    SELECT 
        c.nombre,
        c.documento,
        tc.nombre_tipo,
        COUNT(v.id_venta) as total_compras,
        SUM(CASE WHEN v.estado = 'completada' THEN v.total ELSE 0 END) as monto_total,
        AVG(CASE WHEN v.estado = 'completada' THEN v.total ELSE NULL END) as ticket_promedio,
        MAX(v.fecha_venta) as ultima_compra
    FROM ventas v
    INNER JOIN clientes c ON v.id_cliente = c.id_cliente
    LEFT JOIN tipos_cliente tc ON c.id_tipo_cliente = tc.id_tipo_cliente
    WHERE v.fecha_venta BETWEEN ? AND ?
    GROUP BY c.id_cliente, c.nombre, c.documento, tc.nombre_tipo
    ORDER BY monto_total DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// TOP 10 PRODUCTOS MÁS VENDIDOS
// =============================================

$top_productos = $db->query("
    SELECT 
        p.codigo,
        p.nombre,
        cat.nombre_categoria,
        SUM(vd.cantidad) as total_vendido,
        COUNT(DISTINCT v.id_venta) as num_ventas,
        SUM(vd.subtotal) as ingresos_totales,
        AVG(vd.precio_unitario) as precio_promedio
    FROM ventas_detalle vd
    INNER JOIN ventas v ON vd.id_venta = v.id_venta
    INNER JOIN productos p ON vd.id_producto = p.id_producto
    LEFT JOIN categorias_producto cat ON p.id_categoria = cat.id_categoria
    WHERE v.fecha_venta BETWEEN ? AND ?
        AND v.estado = 'completada'
    GROUP BY p.id_producto, p.codigo, p.nombre, cat.nombre_categoria
    ORDER BY total_vendido DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// TOP 10 VENDEDORES
// =============================================

$top_vendedores = $db->query("
    SELECT 
        u.nombre_completo,
        u.usuario,
        r.nombre_rol,
        COUNT(v.id_venta) as total_ventas,
        SUM(CASE WHEN v.estado = 'completada' THEN v.total ELSE 0 END) as monto_total,
        AVG(CASE WHEN v.estado = 'completada' THEN v.total ELSE NULL END) as ticket_promedio,
        COUNT(DISTINCT v.id_cliente) as clientes_atendidos
    FROM ventas v
    INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    WHERE v.fecha_venta BETWEEN ? AND ?
    GROUP BY u.id_usuario, u.nombre_completo, u.usuario, r.nombre_rol
    ORDER BY monto_total DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// VENTAS POR ESTADO
// =============================================

$ventas_por_estado = $db->query("
    SELECT 
        estado,
        COUNT(*) as cantidad,
        SUM(total) as monto
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ?
    GROUP BY estado
    ORDER BY cantidad DESC
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// VENTAS POR TIPO DE COMPROBANTE
// =============================================

$ventas_por_comprobante = $db->query("
    SELECT 
        tc.nombre as tipo_comprobante,
        tc.codigo,
        COUNT(v.id_venta) as cantidad,
        SUM(CASE WHEN v.estado = 'completada' THEN v.total ELSE 0 END) as monto
    FROM ventas v
    INNER JOIN tipos_comprobante tc ON v.id_tipo_comprobante = tc.id_tipo_comprobante
    WHERE v.fecha_venta BETWEEN ? AND ?
    GROUP BY tc.id_tipo_comprobante, tc.nombre, tc.codigo
    ORDER BY cantidad DESC
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// VENTAS POR FORMA DE PAGO
// =============================================

$ventas_por_forma_pago = $db->query("
    SELECT 
        COALESCE(forma_pago, 'No especificado') as forma_pago,
        COUNT(*) as cantidad,
        SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END) as monto
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ?
    GROUP BY forma_pago
    ORDER BY cantidad DESC
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// ANÁLISIS DE HORARIOS (Ventas por hora)
// =============================================

$ventas_por_hora = $db->query("
    SELECT 
        HOUR(fecha_creacion) as hora,
        COUNT(*) as cantidad_ventas,
        SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END) as monto_total
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ?
    GROUP BY HOUR(fecha_creacion)
    ORDER BY hora ASC
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// VENTAS POR DÍA DE LA SEMANA
// =============================================

$ventas_por_dia_semana = $db->query("
    SELECT 
        DAYOFWEEK(fecha_venta) as dia_num,
        CASE DAYOFWEEK(fecha_venta)
            WHEN 1 THEN 'Domingo'
            WHEN 2 THEN 'Lunes'
            WHEN 3 THEN 'Martes'
            WHEN 4 THEN 'Miércoles'
            WHEN 5 THEN 'Jueves'
            WHEN 6 THEN 'Viernes'
            WHEN 7 THEN 'Sábado'
        END as dia_nombre,
        COUNT(*) as cantidad_ventas,
        SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END) as monto_total
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ?
    GROUP BY 
        dia_num,
        dia_nombre
    ORDER BY dia_num
", [$fecha_desde, $fecha_hasta])->fetchAll();


// =============================================
// ÚLTIMAS VENTAS COMPLETADAS
// =============================================

$ultimas_ventas = $db->query("
    SELECT 
        v.id_venta,
        v.numero_venta,
        v.fecha_venta,
        c.nombre as cliente,
        v.total,
        u.nombre_completo as vendedor,
        (SELECT COUNT(*) FROM ventas_detalle WHERE id_venta = v.id_venta) as items
    FROM ventas v
    INNER JOIN clientes c ON v.id_cliente = c.id_cliente
    INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
    WHERE v.estado = 'completada'
        AND v.fecha_venta BETWEEN ? AND ?
    ORDER BY v.fecha_venta DESC, v.id_venta DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();


// =============================================
// COMPARACIÓN CON PERÍODO ANTERIOR
// =============================================

$fecha_desde_anterior = date('Y-m-d', strtotime($fecha_desde . " -$dias_periodo days"));
$fecha_hasta_anterior = date('Y-m-d', strtotime($fecha_hasta . " -$dias_periodo days"));

$periodo_anterior = $db->query("
    SELECT 
        COUNT(*) as total_ventas,
        COALESCE(SUM(CASE WHEN estado = 'completada' THEN total ELSE 0 END), 0) as monto_total
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ?
", [$fecha_desde_anterior, $fecha_hasta_anterior])->fetch();

// Calcular variaciones
$variacion_ventas = $periodo_anterior['total_ventas'] > 0 
    ? (($stats_generales['total_ventas'] - $periodo_anterior['total_ventas']) / $periodo_anterior['total_ventas']) * 100 
    : 0;

$variacion_monto = $periodo_anterior['monto_total'] > 0 
    ? (($stats_generales['monto_total'] - $periodo_anterior['monto_total']) / $periodo_anterior['monto_total']) * 100 
    : 0;

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="trending-up"></i> Estadísticas de Ventas</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Ventas</a></li>
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
                        <h6 class="text-muted mb-1">Total Ventas</h6>
                        <h3 class="mb-0"><?php echo number_format($stats_generales['total_ventas']); ?></h3>
                        <small class="<?php echo $variacion_ventas >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <i data-feather="<?php echo $variacion_ventas >= 0 ? 'trending-up' : 'trending-down'; ?>" width="14"></i>
                            <?php echo number_format(abs($variacion_ventas), 1); ?>% vs anterior
                        </small>
                    </div>
                    <div class="text-primary">
                        <i data-feather="shopping-cart" width="36"></i>
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
                        <h6 class="text-muted mb-1">Ingresos Totales</h6>
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
                        <small class="text-muted"><?php echo number_format($stats_generales['total_clientes']); ?> clientes</small>
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
                        <h6 class="text-muted mb-1">Productos Vendidos</h6>
                        <h3 class="mb-0"><?php echo number_format($total_productos_vendidos); ?></h3>
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
                <i data-feather="bar-chart-2"></i> Evolución de Ventas
            </div>
            <div class="card-body">
                <div style="position: relative; height: 350px;">
                    <canvas id="chartEvolucion"></canvas>
                </div>

            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="pie-chart"></i> Ventas por Estado
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="chartEstados"></canvas>
                </div>

                <div class="mt-3">
                    <?php foreach ($ventas_por_estado as $estado): ?>
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

<!-- Análisis por Tiempo -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="clock"></i> Ventas por Hora del Día
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="chartHoras"></canvas>
                </div>

            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="calendar"></i> Ventas por Día de la Semana
            </div>
            <div class="card-body">
                <div style="position: relative; height: 320px;">
                    <canvas id="chartDiasSemana"></canvas>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Top 10 Clientes -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="users"></i> Top 10 Clientes (<?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Documento</th>
                        <th>Tipo</th>
                        <th class="text-end">Compras</th>
                        <th class="text-end">Monto Total</th>
                        <th class="text-end">Ticket Promedio</th>
                        <th>Última Compra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_clientes)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No hay datos de clientes en el período seleccionado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($top_clientes as $index => $cliente): ?>
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
                            <td><strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($cliente['documento']); ?></code></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($cliente['nombre_tipo'] ?? 'N/A'); ?></span></td>
                            <td class="text-end"><?php echo number_format($cliente['total_compras']); ?></td>
                            <td class="text-end text-success"><strong><?php echo formatearMoneda($cliente['monto_total']); ?></strong></td>
                            <td class="text-end"><?php echo formatearMoneda($cliente['ticket_promedio']); ?></td>
                            <td><?php echo formatearFecha($cliente['ultima_compra']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top 10 Productos y Vendedores -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="box"></i> Top 10 Productos Más Vendidos
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Vendidos</th>
                                <th class="text-end">Ingresos</th>
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
                                        <strong><?php echo htmlspecialchars(substr($prod['nombre'], 0, 35)); ?></strong>
                                        <?php if ($prod['nombre_categoria']): ?>
                                        <br><span class="badge bg-info"><?php echo htmlspecialchars($prod['nombre_categoria']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo number_format($prod['total_vendido']); ?></strong>
                                        <br><small class="text-muted"><?php echo $prod['num_ventas']; ?> ventas</small>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatearMoneda($prod['ingresos_totales']); ?></strong>
                                        <br><small class="text-muted">$<?php echo number_format($prod['precio_promedio'], 2); ?> c/u</small>
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

<!-- Análisis por Comprobante y Forma de Pago -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="file-text"></i> Ventas por Tipo de Comprobante
            </div>
            <div class="card-body">
                <div style="position: relative; height: 280px;">
                    <canvas id="chartComprobantes"></canvas>
                </div>

            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i data-feather="credit-card"></i> Ventas por Forma de Pago
            </div>
            <div class="card-body">
                <div style="position: relative; height: 280px;">
                    <canvas id="chartFormasPago"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Últimas Ventas Completadas -->
<div class="card">
    <div class="card-header">
        <i data-feather="check-circle"></i> Últimas Ventas Completadas
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>N° Venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th class="text-end">Items</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimas_ventas)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay ventas completadas en el período
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ultimas_ventas as $venta): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($venta['numero_venta']); ?></code></td>
                            <td><?php echo formatearFecha($venta['fecha_venta']); ?></td>
                            <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                            <td><small><?php echo htmlspecialchars($venta['vendedor']); ?></small></td>
                            <td class="text-end"><span class="badge bg-info"><?php echo $venta['items']; ?></span></td>
                            <td class="text-end"><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                            <td class="text-end">
                                <a href="ver.php?id=<?php echo $venta['id_venta']; ?>" class="btn btn-sm btn-info">
                                    <i data-feather="eye" width="14"></i>
                                </a>
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
                <?php foreach ($ventas_evolucion as $ev): ?>
                '<?php echo $ev['periodo_nombre']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Monto Total',
                data: [
                    <?php foreach ($ventas_evolucion as $ev): ?>
                    <?php echo $ev['monto_total']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Cantidad de Ventas',
                data: [
                    <?php foreach ($ventas_evolucion as $ev): ?>
                    <?php echo $ev['cantidad_ventas']; ?>,
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
                <?php foreach ($ventas_por_estado as $estado): ?>
                '<?php echo ucfirst($estado['estado']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($ventas_por_estado as $estado): ?>
                    <?php echo $estado['cantidad']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
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

// Gráfico de Ventas por Hora
const ctxHoras = document.getElementById('chartHoras');
if (ctxHoras) {
    new Chart(ctxHoras, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($ventas_por_hora as $hora): ?>
                '<?php echo str_pad($hora['hora'], 2, '0', STR_PAD_LEFT); ?>:00',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Cantidad de Ventas',
                data: [
                    <?php foreach ($ventas_por_hora as $hora): ?>
                    <?php echo $hora['cantidad_ventas']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#0d6efd',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad de Ventas'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hora del Día'
                    }
                }
            }
        }
    });
}

// Gráfico de Días de la Semana
const ctxDiasSemana = document.getElementById('chartDiasSemana');
if (ctxDiasSemana) {
    new Chart(ctxDiasSemana, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($ventas_por_dia_semana as $dia): ?>
                '<?php echo $dia['dia_nombre']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Monto Total',
                data: [
                    <?php foreach ($ventas_por_dia_semana as $dia): ?>
                    <?php echo $dia['monto_total']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#28a745',
                borderWidth: 1
            }, {
                label: 'Cantidad',
                data: [
                    <?php foreach ($ventas_por_dia_semana as $dia): ?>
                    <?php echo $dia['cantidad_ventas']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#0d6efd',
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

// Gráfico de Comprobantes
const ctxComprobantes = document.getElementById('chartComprobantes');
if (ctxComprobantes) {
    new Chart(ctxComprobantes, {
        type: 'pie',
        data: {
            labels: [
                <?php foreach ($ventas_por_comprobante as $comp): ?>
                '<?php echo $comp['codigo']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($ventas_por_comprobante as $comp): ?>
                    <?php echo $comp['cantidad']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#0d6efd',
                    '#6c757d',
                    '#ffc107',
                    '#28a745',
                    '#dc3545'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Gráfico de Formas de Pago
const ctxFormasPago = document.getElementById('chartFormasPago');
if (ctxFormasPago) {
    new Chart(ctxFormasPago, {
        type: 'doughnut',
        data: {
            labels: [
                <?php foreach ($ventas_por_forma_pago as $fp): ?>
                '<?php echo $fp['forma_pago']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($ventas_por_forma_pago as $fp): ?>
                    <?php echo $fp['cantidad']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#0d6efd',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
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
