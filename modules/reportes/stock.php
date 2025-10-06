<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('reportes_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Filtros
$filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : 'todos';
$id_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;

try {
    $db = getDB();
    
    // Obtener categorías
    $sqlCategorias = "SELECT * FROM Categorias WHERE activo = 1 ORDER BY nombre_categoria";
    $stmtCat = $db->query($sqlCategorias);
    $categorias = $stmtCat->fetchAll();
    
    // === PRODUCTOS CON STOCK ===
    $sql = "SELECT 
                p.id_producto,
                p.codigo_producto,
                p.nombre,
                p.precio_unitario,
                p.stock_minimo,
                c.nombre_categoria,
                COALESCE(SUM(s.cantidad), 0) as stock_actual,
                (COALESCE(SUM(s.cantidad), 0) * p.precio_unitario) as valor_stock,
                CASE 
                    WHEN COALESCE(SUM(s.cantidad), 0) = 0 THEN 'SIN_STOCK'
                    WHEN COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo THEN 'CRITICO'
                    WHEN COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo * 2 THEN 'BAJO'
                    ELSE 'OK'
                END as estado_stock
            FROM Productos p
            LEFT JOIN Stock s ON p.id_producto = s.id_producto
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            WHERE p.activo = 1";
    
    if ($id_categoria > 0) {
        $sql .= " AND p.id_categoria = :categoria";
    }
    
    $sql .= " GROUP BY p.id_producto";
    
    // Filtrar por estado
    if ($filtroEstado === 'critico') {
        $sql .= " HAVING estado_stock = 'CRITICO' OR estado_stock = 'SIN_STOCK'";
    } elseif ($filtroEstado === 'bajo') {
        $sql .= " HAVING estado_stock = 'BAJO'";
    } elseif ($filtroEstado === 'sin_stock') {
        $sql .= " HAVING estado_stock = 'SIN_STOCK'";
    }
    
    $sql .= " ORDER BY stock_actual ASC";
    
    if ($id_categoria > 0) {
        $stmt = $db->prepare($sql);
        $stmt->execute([':categoria' => $id_categoria]);
    } else {
        $stmt = $db->query($sql);
    }
    $productos = $stmt->fetchAll();
    
    // === ESTADÍSTICAS GENERALES ===
    $sqlStats = "SELECT 
                    COUNT(DISTINCT p.id_producto) as total_productos,
                    SUM(COALESCE(s.cantidad, 0)) as stock_total,
                    SUM(COALESCE(s.cantidad, 0) * p.precio_unitario) as valor_total
                 FROM Productos p
                 LEFT JOIN Stock s ON p.id_producto = s.id_producto
                 WHERE p.activo = 1";
    
    if ($id_categoria > 0) {
        $sqlStats .= " AND p.id_categoria = :categoria";
        $stmtStats = $db->prepare($sqlStats);
        $stmtStats->execute([':categoria' => $id_categoria]);
    } else {
        $stmtStats = $db->query($sqlStats);
    }
    $stats = $stmtStats->fetch();
    
    // Contar por estado
    $critico = count(array_filter($productos, fn($p) => $p['estado_stock'] === 'CRITICO' || $p['estado_stock'] === 'SIN_STOCK'));
    $bajo = count(array_filter($productos, fn($p) => $p['estado_stock'] === 'BAJO'));
    $ok = count(array_filter($productos, fn($p) => $p['estado_stock'] === 'OK'));
    
    // === STOCK POR CATEGORÍA ===
    $sqlCategoria = "SELECT 
                        c.nombre_categoria,
                        COUNT(DISTINCT p.id_producto) as cantidad_productos,
                        SUM(COALESCE(s.cantidad, 0)) as stock_total,
                        SUM(COALESCE(s.cantidad, 0) * p.precio_unitario) as valor_total
                     FROM Categorias c
                     INNER JOIN Productos p ON c.id_categoria = p.id_categoria AND p.activo = 1
                     LEFT JOIN Stock s ON p.id_producto = s.id_producto
                     WHERE c.activo = 1";
    
    if ($id_categoria > 0) {
        $sqlCategoria .= " AND c.id_categoria = :categoria";
        $stmtCategoria = $db->prepare($sqlCategoria);
        $stmtCategoria->execute([':categoria' => $id_categoria]);
    } else {
        $sqlCategoria .= " GROUP BY c.id_categoria ORDER BY valor_total DESC";
        $stmtCategoria = $db->query($sqlCategoria);
    }
    $stockCategoria = $stmtCategoria->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error en reporte de stock: " . $e->getMessage());
    $categorias = [];
    $productos = [];
    $stats = ['total_productos' => 0, 'stock_total' => 0, 'valor_total' => 0];
    $critico = 0;
    $bajo = 0;
    $ok = 0;
    $stockCategoria = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Stock - Sistema de Gestión</title>
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

        .btn-primary {
            background: #667eea;
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
            border-left: 5px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card.productos { border-color: #4299e1; }
        .stat-card.stock { border-color: #48bb78; }
        .stat-card.valor { border-color: #9f7aea; }
        .stat-card.critico { border-color: #f56565; }
        .stat-card.bajo { border-color: #ed8936; }
        .stat-card.ok { border-color: #48bb78; }

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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-critico {
            background: #fed7d7;
            color: #742a2a;
        }

        .badge-bajo {
            background: #feebc8;
            color: #7c2d12;
        }

        .badge-ok {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-sin {
            background: #e2e8f0;
            color: #4a5568;
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
            <h1>📊 Reporte de Stock</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Estado del Stock</label>
                        <select name="estado">
                            <option value="todos" <?php echo $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="critico" <?php echo $filtroEstado === 'critico' ? 'selected' : ''; ?>>Crítico/Sin Stock</option>
                            <option value="bajo" <?php echo $filtroEstado === 'bajo' ? 'selected' : ''; ?>>Stock Bajo</option>
                            <option value="sin_stock" <?php echo $filtroEstado === 'sin_stock' ? 'selected' : ''; ?>>Sin Stock</option>
                        </select>
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
                    <a href="stock.php" class="btn btn-secondary">Limpiar</a>
                    <button type="button" class="btn btn-success" onclick="window.print()">🖨️ Imprimir</button>
                </div>
            </form>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card productos">
                    <div class="stat-label">Total Productos</div>
                    <div class="stat-value"><?php echo $stats['total_productos']; ?></div>
                </div>

                <div class="stat-card stock">
                    <div class="stat-label">Unidades en Stock</div>
                    <div class="stat-value"><?php echo number_format($stats['stock_total']); ?></div>
                </div>

                <div class="stat-card valor">
                    <div class="stat-label">Valorización Total</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['valor_total']); ?></div>
                </div>

                <div class="stat-card critico">
                    <div class="stat-label">Stock Crítico</div>
                    <div class="stat-value"><?php echo $critico; ?></div>
                </div>

                <div class="stat-card bajo">
                    <div class="stat-label">Stock Bajo</div>
                    <div class="stat-value"><?php echo $bajo; ?></div>
                </div>

                <div class="stat-card ok">
                    <div class="stat-label">Stock OK</div>
                    <div class="stat-value"><?php echo $ok; ?></div>
                </div>
            </div>

            <!-- Gráfico de Stock por Categoría -->
            <?php if (!empty($stockCategoria)): ?>
            <div class="chart-card">
                <div class="chart-title">📦 Valorización por Categoría</div>
                <canvas id="chartCategoria"></canvas>
            </div>
            <?php endif; ?>

            <!-- Tabla de Productos -->
            <div class="table-container">
                <div class="table-header">
                    <h3>📋 Detalle de Stock (<?php echo count($productos); ?> productos)</h3>
                </div>
                <?php if (empty($productos)): ?>
                    <div class="empty-state">
                        <h3>No hay productos</h3>
                        <p>Ajuste los filtros para ver resultados</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th style="text-align: right;">Stock Actual</th>
                                <th style="text-align: right;">Stock Mínimo</th>
                                <th style="text-align: right;">Precio Unit.</th>
                                <th style="text-align: right;">Valor Stock</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $prod): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($prod['codigo_producto'] ?: '-'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($prod['nombre_categoria']); ?></td>
                                    <td style="text-align: right;"><strong><?php echo $prod['stock_actual']; ?></strong></td>
                                    <td style="text-align: right;"><?php echo $prod['stock_minimo']; ?></td>
                                    <td style="text-align: right;"><?php echo formatearMoneda($prod['precio_unitario']); ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($prod['valor_stock']); ?></strong></td>
                                    <td>
                                        <?php
                                        $badgeClass = '';
                                        $texto = '';
                                        switch ($prod['estado_stock']) {
                                            case 'SIN_STOCK':
                                                $badgeClass = 'badge-sin';
                                                $texto = 'Sin Stock';
                                                break;
                                            case 'CRITICO':
                                                $badgeClass = 'badge-critico';
                                                $texto = 'Crítico';
                                                break;
                                            case 'BAJO':
                                                $badgeClass = 'badge-bajo';
                                                $texto = 'Bajo';
                                                break;
                                            case 'OK':
                                                $badgeClass = 'badge-ok';
                                                $texto = 'OK';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $texto; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Valorización por Categoría -->
            <?php if (!empty($stockCategoria)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3>🏷️ Valorización por Categoría</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th style="text-align: right;">Productos</th>
                            <th style="text-align: right;">Stock Total</th>
                            <th style="text-align: right;">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockCategoria as $cat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cat['nombre_categoria']); ?></strong></td>
                                <td style="text-align: right;"><?php echo $cat['cantidad_productos']; ?></td>
                                <td style="text-align: right;"><?php echo number_format($cat['stock_total']); ?></td>
                                <td style="text-align: right;"><strong><?php echo formatearMoneda($cat['valor_total']); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Gráfico de Valorización por Categoría
        <?php if (!empty($stockCategoria)): ?>
        const stockCategoria = <?php echo json_encode($stockCategoria); ?>;
        const labelsCat = stockCategoria.map(c => c.nombre_categoria);
        const valoresCat = stockCategoria.map(c => parseFloat(c.valor_total));

        new Chart(document.getElementById('chartCategoria'), {
            type: 'doughnut',
            data: {
                labels: labelsCat,
                datasets: [{
                    data: valoresCat,
                    backgroundColor: [
                        '#48bb78', '#4299e1', '#ed8936', '#9f7aea', '#f56565',
                        '#38b2ac', '#805ad5', '#d69e2e', '#e53e3e', '#3182ce'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>