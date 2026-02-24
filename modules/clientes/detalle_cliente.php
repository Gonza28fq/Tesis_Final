<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    if (!isset($_GET['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID no especificado'], 400);
    }
    
    $idCliente = intval($_GET['id']);
    
    // Obtener datos del cliente
    $sqlCliente = "SELECT * FROM Clientes WHERE id_cliente = :id";
    $stmtCliente = $db->prepare($sqlCliente);
    $stmtCliente->execute([':id' => $idCliente]);
    $cliente = $stmtCliente->fetch();
    
    if (!$cliente) {
        jsonResponse(['success' => false, 'message' => 'Cliente no encontrado'], 404);
    }
    
    // Estadísticas del cliente
    $sqlStats = "SELECT 
                    COUNT(DISTINCT v.id_venta) as total_ventas,
                    COALESCE(SUM(v.total), 0) as total_gastado,
                    COALESCE(AVG(v.total), 0) as ticket_promedio,
                    MAX(v.fecha) as ultima_compra,
                    MIN(v.fecha) as primera_compra
                 FROM Ventas v
                 WHERE v.id_cliente = :id";
    
    $stmtStats = $db->prepare($sqlStats);
    $stmtStats->execute([':id' => $idCliente]);
    $stats = $stmtStats->fetch();
    
    // Historial de ventas
    $sqlVentas = "SELECT 
                    v.id_venta,
                    v.fecha,
                    v.numero_comprobante,
                    v.tipo_comprobante,
                    v.total,
                    COUNT(dv.id_detalle) as cantidad_items
                  FROM Ventas v
                  LEFT JOIN Detalle_Venta dv ON v.id_venta = dv.id_venta
                  WHERE v.id_cliente = :id
                  GROUP BY v.id_venta
                  ORDER BY v.fecha DESC
                  LIMIT 10";
    
    $stmtVentas = $db->prepare($sqlVentas);
    $stmtVentas->execute([':id' => $idCliente]);
    $ventas = $stmtVentas->fetchAll();
    
    // Productos más comprados
    $sqlProductos = "SELECT 
                        p.nombre,
                        SUM(dv.cantidad) as cantidad_total,
                        SUM(dv.subtotal) as total_gastado
                     FROM Detalle_Venta dv
                     INNER JOIN Ventas v ON dv.id_venta = v.id_venta
                     INNER JOIN Productos p ON dv.id_producto = p.id_producto
                     WHERE v.id_cliente = :id
                     GROUP BY dv.id_producto
                     ORDER BY cantidad_total DESC
                     LIMIT 5";
    
    $stmtProductos = $db->prepare($sqlProductos);
    $stmtProductos->execute([':id' => $idCliente]);
    $productos = $stmtProductos->fetchAll();
    
    // Determinar segmento
    $segmento = 'Nuevo';
    if ($stats['total_ventas'] == 0) {
        $segmento = 'Nuevo';
    } elseif ($stats['total_ventas'] >= 10 && $stats['total_gastado'] >= 50000) {
        $segmento = 'VIP';
    } elseif ($stats['total_ventas'] >= 5) {
        $segmento = 'Regular';
    } else {
        $segmento = 'Ocasional';
    }
    
    // Generar HTML
    ob_start();
    ?>
    
    <div style="background: linear-gradient(135deg, #4299e1 0%, #667eea 100%); color: white; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">👤 Detalle del Cliente</h2>
        <button onclick="cerrarModal()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 24px; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">×</button>
    </div>
    
    <div style="padding: 25px;">
        <!-- Información del Cliente -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            <div>
                <h4 style="color: #4299e1; margin-bottom: 10px;">📋 Información Personal</h4>
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($cliente['email']); ?></p>
                <?php if ($cliente['telefono']): ?>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente['telefono']); ?></p>
                <?php endif; ?>
                <?php if ($cliente['dni_cuit']): ?>
                    <p><strong>DNI/CUIT:</strong> <?php echo htmlspecialchars($cliente['dni_cuit']); ?></p>
                <?php endif; ?>
                <?php if ($cliente['direccion']): ?>
                    <p><strong>Dirección:</strong> <?php echo nl2br(htmlspecialchars($cliente['direccion'])); ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <h4 style="color: #4299e1; margin-bottom: 10px;">📊 Información Comercial</h4>
                <p><strong>Tipo:</strong> 
                    <?php 
                        $tipos = [
                            'consumidor_final' => 'Consumidor Final',
                            'responsable_inscripto' => 'Responsable Inscripto',
                            'monotributista' => 'Monotributista',
                            'exento' => 'Exento'
                        ];
                        echo $tipos[$cliente['tipo_cliente']] ?? $cliente['tipo_cliente'];
                    ?>
                </p>
                <p><strong>Segmento:</strong> 
                    <span style="background: <?php 
                        echo $segmento === 'VIP' ? 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' : 
                            ($segmento === 'Regular' ? '#bee3f8' : 
                            ($segmento === 'Ocasional' ? '#feebc8' : '#c6f6d5')); 
                    ?>; padding: 4px 12px; border-radius: 12px; font-weight: 600; color: <?php echo $segmento === 'VIP' ? 'white' : '#2d3748'; ?>;">
                        <?php echo $segmento; ?>
                    </span>
                </p>
                <p><strong>Estado:</strong> 
                    <span style="background: <?php echo $cliente['activo'] ? '#c6f6d5' : '#e2e8f0'; ?>; padding: 4px 12px; border-radius: 12px; font-weight: 600; color: <?php echo $cliente['activo'] ? '#22543d' : '#718096'; ?>;">
                        <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </p>
                <p><strong>Cliente desde:</strong> <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></p>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <h4 style="color: #4299e1; margin-bottom: 15px;">📈 Estadísticas de Compra</h4>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; text-align: center; border: 2px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: 700; color: #4299e1;"><?php echo $stats['total_ventas']; ?></div>
                <div style="font-size: 12px; color: #718096;">Compras</div>
            </div>
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; text-align: center; border: 2px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: 700; color: #4299e1;"><?php echo formatearMoneda($stats['total_gastado']); ?></div>
                <div style="font-size: 12px; color: #718096;">Total Gastado</div>
            </div>
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; text-align: center; border: 2px solid #e2e8f0;">
                <div style="font-size: 24px; font-weight: 700; color: #4299e1;"><?php echo formatearMoneda($stats['ticket_promedio']); ?></div>
                <div style="font-size: 12px; color: #718096;">Ticket Promedio</div>
            </div>
            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; text-align: center; border: 2px solid #e2e8f0;">
                <div style="font-size: 14px; font-weight: 700; color: #4299e1;">
                    <?php echo $stats['ultima_compra'] ? date('d/m/Y', strtotime($stats['ultima_compra'])) : 'N/A'; ?>
                </div>
                <div style="font-size: 12px; color: #718096;">Última Compra</div>
            </div>
        </div>
        
        <!-- Productos Más Comprados -->
        <?php if (!empty($productos)): ?>
            <h4 style="color: #4299e1; margin-bottom: 15px;">🛒 Productos Más Comprados</h4>
            <div style="overflow-x: auto; border: 2px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f7fafc;">
                        <tr>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Producto</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Cantidad</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;"><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;"><strong><?php echo $prod['cantidad_total']; ?></strong></td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;"><?php echo formatearMoneda($prod['total_gastado']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Historial de Ventas -->
        <h4 style="color: #4299e1; margin-bottom: 15px;">📋 Últimas Ventas</h4>
        <?php if (empty($ventas)): ?>
            <p style="text-align: center; padding: 20px; color: #a0aec0;">Este cliente aún no ha realizado compras</p>
        <?php else: ?>
            <div style="overflow-x: auto; border: 2px solid #e2e8f0; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f7fafc;">
                        <tr>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Fecha</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Comprobante</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Items</th>
                            <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e2e8f0;">Total</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;"><?php echo formatearFecha($venta['fecha']); ?></td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <span style="background: #e6f0ff; color: #2d3aa3; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                        <?php echo $venta['numero_comprobante']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;"><?php echo $venta['cantidad_items']; ?></td>
                                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e2e8f0;"><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                                    <a href="../../modules/ventas/generar_pdf.php?id_venta=<?php echo $venta['id_venta']; ?>" 
                                       target="_blank"
                                       style="color: #4299e1; text-decoration: none; font-size: 12px;">
                                        📄 Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($stats['total_ventas'] > 10): ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="../../modules/ventas/historial.php?cliente=<?php echo urlencode($cliente['nombre'] . ' ' . $cliente['apellido']); ?>" 
                       style="color: #4299e1; text-decoration: none; font-weight: 500;">
                        Ver todas las ventas →
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    jsonResponse([
        'success' => true,
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    error_log("Error en detalle_cliente.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error al obtener el detalle'
    ], 500);
}
?>