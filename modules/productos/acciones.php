<?php
// =============================================
// modules/productos/acciones.php
// Acciones AJAX para Productos
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion(); // ← AGREGADO

header('Content-Type: application/json');

// Funciones helper para respuestas JSON
function respuestaError($mensaje) {
    echo json_encode(['success' => false, 'mensaje' => $mensaje]);
    exit;
}

function respuestaExito($mensaje, $data = []) {
    echo json_encode(array_merge(['success' => true, 'mensaje' => $mensaje], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaError('Método no permitido');
}

$accion = $_POST['accion'] ?? '';
$db = getDB();

switch ($accion) {
    case 'eliminar':
        if (!tienePermiso('productos', 'eliminar')) {
            respuestaError('No tiene permisos para eliminar productos');
        }
        
        $id = (int)$_POST['id'];
        
        if ($id <= 0) {
            respuestaError('ID no válido');
        }
        
        // Verificar que el producto existe
        $sql = "SELECT nombre, estado FROM productos WHERE id_producto = ?";
        $stmt = $db->query($sql, [$id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            respuestaError('Producto no encontrado');
        }
        
        try {
            // Cambiar estado a inactivo en lugar de eliminar
            $sql = "UPDATE productos SET estado = 'inactivo' WHERE id_producto = ?";
            $db->query($sql, [$id]);
            
            registrarAuditoria('productos', 'eliminar', "Producto inactivado: {$producto['nombre']} (ID: $id)");
            
            respuestaExito('Producto marcado como inactivo correctamente');
        } catch (Exception $e) {
            respuestaError('Error al eliminar el producto: ' . $e->getMessage());
        }
        break;
        
    case 'cambiar_estado':
        if (!tienePermiso('productos', 'editar')) {
            respuestaError('No tiene permisos para editar productos');
        }
        
        $id = (int)$_POST['id'];
        $estado = limpiarInput($_POST['estado']);
        
        if ($id <= 0 || !in_array($estado, ['activo', 'inactivo'])) {
            respuestaError('Datos no válidos');
        }
        
        try {
            $sql = "UPDATE productos SET estado = ? WHERE id_producto = ?";
            $db->query($sql, [$estado, $id]);
            
            registrarAuditoria('productos', 'cambiar_estado', "Producto ID: $id - Nuevo estado: $estado");
            
            respuestaExito('Estado actualizado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al actualizar el estado: ' . $e->getMessage());
        }
        break;
        
    case 'verificar_codigo':
        // Verificar si un código ya existe
        $codigo = limpiarInput($_POST['codigo']);
        $id_producto = (int)($_POST['id_producto'] ?? 0);
        
        if ($id_producto > 0) {
            $sql = "SELECT COUNT(*) as total FROM productos WHERE codigo = ? AND id_producto != ?";
            $stmt = $db->query($sql, [$codigo, $id_producto]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $existe = $result['total'];
        } else {
            $sql = "SELECT COUNT(*) as total FROM productos WHERE codigo = ?";
            $stmt = $db->query($sql, [$codigo]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $existe = $result['total'];
        }
        
        respuestaExito('Verificación completada', ['existe' => $existe > 0]);
        break;
        
    default:
        respuestaError('Acción no válida');
}