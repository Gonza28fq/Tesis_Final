<?php
// =============================================
// modules/stock/get_stock.php
// Obtener stock de un producto en una ubicación
// =============================================

require_once '../../includes/funciones.php';

header('Content-Type: application/json');

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit();
}

$id_producto = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;
$id_ubicacion = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;

if ($id_producto <= 0 || $id_ubicacion <= 0) {
    echo json_encode(['success' => false, 'mensaje' => 'Parámetros inválidos']);
    exit();
}

$db = getDB();

$sql = "SELECT cantidad FROM stock WHERE id_producto = ? AND id_ubicacion = ?";
$stmt = $db->query($sql, [$id_producto, $id_ubicacion]);
$stock = $stmt->fetch();

$cantidad = $stock ? (int)$stock['cantidad'] : 0;

echo json_encode(['success' => true, 'cantidad' => $cantidad]);