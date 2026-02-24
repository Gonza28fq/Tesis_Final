<?php
// =============================================
// modules/notas_pedido/imprimir.php
// Imprimir Nota de Pedido - Diseño Profesional
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('notas_pedido', 'ver');

$id_nota = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_nota <= 0) {
    die('ID de nota inválido');
}

$db = getDB();

// Consultar nota
$sql = "SELECT np.*, 
        p.nombre_proveedor, p.cuit, p.direccion as direccion_proveedor,
        p.telefono as telefono_proveedor, p.email as email_proveedor,
        u1.nombre_completo as solicitante,
        u2.nombre_completo as aprobador
        FROM notas_pedido np
        INNER JOIN proveedores p ON np.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u1 ON np.id_usuario_solicitante = u1.id_usuario
        LEFT JOIN usuarios u2 ON np.id_usuario_aprobador = u2.id_usuario
        WHERE np.id_nota_pedido = ?";

$nota = $db->query($sql, [$id_nota])->fetch();

if (!$nota) {
    die('Nota de pedido no encontrada');
}

// Consultar detalle
$sql_detalle = "SELECT npd.*, 
                p.codigo, p.nombre, p.unidad_medida,
                (SELECT COALESCE(SUM(cantidad), 0) FROM stock WHERE id_producto = p.id_producto) as stock_actual,
                p.stock_minimo
                FROM notas_pedido_detalle npd
                INNER JOIN productos p ON npd.id_producto = p.id_producto
                WHERE npd.id_nota_pedido = ?
                ORDER BY p.nombre";

