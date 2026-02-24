<?php
// =============================================
// modules/stock/reportes.php
// Reporte de Stock e Inventario
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('reportes', 'stock');

$titulo_pagina = 'Reporte de Stock';
$db = getDB();

// Parámetros
$ubicacion = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'inventario';

// ============================================
// ESTADÍSTICAS GENERALES
// ============================================
$sql_totales = "SELECT 
    COUNT(DISTINCT p.id_producto) as total_productos,
    COALESCE(SUM(s.cantidad), 0) as total_unidades,
    COALESCE(SUM(s.cantidad * p.precio_costo), 0) as valor_inventario,
    COUNT(DISTINCT CASE WHEN s.cantidad <= p.stock_minimo THEN p.id_producto END) as productos_bajo_minimo,
    COUNT(DISTINCT CASE WHEN s.cantidad = 0 THEN p.id_producto END) as productos_sin_stock
    FROM productos p
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    WHERE p.estado = 'activo'";
$totales = $db->query($sql_totales)->fetch();

// ============================================
// INVENTARIO ACTUAL POR PRODUCTO Y UBICACIÓN
// ============================================
$where = ['p.estado = "activo"'];
$params = [];

if ($ubicacion > 0) {
    $where[] = 's.id_ubicacion = ?';
    $params[] = $ubicacion;
}

if ($categoria > 0) {
    $where[] = 'p.id_categoria = ?';
    $params[] = $categoria;
}

$where_clause = implode(' AND ', $where);

$sql_inventario = "SELECT 
    p.codigo, p.nombre, p.unidad_medida,
    c.nombre_categoria,
    p.precio_costo, p.precio_base,
    p.stock_minimo, p.stock_maximo,
    u.nombre_ubicacion,
    COALESCE(s.cantidad, 0) as stock_actual,
    (COALESCE(s.cantidad, 0) * p.precio_costo) as valor_stock,
    CASE 
        WHEN COALESCE(s.cantidad, 0) = 0 THEN 'sin_stock'
        WHEN COALESCE(s.cantidad, 0) <= p.stock_minimo THEN 'bajo'
        WHEN COALESCE(s.cantidad, 0) >= p.stock_maximo THEN 'alto'
        ELSE 'normal'
    END as estado_stock
    FROM productos p
    LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    LEFT JOIN ubicaciones u ON s.id_ubicacion = u.id_ubicacion
    WHERE $where_clause
    ORDER BY p.nombre";

$inventario = $db->query($sql_inventario, $params)->fetchAll();

// ============================================
// PRODUCTOS SIN MOVIMIENTO (últimos 90 días)
// ============================================
$sql_sin_movimiento = "SELECT 
    p.codigo, p.nombre,
    COALESCE(SUM(s.cantidad), 0) as stock_actual,
    MAX(ms.fecha_movimiento) as ultimo_movimiento,
    DATEDIFF(CURRENT_DATE, MAX(ms.fecha_movimiento)) as dias_sin_movimiento
    FROM productos p
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    LEFT JOIN movimientos_stock ms ON p.id_producto = ms.id_producto
    WHERE p.estado = 'activo'
    GROUP BY p.id_producto, p.codigo, p.nombre
    HAVING ultimo_movimiento IS NULL OR dias_sin_movimiento > 90
    ORDER BY dias_sin_movimiento DESC
    LIMIT 10";
$sin_movimiento = $db->query($sql_sin_movimiento)->fetchAll();

// ============================================
// MOVIMIENTOS DE STOCK (últimos 30 días)
// ============================================
$sql_movimientos = "SELECT 
    DATE(ms.fecha_movimiento) as fecha,
    ms.tipo_movimiento,
    COUNT(*) as cantidad_movimientos,
    SUM(ms.cantidad) as total_unidades
    FROM movimientos_stock ms
    WHERE ms.fecha_movimiento >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY DATE(ms.fecha_movimiento), ms.tipo_movimiento
    ORDER BY fecha DESC";
$movimientos_resumen = $db->query($sql_movimientos)->fetchAll();

