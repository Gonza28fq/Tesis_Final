<?php
// =============================================
// modules/compras/acciones.php
// Acciones de Compras (AJAX)
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

header('Content-Type: application/json');

$db = getDB();
$pdo = $db->getConexion();

try {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        
        // ============================================
        // CONFIRMAR RECEPCIÓN DE MERCADERÍA
        // ============================================
        case 'confirmar_recepcion':
            requierePermiso('compras', 'crear');
            
            $id_compra = (int)$_POST['id_compra'];
            $id_ubicacion = (int)$_POST['id_ubicacion'];
            $actualizar_precio = isset($_POST['actualizar_precio_costo']) ? 1 : 0;
            $observaciones = limpiarInput($_POST['observaciones_recepcion'] ?? '');
            
            if ($id_compra <= 0 || $id_ubicacion <= 0) {
                throw new Exception('Datos incompletos');
            }
            
            // Verificar que la compra existe y está pendiente
            $sql_compra = "SELECT * FROM compras WHERE id_compra = ? AND estado = 'pendiente'";
            $compra = $db->query($sql_compra, [$id_compra])->fetch();
            
            if (!$compra) {
                throw new Exception('Compra no encontrada o ya fue procesada');
            }
            
            // Verificar que la ubicación existe
            $sql_ubi = "SELECT * FROM ubicaciones WHERE id_ubicacion = ? AND estado = 'activo'";
            $ubicacion = $db->query($sql_ubi, [$id_ubicacion])->fetch();
            
            if (!$ubicacion) {
                throw new Exception('Ubicación no válida');
            }
            
            $pdo->beginTransaction();
            
            // Obtener detalle de compra
            $sql_detalle = "SELECT cd.*, p.nombre 
                           FROM compras_detalle cd
                           INNER JOIN productos p ON cd.id_producto = p.id_producto
                           WHERE cd.id_compra = ?";
            $detalles = $db->query($sql_detalle, [$id_compra])->fetchAll();
            
            foreach ($detalles as $detalle) {
                $id_producto = $detalle['id_producto'];
                $cantidad = $detalle['cantidad'];
                $precio_unitario = $detalle['precio_unitario'];
                
                // 1. ACTUALIZAR O CREAR REGISTRO DE STOCK
                $sql_stock_actual = "SELECT * FROM stock 
                                    WHERE id_producto = ? AND id_ubicacion = ?";
                $stock_actual = $db->query($sql_stock_actual, [$id_producto, $id_ubicacion])->fetch();
                
                if ($stock_actual) {
                    // Actualizar stock existente
                    $sql_update_stock = "UPDATE stock 
                                        SET cantidad = cantidad + ? 
                                        WHERE id_producto = ? AND id_ubicacion = ?";
                    $stmt = $pdo->prepare($sql_update_stock);
                    $stmt->execute([$cantidad, $id_producto, $id_ubicacion]);
                } else {
                    // Crear nuevo registro de stock
                    $sql_insert_stock = "INSERT INTO stock (id_producto, id_ubicacion, cantidad) 
                                        VALUES (?, ?, ?)";
                    $stmt = $pdo->prepare($sql_insert_stock);
                    $stmt->execute([$id_producto, $id_ubicacion, $cantidad]);
                }
                
                // 2. REGISTRAR MOVIMIENTO DE STOCK
                $sql_movimiento = "INSERT INTO movimientos_stock 
                                  (id_producto, id_ubicacion_destino, tipo_movimiento, cantidad, 
                                   motivo, referencia, id_usuario, observaciones)
                                  VALUES (?, ?, 'entrada', ?, 'Compra', ?, ?, ?)";
                $stmt = $pdo->prepare($sql_movimiento);
                $stmt->execute([
                    $id_producto,
                    $id_ubicacion,
                    $cantidad,
                    $compra['numero_compra'],
                    $_SESSION['usuario_id'],
                    "Recepción de compra - " . $observaciones
                ]);
                
                // 3. ACTUALIZAR PRECIO DE COSTO (si está activado)
                if ($actualizar_precio) {
                    // Obtener precio actual
                    $sql_precio_actual = "SELECT precio_costo FROM productos WHERE id_producto = ?";
                    $producto = $db->query($sql_precio_actual, [$id_producto])->fetch();
                    $precio_actual = (float)$producto['precio_costo'];
                    
                    // Calcular diferencia porcentual
                    $diferencia_porcentaje = 0;
                    if ($precio_actual > 0) {
                        $diferencia_porcentaje = abs((($precio_unitario - $precio_actual) / $precio_actual) * 100);
                    }
                    
                    // Actualizar precio de costo
                    $sql_update_precio = "UPDATE productos SET precio_costo = ? WHERE id_producto = ?";
                    $stmt = $pdo->prepare($sql_update_precio);
                    $stmt->execute([$precio_unitario, $id_producto]);
                    
                    // Si la diferencia es mayor al 10%, generar alerta en auditoría
                    if ($diferencia_porcentaje > 10) {
                        registrarAuditoria(
                            'productos',
                            'precio_modificado',
                            "Precio de costo actualizado significativamente: " . 
                            $detalle['nombre'] . 
                            " - Anterior: $" . number_format($precio_actual, 2) . 
                            " - Nuevo: $" . number_format($precio_unitario, 2) . 
                            " - Diferencia: " . number_format($diferencia_porcentaje, 2) . "%"
                        );
                    }
                }
                
                // 4. VERIFICAR STOCK MÁXIMO
                $sql_producto_info = "SELECT stock_maximo, nombre FROM productos WHERE id_producto = ?";
                $prod_info = $db->query($sql_producto_info, [$id_producto])->fetch();
                
                if ($prod_info['stock_maximo'] > 0) {
                    $sql_stock_total = "SELECT SUM(cantidad) as total 
                                       FROM stock 
                                       WHERE id_producto = ?";
                    $stock_total = $db->query($sql_stock_total, [$id_producto])->fetch();
                    
                    if ($stock_total['total'] >= $prod_info['stock_maximo']) {
                        registrarAuditoria(
                            'stock',
                            'alerta_maximo',
                            "Producto alcanzó stock máximo: " . $prod_info['nombre'] . 
                            " - Stock actual: " . $stock_total['total'] . 
                            " - Máximo: " . $prod_info['stock_maximo']
                        );
                    }
                }
            }
            
            // 5. ACTUALIZAR ESTADO DE LA COMPRA
            $sql_update_compra = "UPDATE compras 
                                 SET estado = 'recibida' 
                                 WHERE id_compra = ?";
            $stmt = $pdo->prepare($sql_update_compra);
            $stmt->execute([$id_compra]);
            
            $pdo->commit();
            
            // Registrar auditoría
            registrarAuditoria(
                'compras',
                'recepcion',
                "Recepción confirmada: " . $compra['numero_compra'] . 
                " - Ubicación: " . $ubicacion['nombre_ubicacion']
            );
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Recepción confirmada exitosamente. Stock actualizado.'
            ]);
            break;
            
        // ============================================
        // CANCELAR COMPRA
        // ============================================
        case 'cancelar':
            requierePermiso('compras', 'anular');
            
            $id_compra = (int)$_POST['id'];
            $motivo = limpiarInput($_POST['motivo'] ?? '');
            
            if ($id_compra <= 0) {
                throw new Exception('ID de compra inválido');
            }
            
            if (empty($motivo)) {
                throw new Exception('Debe indicar el motivo de la cancelación');
            }
            
            // Verificar que la compra existe y está pendiente
            $sql = "SELECT * FROM compras WHERE id_compra = ? AND estado = 'pendiente'";
            $compra = $db->query($sql, [$id_compra])->fetch();
            
            if (!$compra) {
                throw new Exception('La compra no existe o ya fue procesada');
            }
            
            $pdo->beginTransaction();
            
            // Actualizar estado
            $sql_update = "UPDATE compras SET estado = 'cancelada', observaciones = CONCAT(COALESCE(observaciones, ''), '\n\n[CANCELADA] ', ?) WHERE id_compra = ?";
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([$motivo, $id_compra]);
            
            // Si proviene de una nota de pedido, regresar su estado a "aprobada"
            if ($compra['id_nota_pedido']) {
                $sql_nota = "UPDATE notas_pedido SET estado = 'aprobada' WHERE id_nota_pedido = ?";
                $stmt = $pdo->prepare($sql_nota);
                $stmt->execute([$compra['id_nota_pedido']]);
            }
            
            $pdo->commit();
            
            registrarAuditoria('compras', 'cancelar', "Compra cancelada: {$compra['numero_compra']} - Motivo: $motivo");
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Compra cancelada correctamente'
            ]);
            break;
            
        // ============================================
        // ACCIÓN NO VÁLIDA
        // ============================================
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>