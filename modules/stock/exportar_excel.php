<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('stock_ver')) {
    die('Sin permisos');
}

try {
    $db = getDB();
    
    // Obtener los mismos filtros que en index.php
    $filtroNombre = isset($_GET['nombre']) ? sanitize($_GET['nombre']) : '';
    $filtroCategoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
    $filtroProveedor = isset($_GET['proveedor']) ? intval($_GET['proveedor']) : 0;
    $filtroUbicacion = isset($_GET['ubicacion']) ? intval($_GET['ubicacion']) : 0;
    $filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
    
    // Construir consulta
    $sql = "SELECT 
                p.codigo_producto as 'Código',
                p.nombre as 'Producto',
                c.nombre_categoria as 'Categoría',
                prov.nombre as 'Proveedor',
                COALESCE(SUM(s.cantidad), 0) as 'Stock',
                p.stock_minimo as 'Stock Mínimo',
                p.precio_unitario as 'Precio Unitario',
                (COALESCE(SUM(s.cantidad), 0) * p.precio_unitario) as 'Valoración',
                CASE 
                    WHEN COALESCE(SUM(s.cantidad), 0) <= 0 THEN 'SIN STOCK'
                    WHEN COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo THEN 'CRÍTICO'
                    WHEN COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo * 2 THEN 'BAJO'
                    ELSE 'OK'
                END AS 'Estado'
            FROM Productos p
            LEFT JOIN Stock s ON p.id_producto = s.id_producto";
    
    if ($filtroUbicacion > 0) {
        $sql .= " AND s.id_ubicacion = :ubicacion";
    }
    
    $sql .= " LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Proveedores prov ON p.id_proveedor = prov.id_proveedor
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
    
    if (!empty($filtroEstado)) {
        $sql .= " HAVING Estado = :estado";
        $params[':estado'] = $filtroEstado;
    }
    
    $sql .= " ORDER BY p.nombre";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar CSV (compatible con Excel)
    $filename = "inventario_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Agregar BOM UTF-8 para que Excel lo reconozca correctamente
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Escribir encabezado
    if (!empty($productos)) {
        fputcsv($output, array_keys($productos[0]), ';');
        
        // Escribir datos
        foreach ($productos as $row) {
            fputcsv($output, $row, ';');
        }
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Error al exportar: " . $e->getMessage());
    die('Error al generar el archivo');
}
?>