<?php
require_once '../../config/conexion.php';
validarSesion();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de venta no válido');
}

$idVenta = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Obtener datos de la venta
    $sqlVenta = "SELECT 
                    v.id_venta,
                    v.numero_comprobante,
                    v.fecha,
                    v.tipo_comprobante,
                    v.forma_pago,
                    v.total,
                    c.nombre AS cliente_nombre,
                    c.apellido AS cliente_apellido,
                    c.email AS cliente_email,
                    c.telefono AS cliente_telefono,
                    c.dni_cuit AS cliente_dni,
                    c.direccion AS cliente_direccion,
                    vend.nombre AS vendedor_nombre,
                    vend.apellido AS vendedor_apellido
                FROM Ventas v
                INNER JOIN Clientes c ON v.id_cliente = c.id_cliente
                INNER JOIN Vendedores vend ON v.id_vendedor = vend.id_vendedor
                WHERE v.id_venta = :id_venta";
    
    $stmtVenta = $db->prepare($sqlVenta);
    $stmtVenta->execute([':id_venta' => $idVenta]);
    $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        die('Venta no encontrada');
    }
    
    // Obtener detalle de la venta
    $sqlDetalle = "SELECT 
                    dv.cantidad,
                    dv.precio_unitario,
                    dv.subtotal,
                    p.nombre AS producto_nombre,
                    p.codigo_producto
                FROM Detalle_Venta dv
                INNER JOIN Productos p ON dv.id_producto = p.id_producto
                WHERE dv.id_venta = :id_venta
                ORDER BY dv.id_detalle";
    
    $stmtDetalle = $db->prepare($sqlDetalle);
    $stmtDetalle->execute([':id_venta' => $idVenta]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die('Error al cargar la venta: ' . $e->getMessage());
}

// Función auxiliar para formato de moneda
function formatMoney($value) {
    return '$' . number_format($value, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante #<?php echo htmlspecialchars($venta['numero_comprobante']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .comprobante {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .empresa {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .tipo-comprobante {
            display: inline-block;
            font-size: 48px;
            font-weight: bold;
            border: 3px solid #333;
            padding: 10px 30px;
            margin: 15px 0;
        }
        
        .numero-comprobante {
            font-size: 18px;
            margin-top: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section {
            border: 1px solid #ddd;
            padding: 15px;
        }
        
        .info-section h3 {
            font-size: 14px;
            margin-bottom: 10px;
            background: #f5f5f5;
            padding: 5px;
        }
        
        .info-line {
            font-size: 12px;
            margin: 5px 0;
        }
        
        .info-line strong {
            display: inline-block;
            width: 100px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 12px;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totales {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .total-final {
            font-size: 18px;
            font-weight: bold;
            background: #f5f5f5;
            margin-top: 10px;
        }
        
        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #333;
            text-align: center;
            font-size: 11px;
        }
        
        .botones {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        .btn {
            padding: 12px 30px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .comprobante {
                box-shadow: none;
                padding: 20px;
            }
            
            .botones {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="comprobante">
        <div class="header">
            <div class="empresa">TU EMPRESA S.A.</div>
            <div>Dirección de tu empresa</div>
            <div>Tel: (123) 456-7890 | Email: info@tuempresa.com</div>
            <div class="tipo-comprobante"><?php echo htmlspecialchars($venta['tipo_comprobante']); ?></div>
            <div class="numero-comprobante">
                Comprobante N°: <strong><?php echo htmlspecialchars($venta['numero_comprobante']); ?></strong>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-section">
                <h3>DATOS DEL CLIENTE</h3>
                <div class="info-line">
                    <strong>Cliente:</strong> 
                    <?php echo htmlspecialchars($venta['cliente_nombre'] . ' ' . $venta['cliente_apellido']); ?>
                </div>
                <div class="info-line">
                    <strong>DNI/CUIT:</strong> 
                    <?php echo htmlspecialchars($venta['cliente_dni'] ?: 'No especificado'); ?>
                </div>
                <div class="info-line">
                    <strong>Email:</strong> 
                    <?php echo htmlspecialchars($venta['cliente_email']); ?>
                </div>
                <div class="info-line">
                    <strong>Teléfono:</strong> 
                    <?php echo htmlspecialchars($venta['cliente_telefono'] ?: 'No especificado'); ?>
                </div>
                <div class="info-line">
                    <strong>Dirección:</strong> 
                    <?php echo htmlspecialchars($venta['cliente_direccion'] ?: 'No especificado'); ?>
                </div>
            </div>
            
            <div class="info-section">
                <h3>DATOS DE LA VENTA</h3>
                <div class="info-line">
                    <strong>Fecha:</strong> 
                    <?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?>
                </div>
                <div class="info-line">
                    <strong>Forma de Pago:</strong> 
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $venta['forma_pago']))); ?>
                </div>
                <div class="info-line">
                    <strong>Vendedor:</strong> 
                    <?php echo htmlspecialchars($venta['vendedor_nombre'] . ' ' . $venta['vendedor_apellido']); ?>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['codigo_producto'] ?: 'S/C'); ?></td>
                    <td><?php echo htmlspecialchars($item['producto_nombre']); ?></td>
                    <td class="text-center"><?php echo $item['cantidad']; ?></td>
                    <td class="text-right"><?php echo formatMoney($item['precio_unitario']); ?></td>
                    <td class="text-right"><?php echo formatMoney($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totales">
            <div class="total-row total-final">
                <span>TOTAL:</span>
                <span><?php echo formatMoney($venta['total']); ?></span>
            </div>
        </div>
        
        <div class="footer">
            <p>Gracias por su compra</p>
            <p style="margin-top: 10px;">Este comprobante es válido como factura</p>
        </div>
    </div>
    
    <div class="botones">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
        <a href="index.php" class="btn btn-success">✓ Nueva Venta</a>
        <a href="historial.php" class="btn btn-secondary">📋 Ver Historial</a>
    </div>
</body>
</html>