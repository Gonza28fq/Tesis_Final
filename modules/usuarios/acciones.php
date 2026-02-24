<?php
// =============================================
// modules/usuarios/acciones.php
// Acciones AJAX para Usuarios
// =============================================

require_once '../../includes/funciones.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaError('Método no permitido');
}

$accion = $_POST['accion'] ?? '';
$db = getDB();

switch ($accion) {
    case 'eliminar':
        requierePermiso('usuarios', 'eliminar');
        
        $id = (int)$_POST['id'];
        
        if ($id <= 0) {
            respuestaError('ID no válido');
        }
        
        // No permitir eliminar al administrador
        if ($id == 1) {
            respuestaError('No se puede eliminar el usuario administrador');
        }
        
        // No permitir que un usuario se elimine a sí mismo
        if ($id == $_SESSION['usuario_id']) {
            respuestaError('No puedes eliminar tu propio usuario');
        }
        
        // Verificar que el usuario existe
        $sql = "SELECT usuario FROM usuarios WHERE id_usuario = ?";
        $stmt = $db->query($sql, [$id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            respuestaError('Usuario no encontrado');
        }
        
        try {
            // Cambiar estado a inactivo
            $sql = "UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?";
            $db->execute($sql, [$id]);
            
            registrarAuditoria('usuarios', 'eliminar', "Usuario: {$usuario['usuario']} (ID: $id)");
            
            respuestaExito('Usuario eliminado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al eliminar el usuario: ' . $e->getMessage());
        }
        break;
        
    case 'cambiar_estado':
        requierePermiso('usuarios', 'editar');
        
        $id = (int)$_POST['id'];
        $estado = limpiarInput($_POST['estado']);
        
        if ($id <= 0 || !in_array($estado, ['activo', 'inactivo'])) {
            respuestaError('Datos no válidos');
        }
        
        // No permitir cambiar estado del administrador
        if ($id == 1) {
            respuestaError('No se puede cambiar el estado del administrador');
        }
        
        try {
            $sql = "UPDATE usuarios SET estado = ? WHERE id_usuario = ?";
            $db->execute($sql, [$estado, $id]);
            
            registrarAuditoria('usuarios', 'cambiar_estado', "Usuario ID: $id - Nuevo estado: $estado");
            
            respuestaExito('Estado actualizado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al actualizar el estado: ' . $e->getMessage());
        }
        break;
        
    case 'resetear_password':
        requierePermiso('usuarios', 'editar');
        
        $id = (int)$_POST['id'];
        $nueva_password = generarToken(4); // Genera una contraseña aleatoria de 8 caracteres
        
        if ($id <= 0) {
            respuestaError('ID no válido');
        }
        
        try {
            $sql = "UPDATE usuarios SET password = ? WHERE id_usuario = ?";
            $db->execute($sql, [encriptarPassword($nueva_password), $id]);
            
            registrarAuditoria('usuarios', 'resetear_password', "Usuario ID: $id");
            
            respuestaExito('Contraseña reseteada correctamente', ['nueva_password' => $nueva_password]);
        } catch (Exception $e) {
            respuestaError('Error al resetear contraseña: ' . $e->getMessage());
        }
        break;
        
    default:
        respuestaError('Acción no válida');
}