<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    $sql = "SELECT id_ubicacion, nombre, descripcion 
            FROM Ubicaciones 
            WHERE activo = 1 
            ORDER BY nombre";
    
    $stmt = $db->query($sql);
    $ubicaciones = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'ubicaciones' => $ubicaciones
    ]);
    
} catch (PDOException $e) {
    error_log("Error en cargar_ubicaciones.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error al cargar ubicaciones'
    ], 500);
}
?>