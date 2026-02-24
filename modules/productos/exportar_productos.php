<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('productos_ver')) {
    die('Sin permisos');
}

// Obtener filtros (los mismos que en index.php)
$filtroBusqueda = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$filtroCategoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtroProveedor = isset($_GET['proveedor']) ? intval($_GET['proveedor']) : 0;
$filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : 'activos';

try {
    $db = getDB();
    
    // Construir consulta de productos
    $sql = "SELECT 
                p.codigo_producto,
                p.nombre,
                p.descripcion,
                c.nombre_categoria,
                prov.nombre as proveedor_nombre,
                p.precio_unitario,
                COALESCE(SUM(s.cantidad), 0) as stock_total,
                p.stock_minimo,
                CASE WHEN p.activo = 1 THEN 'Activo' ELSE 'Inactivo' END as estado
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
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die("Error al obtener los datos");
}

// Configurar headers para descarga Excel
$filename = "productos_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Generar contenido HTML para Excel
echo "\xEF\xBB\xBF"; // BOM para UTF-8
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th {
            background-color: #ed8936;
            color: white;
            font-weight: bold;
            padding: 10px;
            border: 1px solid #000;
            text-align: left;
        }
        td {
            padding: 8px;
            border: 1px solid #ccc;
        }
        .header-info {
            margin-bottom: 20px;
        }
        .header-info h2 {
            color: #ed8936;
        }
        .numero {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header-info">
        <h2>Reporte de Productos</h2>
        <p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
        <p><strong>Usuario:</strong> <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?></p>
        <?php if (!empty($filtroBusqueda)): ?>
            <p><strong>Filtro búsqueda:</strong> <?php echo htmlspecialchars($filtroBusqueda); ?></p>
        <?php endif; ?>
        <p><strong>Total de productos:</strong> <?php echo count($productos); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Descripción</th>
                <th>Categoría</th>
                <th>Proveedor</th>
                <th>Precio Unitario</th>
                <th>Stock Actual</th>
                <th>Stock Mínimo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($productos)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #666;">No hay productos para exportar</td>
                </tr>
            <?php else: ?>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($producto['codigo_producto'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($producto['descripcion'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($producto['nombre_categoria']); ?></td>
                        <td><?php echo htmlspecialchars($producto['proveedor_nombre']); ?></td>
                        <td class="numero"><?php echo number_format($producto['precio_unitario'], 2, ',', '.'); ?></td>
                        <td class="numero"><?php echo $producto['stock_total']; ?></td>
                        <td class="numero"><?php echo $producto['stock_minimo']; ?></td>
                        <td><?php echo $producto['estado']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 11px; color: #666;">
        <p><strong>Resumen:</strong></p>
        <ul>
            <li>Total de productos exportados: <?php echo count($productos); ?></li>
            <li>Sistema de Gestión Comercial</li>
            <li>Generado el: <?php echo date('d/m/Y \a \l\a\s H:i:s'); ?></li>
        </ul>
    </div>
</body>
</html>