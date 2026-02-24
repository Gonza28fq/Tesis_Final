<?php
// =============================================
// modules/clientes/acciones.php
// Acciones AJAX para Clientes
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
        requierePermiso('clientes', 'eliminar');
        
        $id = (int)$_POST['id'];
        
        if ($id <= 0) {
            respuestaError('ID no válido');
        }
        
        // Verificar que el cliente existe
        $sql = "SELECT nombre FROM clientes WHERE id_cliente = ?";
        $stmt = $db->query($sql, [$id]);
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            respuestaError('Cliente no encontrado');
        }
        
        // Verificar si tiene ventas asociadas
        $count = $db->count("SELECT COUNT(*) FROM ventas WHERE id_cliente = ?", [$id]);
        
        if ($count > 0) {
            respuestaError("No se puede eliminar. El cliente tiene $count venta(s) registrada(s). Se cambiará a inactivo.");
        }
        
        try {
            // Cambiar estado a inactivo
            $sql = "UPDATE clientes SET estado = 'inactivo' WHERE id_cliente = ?";
            $db->execute($sql, [$id]);
            
            registrarAuditoria('clientes', 'eliminar', "Cliente: {$cliente['nombre']} (ID: $id)");
            
            respuestaExito('Cliente eliminado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al eliminar el cliente: ' . $e->getMessage());
        }
        break;
        
    case 'cambiar_estado':
        requierePermiso('clientes', 'editar');
        
        $id = (int)$_POST['id'];
        $estado = limpiarInput($_POST['estado']);
        
        if ($id <= 0 || !in_array($estado, ['activo', 'inactivo'])) {
            respuestaError('Datos no válidos');
        }
        
        try {
            $sql = "UPDATE clientes SET estado = ? WHERE id_cliente = ?";
            $db->execute($sql, [$estado, $id]);
            
            registrarAuditoria('clientes', 'cambiar_estado', "Cliente ID: $id - Nuevo estado: $estado");
            
            respuestaExito('Estado actualizado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al actualizar el estado: ' . $e->getMessage());
        }
        break;
        
    default:
        respuestaError('Acción no válida');
}