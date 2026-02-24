<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    $query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
    
    if (strlen($query) < 2) {
        jsonResponse(['success' => false, 'message' => 'Mínimo 2 caracteres']);
    }
    
    $sql = "SELECT 
                p.id_producto,
                p.codigo_producto,
                p.nombre,
                COALESCE(SUM(s.cantidad), 0) as stock_total
            FROM Productos p
            LEFT JOIN Stock s ON p.id_producto = s.id_producto
            WHERE p.activo = 1 
            AND (p.nombre LIKE :query1 OR p.codigo_producto LIKE :query2)
            GROUP BY p.id_producto
            HAVING stock_total > 0
            ORDER BY p.nombre
            LIMIT 15";
    
    $stmt = $db->prepare($sql);
    $searchTerm = "%{$query}%";
    $stmt->execute([':query1' => $searchTerm, ':query2' => $searchTerm]);
    
    jsonResponse(['success' => true, 'productos' => $stmt->fetchAll()]);
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Error del servidor'], 500);
}
?>