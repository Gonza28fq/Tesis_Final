<?php
require_once '../../config/conexion.php';
validarSesion();

if (!isset($_GET['id_venta'])) {
    die('ID de venta no especificado');
}

$idVenta = intval($_GET['id_venta']);

try {
    $db = getDB();
    
    // Obtener datos de la venta
    $sqlVenta = "SELECT 
                    v.*,
                    CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
                    c.email as cliente_email,
                    c.telefono as cliente_telefono,
                    c.direccion as cliente_direccion,
                    c.dni_cuit as cliente_dni,
                    c.tipo_cliente,
                    CONCAT(vend.nombre, ' ', vend.apellido) as vendedor_nombre
                FROM Ventas v
                INNER JOIN Clientes c ON v.id_cliente = c.id_cliente
                INNER JOIN Vendedores vend ON v.id_vendedor = vend.id_vendedor
                WHERE v.id_venta = :id_venta";
    
    $stmt = $db->prepare($sqlVenta);
    $stmt->execute([':id_venta' => $idVenta]);
    $venta = $stmt->fetch();
    
    if (!$venta) {
        die('Venta no encontrada');
    }
    
    // Obtener detalle de la venta
    $sqlDetalle = "SELECT 
                      dv.*,
                      p.nombre as producto_nombre,
                      p.codigo_producto
                   FROM Detalle_Venta dv
                   INNER JOIN Productos p ON dv.id_producto = p.id_producto
                   WHERE dv.id_venta = :id_venta
                   ORDER BY dv.id_detalle";
    
    $stmtDetalle = $db->prepare($sqlDetalle);
    $stmtDetalle->execute([':id_venta' => $idVenta]);
    $detalles = $stmtDetalle->fetchAll();
    
} catch (PDOException $e) {
    die('Error al obtener datos: ' . $e->getMessage());
}

// Generar HTML del comprobante
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante - <?php echo $venta['numero_comprobante']; ?></title>
    <style>
        @media print {
            .no-print { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .comprobante {
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .empresa {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .tipo-comprobante {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 30px;
            font-size: 32px;
            font-weight: bold;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
        }
        
        .info-section p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .totales {
            margin-top: 30px;
            text-align: right;
        }
        
        .totales table {
            margin-left: auto;
            width: 300px;
        }
        
        .totales td {
            border: none;
            padding: 5px 10px;
        }
        
        .totales .total-final {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            border-top: 2px solid #667eea;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #718096;
        }
        
        .btn-print {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .btn-print:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Comprobante</button>
        <button class="btn-print" onclick="window.close()" style="background: #cbd5e0; color: #2d3748;">✕ Cerrar</button>
    </div>

    <div class="comprobante">
        <div class="header">
            <div class="empresa">SISTEMA DE GESTIÓN COMERCIAL</div>
            <div style="font-size: 12px; color: #718096;">
                Dirección de la Empresa | Teléfono: (123) 456-7890 | Email: info@empresa.com
            </div>
            <div class="tipo-comprobante"><?php echo $venta['tipo_comprobante']; ?></div>
            <div style="font-size: 18px; font-weight: bold; margin-top: 10px;">
                <?php echo $venta['numero_comprobante']; ?>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-section">
                <h3>📅 DATOS DE LA VENTA</h3>
                <p><strong>Fecha:</strong> <?php echo formatearFecha($venta['fecha']); ?></p>
                <p><strong>Vendedor:</strong> <?php echo $venta['vendedor_nombre']; ?></p>
                <p><strong>Forma de Pago:</strong> <?php echo ucfirst(str_replace('_', ' ', $venta['forma_pago'])); ?></p>
            </div>

            <div class="info-section">
                <h3>👤 DATOS DEL CLIENTE</h3>
                <p><strong>Cliente:</strong> <?php echo $venta['cliente_nombre']; ?></p>
                <p><strong>DNI/CUIT:</strong> <?php echo $venta['cliente_dni'] ?: 'N/A'; ?></p>
                <p><strong>Email:</strong> <?php echo $venta['cliente_email']; ?></p>
                <p><strong>Teléfono:</strong> <?php echo $venta['cliente_telefono'] ?: 'N/A'; ?></p>
                <?php if ($venta['cliente_direccion']): ?>
                    <p><strong>Dirección:</strong> <?php echo $venta['cliente_direccion']; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo $detalle['codigo_producto'] ?: '-'; ?></td>
                        <td><?php echo $detalle['producto_nombre']; ?></td>
                        <td class="text-right"><?php echo $detalle['cantidad']; ?></td>
                        <td class="text-right"><?php echo formatearMoneda($detalle['precio_unitario']); ?></td>
                        <td class="text-right"><?php echo formatearMoneda($detalle['subtotal']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales">
            <table>
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td class="text-right"><?php echo formatearMoneda($venta['total']); ?></td>
                </tr>
                <tr>
                    <td class="total-final"><strong>TOTAL:</strong></td>
                    <td class="total-final text-right"><?php echo formatearMoneda($venta['total']); ?></td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Gracias por su compra</p>
            <p>Este comprobante es válido como documento fiscal</p>
            <p style="margin-top: 10px; font-size: 10px;">
                Documento generado el <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>
    </div>

    <script>
        // Auto-imprimir al cargar (opcional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>