$detalles = $db->query($sql_detalle, [$id_nota])->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Pedido <?php echo htmlspecialchars($nota['numero_nota']); ?></title>
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
            font-size: 18px;
            margin-bottom: 10px;
            color: #2563eb;
        }
        
        .document-info .numero {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
            padding: 5px;
            background: #f3f4f6;
        }
        
        .document-info p {
            margin: 5px 0;
            font-size: 11px;
        }
        
        .estado-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        .estado-pendiente {
            background: #fef3c7;
            color: #92400e;
        }
        
        .estado-aprobada {
            background: #d1fae5;
            color: #065f46;
        }
        
        .estado-rechazada {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .estado-convertida {
            background: #dbeafe;
            color: #1e40af;
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
        
        .info-section {
            background: #fef3c7;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
            border-radius: 3px;
        }
        
        .info-section h3 {
            font-size: 13px;
            color: #92400e;
            margin-bottom: 8px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .info-grid p {
            margin: 3px 0;
            font-size: 11px;
            color: #78350f;
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
        
        .total-estimado {
            background: #dbeafe !important;
            color: #1e40af !important;
            font-size: 14px !important;
        }
        
        .observations {
            margin: 20px 0;
            padding: 15px;
            background: #e0e7ff;
            border-left: 4px solid #4f46e5;
            border-radius: 3px;
        }
        
        .observations h4 {
            margin-bottom: 8px;
            color: #3730a3;
            font-size: 13px;
        }
        
        .observations p {
            color: #312e81;
            font-size: 11px;
            line-height: 1.5;
        }
        
        .rejection-box {
            margin: 20px 0;
            padding: 15px;
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            border-radius: 3px;
        }
        
        .rejection-box h4 {
            margin-bottom: 8px;
            color: #991b1b;
            font-size: 13px;
        }
        
        .rejection-box p {
            color: #7f1d1d;
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
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
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
        
        .urgente {
            background: #fee2e2;
            color: #991b1b;
            padding: 8px 12px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
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
                <h2>NOTA DE PEDIDO</h2>
                <div class="numero"><?php echo htmlspecialchars($nota['numero_nota']); ?></div>
                <p><strong>Fecha Solicitud:</strong><br><?php echo formatearFecha($nota['fecha_solicitud']); ?></p>
                <?php if ($nota['fecha_necesidad']): ?>
                <p><strong>Fecha Necesidad:</strong><br><?php echo formatearFecha($nota['fecha_necesidad']); ?></p>
                <?php endif; ?>
                <span class="estado-badge estado-<?php echo $nota['estado']; ?>">
                    <?php echo strtoupper($nota['estado']); ?>
                </span>
            </div>
        </div>
        
        <!-- Urgente si la fecha de necesidad es próxima -->
        <?php 
        if ($nota['fecha_necesidad']) {
            $dias_restantes = (strtotime($nota['fecha_necesidad']) - time()) / (60 * 60 * 24);
            if ($dias_restantes <= 3 && $dias_restantes >= 0 && $nota['estado'] == 'pendiente'):
        ?>
        <div class="urgente">
            ⚠️ URGENTE - FECHA DE NECESIDAD: <?php echo formatearFecha($nota['fecha_necesidad']); ?> (<?php echo ceil($dias_restantes); ?> días)
        </div>
        <?php 
            endif;
        }
        ?>
        
        <!-- Información del Proveedor -->
        <div class="proveedor-section">
            <h3>PROVEEDOR SOLICITADO</h3>
            <div class="proveedor-info">
                <div>
                    <p><strong>Razón Social:</strong> <?php echo htmlspecialchars($nota['nombre_proveedor']); ?></p>
                    <?php if ($nota['cuit']): ?>
                    <p><strong>CUIT:</strong> <?php echo htmlspecialchars($nota['cuit']); ?></p>
                    <?php endif; ?>
                    <?php if ($nota['direccion_proveedor']): ?>
                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($nota['direccion_proveedor']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($nota['telefono_proveedor']): ?>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($nota['telefono_proveedor']); ?></p>
                    <?php endif; ?>
                    <?php if ($nota['email_proveedor']): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($nota['email_proveedor']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Información de la Solicitud -->
        <div class="info-section">
            <h3>INFORMACIÓN DE LA SOLICITUD</h3>
            <div class="info-grid">
                <p><strong>Solicitado por:</strong> <?php echo htmlspecialchars($nota['solicitante']); ?></p>
                <p><strong>Fecha de Solicitud:</strong> <?php echo formatearFecha($nota['fecha_solicitud']); ?></p>
                <?php if ($nota['fecha_necesidad']): ?>
                <p><strong>Fecha de Necesidad:</strong> <?php echo formatearFecha($nota['fecha_necesidad']); ?></p>
                <?php endif; ?>
                <?php if ($nota['aprobador']): ?>
                <p><strong><?php echo $nota['estado'] == 'rechazada' ? 'Rechazado' : 'Aprobado'; ?> por:</strong> <?php echo htmlspecialchars($nota['aprobador']); ?></p>
                <?php if ($nota['fecha_aprobacion']): ?>
                <p><strong>Fecha:</strong> <?php echo formatearFechaHora($nota['fecha_aprobacion']); ?></p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detalle de Productos -->
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Código</th>
                    <th style="width: 35%;">Descripción</th>
                    <th style="width: 10%;">Stock</th>
                    <th class="text-center" style="width: 10%;">Cantidad</th>
                    <th class="text-right" style="width: 12%;">Precio Est.</th>
                    <th style="width: 23%;">Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $monto_total_estimado = 0;
                foreach ($detalles as $detalle): 
                    $stock_bajo = $detalle['stock_actual'] < $detalle['stock_minimo'];
                    $subtotal = $detalle['cantidad'] * $detalle['precio_estimado'];
                    $monto_total_estimado += $subtotal;
                ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($detalle['codigo']); ?></code></td>
                    <td>
                        <strong><?php echo htmlspecialchars($detalle['nombre']); ?></strong>
                        <br><small style="color: #6b7280;"><?php echo htmlspecialchars($detalle['unidad_medida']); ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $detalle['stock_actual'] == 0 ? 'danger' : ($stock_bajo ? 'warning' : 'success'); ?>">
                            <?php echo number_format($detalle['stock_actual']); ?>
                        </span>
                        <?php if ($stock_bajo): ?>
                        <br><small style="color: #6b7280;">Mín: <?php echo $detalle['stock_minimo']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-primary" style="font-size: 12px; padding: 5px 10px;">
                            <?php echo number_format($detalle['cantidad']); ?>
                        </span>
                    </td>
                    <td class="text-right">
                        <?php if ($detalle['precio_estimado'] > 0): ?>
                            <strong><?php echo formatearMoneda($detalle['precio_estimado']); ?></strong>
                            <br><small style="color: #6b7280;">Sub: <?php echo formatearMoneda($subtotal); ?></small>
                        <?php else: ?>
                            <span style="color: #9ca3af;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($detalle['observaciones']): ?>
                        <small><?php echo htmlspecialchars($detalle['observaciones']); ?></small>
                        <?php else: ?>
                        <span style="color: #9ca3af;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($monto_total_estimado > 0): ?>
            <tfoot>
                <tr class="total-estimado">
                    <td colspan="4" class="text-right">MONTO TOTAL ESTIMADO:</td>
                    <td class="text-right" colspan="2"><?php echo formatearMoneda($monto_total_estimado); ?></td>
                </tr>
                <tr>
                    <td colspan="6" style="padding: 5px 10px; font-size: 10px; color: #6b7280; text-align: center;">
                        * El monto estimado es referencial y puede variar en la compra real
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        
        <!-- Observaciones -->
        <?php if ($nota['observaciones']): ?>
        <div class="observations">
            <h4>OBSERVACIONES:</h4>
            <p><?php echo nl2br(htmlspecialchars($nota['observaciones'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Motivo de Rechazo -->
        <?php if ($nota['estado'] == 'rechazada' && $nota['motivo_rechazo']): ?>
        <div class="rejection-box">
            <h4>⚠️ MOTIVO DEL RECHAZO:</h4>
            <p><?php echo nl2br(htmlspecialchars($nota['motivo_rechazo'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Footer con Firmas -->
        <div class="footer">
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line">Firma Solicitante</div>
                    <p><?php echo htmlspecialchars($nota['solicitante']); ?></p>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Firma y Sello Aprobación</div>
                    <p><?php echo $nota['aprobador'] ? htmlspecialchars($nota['aprobador']) : 'Pendiente'; ?></p>
                </div>
            </div>
            
            <div class="footer-info">
                <p>Este documento es una nota de pedido generada por el Sistema de Gestión Comercial 2.0</p>
                <p>Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?></p>
                <?php if ($nota['estado'] == 'pendiente'): ?>
                <p style="color: #dc2626; font-weight: bold;">⚠️ DOCUMENTO PENDIENTE DE APROBACIÓN - NO VÁLIDO PARA COMPRA</p>
                <?php elseif ($nota['estado'] == 'aprobada'): ?>
                <p style="color: #059669; font-weight: bold;">✓ DOCUMENTO APROBADO - AUTORIZADO PARA COMPRA</p>
                <?php elseif ($nota['estado'] == 'rechazada'): ?>
                <p style="color: #dc2626; font-weight: bold;">✗ DOCUMENTO RECHAZADO - NO VÁLIDO PARA COMPRA</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>