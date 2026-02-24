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
        
        // Buscar productos (todos, no solo con stock)
        // Incluir el último precio de compra si existe
        $sql = "SELECT 
                    p.id_producto,
                    p.codigo_producto,
                    p.nombre,
                    p.descripcion,
                    p.precio_unitario,
                    c.nombre_categoria,
                    COALESCE(SUM(s.cantidad), 0) as stock_total,
                    (
                        SELECT di.precio_unitario 
                        FROM Detalle_Ingreso di 
                        INNER JOIN Ingresos i ON di.id_ingreso = i.id_ingreso
                        WHERE di.id_producto = p.id_producto 
                        ORDER BY i.fecha DESC 
                        LIMIT 1
                    ) as ultimo_precio_compra
                FROM Productos p
                LEFT JOIN Stock s ON p.id_producto = s.id_producto
                LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                WHERE p.activo = 1 
                AND (
                    p.nombre LIKE :query1 
                    OR p.codigo_producto LIKE :query2
                    OR c.nombre_categoria LIKE :query3
                )
                GROUP BY p.id_producto
                ORDER BY p.nombre
                LIMIT 15";
        
        $stmt = $db->prepare($sql);
        $searchTerm = "%{$query}%";
        $stmt->execute([
            ':query1' => $searchTerm,
            ':query2' => $searchTerm,
            ':query3' => $searchTerm
        ]);
        
        $productos = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'productos' => $productos
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error en buscar_producto_compra.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ], 500);
}
?>