// ============================================
// VALORIZACIÓN POR CATEGORÍA
// ============================================
$sql_valorizacion = "SELECT 
    COALESCE(c.nombre_categoria, 'Sin Categoría') as categoria,
    COUNT(DISTINCT p.id_producto) as cantidad_productos,
    COALESCE(SUM(s.cantidad), 0) as total_unidades,
    COALESCE(SUM(s.cantidad * p.precio_costo), 0) as valor_costo,
    COALESCE(SUM(s.cantidad * p.precio_base), 0) as valor_venta
    FROM productos p
    LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    WHERE p.estado = 'activo'
    GROUP BY c.id_categoria, c.nombre_categoria
    ORDER BY valor_costo DESC";
$valorizacion = $db->query($sql_valorizacion)->fetchAll();

// ============================================
// ROTACIÓN DE INVENTARIO (últimos 3 meses)
// ============================================
$sql_rotacion = "SELECT 
    p.codigo, p.nombre,
    COALESCE(SUM(s.cantidad), 0) as stock_promedio,
    COALESCE(SUM(vd.cantidad), 0) as unidades_vendidas,
    CASE 
        WHEN COALESCE(SUM(s.cantidad), 0) > 0 
        THEN COALESCE(SUM(vd.cantidad), 0) / COALESCE(SUM(s.cantidad), 1)
        ELSE 0 
    END as indice_rotacion
    FROM productos p
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    LEFT JOIN ventas_detalle vd ON p.id_producto = vd.id_producto
    LEFT JOIN ventas v ON vd.id_venta = v.id_venta 
        AND v.fecha_venta >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
        AND v.estado = 'completada'
    WHERE p.estado = 'activo'
    GROUP BY p.id_producto, p.codigo, p.nombre
    HAVING stock_promedio > 0
    ORDER BY indice_rotacion DESC
    LIMIT 10";
$rotacion = $db->query($sql_rotacion)->fetchAll();

// Ubicaciones para filtro
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE estado = 'activo' ORDER BY nombre_ubicacion")->fetchAll();

