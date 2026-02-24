<?php
// =============================================
// modules/stock/consultar_stock.php
// Consultar stock por ubicación (AJAX)
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('stock', 'ver');

header('Content-Type: application/json');

$id_producto = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;

if ($id_producto <= 0) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'ID de producto inválido'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // Obtener información del producto
    $sql_producto = "SELECT id_producto, codigo, nombre 
                     FROM productos 
                     WHERE id_producto = ? AND estado = 'activo'";
    $stmt = $db->query($sql_producto, [$id_producto]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'Producto no encontrado'
        ]);
        exit;
    }
    
    // Obtener stock por ubicación
    $sql_stock = "SELECT 
                    u.id_ubicacion,
                    u.nombre_ubicacion,
                    u.tipo,
                    COALESCE(s.cantidad, 0) as cantidad
                  FROM ubicaciones u
                  LEFT JOIN stock s ON u.id_ubicacion = s.id_ubicacion 
                                    AND s.id_producto = ?
                  WHERE u.estado = 'activo'
                  ORDER BY u.nombre_ubicacion";
    
    $stmt = $db->query($sql_stock, [$id_producto]);
    $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrar solo ubicaciones con stock
    $ubicaciones_con_stock = array_filter($ubicaciones, function($ubi) {
        return $ubi['cantidad'] > 0;
    });
    
    // Calcular total
    $stock_total = array_sum(array_column($ubicaciones, 'cantidad'));
    
    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'ubicaciones' => array_values($ubicaciones_con_stock),
        'stock_total' => $stock_total
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Error al consultar stock: ' . $e->getMessage()
    ]);
}
?>