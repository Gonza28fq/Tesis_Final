<?php
// =============================================
// modules/productos/estadisticas.php
// Sistema de Estadísticas de Productos
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'ver');

$titulo_pagina = 'Estadísticas de Productos';
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

// Total de productos activos
$total_productos = $db->count("SELECT COUNT(*) FROM productos WHERE estado = 'activo'");

// Total categorías activas
$total_categorias = $db->count("SELECT COUNT(*) FROM categorias_producto WHERE estado = 'activa'");

// Total proveedores activos
$total_proveedores = $db->count("SELECT COUNT(*) FROM proveedores WHERE estado = 'activo'");

// Valor total del inventario
$valor_inventario = $db->query("
    SELECT COALESCE(SUM(s.cantidad * p.precio_costo), 0) as valor
    FROM stock s
    INNER JOIN productos p ON s.id_producto = p.id_producto
    WHERE p.estado = 'activo'
")->fetch()['valor'] ?? 0;

// =============================================
// ESTADÍSTICAS DE STOCK
// =============================================

// Productos sin stock
$productos_sin_stock = $db->count("
    SELECT COUNT(DISTINCT p.id_producto) 
    FROM productos p 
    LEFT JOIN stock s ON p.id_producto = s.id_producto 
    WHERE p.estado = 'activo' AND (s.cantidad IS NULL OR s.cantidad = 0)
");

// Productos con stock bajo
$productos_stock_bajo = $db->count("
    SELECT COUNT(DISTINCT p.id_producto) 
    FROM productos p 
    INNER JOIN stock s ON p.id_producto = s.id_producto 
    WHERE p.estado = 'activo' AND s.cantidad > 0 AND s.cantidad <= p.stock_minimo
");

// Productos con stock óptimo
$productos_stock_optimo = $db->count("
    SELECT COUNT(DISTINCT p.id_producto) 
    FROM productos p 
    INNER JOIN stock s ON p.id_producto = s.id_producto 
    WHERE p.estado = 'activo' AND s.cantidad > p.stock_minimo
");

// =============================================
// TOP 10 PRODUCTOS MÁS VENDIDOS
// =============================================

$productos_mas_vendidos = $db->query("
    SELECT 
        p.codigo,
        p.nombre,
        c.nombre_categoria,
        SUM(vd.cantidad) as total_vendido,
        SUM(vd.subtotal) as ingresos_totales,
        COUNT(DISTINCT v.id_venta) as num_ventas
    FROM ventas_detalle vd
    INNER JOIN ventas v ON vd.id_venta = v.id_venta
    INNER JOIN productos p ON vd.id_producto = p.id_producto
    LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
    WHERE v.estado IN ('completada', 'pendiente')
        AND v.fecha_venta BETWEEN ? AND ?
    GROUP BY p.id_producto
    ORDER BY total_vendido DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// PRODUCTOS CON MENOR ROTACIÓN
// =============================================

$productos_menor_rotacion = $db->query("
    SELECT 
        p.codigo,
        p.nombre,
        c.nombre_categoria,
        COALESCE(SUM(s.cantidad), 0) as stock_actual,
        COALESCE(SUM(vd.cantidad), 0) as total_vendido
    FROM productos p
    LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    LEFT JOIN ventas_detalle vd ON p.id_producto = vd.id_producto
    LEFT JOIN ventas v ON vd.id_venta = v.id_venta 
        AND v.fecha_venta BETWEEN ? AND ?
        AND v.estado IN ('completada', 'pendiente')
    WHERE p.estado = 'activo'
    GROUP BY p.id_producto
    HAVING stock_actual > 0
    ORDER BY total_vendido ASC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// PRODUCTOS POR CATEGORÍA
// =============================================

$productos_por_categoria = $db->query("
    SELECT 
        COALESCE(c.nombre_categoria, 'Sin categoría') as categoria,
        COUNT(p.id_producto) as cantidad_productos,
        SUM(COALESCE(s.cantidad, 0)) as stock_total,
        SUM(COALESCE(s.cantidad, 0) * p.precio_costo) as valor_total
    FROM productos p
    LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    WHERE p.estado = 'activo'
    GROUP BY c.id_categoria, c.nombre_categoria
    ORDER BY cantidad_productos DESC
")->fetchAll();

// =============================================
// MOVIMIENTOS DE STOCK RECIENTES
// =============================================

$movimientos_recientes = $db->query("
    SELECT 
        ms.tipo_movimiento,
        COUNT(*) as cantidad_movimientos,
        SUM(ms.cantidad) as total_unidades
    FROM movimientos_stock ms
    INNER JOIN productos p ON ms.id_producto = p.id_producto
    WHERE ms.fecha_movimiento BETWEEN ? AND ?
        AND p.estado = 'activo'
    GROUP BY ms.tipo_movimiento
    ORDER BY cantidad_movimientos DESC
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// PRODUCTOS AGREGADOS RECIENTEMENTE
// =============================================

$productos_nuevos = $db->query("
    SELECT 
        p.codigo,
        p.nombre,
        c.nombre_categoria,
        p.precio_base,
        COALESCE(SUM(s.cantidad), 0) as stock_actual,
        p.fecha_creacion
    FROM productos p
    LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
    LEFT JOIN stock s ON p.id_producto = s.id_producto
    WHERE p.estado = 'activo'
        AND p.fecha_creacion BETWEEN ? AND ?
    GROUP BY p.id_producto
    ORDER BY p.fecha_creacion DESC
    LIMIT 10
", [$fecha_desde, $fecha_hasta])->fetchAll();

// =============================================
// ANÁLISIS DE PRECIOS
// =============================================

$analisis_precios = $db->query("
    SELECT 
        COUNT(*) as total_productos,
        AVG(precio_base) as precio_promedio,
        MIN(precio_base) as precio_minimo,
        MAX(precio_base) as precio_maximo,
        AVG(precio_costo) as costo_promedio,
        AVG((precio_base - precio_costo) / precio_costo * 100) as margen_promedio
    FROM productos
    WHERE estado = 'activo' AND precio_base > 0
")->fetch();

$breadcrumb = [
    'Productos' => 'modules/productos/index.php',
    'Estadísticas' => ''
];

include '../../includes/header.php';
?>

<!-- Filtros de Período -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-calendar3"></i> Período de Análisis
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
                    <i class="bi bi-search"></i> Aplicar
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
                        <h6 class="text-muted mb-1">Total Productos</h6>
                        <h3 class="mb-0"><?php echo formatearNumero($total_productos); ?></h3>
                        <small class="text-muted"><?php echo formatearNumero($total_categorias); ?> categorías</small>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-box-seam fs-1"></i>
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
                        <h6 class="text-muted mb-1">Valor Inventario</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($valor_inventario); ?></h3>
                        <small class="text-muted">Costo total</small>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-currency-dollar fs-1"></i>
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
                        <h6 class="text-muted mb-1">Stock Bajo</h6>
                        <h3 class="mb-0 text-warning"><?php echo formatearNumero($productos_stock_bajo); ?></h3>
                        <small class="text-muted">Requieren reposición</small>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Sin Stock</h6>
                        <h3 class="mb-0 text-danger"><?php echo formatearNumero($productos_sin_stock); ?></h3>
                        <small class="text-muted">Agotados</small>
                    </div>
                    <div class="text-danger">
                        <i class="bi bi-x-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Distribución de Stock -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart"></i> Distribución de Stock
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="chartDistribucionStock"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-calculator"></i> Análisis de Precios
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Precio Promedio</h6>
                        <h4><?php echo formatearMoneda($analisis_precios['precio_promedio'] ?? 0); ?></h4>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted">Margen Promedio</h6>
                        <h4 class="text-success"><?php echo formatearNumero($analisis_precios['margen_promedio'] ?? 0, 1); ?>%</h4>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Precio Mínimo</h6>
                        <h5><?php echo formatearMoneda($analisis_precios['precio_minimo'] ?? 0); ?></h5>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Precio Máximo</h6>
                        <h5><?php echo formatearMoneda($analisis_precios['precio_maximo'] ?? 0); ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top 10 Productos Más Vendidos -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-trophy"></i> Top 10 Productos Más Vendidos (<?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?>)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="text-end">Unidades</th>
                        <th class="text-end">Ventas</th>
                        <th class="text-end">Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos_mas_vendidos)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay datos de ventas en el período seleccionado
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($productos_mas_vendidos as $index => $producto): ?>
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
                            <td><code><?php echo htmlspecialchars($producto['codigo']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong></td>
                            <td>
                                <?php if ($producto['nombre_categoria']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($producto['nombre_categoria']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><strong><?php echo formatearNumero($producto['total_vendido']); ?></strong></td>
                            <td class="text-end"><?php echo formatearNumero($producto['num_ventas']); ?></td>
                            <td class="text-end text-success"><strong><?php echo formatearMoneda($producto['ingresos_totales']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Productos por Categoría y Menor Rotación -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-folder"></i> Productos por Categoría
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th class="text-end">Productos</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_por_categoria as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cat['categoria']); ?></td>
                                <td class="text-end"><?php echo formatearNumero($cat['cantidad_productos']); ?></td>
                                <td class="text-end"><?php echo formatearNumero($cat['stock_total']); ?></td>
                                <td class="text-end"><?php echo formatearMoneda($cat['valor_total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-arrow-down-circle"></i> Productos con Menor Rotación
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Vendidos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_menor_rotacion as $producto): ?>
                            <tr>
                                <td>
                                    <small><code><?php echo htmlspecialchars($producto['codigo']); ?></code></small><br>
                                    <?php echo htmlspecialchars(substr($producto['nombre'], 0, 30)); ?>...
                                </td>
                                <td class="text-end"><?php echo formatearNumero($producto['stock_actual']); ?></td>
                                <td class="text-end">
                                    <?php if ($producto['total_vendido'] == 0): ?>
                                    <span class="badge bg-danger">0</span>
                                    <?php else: ?>
                                    <?php echo formatearNumero($producto['total_vendido']); ?>
                                    <?php endif; ?>
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

<!-- Movimientos de Stock y Productos Nuevos -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-arrow-left-right"></i> Movimientos de Stock
            </div>
            <div class="card-body">
                <?php if (empty($movimientos_recientes)): ?>
                <p class="text-muted text-center py-4">No hay movimientos en el período</p>
                <?php else: ?>
                <canvas id="chartMovimientos" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-plus-circle"></i> Productos Agregados Recientemente
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <?php if (empty($productos_nuevos)): ?>
                            <tr>
                                <td class="text-center text-muted py-4">
                                    No se agregaron productos en el período
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($productos_nuevos as $producto): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> <?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?> |
                                            <i class="bi bi-cash"></i> <?php echo formatearMoneda($producto['precio_base']); ?> |
                                            <i class="bi bi-box"></i> Stock: <?php echo formatearNumero($producto['stock_actual']); ?>
                                        </small>
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

<!-- Scripts de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Distribución de Stock
const ctxStock = document.getElementById('chartDistribucionStock');
if (ctxStock) {
    new Chart(ctxStock, {
        type: 'doughnut',
        data: {
            labels: ['Stock Óptimo', 'Stock Bajo', 'Sin Stock'],
            datasets: [{
                data: [
                    <?php echo $productos_stock_optimo; ?>,
                    <?php echo $productos_stock_bajo; ?>,
                    <?php echo $productos_sin_stock; ?>
                ],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
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

// Gráfico de Movimientos
const ctxMovimientos = document.getElementById('chartMovimientos');
if (ctxMovimientos) {
    new Chart(ctxMovimientos, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($movimientos_recientes as $mov): ?>
                '<?= ucfirst($mov['tipo_movimiento']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Cantidad de Movimientos',
                data: [
                    <?php foreach ($movimientos_recientes as $mov): ?>
                    <?= $mov['cantidad_movimientos']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#0d6efd',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // <- depende de la altura definida
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
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
toggleFechas();
</script>

<?php include '../../includes/footer.php'; ?>