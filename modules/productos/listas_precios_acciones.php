<?php
// =============================================
// modules/productos/listas_precios_acciones.php
// Acciones para Listas de Precios
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'gestionar_listas');

header('Content-Type: application/json');

$db = getDB();
$pdo = $db->getConexion();
$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'crear':
            // Validaciones
            $nombre_lista = limpiarInput($_POST['nombre_lista']);
            $descripcion = limpiarInput($_POST['descripcion'] ?? '');
            $id_tipo_cliente = !empty($_POST['id_tipo_cliente']) ? (int)$_POST['id_tipo_cliente'] : null;
            $porcentaje_incremento = (float)($_POST['porcentaje_incremento'] ?? 0);
            $estado = $_POST['estado'] ?? 'activa';
            
            if (empty($nombre_lista)) {
                echo json_encode(['success' => false, 'mensaje' => 'El nombre de la lista es obligatorio']);
                exit;
            }
            
            // Verificar si ya existe
            $sql_check = "SELECT COUNT(*) as total FROM listas_precios WHERE nombre_lista = ?";
            $stmt = $db->query($sql_check, [$nombre_lista]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado['total'] > 0) {
                echo json_encode(['success' => false, 'mensaje' => 'Ya existe una lista con ese nombre']);
                exit;
            }
            
            // Insertar lista
            $sql = "INSERT INTO listas_precios (nombre_lista, descripcion, id_tipo_cliente, porcentaje_incremento, estado)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre_lista, $descripcion, $id_tipo_cliente, $porcentaje_incremento, $estado]);
            
            $id_lista_nuevo = $pdo->lastInsertId();
            
            // Generar precios para todos los productos activos
            if ($estado == 'activa') {
                generarPreciosLista($id_lista_nuevo, $porcentaje_incremento);
            }
            
            registrarAuditoria('listas_precios', 'crear', "Lista: $nombre_lista (ID: $id_lista_nuevo)");
            
            echo json_encode([
                'success' => true, 
                'mensaje' => 'Lista de precios creada correctamente. Se generaron precios para todos los productos activos.'
            ]);
            break;
            
        case 'editar':
            $id_lista_precio = (int)$_POST['id_lista_precio'];
            $nombre_lista = limpiarInput($_POST['nombre_lista']);
            $descripcion = limpiarInput($_POST['descripcion'] ?? '');
            $id_tipo_cliente = !empty($_POST['id_tipo_cliente']) ? (int)$_POST['id_tipo_cliente'] : null;
            $porcentaje_incremento = (float)($_POST['porcentaje_incremento'] ?? 0);
            $estado = $_POST['estado'] ?? 'activa';
            
            if (empty($nombre_lista)) {
                echo json_encode(['success' => false, 'mensaje' => 'El nombre de la lista es obligatorio']);
                exit;
            }
            
            // Verificar si el nombre ya existe (excluyendo el actual)
            $sql_check = "SELECT COUNT(*) as total FROM listas_precios 
                         WHERE nombre_lista = ? AND id_lista_precio != ?";
            $stmt = $db->query($sql_check, [$nombre_lista, $id_lista_precio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado['total'] > 0) {
                echo json_encode(['success' => false, 'mensaje' => 'Ya existe otra lista con ese nombre']);
                exit;
            }
            
            // Obtener porcentaje anterior
            $sql_old = "SELECT porcentaje_incremento FROM listas_precios WHERE id_lista_precio = ?";
            $stmt = $db->query($sql_old, [$id_lista_precio]);
            $lista_old = $stmt->fetch(PDO::FETCH_ASSOC);
            $porcentaje_anterior = $lista_old['porcentaje_incremento'];
            
            // Actualizar lista
            $sql = "UPDATE listas_precios SET 
                    nombre_lista = ?, descripcion = ?, id_tipo_cliente = ?, 
                    porcentaje_incremento = ?, estado = ?
                    WHERE id_lista_precio = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre_lista, $descripcion, $id_tipo_cliente, 
                $porcentaje_incremento, $estado, $id_lista_precio
            ]);
            
            // Si cambió el porcentaje, regenerar precios
            if ($porcentaje_anterior != $porcentaje_incremento) {
                regenerarPreciosLista($id_lista_precio, $porcentaje_incremento);
                $mensaje_extra = ' Los precios fueron recalculados.';
            } else {
                $mensaje_extra = '';
            }
            
            registrarAuditoria('listas_precios', 'editar', "Lista: $nombre_lista (ID: $id_lista_precio)");
            
            echo json_encode([
                'success' => true, 
                'mensaje' => 'Lista de precios actualizada correctamente.' . $mensaje_extra
            ]);
            break;
            
        case 'eliminar':
            $id_lista_precio = (int)$_POST['id_lista_precio'];
            
            // Verificar que no tenga productos
            $sql_check = "SELECT COUNT(*) as total FROM productos_precios WHERE id_lista_precio = ?";
            $stmt = $db->query($sql_check, [$id_lista_precio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado['total'] > 0) {
                echo json_encode([
                    'success' => false, 
                    'mensaje' => 'No se puede eliminar. La lista tiene productos con precios asignados. Elimine los precios primero.'
                ]);
                exit;
            }
            
            // Verificar que no esté asociada a clientes
            $sql_check = "SELECT COUNT(*) as total FROM clientes WHERE id_lista_precio = ?";
            $stmt = $db->query($sql_check, [$id_lista_precio]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado['total'] > 0) {
                echo json_encode([
                    'success' => false, 
                    'mensaje' => 'No se puede eliminar. La lista está asociada a ' . $resultado['total'] . ' cliente(s).'
                ]);
                exit;
            }
            
            // Eliminar
            $sql = "DELETE FROM listas_precios WHERE id_lista_precio = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_lista_precio]);
            
            registrarAuditoria('listas_precios', 'eliminar', "ID Lista: $id_lista_precio");
            
            echo json_encode(['success' => true, 'mensaje' => 'Lista eliminada correctamente']);
            break;
            
        case 'regenerar_todos':
            $total_actualizados = 0;
            
            // Obtener todas las listas activas
            $listas = $db->query("SELECT id_lista_precio, porcentaje_incremento FROM listas_precios WHERE estado = 'activa'")->fetchAll();
            
            foreach ($listas as $lista) {
                $total_actualizados += regenerarPreciosLista($lista['id_lista_precio'], $lista['porcentaje_incremento']);
            }
            
            registrarAuditoria('listas_precios', 'regenerar_todos', "Total precios actualizados: $total_actualizados");
            
            echo json_encode([
                'success' => true, 
                'mensaje' => "Se regeneraron correctamente $total_actualizados precios en todas las listas."
            ]);
            break;
        case 'editar_precio_individual':
            $id_producto = (int)$_POST['id_producto'];
            $id_lista_precio = (int)$_POST['id_lista_precio'];
            $precio = (float)$_POST['precio'];
            
            if ($precio <= 0) {
                echo json_encode(['success' => false, 'mensaje' => 'El precio debe ser mayor a 0']);
                exit;
            }
            
            // Verificar si ya existe el precio
            $sql_check = "SELECT id_producto_precio FROM productos_precios 
                        WHERE id_producto = ? AND id_lista_precio = ?";
            $stmt = $db->query($sql_check, [$id_producto, $id_lista_precio]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existe) {
                // Actualizar
                $sql = "UPDATE productos_precios SET precio = ? 
                        WHERE id_producto = ? AND id_lista_precio = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$precio, $id_producto, $id_lista_precio]);
                $mensaje = 'Precio actualizado correctamente';
            } else {
                // Insertar
                $sql = "INSERT INTO productos_precios (id_producto, id_lista_precio, precio)
                        VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_producto, $id_lista_precio, $precio]);
                $mensaje = 'Precio creado correctamente';
            }
            
            registrarAuditoria('productos_precios', 'editar_individual', 
                "Producto ID: $id_producto, Lista ID: $id_lista_precio, Precio: $$precio");
            
            echo json_encode(['success' => true, 'mensaje' => $mensaje]);
            break;

    case 'regenerar_lista':
        $id_lista_precio = (int)$_POST['id_lista_precio'];
        
        // Obtener porcentaje de la lista
        $sql = "SELECT porcentaje_incremento, nombre_lista FROM listas_precios WHERE id_lista_precio = ?";
        $stmt = $db->query($sql, [$id_lista_precio]);
        $lista = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lista) {
            echo json_encode(['success' => false, 'mensaje' => 'Lista no encontrada']);
            exit;
        }
        
        $total = regenerarPreciosLista($id_lista_precio, $lista['porcentaje_incremento']);
        
        registrarAuditoria('listas_precios', 'regenerar_lista', 
            "Lista: {$lista['nombre_lista']} (ID: $id_lista_precio), Total actualizados: $total");
        
        echo json_encode([
            'success' => true, 
            'mensaje' => "Se regeneraron $total precios correctamente en la lista '{$lista['nombre_lista']}'"
        ]);
        break;
            
        default:
            echo json_encode(['success' => false, 'mensaje' => 'Acción no válida']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}

// =============================================
// FUNCIONES AUXILIARES
// =============================================

/**
 * Genera precios para todos los productos en una lista nueva
 */
function generarPreciosLista($id_lista_precio, $porcentaje_incremento) {
    global $pdo;
    
    $sql = "INSERT INTO productos_precios (id_producto, id_lista_precio, precio)
            SELECT id_producto, ?, precio_base * (1 + ? / 100)
            FROM productos 
            WHERE estado = 'activo'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_lista_precio, $porcentaje_incremento]);
    
    return $stmt->rowCount();
}

/**
 * Regenera precios existentes de una lista
 */
function regenerarPreciosLista($id_lista_precio, $porcentaje_incremento) {
    global $pdo;
    
    // Primero eliminar precios existentes
    $sql_delete = "DELETE FROM productos_precios WHERE id_lista_precio = ?";
    $stmt = $pdo->prepare($sql_delete);
    $stmt->execute([$id_lista_precio]);
    
    // Generar nuevos precios
    return generarPreciosLista($id_lista_precio, $porcentaje_incremento);
}
?>