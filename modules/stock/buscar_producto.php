<?php
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('stock', 'ver');

header('Content-Type: application/json');

try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'productos' => []]);
        exit;
    }
    
    $db = getDB();
    
    // Construir consulta
    if ($tipo === 'nombre') {
        $where = "p.nombre LIKE ?";
        $params = ["%{$query}%"];
    } elseif ($tipo === 'codigo') {
        $where = "p.codigo LIKE ?";
        $params = ["%{$query}%"];
    } else {
        $where = "(p.nombre LIKE ? OR p.codigo LIKE ?)";
        $params = ["%{$query}%", "%{$query}%"];
    }
    
    $sql = "SELECT 
                p.id_producto,
                p.codigo,
                p.nombre,
                COALESCE(c.nombre_categoria, 'Sin categoría') as nombre_categoria,
                COALESCE(SUM(s.cantidad), 0) as stock_total
            FROM productos p
            LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
            LEFT JOIN stock s ON p.id_producto = s.id_producto
            WHERE p.estado = 'activo' AND {$where}
            GROUP BY p.id_producto
            ORDER BY p.nombre
            LIMIT 15";
    
    $stmt = $db->query($sql, $params);
    $productos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'productos' => $productos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage(),
        'productos' => []
    ]);
}
