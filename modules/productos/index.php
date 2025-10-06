<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('productos_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Filtros
$filtroBusqueda = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$filtroCategoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtroProveedor = isset($_GET['proveedor']) ? intval($_GET['proveedor']) : 0;
$filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : 'activos';

try {
    $db = getDB();
    
    // Obtener categorías
    $sqlCategorias = "SELECT * FROM Categorias WHERE activo = 1 ORDER BY nombre_categoria";
    $stmtCat = $db->query($sqlCategorias);
    $categorias = $stmtCat->fetchAll();
    
    // Obtener proveedores
    $sqlProveedores = "SELECT * FROM Proveedores WHERE activo = 1 ORDER BY nombre";
    $stmtProv = $db->query($sqlProveedores);
    $proveedores = $stmtProv->fetchAll();
    
    // Construir consulta de productos
    $sql = "SELECT 
                p.*,
                c.nombre_categoria,
                prov.nombre as proveedor_nombre,
                COALESCE(SUM(s.cantidad), 0) as stock_total
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Proveedores prov ON p.id_proveedor = prov.id_proveedor
            LEFT JOIN Stock s ON p.id_producto = s.id_producto
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtroBusqueda)) {
        $sql .= " AND (p.nombre LIKE :busqueda OR p.codigo_producto LIKE :busqueda2)";
        $searchTerm = "%{$filtroBusqueda}%";
        $params[':busqueda'] = $searchTerm;
        $params[':busqueda2'] = $searchTerm;
    }
    
    if ($filtroCategoria > 0) {
        $sql .= " AND p.id_categoria = :categoria";
        $params[':categoria'] = $filtroCategoria;
    }
    
    if ($filtroProveedor > 0) {
        $sql .= " AND p.id_proveedor = :proveedor";
        $params[':proveedor'] = $filtroProveedor;
    }
    
    if ($filtroEstado === 'activos') {
        $sql .= " AND p.activo = 1";
    } elseif ($filtroEstado === 'inactivos') {
        $sql .= " AND p.activo = 0";
    }
    
    $sql .= " GROUP BY p.id_producto ORDER BY p.nombre";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
    
    // Estadísticas
    $sqlStats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
                 FROM Productos";
    $stmtStats = $db->query($sqlStats);
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $productos = [];
    $categorias = [];
    $proveedores = [];
    $stats = ['total' => 0, 'activos' => 0, 'inactivos' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Sistema de Gestión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ed8936 0%, #f5a623 100%);
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
            background: linear-gradient(135deg, #ed8936 0%, #f5a623 100%);
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
            background: #ed8936;
            color: white;
        }

        .btn-success {
            background: #48bb78;
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
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total { border-color: #ed8936; }
        .stat-card.activos { border-color: #48bb78; }
        .stat-card.inactivos { border-color: #a0aec0; }

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

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
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

        .badge-activo {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-inactivo {
            background: #e2e8f0;
            color: #718096;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #c6f6d5;
            border: 2px solid #9ae6b4;
            color: #22543d;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Gestión de Productos</h1>
            <div class="header-actions">
                <?php if (tienePermiso('productos_crear')): ?>
                    <a href="nuevo.php" class="btn btn-success">➕ Nuevo Producto</a>
                <?php endif; ?>
                <a href="categorias.php" class="btn btn-primary">🏷️ Categorías</a>
                <a href="proveedores.php" class="btn btn-primary">🏭 Proveedores</a>
                <button class="btn btn-secondary" onclick="exportarExcel()">📊 Exportar</button>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✓ <?php 
                        if ($_GET['success'] === 'created') echo 'Producto creado exitosamente';
                        elseif ($_GET['success'] === 'updated') echo 'Producto actualizado exitosamente';
                        elseif ($_GET['success'] === 'deleted') echo 'Producto desactivado exitosamente';
                    ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Total Productos</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>

                <div class="stat-card activos">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Activos</div>
                    <div class="stat-value"><?php echo $stats['activos']; ?></div>
                </div>

                <div class="stat-card inactivos">
                    <div class="stat-icon">⊗</div>
                    <div class="stat-label">Inactivos</div>
                    <div class="stat-value"><?php echo $stats['inactivos']; ?></div>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros de Búsqueda</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Buscar</label>
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($filtroBusqueda); ?>" placeholder="Nombre o código...">
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
                        <label>Estado</label>
                        <select name="estado">
                            <option value="todos" <?php echo $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="activos" <?php echo $filtroEstado === 'activos' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivos" <?php echo $filtroEstado === 'inactivos' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="index.php" class="btn btn-secondary">Limpiar</a>
            </form>

            <!-- Tabla de Productos -->
            <div class="table-container">
                <?php if (empty($productos)): ?>
                    <div class="empty-state">
                        <h3>No se encontraron productos</h3>
                        <p>Ajuste los filtros o cree un nuevo producto</p>
                    </div>
                <?php else: ?>
                    <table id="tablaProductos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Proveedor</th>
                                <th style="text-align: right;">Precio</th>
                                <th style="text-align: right;">Stock</th>
                                <th style="text-align: right;">Stock Mín.</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?php echo $producto['codigo_producto'] ?: '-'; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                        <?php if ($producto['descripcion']): ?>
                                            <br><small style="color: #718096;"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)) . (strlen($producto['descripcion']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $producto['nombre_categoria']; ?></td>
                                    <td><?php echo $producto['proveedor_nombre']; ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($producto['precio_unitario']); ?></strong></td>
                                    <td style="text-align: right;">
                                        <strong><?php echo $producto['stock_total']; ?></strong>
                                    </td>
                                    <td style="text-align: right;"><?php echo $producto['stock_minimo']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $producto['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $producto['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <?php if (tienePermiso('productos_editar')): ?>
                                            <a href="editar.php?id=<?php echo $producto['id_producto']; ?>" class="btn btn-primary btn-small">✏️ Editar</a>
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

    <script>
        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            window.open('exportar_productos.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>