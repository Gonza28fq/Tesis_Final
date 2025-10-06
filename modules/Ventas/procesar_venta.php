<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validaciones básicas
        if (empty($input['id_cliente'])) {
            jsonResponse(['success' => false, 'message' => 'Cliente no especificado'], 400);
        }
        
        if (empty($input['productos']) || !is_array($input['productos'])) {
            jsonResponse(['success' => false, 'message' => 'No hay productos en la venta'], 400);
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        try {
            // 1. Validar stock de todos los productos
            foreach ($input['productos'] as $producto) {
                $sqlStock = "SELECT COALESCE(SUM(cantidad), 0) as stock_total 
                            FROM Stock 
                            WHERE id_producto = :id_producto";
                $stmtStock = $db->prepare($sqlStock);
                $stmtStock->execute([':id_producto' => $producto['id_producto']]);
                $stockData = $stmtStock->fetch();
                
                if ($stockData['stock_total'] < $producto['cantidad']) {
                    throw new Exception("Stock insuficiente para el producto: {$producto['nombre']}. Disponible: {$stockData['stock_total']}");
                }
            }
            
            // 2. Calcular total
            $total = 0;
            foreach ($input['productos'] as $producto) {
                $total += $producto['cantidad'] * $producto['precio_unitario'];
            }
            
            // 3. Generar número de comprobante
            $sqlUltimoComprobante = "SELECT MAX(CAST(SUBSTRING(numero_comprobante, -8) AS UNSIGNED)) as ultimo 
                                    FROM Ventas 
                                    WHERE tipo_comprobante = :tipo";
            $stmtUltimo = $db->prepare($sqlUltimoComprobante);
            $stmtUltimo->execute([':tipo' => $input['tipo_comprobante']]);
            $ultimoNum = $stmtUltimo->fetch();
            
            $nuevoNumero = ($ultimoNum['ultimo'] ?? 0) + 1;
            $numeroComprobante = $input['tipo_comprobante'] . '-' . str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);
            
            // 4. Insertar venta
            $sqlVenta = "INSERT INTO Ventas 
                        (id_cliente, id_vendedor, fecha, tipo_comprobante, numero_comprobante, forma_pago, total) 
                        VALUES 
                        (:id_cliente, :id_vendedor, NOW(), :tipo_comprobante, :numero_comprobante, :forma_pago, :total)";
            
            $stmtVenta = $db->prepare($sqlVenta);
            $stmtVenta->execute([
                ':id_cliente' => $input['id_cliente'],
                ':id_vendedor' => $_SESSION['id_vendedor'],
                ':tipo_comprobante' => $input['tipo_comprobante'],
                ':numero_comprobante' => $numeroComprobante,
                ':forma_pago' => $input['forma_pago'] ?? 'efectivo',
                ':total' => $total
            ]);
            
            $idVenta = $db->lastInsertId();
            
            // 5. Insertar detalle de venta y actualizar stock
            $sqlDetalle = "INSERT INTO Detalle_Venta (id_venta, id_producto, cantidad, precio_unitario) 
                          VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario)";
            $stmtDetalle = $db->prepare($sqlDetalle);
            
            $sqlMovimiento = "INSERT INTO Movimientos_Stock 
                             (id_producto, tipo_movimiento, cantidad, detalle, id_usuario) 
                             VALUES (:id_producto, 'VENTA', :cantidad, :detalle, :id_usuario)";
            $stmtMovimiento = $db->prepare($sqlMovimiento);
            
            foreach ($input['productos'] as $producto) {
                // Insertar detalle
                $stmtDetalle->execute([
                    ':id_venta' => $idVenta,
                    ':id_producto' => $producto['id_producto'],
                    ':cantidad' => $producto['cantidad'],
                    ':precio_unitario' => $producto['precio_unitario']
                ]);
                
                // Actualizar stock (reducir)
                $sqlUpdateStock = "UPDATE Stock 
                                  SET cantidad = cantidad - :cantidad 
                                  WHERE id_producto = :id_producto 
                                  AND cantidad >= :cantidad2";
                $stmtUpdateStock = $db->prepare($sqlUpdateStock);
                $result = $stmtUpdateStock->execute([
                    ':cantidad' => $producto['cantidad'],
                    ':id_producto' => $producto['id_producto'],
                    ':cantidad2' => $producto['cantidad']
                ]);
                
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception("Error al actualizar stock del producto: {$producto['nombre']}");
                }
                
                // Registrar movimiento de stock
                $stmtMovimiento->execute([
                    ':id_producto' => $producto['id_producto'],
                    ':cantidad' => $producto['cantidad'],
                    ':detalle' => "Venta #{$idVenta} - {$numeroComprobante}",
                    ':id_usuario' => $_SESSION['id_vendedor']
                ]);
            }
            
            // 6. Confirmar transacción
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'Venta procesada exitosamente',
                'id_venta' => $idVenta,
                'numero_comprobante' => $numeroComprobante,
                'total' => $total
            ]);
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $db->rollBack();
            throw $e;
        }
    }
    
}  catch (Exception $e) {
    error_log("Error en procesar_venta.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>