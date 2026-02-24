<?php
// =============================================
// modules/compras/imprimir.php
// Imprimir Compra - Diseño Profesional
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('compras', 'ver');

$id_compra = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_compra <= 0) {
    die('ID de compra inválido');
}

$db = getDB();

// Consultar compra
$sql = "SELECT c.*, 
        p.nombre_proveedor, p.cuit, p.direccion as direccion_proveedor,
        p.telefono as telefono_proveedor, p.email as email_proveedor,
        u.nombre_completo as usuario
        FROM compras c
        INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        WHERE c.id_compra = ?";

$compra = $db->query($sql, [$id_compra])->fetch();

if (!$compra) {
    die('Compra no encontrada');
}

// Consultar detalle
$sql_detalle = "SELECT cd.*, p.codigo, p.nombre, p.unidad_medida
                FROM compras_detalle cd
                INNER JOIN productos p ON cd.id_producto = p.id_producto
                WHERE cd.id_compra = ?
                ORDER BY p.nombre";

$detalles = $db->query($sql_detalle, [$id_compra])->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra <?php echo htmlspecialchars($compra['numero_compra']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-info h1 {
            font-size: 24px;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .company-info p {
            margin: 3px 0;
            font-size: 11px;
        }
        
        .document-info {
            text-align: right;
            border: 2px solid #333;
            padding: 15px;
            min-width: 200px;
        }
        
        .document-info h2 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2563eb;
        }
        
        .document-info .tipo {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            padding: 5px;
            background: #f3f4f6;
        }
        
        .document-info p {
            margin: 5px 0;
            font-size: 11px;
        }
        
        .proveedor-section {
            background: #f9fafb;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
        }
        
        .proveedor-section h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #1f2937;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 5px;
        }
        
        .proveedor-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .proveedor-info p {
            margin: 3px 0;
        }
        
        .proveedor-info strong {
            color: #4b5563;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table thead {
            background: #1f2937;
            color: white;
        }
        
        table th {
            padding: 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        table th.text-center {
            text-align: center;
        }
        
        table th.text-right {
            text-align: right;
        }
        
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        
        table tbody tr:hover {
            background: #f3f4f6;
        }
        
        table td.text-center {
            text-align: center;
        }
        
        table td.text-right {
            text-align: right;
        }
        
        table tfoot {
            background: #f3f4f6;
            font-weight: bold;
        }
        
        table tfoot td {
            padding: 10px;
            border-top: 2px solid #333;
        }
        
        .totals-row {
            background: #dbeafe !important;
        }
        
        .total-final {
            background: #2563eb !important;
            color: white !important;
            font-size: 16px !important;
        }
        
        .observations {
            margin: 20px 0;
            padding: 15px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 3px;
        }
        
        .observations h4 {
            margin-bottom: 8px;
            color: #92400e;
            font-size: 13px;
        }
        
        .observations p {
            color: #78350f;
            font-size: 11px;
            line-height: 1.5;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #d1d5db;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 60px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-bottom: 5px;
            padding-top: 5px;
        }
        
        .footer-info {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: #6b7280;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }
        
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .container {
                border: none;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                margin: 1cm;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimir</button>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1>Sistema de Gestión Comercial 2.0</h1>
                <p><strong>Tu Empresa S.A.</strong></p>
                <p>Dirección de la Empresa</p>
                <p>Teléfono: (0000) 000-0000</p>
                <p>Email: contacto@tuempresa.com</p>
                <p>CUIT: 00-00000000-0</p>
            </div>
            
            <div class="document-info">
                <h2>COMPRA</h2>
                <div class="tipo"><?php echo htmlspecialchars($compra['tipo_comprobante'] ?? 'COMPROBANTE'); ?></div>
                <p><strong>N°:</strong> <?php echo htmlspecialchars($compra['numero_compra']); ?></p>
                <?php if ($compra['numero_factura']): ?>
                <p><strong>Factura:</strong> <?php echo htmlspecialchars($compra['numero_factura']); ?></p>
                <?php endif; ?>
                <p><strong>Fecha:</strong> <?php echo formatearFecha($compra['fecha_compra']); ?></p>
                <p>
                    <span class="badge badge-<?php echo $compra['estado'] == 'recibida' ? 'success' : 'warning'; ?>">
                        <?php echo strtoupper($compra['estado']); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <!-- Información del Proveedor -->
        <div class="proveedor-section">
            <h3>DATOS DEL PROVEEDOR</h3>
            <div class="proveedor-info">
                <div>
                    <p><strong>Razón Social:</strong> <?php echo htmlspecialchars($compra['nombre_proveedor']); ?></p>
                    <?php if ($compra['cuit']): ?>
                    <p><strong>CUIT:</strong> <?php echo htmlspecialchars($compra['cuit']); ?></p>
                    <?php endif; ?>
                    <?php if ($compra['direccion_proveedor']): ?>
                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($compra['direccion_proveedor']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($compra['telefono_proveedor']): ?>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($compra['telefono_proveedor']); ?></p>
                    <?php endif; ?>
                    <?php if ($compra['email_proveedor']): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($compra['email_proveedor']); ?></p>
                    <?php endif; ?>
                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($compra['usuario']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Detalle de Productos -->
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Código</th>
                    <th style="width: 40%;">Descripción</th>
                    <th style="width: 10%;">Unidad</th>
                    <th class="text-center" style="width: 10%;">Cantidad</th>
                    <th class="text-right" style="width: 15%;">Precio Unit.</th>
                    <th class="text-right" style="width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($detalle['codigo']); ?></code></td>
                    <td><strong><?php echo htmlspecialchars($detalle['nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($detalle['unidad_medida']); ?></td>
                    <td class="text-center">
                        <span class="badge badge-primary"><?php echo number_format($detalle['cantidad']); ?></span>
                    </td>
                    <td class="text-right"><?php echo formatearMoneda($detalle['precio_unitario']); ?></td>
                    <td class="text-right"><strong><?php echo formatearMoneda($detalle['subtotal']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">SUBTOTAL:</td>
                    <td class="text-right"><?php echo formatearMoneda($compra['subtotal']); ?></td>
                </tr>
                <tr>
                    <td colspan="5" class="text-right">IVA (21%):</td>
                    <td class="text-right"><?php echo formatearMoneda($compra['impuestos']); ?></td>
                </tr>
                <tr class="total-final">
                    <td colspan="5" class="text-right">TOTAL:</td>
                    <td class="text-right"><?php echo formatearMoneda($compra['total']); ?></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- Observaciones -->
        <?php if ($compra['observaciones']): ?>
        <div class="observations">
            <h4>OBSERVACIONES:</h4>
            <p><?php echo nl2br(htmlspecialchars($compra['observaciones'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Footer con Firmas -->
        <div class="footer">
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line">Firma y Sello Proveedor</div>
                    <p><?php echo htmlspecialchars($compra['nombre_proveedor']); ?></p>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Firma y Sello Receptor</div>
                    <p>Tu Empresa S.A.</p>
                </div>
            </div>
            
            <div class="footer-info">
                <p>Este documento es un comprobante de compra generado por el Sistema de Gestión Comercial 2.0</p>
                <p>Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-imprimir al cargar (opcional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>