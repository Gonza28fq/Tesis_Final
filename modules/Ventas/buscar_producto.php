<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json');

if (!tienePermiso('ventas_crear')) {
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

$busqueda = isset($_GET['q']) ? sanitize($_GET['q']) : '';

if (empty($busqueda)) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDB();
    
    // Buscar productos activos con stock
    $sql = "SELECT 
                p.id_producto,
                p.nombre,
                p.codigo_producto,
                p.precio_unitario,
                c.nombre_categoria,
                COALESCE(SUM(s.cantidad), 0) as stock_disponible
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Stock s ON p.id_producto = s.id_producto
            WHERE p.activo = 1
            AND (p.nombre LIKE :busqueda 
                 OR p.codigo_producto LIKE :busqueda2)
            GROUP BY p.id_producto
            HAVING stock_disponible > 0
            ORDER BY p.nombre
            LIMIT 10";
    
    $searchTerm = "%{$busqueda}%";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':busqueda' => $searchTerm,
        ':busqueda2' => $searchTerm
    ]);
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos para el frontend
    $resultado = array_map(function($prod) {
        return [
            'id' => $prod['id_producto'],
            'nombre' => $prod['nombre'],
            'codigo' => $prod['codigo_producto'] ?: 'S/C',
            'precio' => floatval($prod['precio_unitario']),
            'precio_formato' => formatearMoneda($prod['precio_unitario']),
            'categoria' => $prod['nombre_categoria'],
            'stock' => intval($prod['stock_disponible'])
        ];
    }, $productos);
    
    echo json_encode($resultado);
    
} catch (PDOException $e) {
    error_log("Error al buscar productos: " . $e->getMessage());
    echo json_encode(['error' => 'Error en la búsqueda']);
}
?>