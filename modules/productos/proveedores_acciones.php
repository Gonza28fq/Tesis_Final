<?php
// =============================================
// modules/productos/proveedores_acciones.php
// Acciones AJAX para Proveedores
// =============================================

ob_start();
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';
ob_end_clean();

iniciarSesion();

header('Content-Type: application/json');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaError('Método no permitido');
}

$accion = $_POST['accion'] ?? '';
$db = getDB();
$pdo = $db->getConexion();

try {
    switch ($accion) {
        case 'crear':
            requierePermiso('productos', 'crear');
            
            $datos = [
                'nombre_proveedor' => limpiarInput($_POST['nombre_proveedor']),
                'razon_social' => limpiarInput($_POST['razon_social'] ?? ''),
                'cuit' => limpiarInput($_POST['cuit'] ?? ''),
                'email' => limpiarInput($_POST['email'] ?? ''),
                'telefono' => limpiarInput($_POST['telefono'] ?? ''),
                'direccion' => limpiarInput($_POST['direccion'] ?? ''),
                'ciudad' => limpiarInput($_POST['ciudad'] ?? ''),
                'provincia' => limpiarInput($_POST['provincia'] ?? ''),
                'codigo_postal' => limpiarInput($_POST['codigo_postal'] ?? ''),
                'contacto_nombre' => limpiarInput($_POST['contacto_nombre'] ?? ''),
                'contacto_telefono' => limpiarInput($_POST['contacto_telefono'] ?? ''),
                'contacto_email' => limpiarInput($_POST['contacto_email'] ?? ''),
                'condicion_pago' => limpiarInput($_POST['condicion_pago'] ?? ''),
                'observaciones' => limpiarInput($_POST['observaciones'] ?? ''),
                'estado' => $_POST['estado'] ?? 'activo'
            ];
            
            if (empty($datos['nombre_proveedor'])) {
                respuestaError('El nombre del proveedor es obligatorio');
            }
            
            $sql = "INSERT INTO proveedores 
                    (nombre_proveedor, razon_social, cuit, email, telefono, direccion, 
                     ciudad, provincia, codigo_postal, contacto_nombre, contacto_telefono, 
                     contacto_email, condicion_pago, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($datos));
            
            registrarAuditoria('productos', 'crear_proveedor', "Proveedor: {$datos['nombre_proveedor']}");
            
            respuestaExito('Proveedor creado correctamente');
            break;
            
        case 'editar':
            requierePermiso('productos', 'editar');
            
            $id = (int)$_POST['id_proveedor'];
            
            if ($id <= 0) {
                respuestaError('ID no válido');
            }
            
            $datos = [
                'nombre_proveedor' => limpiarInput($_POST['nombre_proveedor']),
                'razon_social' => limpiarInput($_POST['razon_social'] ?? ''),
                'cuit' => limpiarInput($_POST['cuit'] ?? ''),
                'email' => limpiarInput($_POST['email'] ?? ''),
                'telefono' => limpiarInput($_POST['telefono'] ?? ''),
                'direccion' => limpiarInput($_POST['direccion'] ?? ''),
                'ciudad' => limpiarInput($_POST['ciudad'] ?? ''),
                'provincia' => limpiarInput($_POST['provincia'] ?? ''),
                'codigo_postal' => limpiarInput($_POST['codigo_postal'] ?? ''),
                'contacto_nombre' => limpiarInput($_POST['contacto_nombre'] ?? ''),
                'contacto_telefono' => limpiarInput($_POST['contacto_telefono'] ?? ''),
                'contacto_email' => limpiarInput($_POST['contacto_email'] ?? ''),
                'condicion_pago' => limpiarInput($_POST['condicion_pago'] ?? ''),
                'observaciones' => limpiarInput($_POST['observaciones'] ?? ''),
                'estado' => $_POST['estado'] ?? 'activo'
            ];
            
            if (empty($datos['nombre_proveedor'])) {
                respuestaError('El nombre del proveedor es obligatorio');
            }
            
            $sql = "UPDATE proveedores SET 
                    nombre_proveedor = ?, razon_social = ?, cuit = ?, email = ?, 
                    telefono = ?, direccion = ?, ciudad = ?, provincia = ?, 
                    codigo_postal = ?, contacto_nombre = ?, contacto_telefono = ?, 
                    contacto_email = ?, condicion_pago = ?, observaciones = ?, estado = ?
                    WHERE id_proveedor = ?";
            
            $params = array_values($datos);
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            registrarAuditoria('productos', 'editar_proveedor', "Proveedor ID: $id");
            
            respuestaExito('Proveedor actualizado correctamente');
            break;
            
        case 'eliminar':
            requierePermiso('productos', 'eliminar');
            
            $id = (int)$_POST['id_proveedor'];
            
            if ($id <= 0) {
                respuestaError('ID no válido');
            }
            
            // Verificar que el proveedor existe
            $sql = "SELECT nombre_proveedor FROM proveedores WHERE id_proveedor = ?";
            $stmt = $db->query($sql, [$id]);
            $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$proveedor) {
                respuestaError('Proveedor no encontrado');
            }
            
            // Verificar si tiene productos asociados
            $sql_count = "SELECT COUNT(*) as total FROM productos WHERE id_proveedor = ?";
            $stmt = $db->query($sql_count, [$id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $resultado['total'];
            
            if ($count > 0) {
                respuestaError("No se puede eliminar. Hay $count productos asociados a este proveedor");
            }
            
            // Cambiar estado a inactivo
            $sql = "UPDATE proveedores SET estado = 'inactivo' WHERE id_proveedor = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            registrarAuditoria('productos', 'eliminar_proveedor', "Proveedor: {$proveedor['nombre_proveedor']} (ID: $id)");
            
            respuestaExito('Proveedor eliminado correctamente');
            break;
            
        default:
            respuestaError('Acción no válida', ['accion' => $accion]);
    }
} catch (Exception $e) {
    error_log("Error en proveedores_acciones.php: " . $e->getMessage());
    respuestaError('Error: ' . $e->getMessage(), [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>