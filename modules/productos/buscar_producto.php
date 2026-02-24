<?php
// =============================================
// modules/productos/buscar_producto.php
// Buscar productos vía AJAX
// =============================================

require_once '../../includes/funciones.php';

header('Content-Type: application/json');

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit();
}

$query = isset($_GET['q']) ? limpiarInput($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'productos' => []]);
    exit();
}

$db = getDB();

$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.precio_base,
        COALESCE(SUM(s.cantidad), 0) as stock_total
        FROM productos p
        LEFT JOIN stock s ON p.id_producto = s.id_producto
        WHERE (p.nombre LIKE ? OR p.codigo LIKE ?) AND p.estado = 'activo'
        GROUP BY p.id_producto
        ORDER BY p.nombre
        LIMIT 10";

$stmt = $db->query($sql, ["%$query%", "%$query%"]);
$productos = $stmt->fetchAll();

echo json_encode(['success' => true, 'productos' => $productos]);