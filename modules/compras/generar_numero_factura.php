<?php
// =============================================
// modules/compras/generar_numero_factura.php
// Generar número de factura automático
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

iniciarSesion();

$db = getDB();

// Obtener el último número de compra
$sql = "SELECT numero_compra FROM compras ORDER BY id_compra DESC LIMIT 1";
$stmt = $db->query($sql);
$ultimo = $stmt->fetch();

if ($ultimo) {
    // Extraer número y sumar 1
    $ultimo_num = (int)str_replace('CP-', '', $ultimo['numero_compra']);
    $nuevo_num = $ultimo_num + 1;
} else {
    $nuevo_num = 1;
}

// Formato: 0001-00000001
$numero_factura = '0001-' . str_pad($nuevo_num, 8, '0', STR_PAD_LEFT);

echo json_encode([
    'success' => true,
    'numero' => $numero_factura
]);