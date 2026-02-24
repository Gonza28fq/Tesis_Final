<?php
// =============================================
// modules/clientes/estadisticas.php
// Estadísticas y Reportes de Clientes
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('clientes', 'ver');

$titulo_pagina = 'Estadísticas de Clientes';
$db = getDB();

// Filtros de fecha
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

// ===== ESTADÍSTICAS GENERALES =====
$stats = $db->query("
    SELECT 
        COUNT(CASE WHEN estado = 'activo' THEN 1 END) as total_activos,
        COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as total_inactivos,
        COUNT(*) as total_clientes,
        COUNT(CASE WHEN DATE(fecha_creacion) >= ? THEN 1 END) as nuevos_periodo,
        AVG(limite_credito) as promedio_limite_credito
    FROM clientes
", [$fecha_desde])->fetch();

// ===== DISTRIBUCIÓN POR TIPO DE CLIENTE =====
$tipos_distribucion = $db->query("
    SELECT 
        tc.nombre_tipo,
        tc.codigo_afip,
        COUNT(c.id_cliente) as cantidad,
        ROUND(COUNT(c.id_cliente) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM tipos_cliente tc
    LEFT JOIN clientes c ON tc.id_tipo_cliente = c.id_tipo_cliente
    GROUP BY tc.id_tipo_cliente, tc.nombre_tipo, tc.codigo_afip
    ORDER BY cantidad DESC
")->fetchAll();

// ===== DISTRIBUCIÓN POR LISTA DE PRECIOS =====
$listas_distribucion = $db->query("
    SELECT 
        COALESCE(lp.nombre_lista, 'Sin lista asignada') as nombre_lista,
        COUNT(c.id_cliente) as cantidad,
        ROUND(COUNT(c.id_cliente) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM clientes c
    LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
    GROUP BY c.id_lista_precio, lp.nombre_lista
    ORDER BY cantidad DESC
")->fetchAll();

// ===== TOP 10 CLIENTES POR VENTAS =====
$top_clientes = $db->query("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.documento,
        COUNT(v.id_venta) as total_compras,
        COALESCE(SUM(CASE WHEN v.estado = 'completada' THEN v.total ELSE 0 END), 0) as total_gastado,
        MAX(v.fecha_venta) as ultima_compra
    FROM clientes c
    LEFT JOIN ventas v ON c.id_cliente = v.id_cliente 
        AND v.fecha_venta BETWEEN ? AND ?
    GROUP BY c.id_cliente, c.nombre, c.documento
    HAVING total_compras > 0
    ORDER BY total_gastado DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// ===== CLIENTES SIN COMPRAS =====
$clientes_sin_compras = $db->query("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.email,
        c.telefono,
        c.fecha_creacion,
        DATEDIFF(CURDATE(), c.fecha_creacion) as dias_desde_registro
    FROM clientes c
    LEFT JOIN ventas v ON c.id_cliente = v.id_cliente
    WHERE c.estado = 'activo'
        AND v.id_venta IS NULL
    ORDER BY c.fecha_creacion DESC
    LIMIT 20
")->fetchAll();

// ===== CLIENTES INACTIVOS (sin compras en los últimos 90 días) =====
$clientes_inactivos = $db->query("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.email,
        c.telefono,
        MAX(v.fecha_venta) as ultima_compra,
        DATEDIFF(CURDATE(), MAX(v.fecha_venta)) as dias_inactivo,
        COUNT(v.id_venta) as total_compras_historicas
    FROM clientes c
    INNER JOIN ventas v ON c.id_cliente = v.id_cliente
    WHERE c.estado = 'activo'
    GROUP BY c.id_cliente, c.nombre, c.email, c.telefono
    HAVING dias_inactivo > 90
    ORDER BY dias_inactivo DESC
    LIMIT 20
")->fetchAll();

// ===== DISTRIBUCIÓN GEOGRÁFICA =====
$distribucion_geografica = $db->query("
    SELECT 
        COALESCE(provincia, 'Sin especificar') as provincia,
        COUNT(*) as cantidad,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM clientes
    WHERE estado = 'activo'
    GROUP BY provincia
    ORDER BY cantidad DESC
    LIMIT 10
")->fetchAll();

// ===== EVOLUCIÓN MENSUAL DE NUEVOS CLIENTES (últimos 12 meses) =====
$evolucion_mensual = $db->query("
    SELECT 
        DATE_FORMAT(fecha_creacion, '%Y-%m') AS mes,
        DATE_FORMAT(fecha_creacion, '%b %Y') AS mes_nombre,
        COUNT(*) AS cantidad
    FROM clientes
    WHERE fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m'), DATE_FORMAT(fecha_creacion, '%b %Y')
    ORDER BY mes ASC
")->fetchAll();

// ===== ANÁLISIS DE CONDICIONES IVA =====
$condiciones_iva = $db->query("
    SELECT 
        COALESCE(condicion_iva, 'No especificada') as condicion,
        COUNT(*) as cantidad,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM clientes
    WHERE estado = 'activo'
    GROUP BY condicion_iva
    ORDER BY cantidad DESC
")->fetchAll();

// ===== CLIENTES CON LÍMITE DE CRÉDITO =====
$limite_credito_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN limite_credito > 0 THEN 1 END) as con_limite,
        COUNT(CASE WHEN limite_credito = 0 THEN 1 END) as sin_limite,
        MIN(CASE WHEN limite_credito > 0 THEN limite_credito END) as limite_minimo,
        MAX(limite_credito) as limite_maximo,
        AVG(CASE WHEN limite_credito > 0 THEN limite_credito END) as limite_promedio
    FROM clientes
    WHERE estado = 'activo'
")->fetch();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="bar-chart-2"></i> Estadísticas de Clientes</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
            <li class="breadcrumb-item active">Estadísticas</li>
        </ol>
    </nav>
</div>

<!-- Filtros de Período -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="calendar"></i> Seleccionar Período de Análisis
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i data-feather="refresh-cw"></i> Actualizar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas Generales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Clientes</h6>
                <h2 class="mb-0"><?php echo number_format($stats['total_clientes']); ?></h2>
                <small class="text-success">
                    <i data-feather="trending-up" width="14"></i>
                    <?php echo $stats['nuevos_periodo']; ?> nuevos en el período
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Clientes Activos</h6>
                <h2 class="mb-0 text-success"><?php echo number_format($stats['total_activos']); ?></h2>
                <small class="text-muted">
                    <?php echo round(($stats['total_activos'] / max($stats['total_clientes'], 1)) * 100, 1); ?>% del total
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Clientes Inactivos</h6>
                <h2 class="mb-0 text-warning"><?php echo number_format($stats['total_inactivos']); ?></h2>
                <small class="text-muted">
                    <?php echo round(($stats['total_inactivos'] / max($stats['total_clientes'], 1)) * 100, 1); ?>% del total
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Límite Crédito Promedio</h6>
                <h2 class="mb-0 text-info"><?php echo formatearMoneda($stats['promedio_limite_credito']); ?></h2>
                <small class="text-muted">Por cliente</small>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos de Distribución -->
<div class="row mb-4">
    <!-- Distribución por Tipo de Cliente -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i data-feather="pie-chart"></i> Distribución por Tipo de Cliente
            </div>
            <div class="card-body">
                <canvas id="chartTiposCliente" height="200"></canvas>
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos_distribucion as $tipo): ?>
                            <tr>
                                <td><?php echo $tipo['nombre_tipo']; ?> (<?php echo $tipo['codigo_afip']; ?>)</td>
                                <td><strong><?php echo $tipo['cantidad']; ?></strong></td>
                                <td><?php echo $tipo['porcentaje']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Distribución por Lista de Precios -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i data-feather="pie-chart"></i> Distribución por Lista de Precios
            </div>
            <div class="card-body">
                <canvas id="chartListasPrecios" height="200"></canvas>
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Lista</th>
                                <th>Cantidad</th>
                                <th>Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listas_distribucion as $lista): ?>
                            <tr>
                                <td><?php echo $lista['nombre_lista']; ?></td>
                                <td><strong><?php echo $lista['cantidad']; ?></strong></td>
                                <td><?php echo $lista['porcentaje']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Evolución Mensual -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="trending-up"></i> Evolución de Nuevos Clientes (Últimos 12 Meses)
    </div>
    <div class="card-body">
        <canvas id="chartEvolucionMensual" height="80"></canvas>
    </div>
</div>

<!-- Top 10 Clientes -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="award"></i> Top 10 Clientes por Ventas (Período Seleccionado)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Total Compras</th>
                        <th>Total Gastado</th>
                        <th>Última Compra</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_clientes)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No hay ventas en el período seleccionado</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($top_clientes as $index => $cliente): ?>
                        <tr>
                            <td><strong><?php echo $index + 1; ?></strong></td>
                            <td>
                                <strong><?php echo $cliente['nombre']; ?></strong>
                                <br><small class="text-muted"><?php echo $cliente['documento']; ?></small>
                            </td>
                            <td><span class="badge bg-primary"><?php echo $cliente['total_compras']; ?></span></td>
                            <td><strong class="text-success"><?php echo formatearMoneda($cliente['total_gastado']); ?></strong></td>
                            <td><?php echo formatearFecha($cliente['ultima_compra']); ?></td>
                            <td>
                                <a href="ver.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-info">
                                    <i data-feather="eye"></i> Ver
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

<!-- Distribución Geográfica -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="map"></i> Distribución Geográfica (Top 10 Provincias)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Provincia</th>
                        <th>Cantidad</th>
                        <th>Porcentaje</th>
                        <th width="40%">Distribución</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distribucion_geografica as $geo): ?>
                    <tr>
                        <td><strong><?php echo $geo['provincia']; ?></strong></td>
                        <td><?php echo $geo['cantidad']; ?></td>
                        <td><?php echo $geo['porcentaje']; ?>%</td>
                        <td>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?php echo $geo['porcentaje']; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Alertas y Análisis -->
<div class="row mb-4">
    <!-- Clientes sin Compras -->
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header bg-warning text-white">
                <i data-feather="alert-circle"></i> Clientes Activos Sin Compras
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Días desde Registro</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes_sin_compras)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-3">
                                    <span class="text-success">¡Todos los clientes activos tienen compras!</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($clientes_sin_compras as $cliente): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $cliente['nombre']; ?></strong>
                                        <br><small class="text-muted"><?php echo $cliente['email']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $cliente['dias_desde_registro'] > 30 ? 'danger' : 'warning'; ?>">
                                            <?php echo $cliente['dias_desde_registro']; ?> días
                                        </span>
                                    </td>
                                    <td>
                                        <a href="ver.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-primary">
                                            <i data-feather="eye"></i>
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
    </div>
    
    <!-- Clientes Inactivos (más de 90 días sin comprar) -->
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i data-feather="user-x"></i> Clientes Inactivos (>90 días sin comprar)
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Última Compra</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes_inactivos)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-3">
                                    <span class="text-success">¡Todos los clientes están activos!</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($clientes_inactivos as $cliente): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $cliente['nombre']; ?></strong>
                                        <br><small class="text-muted">Compras históricas: <?php echo $cliente['total_compras_historicas']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo formatearFecha($cliente['ultima_compra']); ?>
                                        <br><span class="badge bg-danger"><?php echo $cliente['dias_inactivo']; ?> días</span>
                                    </td>
                                    <td>
                                        <a href="ver.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-sm btn-primary">
                                            <i data-feather="eye"></i>
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
    </div>
</div>

<!-- Análisis de Límites de Crédito -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="credit-card"></i> Análisis de Límites de Crédito
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h6 class="text-muted">Con Límite Asignado</h6>
                <h3 class="text-success"><?php echo number_format($limite_credito_stats['con_limite']); ?></h3>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Sin Límite Asignado</h6>
                <h3 class="text-warning"><?php echo number_format($limite_credito_stats['sin_limite']); ?></h3>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Límite Promedio</h6>
                <h3 class="text-info"><?php echo formatearMoneda($limite_credito_stats['limite_promedio']); ?></h3>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Límite Máximo</h6>
                <h3 class="text-primary"><?php echo formatearMoneda($limite_credito_stats['limite_maximo']); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Botones de Acción -->
<div class="card">
    <div class="card-body">
        <div class="d-flex gap-2 justify-content-center">
            <a href="index.php" class="btn btn-secondary">
                <i data-feather="arrow-left"></i> Volver a Clientes
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i data-feather="printer"></i> Imprimir Reporte
            </button>
            <button onclick="exportarEstadisticas()" class="btn btn-success">
                <i data-feather="download"></i> Exportar a Excel
            </button>
        </div>
    </div>
</div>

<!-- Estilos para impresión -->
<style>
@media print {
    .btn, .breadcrumb, .sidebar, .navbar, .card-header .dropdown { 
        display: none !important; 
    }
    .card { 
        page-break-inside: avoid; 
        border: 1px solid #ddd !important; 
        margin-bottom: 15px;
    }
    .page-header h1 { 
        font-size: 24px; 
    }
    body {
        font-size: 12px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Paletas de colores
    const coloresPrimarios = [
        'rgba(54, 162, 235, 0.8)',
        'rgba(255, 99, 132, 0.8)',
        'rgba(255, 206, 86, 0.8)',
        'rgba(75, 192, 192, 0.8)',
        'rgba(153, 102, 255, 0.8)',
        'rgba(255, 159, 64, 0.8)',
        'rgba(201, 203, 207, 0.8)'
    ];
    
    const coloresBordes = [
        'rgba(54, 162, 235, 1)',
        'rgba(255, 99, 132, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)',
        'rgba(201, 203, 207, 1)'
    ];

    // Gráfico: Tipos de Cliente
    const ctxTipos = document.getElementById('chartTiposCliente');
    if (ctxTipos) {
        const tiposLabels = <?php echo json_encode(array_column($tipos_distribucion, 'nombre_tipo')); ?>;
        const tiposData = <?php echo json_encode(array_column($tipos_distribucion, 'cantidad')); ?>;
        
        if (tiposLabels.length > 0 && tiposData.length > 0) {
            new Chart(ctxTipos.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: tiposLabels,
                    datasets: [{
                        data: tiposData,
                        backgroundColor: coloresPrimarios,
                        borderColor: coloresBordes,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Gráfico: Evolución Mensual
    const ctxEvolucion = document.getElementById('chartEvolucionMensual');
    if (ctxEvolucion) {
        const evolucionLabels = <?php echo json_encode(array_column($evolucion_mensual, 'mes_nombre')); ?>;
        const evolucionData = <?php echo json_encode(array_column($evolucion_mensual, 'cantidad')); ?>;
        
        if (evolucionLabels.length > 0 && evolucionData.length > 0) {
            new Chart(ctxEvolucion.getContext('2d'), {
                type: 'line',
                data: {
                    labels: evolucionLabels,
                    datasets: [{
                        label: 'Nuevos Clientes',
                        data: evolucionData,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Nuevos clientes: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Cantidad de Clientes'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Mes'
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }
    }

    // Actualizar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

// Función exportar estadísticas
function exportarEstadisticas() {
    const params = new URLSearchParams(window.location.search);
    window.open('exportar_estadisticas.php?' + params.toString(), '_blank');
}
</script>

<?php
include '../../includes/footer.php';
?>
