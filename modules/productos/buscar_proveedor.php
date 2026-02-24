<?php
// =============================================
// modules/productos/buscar_proveedor.php
// Buscar proveedores vía AJAX
// =============================================

require_once '../../includes/funciones.php';

header('Content-Type: application/json');

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit();
}

$query = isset($_GET['q']) ? limpiarInput($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'proveedores' => []]);
    exit();
}

$db = getDB();

$sql = "SELECT id_proveedor, nombre_proveedor, cuit, telefono, email
        FROM proveedores
        WHERE (nombre_proveedor LIKE ? OR cuit LIKE ?) AND estado = 'activo'
        ORDER BY nombre_proveedor
        LIMIT 10";

$stmt = $db->query($sql, ["%$query%", "%$query%"]);
$proveedores = $stmt->fetchAll();

echo json_encode(['success' => true, 'proveedores' => $proveedores]);