<?php
// =============================================
// modules/roles/roles_acciones.php
// COMPATIBLE CON TU CLASE DATABASE
// =============================================

ob_start();
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';
ob_end_clean();

ini_set('display_errors', 0);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'mensaje' => 'Error fatal del servidor',
            'debug' => [
                'error' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]
        ]);
        exit;
    }
});

iniciarSesion();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

function respuestaExito($mensaje, $datos = []) {
    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'datos' => $datos
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function respuestaError($mensaje, $debug = []) {
    echo json_encode([
        'success' => false,
        'mensaje' => $mensaje,
        'debug' => $debug
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function registrarAuditoriaSafe($modulo, $accion, $detalle = '') {
    try {
        if (function_exists('registrarAuditoria')) {
            registrarAuditoria($modulo, $accion, $detalle);
        }
    } catch (Exception $e) {
        error_log("Error en auditoría: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaError('Método no permitido');
}

try {
    requierePermiso('roles', 'gestionar');
} catch (Exception $e) {
    respuestaError('No tiene permisos: ' . $e->getMessage());
}

$accion = $_POST['accion'] ?? '';
$db = getDB();
$pdo = $db->getConexion();

try {
    switch ($accion) {
        case 'guardar_cambios_rol':
            $id_rol = isset($_POST['id_rol']) ? (int)$_POST['id_rol'] : 0;
            $asignar_json = $_POST['asignar'] ?? '[]';
            $quitar_json = $_POST['quitar'] ?? '[]';
            
            if ($id_rol <= 0) {
                respuestaError('ID de rol inválido', ['id_rol' => $id_rol]);
            }
            
            if ($id_rol == 1) {
                respuestaError('No se puede modificar el rol de Administrador');
            }
            
            $permisos_asignar = json_decode($asignar_json, true);
            $permisos_quitar = json_decode($quitar_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                respuestaError('Error al decodificar JSON', ['error' => json_last_error_msg()]);
            }
            
            if (!is_array($permisos_asignar)) $permisos_asignar = [];
            if (!is_array($permisos_quitar)) $permisos_quitar = [];
            
            if (empty($permisos_asignar) && empty($permisos_quitar)) {
                respuestaError('No hay cambios para guardar');
            }
            
            $pdo->beginTransaction();
            
            $asignados = 0;
            $quitados = 0;
            
            try {
                // Asignar permisos
                foreach ($permisos_asignar as $id_permiso) {
                    $id_permiso = (int)$id_permiso;
                    
                    if ($id_permiso <= 0) continue;
                    
                    // Verificar si ya existe usando query()
                    $sql_check = "SELECT COUNT(*) as total FROM roles_permisos WHERE id_rol = ? AND id_permiso = ?";
                    $stmt = $db->query($sql_check, [$id_rol, $id_permiso]);
                    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                    $existe = $resultado['total'] ?? 0;
                    
                    if ($existe == 0) {
                        // Insertar usando PDO directamente
                        $sql = "INSERT INTO roles_permisos (id_rol, id_permiso) VALUES (?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$id_rol, $id_permiso]);
                        $asignados++;
                    }
                }
                
                // Quitar permisos
                foreach ($permisos_quitar as $id_permiso) {
                    $id_permiso = (int)$id_permiso;
                    
                    if ($id_permiso <= 0) continue;
                    
                    // Eliminar usando PDO directamente
                    $sql = "DELETE FROM roles_permisos WHERE id_rol = ? AND id_permiso = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id_rol, $id_permiso]);
                    $quitados++;
                }
                
                $pdo->commit();
                
                registrarAuditoriaSafe('roles', 'cambios_masivos', 
                    "Rol ID: $id_rol - Asignados: $asignados, Quitados: $quitados");
                
                $mensaje = "Cambios guardados correctamente. ";
                if ($asignados > 0) $mensaje .= "$asignados permiso(s) asignado(s). ";
                if ($quitados > 0) $mensaje .= "$quitados permiso(s) quitado(s).";
                
                respuestaExito($mensaje, [
                    'asignados' => $asignados,
                    'quitados' => $quitados
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
        
        case 'crear':
            $nombre_rol = strtoupper(limpiarInput($_POST['nombre_rol'] ?? ''));
            $descripcion = limpiarInput($_POST['descripcion'] ?? '');
            $estado = $_POST['estado'] ?? 'activo';
            
            if (empty($nombre_rol)) {
                respuestaError('El nombre del rol es obligatorio');
            }
            
            $sql_check = "SELECT COUNT(*) as total FROM roles WHERE nombre_rol = ?";
            $stmt = $db->query($sql_check, [$nombre_rol]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $existe = $resultado['total'] ?? 0;
            
            if ($existe > 0) {
                respuestaError('Ya existe un rol con ese nombre');
            }
            
            $sql = "INSERT INTO roles (nombre_rol, descripcion, estado) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_rol, $descripcion, $estado]);
            $id_rol = $pdo->lastInsertId();
            
            registrarAuditoriaSafe('roles', 'crear', "Rol: $nombre_rol");
            
            respuestaExito('Rol creado correctamente', ['id_rol' => $id_rol]);
            break;
            
        case 'editar':
            $id_rol = isset($_POST['id_rol']) ? (int)$_POST['id_rol'] : 0;
            $nombre_rol = strtoupper(limpiarInput($_POST['nombre_rol'] ?? ''));
            $descripcion = limpiarInput($_POST['descripcion'] ?? '');
            $estado = $_POST['estado'] ?? 'activo';
            
            if ($id_rol <= 0) {
                respuestaError('ID de rol inválido');
            }
            
            if ($id_rol == 1) {
                respuestaError('No se puede modificar el rol de Administrador');
            }
            
            if (empty($nombre_rol)) {
                respuestaError('El nombre del rol es obligatorio');
            }
            
            $sql_check = "SELECT COUNT(*) as total FROM roles WHERE nombre_rol = ? AND id_rol != ?";
            $stmt = $db->query($sql_check, [$nombre_rol, $id_rol]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $existe = $resultado['total'] ?? 0;
            
            if ($existe > 0) {
                respuestaError('Ya existe otro rol con ese nombre');
            }
            
            $sql = "UPDATE roles SET nombre_rol = ?, descripcion = ?, estado = ? WHERE id_rol = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_rol, $descripcion, $estado, $id_rol]);
            
            registrarAuditoriaSafe('roles', 'editar', "Rol: $id_rol - $nombre_rol");
            
            respuestaExito('Rol actualizado correctamente');
            break;
            
        case 'eliminar':
            $id_rol = isset($_POST['id_rol']) ? (int)$_POST['id_rol'] : 0;
            
            if ($id_rol <= 0) {
                respuestaError('ID de rol inválido');
            }
            
            if ($id_rol == 1) {
                respuestaError('No se puede eliminar el rol de Administrador');
            }
            
            $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE id_rol = ?";
            $stmt = $db->query($sql_check, [$id_rol]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $usuarios = $resultado['total'] ?? 0;
            
            if ($usuarios > 0) {
                respuestaError("No se puede eliminar: hay $usuarios usuario(s) con este rol");
            }
            
            $pdo->beginTransaction();
            
            try {
                $stmt = $pdo->prepare("DELETE FROM roles_permisos WHERE id_rol = ?");
                $stmt->execute([$id_rol]);
                
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id_rol = ?");
                $stmt->execute([$id_rol]);
                
                $pdo->commit();
                
                registrarAuditoriaSafe('roles', 'eliminar', "Rol: $id_rol");
                
                respuestaExito('Rol eliminado correctamente');
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            respuestaError('Acción no válida', ['accion' => $accion]);
    }
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en roles_acciones.php: " . $e->getMessage());
    respuestaError('Error: ' . $e->getMessage(), [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>