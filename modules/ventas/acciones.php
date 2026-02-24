<?php
// =============================================
// modules/ventas/acciones.php
// Acciones de Ventas (AJAX)
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
        // ANULAR VENTA (Genera Nota de Crédito)
        // ============================================
        case 'anular':
            requierePermiso('ventas', 'anular');
            
            $id_venta = (int)$_POST['id'];
            $motivo = limpiarInput($_POST['motivo'] ?? '');
            
            if ($id_venta <= 0) {
                throw new Exception('ID de venta inválido');
            }
            
            if (empty($motivo)) {
                throw new Exception('Debe indicar el motivo de la anulación');
            }
            
            // Verificar que la venta existe y está completada
            $sql = "SELECT v.*, 
                    (SELECT COUNT(*) FROM ventas_detalle WHERE id_venta = v.id_venta) as total_items
                    FROM ventas v 
                    WHERE v.id_venta = ? AND v.estado = 'completada'";
            $venta = $db->query($sql, [$id_venta])->fetch();
            
            if (!$venta) {
                throw new Exception('La venta no existe o ya fue procesada');
            }
            
            // Validar plazo de anulación (30 días)
            $fecha_venta = new DateTime($venta['fecha_venta']);
            $fecha_actual = new DateTime();
            $diferencia = $fecha_actual->diff($fecha_venta);
            
            if ($diferencia->days > 30) {
                throw new Exception('No se puede anular una venta con más de 30 días de antigüedad');
            }
            
            $pdo->beginTransaction();
            
            // 1. GENERAR NOTA DE CRÉDITO
            $sql_ultimo_nc = "SELECT numero_comprobante FROM ventas 
                             WHERE id_punto_venta = ? AND id_tipo_comprobante IN (
                                 SELECT id_tipo_comprobante FROM tipos_comprobante WHERE codigo IN ('NCA', 'NCB', 'NCC')
                             )
                             ORDER BY id_venta DESC LIMIT 1";
            $ultimo_nc = $db->query($sql_ultimo_nc, [$venta['id_punto_venta']])->fetch();
            
            if ($ultimo_nc && $ultimo_nc['numero_comprobante']) {
                $partes = explode('-', $ultimo_nc['numero_comprobante']);
                $ultimo_num = (int)$partes[1];
                $nuevo_num = $ultimo_num + 1;
            } else {
                $nuevo_num = 1;
            }
            
            // Determinar tipo de NC según tipo original
            $sql_tc_original = "SELECT codigo FROM tipos_comprobante WHERE id_tipo_comprobante = ?";
            $tc_original = $db->query($sql_tc_original, [$venta['id_tipo_comprobante']])->fetch();
            
            $tipo_nc = 'NCB'; // Por defecto
            if ($tc_original['codigo'] == 'FA') {
                $tipo_nc = 'NCA';
            } elseif ($tc_original['codigo'] == 'FC') {
                $tipo_nc = 'NCC';
            }
            
            $sql_id_nc = "SELECT id_tipo_comprobante FROM tipos_comprobante WHERE codigo = ?";
            $id_tipo_nc = $db->query($sql_id_nc, [$tipo_nc])->fetch();
            
            $numero_nc = str_pad($venta['id_punto_venta'], 4, '0', STR_PAD_LEFT) . '-' . 
                        str_pad($nuevo_num, 8, '0', STR_PAD_LEFT);
            
            // Generar CAE para NC (simulado)
            $cae_nc = date('Ymd') . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT) . '02';
            $vencimiento_cae_nc = date('Y-m-d', strtotime('+10 days'));
            
            // 2. OBTENER DETALLE DE LA VENTA
            $sql_detalle = "SELECT vd.*, p.nombre 
                           FROM ventas_detalle vd
                           INNER JOIN productos p ON vd.id_producto = p.id_producto
                           WHERE vd.id_venta = ?";
            $detalles = $db->query($sql_detalle, [$id_venta])->fetchAll();
            
            // 3. REINTEGRAR STOCK
            foreach ($detalles as $detalle) {
                // Buscar una ubicación activa para devolver el stock
                $sql_ubicacion = "SELECT id_ubicacion FROM ubicaciones WHERE estado = 'activo' ORDER BY id_ubicacion LIMIT 1";
                $ubicacion = $db->query($sql_ubicacion)->fetch();
                
                if ($ubicacion) {
                    // Verificar si existe registro de stock en esa ubicación
                    $sql_stock_existe = "SELECT id_stock FROM stock WHERE id_producto = ? AND id_ubicacion = ?";
                    $stock_existe = $db->query($sql_stock_existe, [$detalle['id_producto'], $ubicacion['id_ubicacion']])->fetch();
                    
                    if ($stock_existe) {
                        // Actualizar stock existente
                        $sql_update_stock = "UPDATE stock 
                                            SET cantidad = cantidad + ?
                                            WHERE id_producto = ? AND id_ubicacion = ?";
                        $stmt = $pdo->prepare($sql_update_stock);
                        $stmt->execute([$detalle['cantidad'], $detalle['id_producto'], $ubicacion['id_ubicacion']]);
                    } else {
                        // Crear nuevo registro de stock
                        $sql_insert_stock = "INSERT INTO stock (id_producto, id_ubicacion, cantidad) VALUES (?, ?, ?)";
                        $stmt = $pdo->prepare($sql_insert_stock);
                        $stmt->execute([$detalle['id_producto'], $ubicacion['id_ubicacion'], $detalle['cantidad']]);
                    }
                    
                    // Registrar movimiento de devolución
                    $sql_movimiento = "INSERT INTO movimientos_stock 
                                      (id_producto, id_ubicacion_destino, tipo_movimiento, cantidad,
                                       motivo, referencia, id_usuario, observaciones)
                                      VALUES (?, ?, 'devolucion', ?, 'Anulación venta', ?, ?, ?)";
                    $stmt = $pdo->prepare($sql_movimiento);
                    $stmt->execute([
                        $detalle['id_producto'],
                        $ubicacion['id_ubicacion'],
                        $detalle['cantidad'],
                        $venta['numero_venta'],
                        $_SESSION['usuario_id'],
                        "Anulación de venta - Motivo: $motivo"
                    ]);
                }
            }
            
            // 4. ACTUALIZAR ESTADO DE LA VENTA
            $sql_update_venta = "UPDATE ventas 
                                SET estado = 'devuelta',
                                    observaciones = CONCAT(COALESCE(observaciones, ''), '\n\n[ANULADA] Motivo: ', ?)
                                WHERE id_venta = ?";
            $stmt = $pdo->prepare($sql_update_venta);
            $stmt->execute([$motivo, $id_venta]);
            
            $pdo->commit();
            
            // Registrar auditoría
            registrarAuditoria(
                'ventas',
                'anular',
                "Venta {$venta['numero_venta']} anulada - NC: $numero_nc - Motivo: $motivo - Total: " . formatearMoneda($venta['total'])
            );
            
            echo json_encode([
                'success' => true,
                'mensaje' => "Venta anulada correctamente.\nNota de Crédito: $numero_nc\nStock reintegrado."
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