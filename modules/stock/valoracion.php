<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('stock_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

try {
    $db = getDB();
    
    // Valoración por categoría
    $sqlCategorias = "SELECT 
                        c.nombre_categoria,
                        COUNT(DISTINCT p.id_producto) as total_productos,
                        COALESCE(SUM(s.cantidad), 0) as stock_total,
                        SUM(s.cantidad * p.precio_unitario) as valoracion
                    FROM Categorias c
                    INNER JOIN Productos p ON c.id_categoria = p.id_categoria
                    LEFT JOIN Stock s ON p.id_producto = s.id_producto
                    WHERE p.activo = 1
                    GROUP BY c.id_categoria
                    ORDER BY valoracion DESC";
    
    $stmtCat = $db->query($sqlCategorias);
    $categorias = $stmtCat->fetchAll();
    
    // Valoración por ubicación
    $sqlUbicaciones = "SELECT 
                         u.nombre as ubicacion,
                         COUNT(DISTINCT s.id_producto) as total_productos,
                         SUM(s.cantidad) as stock_total,
                         SUM(s.cantidad * p.precio_unitario) as valoracion
                     FROM Ubicaciones u
                     INNER JOIN Stock s ON u.id_ubicacion = s.id_ubicacion
                     INNER JOIN Productos p ON s.id_producto = p.id_producto
                     WHERE u.activo = 1 AND p.activo = 1
                     GROUP BY u.id_ubicacion
                     ORDER BY valoracion DESC";
    
    $stmtUb = $db->query($sqlUbicaciones);
    $ubicaciones = $stmtUb->fetchAll();
    
    // Totales generales
    $sqlTotales = "SELECT 
                     COUNT(DISTINCT p.id_producto) as total_productos,
                     COALESCE(SUM(s.cantidad), 0) as stock_total,
                     SUM(s.cantidad * p.precio_unitario) as valoracion_total
                   FROM Productos p
                   LEFT JOIN Stock s ON p.id_producto = s.id_producto
                   WHERE p.activo = 1";
    
    $stmtTot = $db->query($sqlTotales);
    $totales = $stmtTot->fetch();
    
    // Top 10 productos más valiosos
    $sqlTop = "SELECT 
                 p.nombre,
                 p.codigo_producto,
                 c.nombre_categoria,
                 COALESCE(SUM(s.cantidad), 0) as stock_total,
                 p.precio_unitario,
                 SUM(s.cantidad * p.precio_unitario) as valoracion
               FROM Productos p
               LEFT JOIN Stock s ON p.id_producto = s.id_producto
               LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
               WHERE p.activo = 1
               GROUP BY p.id_producto
               ORDER BY valoracion DESC
               LIMIT 10";
    
    $stmtTop = $db->query($sqlTop);
    $topProductos = $stmtTop->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $categorias = [];
    $ubicaciones = [];
    $totales = ['total_productos' => 0, 'stock_total' => 0, 'valoracion_total' => 0];
    $topProductos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valoración de Inventario - Sistema de Gestión</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
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
        }

        .content { padding: 30px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }

        .section {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f7fafc;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s;
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
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Valoración de Inventario</h1>
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-primary" style="margin-right: 10px;">🖨️ Imprimir</button>
                <a href="index.php" class="btn btn-secondary">← Volver</a>
            </div>
        </div>

        <div class="content">
            <!-- Totales Generales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Productos</div>
                    <div class="stat-value"><?php echo $totales['total_productos']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Stock Total (Unidades)</div>
                    <div class="stat-value"><?php echo number_format($totales['stock_total']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Valoración Total</div>
                    <div class="stat-value"><?php echo formatearMoneda($totales['valoracion_total']); ?></div>
                </div>
            </div>

            <!-- Valoración por Categoría -->
            <div class="section">
                <h2>📊 Valoración por Categoría</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th style="text-align: right;">Productos</th>
                            <th style="text-align: right;">Stock</th>
                            <th style="text-align: right;">Valoración</th>
                            <th>Distribución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $maxValoracion = max(array_column($categorias, 'valoracion'));
                        foreach ($categorias as $cat): 
                            $porcentaje = $maxValoracion > 0 ? ($cat['valoracion'] / $maxValoracion * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo $cat['nombre_categoria']; ?></strong></td>
                                <td style="text-align: right;"><?php echo $cat['total_productos']; ?></td>
                                <td style="text-align: right;"><?php echo number_format($cat['stock_total']); ?></td>
                                <td style="text-align: right;"><strong><?php echo formatearMoneda($cat['valoracion']); ?></strong></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Valoración por Ubicación -->
            <div class="section">
                <h2>📍 Valoración por Ubicación</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Ubicación</th>
                            <th style="text-align: right;">Productos</th>
                            <th style="text-align: right;">Stock</th>
                            <th style="text-align: right;">Valoración</th>
                            <th>Distribución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $maxValoracionUb = max(array_column($ubicaciones, 'valoracion'));
                        foreach ($ubicaciones as $ub): 
                            $porcentaje = $maxValoracionUb > 0 ? ($ub['valoracion'] / $maxValoracionUb * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo $ub['ubicacion']; ?></strong></td>
                                <td style="text-align: right;"><?php echo $ub['total_productos']; ?></td>
                                <td style="text-align: right;"><?php echo number_format($ub['stock_total']); ?></td>
                                <td style="text-align: right;"><strong><?php echo formatearMoneda($ub['valoracion']); ?></strong></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top 10 Productos Más Valiosos -->
            <div class="section">
                <h2>🏆 Top 10 Productos Más Valiosos</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Ranking</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th style="text-align: right;">Stock</th>
                            <th style="text-align: right;">Precio Unit.</th>
                            <th style="text-align: right;">Valoración</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProductos as $index => $prod): ?>
                            <tr>
                                <td>
                                    <strong style="color: <?php 
                                        echo $index === 0 ? '#f59e0b' : ($index === 1 ? '#9ca3af' : ($index === 2 ? '#cd7f32' : '#667eea')); 
                                    ?>;">
                                        #<?php echo $index + 1; ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                                    <?php if ($prod['codigo_producto']): ?>
                                        <br><small style="color: #718096;"><?php echo $prod['codigo_producto']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $prod['nombre_categoria']; ?></td>
                                <td style="text-align: right;"><?php echo $prod['stock_total']; ?></td>
                                <td style="text-align: right;"><?php echo formatearMoneda($prod['precio_unitario']); ?></td>
                                <td style="text-align: right;"><strong style="color: #667eea;"><?php echo formatearMoneda($prod['valoracion']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="text-align: center; color: #718096; font-size: 13px; margin-top: 30px;">
                <p>Reporte generado el <?php echo date('d/m/Y H:i:s'); ?></p>
                <p>Sistema de Gestión Comercial v1.0</p>
            </div>
        </div>
    </div>
</body>
</html>