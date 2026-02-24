<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    if (!isset($_GET['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID no especificado'], 400);
    }
    
    $idProducto = intval($_GET['id']);
    
    // Obtener datos del producto
    $sqlProducto = "SELECT 
                      p.*,
                      c.nombre_categoria,
                      prov.nombre as proveedor_nombre,
                      prov.email as proveedor_email,
                      prov.telefono as proveedor_telefono
                   FROM Productos p
                   LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                   LEFT JOIN Proveedores prov ON p.id_proveedor = prov.id_proveedor
                   WHERE p.id_producto = :id_producto";
    
    $stmtProducto = $db->prepare($sqlProducto);
    $stmtProducto->execute([':id_producto' => $idProducto]);
    $producto = $stmtProducto->fetch();
    
    if (!$producto) {
        jsonResponse(['success' => false, 'message' => 'Producto no encontrado'], 404);
    }
    
    // Obtener stock por ubicación
    $sqlStock = "SELECT 
                   s.*,
                   u.nombre as ubicacion_nombre,
                   u.descripcion as ubicacion_descripcion
                FROM Stock s
                INNER JOIN Ubicaciones u ON s.id_ubicacion = u.id_ubicacion
                WHERE s.id_producto = :id_producto
                ORDER BY u.nombre";
    
    $stmtStock = $db->prepare($sqlStock);
    $stmtStock->execute([':id_producto' => $idProducto]);
    $stockUbicaciones = $stmtStock->fetchAll();
    
    $stockTotal = array_sum(array_column($stockUbicaciones, 'cantidad'));
    
    // Obtener últimos 10 movimientos
    $sqlMovimientos = "SELECT 
                         ms.*,
                         v.usuario as usuario_nombre
                      FROM Movimientos_Stock ms
                      LEFT JOIN Vendedores v ON ms.id_usuario = v.id_vendedor
                      WHERE ms.id_producto = :id_producto
                      ORDER BY ms.fecha DESC
                      LIMIT 10";
    
    $stmtMovimientos = $db->prepare($sqlMovimientos);
    $stmtMovimientos->execute([':id_producto' => $idProducto]);
    $movimientos = $stmtMovimientos->fetchAll();
    
    // Determinar estado del stock
    $estadoStock = 'OK';
    if ($stockTotal <= 0) {
        $estadoStock = 'SIN_STOCK';
    } elseif ($stockTotal <= $producto['stock_minimo']) {
        $estadoStock = 'CRITICO';
    } elseif ($stockTotal <= $producto['stock_minimo'] * 2) {
        $estadoStock = 'BAJO';
    }
    
    // Generar HTML
    ob_start();
    ?>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">📦 Detalle del Producto</h2>
        <button onclick="cerrarModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 24px; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">×</button>
    </div>
    
    <div style="padding: 25px;">
        <!-- Información del Producto -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            <div>
                <h4 style="color: #667eea; margin-bottom: 10px;">📋 Información General</h4>
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($producto['nombre']); ?></p>
                <p><strong>Código:</strong> <?php echo $producto['codigo_producto'] ?: 'Sin código'; ?></p>
                <p><strong>Categoría:</strong> <?php echo $producto['nombre_categoria']; ?></p>
                <?php if ($producto['descripcion']): ?>
                    <p><strong>Descripción:</strong><br><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <h4 style="color: #667eea; margin-bottom: 10px;">💰 Información Comercial</h4>
                <p><strong>Precio Unitario:</strong> <?php echo formatearMoneda($producto['precio_unitario']); ?></p>
                <p><strong>Proveedor:</strong> <?php echo $producto['proveedor_nombre']; ?></p>
                <?php if ($producto['proveedor_telefono']): ?>
                    <p><strong>Tel. Proveedor:</strong> <?php echo $producto['proveedor_telefono']; ?></p>
                <?php endif; ?>
                <p><strong>Stock Mínimo:</strong> <?php echo $producto['stock_minimo']; ?> unidades</p>
            </div>
        </div>
        
        <!-- Estado del Stock -->
        <div style="background: <?php 
            echo $estadoStock === 'OK' ? '#c6f6d5' : 
                ($estadoStock === 'BAJO' ? '#feebc8' : 
                ($estadoStock === 'CRITICO' ? '#fed7d7' : '#e2e8f0')); 
        ?>; padding: 15px; border-radius: 8px; margin-bottom: 25px; border-left: 5px solid <?php 
            echo $estadoStock === 'OK' ? '#48bb78' : 
                ($estadoStock === 'BAJO' ? '#ed8936' : 
                ($estadoStock === 'CRITICO' ? '#f56565' : '#a0aec0')); 
        ?>;">
            <h3 style="margin: 0 0 10px 0;">
                <?php 
                    switch($estadoStock) {
                        case 'OK':
                            echo '✓ Stock en Nivel Óptimo';
                            break;
                        case 'BAJO':
                            echo '⚠️ Stock Bajo - Considere Reabastecer';
                            break;
                        case 'CRITICO':
                            echo '🚨 Stock Crítico - Reabastecimiento Urgente';
                            break;
                        case 'SIN_STOCK':
                            echo '❌ Sin Stock Disponible';
                            break;
                    }
                ?>
            </h3>
            <p style="margin: 0; font-size: 24px; font-weight: 700;">
                Stock Total: <?php echo $stockTotal; ?> unidades
            </p>
            <p style="margin: 5px 0 0 0;">
                Valoración: <?php echo formatearMoneda($stockTotal * $producto['precio_unitario']); ?>
            </p>
        </div>
        
        <!-- Stock por Ubicación -->
        <h4 style="color: #667eea; margin-bottom: 15px;">📍 Stock por Ubicación</h4>
        <?php if (empty($stockUbicaciones)): ?>
            <p style="color: #a0aec0; text-align: center; padding: 20px;">No hay stock registrado</p>
        <?php else: ?>
            <div style="overflow-x: auto; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f7fafc;">
                        <tr>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Ubicación</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Cantidad</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Valoración</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Última Act.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockUbicaciones as $stock): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo $stock['ubicacion_nombre']; ?></strong>
                                    <?php if ($stock['ubicacion_descripcion']): ?>
                                        <br><small style="color: #718096;"><?php echo $stock['ubicacion_descripcion']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo $stock['cantidad']; ?></strong>
                                </td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo formatearMoneda($stock['cantidad'] * $producto['precio_unitario']); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo formatearFecha($stock['fecha_actualizacion']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Últimos Movimientos -->
        <h4 style="color: #667eea; margin-bottom: 15px;">📋 Últimos Movimientos</h4>
        <?php if (empty($movimientos)): ?>
            <p style="color: #a0aec0; text-align: center; padding: 20px;">No hay movimientos registrados</p>
        <?php else: ?>
            <div style="overflow-x: auto; border: 2px solid #e2e8f0; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f7fafc;">
                        <tr>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Fecha</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Tipo</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Cantidad</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Detalle</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo formatearFecha($mov['fecha']); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php
                                        $badge = '';
                                        switch($mov['tipo_movimiento']) {
                                            case 'INGRESO':
                                                $badge = '<span style="background: #c6f6d5; color: #22543d; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">↑ Ingreso</span>';
                                                break;
                                            case 'VENTA':
                                                $badge = '<span style="background: #bee3f8; color: #2c5282; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">↓ Venta</span>';
                                                break;
                                            case 'AJUSTE':
                                                $badge = '<span style="background: #feebc8; color: #744210; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">⚙ Ajuste</span>';
                                                break;
                                            case 'DEVOLUCION':
                                                $badge = '<span style="background: #e6f0ff; color: #2d3aa3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">↩ Devolución</span>';
                                                break;
                                        }
                                        echo $badge;
                                    ?>
                                </td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo $mov['cantidad']; ?></strong>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo htmlspecialchars($mov['detalle'] ?: '-'); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo $mov['usuario_nombre'] ?: 'Sistema'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    jsonResponse([
        'success' => true,
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    error_log("Error en detalle_producto.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error al obtener el detalle'
    ], 500);
}
?>