// Categorías para filtro
$categorias = $db->query("SELECT * FROM categorias_producto WHERE estado = 'activa' ORDER BY nombre_categoria")->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="package"></i> Reporte de Stock e Inventario</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Reportes</a></li>
            <li class="breadcrumb-item active">Stock</li>
        </ol>
    </nav>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tipo de Reporte</label>
                <select class="form-select" name="tipo">
                    <option value="inventario" <?php echo $tipo_reporte == 'inventario' ? 'selected' : ''; ?>>Inventario Actual</option>
                    <option value="movimientos" <?php echo $tipo_reporte == 'movimientos' ? 'selected' : ''; ?>>Movimientos</option>
                    <option value="valorizacion" <?php echo $tipo_reporte == 'valorizacion' ? 'selected' : ''; ?>>Valorización</option>
                    <option value="rotacion" <?php echo $tipo_reporte == 'rotacion' ? 'selected' : ''; ?>>Rotación</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Ubicación</label>
                <select class="form-select" name="ubicacion">
                    <option value="0">Todas</option>
                    <?php foreach ($ubicaciones as $ubi): ?>
                    <option value="<?php echo $ubi['id_ubicacion']; ?>" <?php echo $ubicacion == $ubi['id_ubicacion'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ubi['nombre_ubicacion']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="categoria">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="search"></i> Filtrar
                </button>
               <!-- <button type="button" class="btn btn-success" onclick="exportarPDF()">
                    <i data-feather="download"></i> Exportar
                </button>-->
            </div>
        </form>
    </div>
</div>

<!-- KPIs de Stock -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Productos</h6>
                        <h2 class="mb-0"><?php echo number_format($totales['total_productos']); ?></h2>
                        <small class="text-muted">En inventario</small>
                    </div>
                    <div class="text-primary">
                        <i data-feather="box" width="36"></i>
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
                        <h6 class="text-muted mb-1">Valor Inventario</h6>
                        <h2 class="mb-0 text-success"><?php echo formatearMoneda($totales['valor_inventario']); ?></h2>
                        <small class="text-muted">A precio costo</small>
                    </div>
                    <div class="text-success">
                        <i data-feather="dollar-sign" width="36"></i>
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
                        <h6 class="text-muted mb-1">Stock Bajo Mínimo</h6>
                        <h2 class="mb-0 text-warning"><?php echo number_format($totales['productos_bajo_minimo']); ?></h2>
                        <small class="text-muted">Productos</small>
                    </div>
                    <div class="text-warning">
                        <i data-feather="alert-triangle" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Sin Stock</h6>
                        <h2 class="mb-0 text-danger"><?php echo number_format($totales['productos_sin_stock']); ?></h2>
                        <small class="text-muted">Productos</small>
                    </div>
                    <div class="text-danger">
                        <i data-feather="x-circle" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenido según tipo de reporte -->
<?php if ($tipo_reporte == 'inventario'): ?>
    <!-- Inventario Actual -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i data-feather="list"></i> Inventario Actual por Producto</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Ubicación</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center">Mín/Máx</th>
                            <th class="text-end">Precio Costo</th>
                            <th class="text-end">Valor Stock</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventario as $item): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($item['codigo']); ?></code></td>
                            <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                            <td><small><?php echo htmlspecialchars($item['nombre_categoria'] ?? '-'); ?></small></td>
                            <td><small><?php echo htmlspecialchars($item['nombre_ubicacion'] ?? 'N/A'); ?></small></td>
                            <td class="text-center">
                                <span class="badge bg-<?php 
                                    echo $item['estado_stock'] == 'sin_stock' ? 'danger' : 
                                        ($item['estado_stock'] == 'bajo' ? 'warning' : 
                                        ($item['estado_stock'] == 'alto' ? 'info' : 'success')); 
                                ?>">
                                    <?php echo number_format($item['stock_actual']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <small><?php echo $item['stock_minimo']; ?> / <?php echo $item['stock_maximo']; ?></small>
                            </td>
                            <td class="text-end"><?php echo formatearMoneda($item['precio_costo']); ?></td>
                            <td class="text-end"><strong><?php echo formatearMoneda($item['valor_stock']); ?></strong></td>
                            <td>
                                <?php
                                $estados = [
                                    'sin_stock' => ['text' => 'Sin Stock', 'class' => 'danger'],
                                    'bajo' => ['text' => 'Bajo', 'class' => 'warning'],
                                    'alto' => ['text' => 'Alto', 'class' => 'info'],
                                    'normal' => ['text' => 'Normal', 'class' => 'success']
                                ];
                                $estado_info = $estados[$item['estado_stock']];
                                ?>
                                <span class="badge bg-<?php echo $estado_info['class']; ?>">
                                    <?php echo $estado_info['text']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">TOTAL VALORIZACIÓN:</td>
                            <td class="text-end"><?php echo formatearMoneda($totales['valor_inventario']); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tipo_reporte == 'valorizacion'): ?>
    <!-- Valorización por Categoría -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i data-feather="pie-chart"></i> Valorización por Categoría</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-center">Productos</th>
                                    <th class="text-center">Unidades</th>
                                    <th class="text-end">Valor Costo</th>
                                    <th class="text-end">Valor Venta</th>
                                    <th class="text-end">Utilidad Potencial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_costo = 0;
                                $total_venta = 0;
                                foreach ($valorizacion as $cat): 
                                    $utilidad = $cat['valor_venta'] - $cat['valor_costo'];
                                    $total_costo += $cat['valor_costo'];
                                    $total_venta += $cat['valor_venta'];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['categoria']); ?></strong></td>
                                    <td class="text-center"><?php echo number_format($cat['cantidad_productos']); ?></td>
                                    <td class="text-center"><?php echo number_format($cat['total_unidades']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($cat['valor_costo']); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($cat['valor_venta']); ?></td>
                                    <td class="text-end text-success">
                                        <strong><?php echo formatearMoneda($utilidad); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="3">TOTALES</td>
                                    <td class="text-end"><?php echo formatearMoneda($total_costo); ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($total_venta); ?></td>
                                    <td class="text-end text-success"><?php echo formatearMoneda($total_venta - $total_costo); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Distribución del Inventario</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartValorizacion" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tipo_reporte == 'rotacion'): ?>
    <!-- Rotación de Inventario -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i data-feather="refresh-cw"></i> Top 10 Productos con Mayor Rotación</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Producto</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Vendidas</th>
                                    <th class="text-center">Índice</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $pos = 1; foreach ($rotacion as $prod): ?>
                                <tr>
                                    <td><?php echo $pos++; ?></td>
                                    <td>
                                        <small class="text-muted"><?php echo $prod['codigo']; ?></small><br>
                                        <?php echo htmlspecialchars($prod['nombre']); ?>
                                    </td>
                                    <td class="text-center"><?php echo number_format($prod['stock_promedio']); ?></td>
                                    <td class="text-center"><?php echo number_format($prod['unidades_vendidas']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $prod['indice_rotacion'] > 2 ? 'success' : ($prod['indice_rotacion'] > 1 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($prod['indice_rotacion'], 2); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">
                        <i data-feather="info"></i>
                        Índice de rotación = Unidades vendidas / Stock promedio (últimos 3 meses)
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i data-feather="archive"></i> Productos Sin Movimiento (90+ días)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($sin_movimiento)): ?>
                    <div class="text-center text-success py-4">
                        <i data-feather="check-circle" width="48"></i>
                        <p>Todos los productos tienen movimiento reciente</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Días sin mov.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sin_movimiento as $prod): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?php echo $prod['codigo']; ?></small><br>
                                        <?php echo htmlspecialchars($prod['nombre']); ?>
                                    </td>
                                    <td class="text-center"><?php echo number_format($prod['stock_actual']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            <?php echo $prod['dias_sin_movimiento'] ?? 'Nunca'; ?>
                                        </span>
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
    </div>

<?php else: ?>
    <!-- Movimientos de Stock -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i data-feather="activity"></i> Movimientos de Stock (Últimos 30 días)</h5>
        </div>
        <div class="card-body">
            <canvas id="chartMovimientos" style="max-height: 300px;"></canvas>
        </div>
    </div>
<?php endif; ?>

<!-- Scripts de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
<?php if ($tipo_reporte == 'valorizacion'): ?>
    <?php
    // Preparar datos para gráfico
    $labels_val = [];
    $datos_val = [];
    $colores_val = ['#28a745', '#007bff', '#ffc107', '#17a2b8', '#6c757d', '#dc3545', '#20c997', '#fd7e14'];
    
    foreach ($valorizacion as $cat) {
        $labels_val[] = $cat['categoria'];
        $datos_val[] = $cat['valor_costo'];
    }
    ?>
    
    // Gráfico de Valorización
    document.addEventListener('DOMContentLoaded', function() {
        const ctxVal = document.getElementById('chartValorizacion');
        if (ctxVal) {
            new Chart(ctxVal.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($labels_val); ?>,
                    datasets: [{
                        data: <?php echo json_encode($datos_val); ?>,
                        backgroundColor: <?php echo json_encode($colores_val); ?>
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { 
                                font: { size: 11 },
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toLocaleString('es-AR', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    }
                }
            });
        }
    });
<?php elseif ($tipo_reporte == 'movimientos'): ?>
    <?php
    // Preparar datos para gráfico de movimientos
    $labels_mov = [];
    $datos_mov = [];
    $colores_mov = [
        'entrada' => '#28a745',
        'salida' => '#dc3545',
        'transferencia' => '#17a2b8',
        'ajuste' => '#ffc107',
        'devolucion' => '#6c757d'
    ];
    
    // Agrupar movimientos por tipo
    $movimientos_agrupados = [];
    foreach ($movimientos_resumen as $mov) {
        $tipo = ucfirst($mov['tipo_movimiento']);
        if (!isset($movimientos_agrupados[$tipo])) {
            $movimientos_agrupados[$tipo] = 0;
        }
        $movimientos_agrupados[$tipo] += $mov['cantidad_movimientos'];
    }
    
    foreach ($movimientos_agrupados as $tipo => $cantidad) {
        $labels_mov[] = $tipo;
        $datos_mov[] = $cantidad;
    }
    
    // Extraer colores según el orden de los tipos
    $colores_mov_chart = [];
    foreach ($labels_mov as $label) {
        $tipo_lower = strtolower($label);
        $colores_mov_chart[] = $colores_mov[$tipo_lower] ?? '#6c757d';
    }
    ?>
    
    // Gráfico de Movimientos
    document.addEventListener('DOMContentLoaded', function() {
        const ctxMov = document.getElementById('chartMovimientos');
        if (ctxMov) {
            new Chart(ctxMov.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($labels_mov); ?>,
                    datasets: [{
                        data: <?php echo json_encode($datos_mov); ?>,
                        backgroundColor: <?php echo json_encode($colores_mov_chart); ?>
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { 
                                font: { size: 11 },
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' movimientos';
                                }
                            }
                        }
                    }
                }
            });
        }
    });

<?php endif; ?>

function exportarPDF() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'pdf');
    window.open('exportar2.php?' + params.toString(), '_blank');
}

// Inicializar Feather Icons
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>