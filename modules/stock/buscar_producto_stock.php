<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
        
        if (strlen($query) < 2) {
            jsonResponse([
                'success' => false,
                'message' => 'La búsqueda debe tener al menos 2 caracteres'
            ]);
        }
        
        $sql = "SELECT 
                    p.id_producto,
                    p.codigo_producto,
                    p.nombre,
                    c.nombre_categoria,
                    COALESCE(SUM(s.cantidad), 0) as stock_total
                FROM Productos p
                LEFT JOIN Stock s ON p.id_producto = s.id_producto
                LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                WHERE p.activo = 1 
                AND (
                    p.nombre LIKE :query1 
                    OR p.codigo_producto LIKE :query2
                )
                GROUP BY p.id_producto
                ORDER BY p.nombre
                LIMIT 15";
        
        $stmt = $db->prepare($sql);
        $searchTerm = "%{$query}%";
        $stmt->execute([
            ':query1' => $searchTerm,
            ':query2' => $searchTerm
        ]);
        
        $productos = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'productos' => $productos
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error en buscar_producto_stock.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error en el servidor'
    ], 500);
}
?>