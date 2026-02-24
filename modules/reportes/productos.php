<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('reportes_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Filtros
$fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : date('Y-m-d');
$id_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;

try {
    $db = getDB();
    
    // Obtener categorías
    $sqlCategorias = "SELECT * FROM Categorias WHERE activo = 1 ORDER BY nombre_categoria";
    $stmtCat = $db->query($sqlCategorias);
    $categorias = $stmtCat->fetchAll();
    
    // === PRODUCTOS MÁS VENDIDOS ===
    $sqlTopProductos = "SELECT 
                            p.nombre,
                            c.nombre_categoria,
                            SUM(dv.cantidad) as cantidad_vendida,
                            SUM(dv.subtotal) as monto_total,
                            COUNT(DISTINCT dv.id_venta) as numero_ventas
                        FROM Detalle_Venta dv
                        INNER JOIN Productos p ON dv.id_producto = p.id_producto
                        INNER JOIN Ventas v ON dv.id_venta = v.id_venta
                        LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                        WHERE DATE(v.fecha) BETWEEN :desde AND :hasta";
    
    $params = [':desde' => $fecha_desde, ':hasta' => $fecha_hasta];
    
    if ($id_categoria > 0) {
        $sqlTopProductos .= " AND p.id_categoria = :categoria";
        $params[':categoria'] = $id_categoria;
    }
    
    $sqlTopProductos .= " GROUP BY p.id_producto ORDER BY cantidad_vendida DESC LIMIT 20";
    
    $stmtTop = $db->prepare($sqlTopProductos);
    $stmtTop->execute($params);
    $topProductos = $stmtTop->fetchAll();
    
    // === PRODUCTOS MENOS VENDIDOS ===
    $sqlMenosVendidos = "SELECT 
                            p.nombre,
                            c.nombre_categoria,
                            COALESCE(SUM(dv.cantidad), 0) as cantidad_vendida
                         FROM Productos p
                         LEFT JOIN Detalle_Venta dv ON p.id_producto = dv.id_producto
                         LEFT JOIN Ventas v ON dv.id_venta = v.id_venta AND DATE(v.fecha) BETWEEN :desde AND :hasta
                         LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                         WHERE p.activo = 1";
    
    if ($id_categoria > 0) {
        $sqlMenosVendidos .= " AND p.id_categoria = :categoria";
    }
    
    $sqlMenosVendidos .= " GROUP BY p.id_producto
                           HAVING cantidad_vendida < 5
                           ORDER BY cantidad_vendida ASC
                           LIMIT 20";
    
    $stmtMenos = $db->prepare($sqlMenosVendidos);
    $stmtMenos->execute($params);
    $menosVendidos = $stmtMenos->fetchAll();
    
    // === VENTAS POR CATEGORÍA ===
    $sqlCategoria = "SELECT 
                        c.nombre_categoria,
                        COUNT(DISTINCT p.id_producto) as cantidad_productos,
                        SUM(dv.cantidad) as unidades_vendidas,
                        SUM(dv.subtotal) as monto_total
                     FROM Categorias c
                     LEFT JOIN Productos p ON c.id_categoria = p.id_categoria
                     LEFT JOIN Detalle_Venta dv ON p.id_producto = dv.id_producto
                     LEFT JOIN Ventas v ON dv.id_venta = v.id_venta AND DATE(v.fecha) BETWEEN :desde AND :hasta
                     WHERE c.activo = 1";
    
    if ($id_categoria > 0) {
        $sqlCategoria .= " AND c.id_categoria = :categoria";
    }
    
    $sqlCategoria .= " GROUP BY c.id_categoria ORDER BY monto_total DESC";
    
    $stmtCategoria = $db->prepare($sqlCategoria);
    $stmtCategoria->execute($params);
    $ventasCategoria = $stmtCategoria->fetchAll();
    
    // === ESTADÍSTICAS GENERALES ===
    $totalProductosVendidos = array_sum(array_column($topProductos, 'cantidad_vendida'));
    $totalMontoProductos = array_sum(array_column($topProductos, 'monto_total'));
    
} catch (PDOException $e) {
    error_log("Error en reporte de productos: " . $e->getMessage());
    $categorias = [];
    $topProductos = [];
    $menosVendidos = [];
    $ventasCategoria = [];
    $totalProductosVendidos = 0;
    $totalMontoProductos = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Productos - Sistema de Gestión</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .content {
            padding: 30px;
        }

        .filters {
            background: #f7fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 13px;
            color: #4a5568;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid #ed8936;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .table-header {
            background: #f7fafc;
            padding: 15px 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-header h3 {
            font-size: 16px;
            color: #2d3748;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Reporte de Productos</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo $fecha_desde; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria">
                            <option value="0">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $id_categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="productos.php" class="btn btn-secondary">Limpiar</a>
                    <button type="button" class="btn btn-success" onclick="window.print()">🖨️ Imprimir</button>
                </div>
            </form>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Unidades Vendidas</div>
                    <div class="stat-value"><?php echo number_format($totalProductosVendidos); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Monto Total en Productos</div>
                    <div class="stat-value"><?php echo formatearMoneda($totalMontoProductos); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Categorías Activas</div>
                    <div class="stat-value"><?php echo count($ventasCategoria); ?></div>
                </div>
            </div>

            <!-- Gráfico de Categorías -->
            <div class="chart-card">
                <div class="chart-title">📊 Ventas por Categoría</div>
                <canvas id="chartCategorias"></canvas>
            </div>

            <!-- Top 20 Productos Más Vendidos -->
            <div class="table-container">
                <div class="table-header">
                    <h3>🏆 Top 20 Productos Más Vendidos</h3>
                </div>
                <?php if (empty($topProductos)): ?>
                    <div class="empty-state">
                        <h3>No hay datos</h3>
                        <p>No se encontraron productos vendidos en este período</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th style="text-align: right;">Unidades</th>
                                <th style="text-align: right;">Ventas</th>
                                <th style="text-align: right;">Monto Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProductos as $index => $prod): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($prod['nombre_categoria']); ?></td>
                                    <td style="text-align: right;"><strong><?php echo $prod['cantidad_vendida']; ?></strong></td>
                                    <td style="text-align: right;"><?php echo $prod['numero_ventas']; ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($prod['monto_total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Productos Menos Vendidos -->
            <div class="table-container">
                <div class="table-header">
                    <h3>⚠️ Productos con Baja Rotación (Menos de 5 unidades vendidas)</h3>
                </div>
                <?php if (empty($menosVendidos)): ?>
                    <div class="empty-state">
                        <h3>¡Excelente!</h3>
                        <p>Todos los productos tienen buena rotación</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th style="text-align: right;">Unidades Vendidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menosVendidos as $prod): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($prod['nombre_categoria']); ?></td>
                                    <td style="text-align: right;"><?php echo $prod['cantidad_vendida']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Ventas por Categoría -->
            <div class="table-container">
                <div class="table-header">
                    <h3>🏷️ Resumen por Categoría</h3>
                </div>
                <?php if (empty($ventasCategoria)): ?>
                    <div class="empty-state">
                        <p>No hay datos de categorías</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th style="text-align: right;">Productos</th>
                                <th style="text-align: right;">Unidades Vendidas</th>
                                <th style="text-align: right;">Monto Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventasCategoria as $cat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['nombre_categoria']); ?></strong></td>
                                    <td style="text-align: right;"><?php echo $cat['cantidad_productos']; ?></td>
                                    <td style="text-align: right;"><?php echo $cat['unidades_vendidas'] ?: 0; ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($cat['monto_total'] ?: 0); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Gráfico de Categorías
        const ventasCategoria = <?php echo json_encode($ventasCategoria); ?>;
        const labelsCat = ventasCategoria.map(c => c.nombre_categoria);
        const montosCat = ventasCategoria.map(c => parseFloat(c.monto_total || 0));

        new Chart(document.getElementById('chartCategorias'), {
            type: 'bar',
            data: {
                labels: labelsCat,
                datasets: [{
                    label: 'Ventas ($)',
                    data: montosCat,
                    backgroundColor: '#ed8936'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>