<?php
require_once '../../config/conexion.php';
validarSesion();

// Verificar permiso
if (!tienePermiso('stock_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Obtener filtros
$filtroNombre = isset($_GET['nombre']) ? sanitize($_GET['nombre']) : '';
$filtroCategoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtroProveedor = isset($_GET['proveedor']) ? intval($_GET['proveedor']) : 0;
$filtroUbicacion = isset($_GET['ubicacion']) ? intval($_GET['ubicacion']) : 0;
$filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$ordenar = isset($_GET['ordenar']) ? sanitize($_GET['ordenar']) : 'nombre';

try {
    $db = getDB();
    
    // Obtener categorías para el filtro
    $sqlCategorias = "SELECT id_categoria, nombre_categoria FROM Categorias WHERE activo = 1 ORDER BY nombre_categoria";
    $stmtCategorias = $db->query($sqlCategorias);
    $categorias = $stmtCategorias->fetchAll();
    
    // Obtener proveedores para el filtro
    $sqlProveedores = "SELECT id_proveedor, nombre FROM Proveedores WHERE activo = 1 ORDER BY nombre";
    $stmtProveedores = $db->query($sqlProveedores);
    $proveedores = $stmtProveedores->fetchAll();
    
    // Obtener ubicaciones para el filtro
    $sqlUbicaciones = "SELECT id_ubicacion, nombre FROM Ubicaciones WHERE activo = 1 ORDER BY nombre";
    $stmtUbicaciones = $db->query($sqlUbicaciones);
    $ubicaciones = $stmtUbicaciones->fetchAll();
    
    // Construir consulta principal de stock
    $sql = "SELECT 
                p.id_producto,
                p.codigo_producto,
                p.nombre,
                p.descripcion,
                p.precio_unitario,
                p.stock_minimo,
                c.nombre_categoria,
                prov.nombre as proveedor,
                COALESCE(SUM(s.cantidad), 0) as stock_total,
                CASE 
                    WHEN COALESCE(SUM(s.cantidad), 0) <= 0 THEN 'SIN_STOCK'
                    WHEN COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo THEN 'CRITICO'
                    WHEN COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo * 2 THEN 'BAJO'
                    ELSE 'OK'
                END AS estado_stock,
                (COALESCE(SUM(s.cantidad), 0) * p.precio_unitario) as valoracion,
                MAX(ms.fecha) as ultima_actualizacion
            FROM Productos p
            LEFT JOIN Stock s ON p.id_producto = s.id_producto";
    
    if ($filtroUbicacion > 0) {
        $sql .= " AND s.id_ubicacion = :ubicacion";
    }
    
    $sql .= " LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Proveedores prov ON p.id_proveedor = prov.id_proveedor
            LEFT JOIN Movimientos_Stock ms ON p.id_producto = ms.id_producto
            WHERE p.activo = 1";
    
    $params = [];
    
    if (!empty($filtroNombre)) {
        $sql .= " AND (p.nombre LIKE :nombre OR p.codigo_producto LIKE :nombre2)";
        $params[':nombre'] = "%{$filtroNombre}%";
        $params[':nombre2'] = "%{$filtroNombre}%";
    }
    
    if ($filtroCategoria > 0) {
        $sql .= " AND p.id_categoria = :categoria";
        $params[':categoria'] = $filtroCategoria;
    }
    
    if ($filtroProveedor > 0) {
        $sql .= " AND p.id_proveedor = :proveedor";
        $params[':proveedor'] = $filtroProveedor;
    }
    
    if ($filtroUbicacion > 0) {
        $params[':ubicacion'] = $filtroUbicacion;
    }
    
    $sql .= " GROUP BY p.id_producto";
    
    // Filtrar por estado de stock
    if (!empty($filtroEstado)) {
        $sql .= " HAVING estado_stock = :estado";
        $params[':estado'] = $filtroEstado;
    }
    
    // Ordenar
    switch($ordenar) {
        case 'nombre':
            $sql .= " ORDER BY p.nombre ASC";
            break;
        case 'stock':
            $sql .= " ORDER BY stock_total ASC";
            break;
        case 'valoracion':
            $sql .= " ORDER BY valoracion DESC";
            break;
        case 'actualizacion':
            $sql .= " ORDER BY ultima_actualizacion DESC";
            break;
        default:
            $sql .= " ORDER BY p.nombre ASC";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
    
    // Calcular estadísticas
    $totalProductos = count($productos);
    $stockCritico = count(array_filter($productos, function($p) { return $p['estado_stock'] === 'CRITICO' || $p['estado_stock'] === 'SIN_STOCK'; }));
    $stockBajo = count(array_filter($productos, function($p) { return $p['estado_stock'] === 'BAJO'; }));
    $valorTotal = array_sum(array_column($productos, 'valoracion'));
    
} catch (PDOException $e) {
    error_log("Error en stock/index.php: " . $e->getMessage());
    $productos = [];
    $categorias = [];
    $proveedores = [];
    $ubicaciones = [];
    $totalProductos = 0;
    $stockCritico = 0;
    $stockBajo = 0;
    $valorTotal = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Stock - Sistema de Gestión</title>
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

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-warning {
            background: #ed8936;
            color: white;
        }

        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.ok { border-color: #48bb78; }
        .stat-card.warning { border-color: #ed8936; }
        .stat-card.danger { border-color: #f56565; }
        .stat-card.info { border-color: #667eea; }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
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

        .table-container {
            overflow-x: auto;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: #f7fafc;
            position: sticky;
            top: 0;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
        }

        td {
            padding: 15px;
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

        .badge-ok {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-bajo {
            background: #feebc8;
            color: #744210;
        }

        .badge-critico {
            background: #fed7d7;
            color: #742a2a;
        }

        .badge-sin-stock {
            background: #e2e8f0;
            color: #2d3748;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
            transition: width 0.3s;
        }

        .progress-ok { background: #48bb78; }
        .progress-bajo { background: #ed8936; }
        .progress-critico { background: #f56565; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-warning {
            background: #feebc8;
            border: 2px solid #f5cba7;
            color: #744210;
        }

        .alert-danger {
            background: #fed7d7;
            border: 2px solid #fc8181;
            color: #742a2a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Módulo de Stock</h1>
            <div class="header-actions">
                <?php if (tienePermiso('stock_ajustar')): ?>
                    <a href="ajustar.php" class="btn btn-warning">⚙️ Ajustar Stock</a>
                    <a href="transferir.php" class="btn btn-success">🔄 Transferir</a>
                <?php endif; ?>
                <a href="movimientos.php" class="btn btn-primary">📋 Movimientos</a>
                <a href="valoracion.php" class="btn btn-secondary">💰 Valoración</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <!-- Alertas de Stock Crítico -->
            <?php if ($stockCritico > 0): ?>
                <div class="alert alert-danger show">
                    <strong>⚠️ Atención:</strong> Hay <?php echo $stockCritico; ?> producto(s) con stock crítico o sin stock.
                    <a href="?estado=CRITICO" style="color: inherit; text-decoration: underline; margin-left: 10px;">Ver productos</a>
                </div>
            <?php endif; ?>

            <?php if ($stockBajo > 0): ?>
                <div class="alert alert-warning show">
                    <strong>⚠️ Aviso:</strong> Hay <?php echo $stockBajo; ?> producto(s) con stock bajo.
                    <a href="?estado=BAJO" style="color: inherit; text-decoration: underline; margin-left: 10px;">Ver productos</a>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card info">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Total Productos</div>
                    <div class="stat-value"><?php echo $totalProductos; ?></div>
                </div>

                <div class="stat-card danger">
                    <div class="stat-icon">🚨</div>
                    <div class="stat-label">Stock Crítico</div>
                    <div class="stat-value"><?php echo $stockCritico; ?></div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-label">Stock Bajo</div>
                    <div class="stat-value"><?php echo $stockBajo; ?></div>
                </div>

                <div class="stat-card ok">
                    <div class="stat-icon">💰</div>
                    <div class="stat-label">Valoración Total</div>
                    <div class="stat-value"><?php echo formatearMoneda($valorTotal); ?></div>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros y Búsqueda</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Buscar</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($filtroNombre); ?>" placeholder="Nombre o código...">
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria">
                            <option value="0">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $filtroCategoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['nombre_categoria']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select name="proveedor">
                            <option value="0">Todos</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $filtroProveedor == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                                    <?php echo $prov['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ubicación</label>
                        <select name="ubicacion">
                            <option value="0">Todas</option>
                            <?php foreach ($ubicaciones as $ub): ?>
                                <option value="<?php echo $ub['id_ubicacion']; ?>" <?php echo $filtroUbicacion == $ub['id_ubicacion'] ? 'selected' : ''; ?>>
                                    <?php echo $ub['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="OK" <?php echo $filtroEstado === 'OK' ? 'selected' : ''; ?>>OK</option>
                            <option value="BAJO" <?php echo $filtroEstado === 'BAJO' ? 'selected' : ''; ?>>Stock Bajo</option>
                            <option value="CRITICO" <?php echo $filtroEstado === 'CRITICO' ? 'selected' : ''; ?>>Crítico</option>
                            <option value="SIN_STOCK" <?php echo $filtroEstado === 'SIN_STOCK' ? 'selected' : ''; ?>>Sin Stock</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ordenar por</label>
                        <select name="ordenar">
                            <option value="nombre" <?php echo $ordenar === 'nombre' ? 'selected' : ''; ?>>Nombre</option>
                            <option value="stock" <?php echo $ordenar === 'stock' ? 'selected' : ''; ?>>Cantidad</option>
                            <option value="valoracion" <?php echo $ordenar === 'valoracion' ? 'selected' : ''; ?>>Valoración</option>
                            <option value="actualizacion" <?php echo $ordenar === 'actualizacion' ? 'selected' : ''; ?>>Última actualización</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="index.php" class="btn btn-secondary">Limpiar</a>
                <button type="button" class="btn btn-success" onclick="exportarExcel()">📊 Exportar Excel</button>
            </form>

            <!-- Tabla de Stock -->
            <div class="table-container">
                <?php if (empty($productos)): ?>
                    <div class="empty-state">
                        <h3>No se encontraron productos</h3>
                        <p>Ajuste los filtros o verifique el inventario</p>
                    </div>
                <?php else: ?>
                    <table id="tablaStock">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Proveedor</th>
                                <th style="text-align: right;">Stock</th>
                                <th>Estado</th>
                                <th style="text-align: right;">Precio Unit.</th>
                                <th style="text-align: right;">Valoración</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <?php
                                    $porcentajeStock = $producto['stock_minimo'] > 0 
                                        ? min(100, ($producto['stock_total'] / $producto['stock_minimo']) * 100) 
                                        : 100;
                                    
                                    $claseProgress = 'progress-ok';
                                    if ($producto['estado_stock'] === 'BAJO') $claseProgress = 'progress-bajo';
                                    if ($producto['estado_stock'] === 'CRITICO' || $producto['estado_stock'] === 'SIN_STOCK') $claseProgress = 'progress-critico';
                                ?>
                                <tr>
                                    <td><?php echo $producto['codigo_producto'] ?: '-'; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                        <?php if ($producto['descripcion']): ?>
                                            <br><small style="color: #718096;"><?php echo htmlspecialchars($producto['descripcion']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $producto['nombre_categoria']; ?></td>
                                    <td><?php echo $producto['proveedor']; ?></td>
                                    <td style="text-align: right;">
                                        <strong><?php echo $producto['stock_total']; ?></strong>
                                        <small style="color: #a0aec0;"> / <?php echo $producto['stock_minimo']; ?></small>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $claseProgress; ?>" style="width: <?php echo $porcentajeStock; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $badgeClass = '';
                                            $badgeText = '';
                                            switch($producto['estado_stock']) {
                                                case 'OK':
                                                    $badgeClass = 'badge-ok';
                                                    $badgeText = '✓ OK';
                                                    break;
                                                case 'BAJO':
                                                    $badgeClass = 'badge-bajo';
                                                    $badgeText = '⚠ Bajo';
                                                    break;
                                                case 'CRITICO':
                                                    $badgeClass = 'badge-critico';
                                                    $badgeText = '🚨 Crítico';
                                                    break;
                                                case 'SIN_STOCK':
                                                    $badgeClass = 'badge-sin-stock';
                                                    $badgeText = '❌ Sin Stock';
                                                    break;
                                            }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                    </td>
                                    <td style="text-align: right;"><?php echo formatearMoneda($producto['precio_unitario']); ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($producto['valoracion']); ?></strong></td>
                                    <td class="actions">
                                        <button class="btn btn-primary btn-small" onclick="verDetalle(<?php echo $producto['id_producto']; ?>)">👁 Ver</button>
                                        <?php if (tienePermiso('stock_ajustar')): ?>
                                            <a href="ajustar.php?id=<?php echo $producto['id_producto']; ?>" class="btn btn-warning btn-small">⚙️ Ajustar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/stock.js"></script>
</body>
</html>