<?php
// =============================================
// modules/roles/test_roles_debug.php
// ARCHIVO TEMPORAL PARA DIAGNÓSTICO
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

header('Content-Type: application/json');

$resultado = [
    'test' => 'Debug de Roles y Permisos',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

try {
    $db = getDB();
    
    // 1. Verificar conexión
    $resultado['checks']['conexion'] = 'OK';
    
    // 2. Verificar tablas
    $tablas = ['roles', 'permisos', 'roles_permisos'];
    foreach ($tablas as $tabla) {
        try {
            $count = $db->count("SELECT COUNT(*) FROM $tabla");
            $resultado['checks']["tabla_$tabla"] = "OK - $count registros";
        } catch (Exception $e) {
            $resultado['checks']["tabla_$tabla"] = "ERROR: " . $e->getMessage();
        }
    }
    
    // 3. Verificar usuario actual
    $resultado['checks']['usuario_actual'] = [
        'id' => $_SESSION['id_usuario'] ?? 'No definido',
        'nombre' => $_SESSION['nombre_usuario'] ?? 'No definido',
        'rol' => $_SESSION['nombre_rol'] ?? 'No definido'
    ];
    
    // 4. Verificar permisos del usuario
    try {
        $tiene_permiso = tienePermiso('roles', 'gestionar');
        $resultado['checks']['permiso_gestionar_roles'] = $tiene_permiso ? 'SI' : 'NO';
    } catch (Exception $e) {
        $resultado['checks']['permiso_gestionar_roles'] = 'ERROR: ' . $e->getMessage();
    }
    
    // 5. Listar roles
    $roles = $db->query("SELECT id_rol, nombre_rol FROM roles")->fetchAll();
    $resultado['checks']['roles_disponibles'] = $roles;
    
    // 6. Listar permisos
    $permisos = $db->query("SELECT id_permiso, modulo, accion FROM permisos LIMIT 5")->fetchAll();
    $resultado['checks']['permisos_muestra'] = $permisos;
    
    // 7. Probar inserción en roles_permisos (simulación)
    $resultado['checks']['test_insert'] = "Usar POST real para probar";
    
    // 8. Si viene por POST, probar acción
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        $id_rol = isset($_POST['id_rol']) ? (int)$_POST['id_rol'] : 0;
        $id_permiso = isset($_POST['id_permiso']) ? (int)$_POST['id_permiso'] : 0;
        
        $resultado['checks']['post_recibido'] = [
            'accion' => $accion,
            'id_rol' => $id_rol,
            'id_permiso' => $id_permiso
        ];
        
        if ($accion === 'test_asignar' && $id_rol > 0 && $id_permiso > 0) {
            try {
                // Verificar si existe
                $existe = $db->count(
                    "SELECT COUNT(*) FROM roles_permisos WHERE id_rol = ? AND id_permiso = ?",
                    [$id_rol, $id_permiso]
                );
                
                $resultado['checks']['existe_antes'] = $existe;
                
                if ($existe == 0) {
                    $sql = "INSERT INTO roles_permisos (id_rol, id_permiso) VALUES (?, ?)";
                    $db->execute($sql, [$id_rol, $id_permiso]);
                    $resultado['checks']['insert_test'] = 'OK - Permiso insertado';
                } else {
                    $resultado['checks']['insert_test'] = 'Ya existe';
                }
            } catch (Exception $e) {
                $resultado['checks']['insert_test'] = 'ERROR: ' . $e->getMessage();
            }
        }
    }
    
    $resultado['status'] = 'success';
    
} catch (Exception $e) {
    $resultado['status'] = 'error';
    $resultado['error'] = $e->getMessage();
    $resultado['trace'] = $e->getTraceAsString();
}

echo json_encode($resultado, JSON_PRETTY_PRINT);
?>