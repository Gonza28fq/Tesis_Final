<?php
// =============================================
// modules/clientes/buscar_cliente.php
// Buscar clientes con su lista de precios
// =============================================

require_once '../../config/constantes.php';      // 1️⃣ Primero
require_once '../../config/conexion.php';        // 2️⃣ Segundo
require_once '../../includes/funciones.php';     // 3️⃣ Tercero

iniciarSesion();  // 4️⃣ Cuarto

header('Content-Type: application/json');

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
    exit();
}

$query = isset($_GET['q']) ? limpiarInput($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'clientes' => []]);
    exit();
}

$db = getDB();

$sql = "SELECT c.id_cliente, c.nombre, c.documento, c.email, c.telefono,
        tc.nombre_tipo, lp.nombre_lista, lp.id_lista_precio
        FROM clientes c
        INNER JOIN tipos_cliente tc ON c.id_tipo_cliente = tc.id_tipo_cliente
        LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
        WHERE (c.nombre LIKE ? OR c.documento LIKE ?) AND c.estado = 'activo'
        ORDER BY c.nombre
        LIMIT 10";

$stmt = $db->query($sql, ["%$query%", "%$query%"]);
$clientes = $stmt->fetchAll();

echo json_encode(['success' => true, 'clientes' => $clientes]);