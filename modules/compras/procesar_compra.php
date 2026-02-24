<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validaciones básicas
        if (empty($input['id_proveedor'])) {
            jsonResponse(['success' => false, 'message' => 'Proveedor no especificado'], 400);
        }
        
        if (empty($input['productos']) || !is_array($input['productos'])) {
            jsonResponse(['success' => false, 'message' => 'No hay productos en el ingreso'], 400);
        }
        
        if (empty($input['fecha'])) {
            jsonResponse(['success' => false, 'message' => 'Fecha no especificada'], 400);
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        try {
            // 1. Calcular total
            $total = 0;
            foreach ($input['productos'] as $producto) {
                $total += $producto['cantidad'] * $producto['precio_compra'];
            }
            
            // 2. Insertar ingreso
            $sqlIngreso = "INSERT INTO Ingresos 
                          (id_proveedor, numero_comprobante, fecha, total_ingresado, observaciones) 
                          VALUES 
                          (:id_proveedor, :numero_comprobante, :fecha, :total, :observaciones)";
            
            $stmtIngreso = $db->prepare($sqlIngreso);
            $stmtIngreso->execute([
                ':id_proveedor' => $input['id_proveedor'],
                ':numero_comprobante' => $input['numero_comprobante'] ?? null,
                ':fecha' => $input['fecha'],
                ':total' => $total,
                ':observaciones' => $input['observaciones'] ?? null
            ]);
            
            $idIngreso = $db->lastInsertId();
            
            // 3. Insertar detalle de ingreso y actualizar stock
            $sqlDetalle = "INSERT INTO Detalle_Ingreso (id_ingreso, id_producto, cantidad, precio_unitario) 
                          VALUES (:id_ingreso, :id_producto, :cantidad, :precio_unitario)";
            $stmtDetalle = $db->prepare($sqlDetalle);
            
            $sqlMovimiento = "INSERT INTO Movimientos_Stock 
                             (id_producto, tipo_movimiento, cantidad, detalle, id_usuario) 
                             VALUES (:id_producto, 'INGRESO', :cantidad, :detalle, :id_usuario)";
            $stmtMovimiento = $db->prepare($sqlMovimiento);
            
            foreach ($input['productos'] as $producto) {
                // Insertar detalle
                $stmtDetalle->execute([
                    ':id_ingreso' => $idIngreso,
                    ':id_producto' => $producto['id_producto'],
                    ':cantidad' => $producto['cantidad'],
                    ':precio_unitario' => $producto['precio_compra']
                ]);
                
                // Verificar si ya existe stock en esa ubicación
                $sqlCheckStock = "SELECT id_stock FROM Stock 
                                 WHERE id_producto = :id_producto 
                                 AND id_ubicacion = :id_ubicacion";
                $stmtCheckStock = $db->prepare($sqlCheckStock);
                $stmtCheckStock->execute([
                    ':id_producto' => $producto['id_producto'],
                    ':id_ubicacion' => $producto['id_ubicacion']
                ]);
                
                if ($stmtCheckStock->fetch()) {
                    // Actualizar stock existente (sumar)
                    $sqlUpdateStock = "UPDATE Stock 
                                      SET cantidad = cantidad + :cantidad,
                                          fecha_actualizacion = NOW()
                                      WHERE id_producto = :id_producto 
                                      AND id_ubicacion = :id_ubicacion";
                    $stmtUpdateStock = $db->prepare($sqlUpdateStock);
                    $stmtUpdateStock->execute([
                        ':cantidad' => $producto['cantidad'],
                        ':id_producto' => $producto['id_producto'],
                        ':id_ubicacion' => $producto['id_ubicacion']
                    ]);
                } else {
                    // Crear nuevo registro de stock
                    $sqlInsertStock = "INSERT INTO Stock (id_producto, cantidad, id_ubicacion) 
                                      VALUES (:id_producto, :cantidad, :id_ubicacion)";
                    $stmtInsertStock = $db->prepare($sqlInsertStock);
                    $stmtInsertStock->execute([
                        ':id_producto' => $producto['id_producto'],
                        ':cantidad' => $producto['cantidad'],
                        ':id_ubicacion' => $producto['id_ubicacion']
                    ]);
                }
                
                // Registrar movimiento de stock
                $stmtMovimiento->execute([
                    ':id_producto' => $producto['id_producto'],
                    ':cantidad' => $producto['cantidad'],
                    ':detalle' => "Ingreso #{$idIngreso}" . ($input['numero_comprobante'] ? " - {$input['numero_comprobante']}" : ""),
                    ':id_usuario' => $_SESSION['id_vendedor']
                ]);
            }
            
            // 4. Confirmar transacción
            $db->commit();
            
            jsonResponse([
                'success' => true,
                'message' => 'Ingreso registrado exitosamente',
                'id_ingreso' => $idIngreso,
                'total' => $total
            ]);
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $db->rollBack();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    error_log("Error en procesar_compra.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>