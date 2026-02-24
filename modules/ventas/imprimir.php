<?php
// =============================================
// modules/ventas/imprimir.php
// Imprimir Comprobante de Venta
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('ventas', 'ver');

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Venta no válida');
}

$id_venta = (int)$_GET['id'];
$db = getDB();

// Obtener datos de la venta
$sql = "SELECT v.*, 
        c.nombre as cliente_nombre, 
        c.documento as cliente_documento,
        c.direccion as cliente_direccion,
        c.ciudad as cliente_ciudad,
        c.provincia as cliente_provincia,
        c.codigo_postal as cliente_cp,
        c.cuit_cuil as cliente_cuit,
        c.condicion_iva as cliente_condicion_iva,
        tc.nombre as tipo_comprobante_nombre, 
        tc.codigo as tipo_comprobante_codigo,
        u.nombre_completo as vendedor,
        pv.nombre as punto_venta_nombre,
        pv.numero_punto,
        pv.direccion as punto_venta_direccion
        FROM ventas v
        INNER JOIN clientes c ON v.id_cliente = c.id_cliente
        INNER JOIN tipos_comprobante tc ON v.id_tipo_comprobante = tc.id_tipo_comprobante
        INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
        INNER JOIN puntos_venta pv ON v.id_punto_venta = pv.id_punto_venta
        WHERE v.id_venta = ?";

$venta = $db->query($sql, [$id_venta])->fetch();

if (!$venta) {
    die('Venta no encontrada');
}

// Verificar si es vendedor y solo puede ver sus propias ventas
$es_vendedor = (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 4);
if ($es_vendedor && $venta['id_usuario'] != $_SESSION['usuario_id']) {
    die('No tiene permisos para ver esta venta');
}

// Obtener detalle de la venta
$sql_detalle = "SELECT vd.*, 
                p.codigo as producto_codigo,
                p.nombre as producto_nombre,
                p.unidad_medida
                FROM ventas_detalle vd
                INNER JOIN productos p ON vd.id_producto = p.id_producto
                WHERE vd.id_venta = ?
                ORDER BY vd.id_detalle_venta";

$detalle = $db->query($sql_detalle, [$id_venta])->fetchAll();

// Calcular totales
$subtotal_sin_desc = array_sum(array_column($detalle, 'subtotal'));
$total_descuentos = $venta['descuento'];
$subtotal = $subtotal_sin_desc - $total_descuentos;
$iva = $venta['impuestos'];
$total = $venta['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante - <?php echo $venta['numero_comprobante'] ?: $venta['numero_venta']; ?></title>
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
            color: #333;
            padding: 20px;
        }
        
        .comprobante {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 0;
        }
        
        .header {
            display: flex;
            border-bottom: 2px solid #333;
        }
        
        .header-left, .header-right {
            flex: 1;
            padding: 15px;
        }
        
        .header-center {
            width: 100px;
            border-left: 2px solid #333;
            border-right: 2px solid #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .tipo-comprobante {
            font-size: 48px;
            font-weight: bold;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .codigo-comprobante {
            font-size: 11px;
            text-align: center;
        }
        
        .empresa-nombre {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .empresa-info {
            font-size: 11px;
            margin: 2px 0;
        }
        
        .numero-comprobante {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            margin-top: 10px;
        }
        
        .fecha {
            text-align: right;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .cliente-section {
            padding: 15px;
            border-bottom: 1px solid #333;
            background: #f8f9fa;
        }
        
        .cliente-section h3 {
            font-size: 13px;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .cliente-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
            font-size: 11px;
        }
        
        .info-row {
            display: flex;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 100px;
        }
        
        .detalle-section {
            padding: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #333;
            color: white;
        }
        
        th {
            padding: 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }
        
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totales-section {
            padding: 15px;
            border-top: 2px solid #333;
        }
        
        .totales {
            margin-left: auto;
            width: 300px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12px;
        }
        
        .total-row.final {
            border-top: 2px solid #333;
            margin-top: 5px;
            padding-top: 8px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .footer {
            padding: 15px;
            border-top: 1px solid #333;
            font-size: 10px;
            background: #f8f9fa;
        }
        
        .cae-section {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cae-info {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }
        
        .observaciones {
            margin-top: 10px;
            padding: 10px;
            background: #fffbcc;
            border-left: 3px solid #ffeb3b;
        }
        
        .qr-code {
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            border: 1px dashed #999;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .comprobante {
                border: 1px solid #333;
                page-break-after: always;
            }
        }
        
        .btn-imprimir {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .btn-imprimir:hover {
            background: #0056b3;
        }
        
        .btn-cerrar {
            position: fixed;
            top: 20px;
            right: 150px;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .btn-cerrar:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <!-- Botones de acción -->
    <button onclick="window.print()" class="btn-imprimir no-print">
        🖨️ Imprimir
    </button>
    <button onclick="window.close()" class="btn-cerrar no-print">
        ✖️ Cerrar
    </button>

    <!-- Comprobante -->
    <div class="comprobante">
        <!-- Encabezado -->
        <div class="header">
            <div class="header-left">
                <div class="empresa-nombre">TU EMPRESA S.A.</div>
                <div class="empresa-info">Razón Social: Tu Empresa S.A.</div>
                <div class="empresa-info">Domicilio Comercial: <?php echo htmlspecialchars($venta['punto_venta_direccion'] ?: 'Av. Principal 1234'); ?></div>
                <div class="empresa-info">Condición frente al IVA: Responsable Inscripto</div>
                <div class="empresa-info">CUIT: 30-12345678-9</div>
                <div class="empresa-info">Ingresos Brutos: 901-123456-7</div>
                <div class="empresa-info">Inicio de Actividades: 01/01/2020</div>
            </div>
            
            <div class="header-center">
                <div class="tipo-comprobante"><?php echo htmlspecialchars($venta['tipo_comprobante_codigo']); ?></div>
                <div class="codigo-comprobante">COD. <?php echo str_pad($venta['id_tipo_comprobante'], 2, '0', STR_PAD_LEFT); ?></div>
            </div>
            
            <div class="header-right">
                <div class="empresa-info"><strong><?php echo htmlspecialchars($venta['tipo_comprobante_nombre']); ?></strong></div>
                <div class="numero-comprobante">
                    N° <?php echo htmlspecialchars($venta['numero_comprobante'] ?: $venta['numero_venta']); ?>
                </div>
                <div class="fecha">
                    <strong>Fecha:</strong> <?php echo formatearFecha($venta['fecha_venta']); ?>
                </div>
                <div class="empresa-info" style="margin-top: 10px;">
                    <strong>Punto de Venta:</strong> <?php echo str_pad($venta['numero_punto'], 4, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
        </div>
        
        <!-- Información del Cliente -->
        <div class="cliente-section">
            <h3>DATOS DEL CLIENTE</h3>
            <div class="cliente-info">
                <div class="info-row">
                    <span class="info-label">Razón Social:</span>
                    <span><?php echo htmlspecialchars($venta['cliente_nombre']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CUIT/DNI:</span>
                    <span><?php echo htmlspecialchars($venta['cliente_cuit'] ?: $venta['cliente_documento']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Domicilio:</span>
                    <span><?php echo htmlspecialchars($venta['cliente_direccion'] ?: '-'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Localidad:</span>
                    <span><?php echo htmlspecialchars($venta['cliente_ciudad'] ?: '-'); ?>, <?php echo htmlspecialchars($venta['cliente_provincia'] ?: '-'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Condición IVA:</span>
                    <span><?php echo htmlspecialchars($venta['cliente_condicion_iva'] ?: 'Consumidor Final'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Forma de Pago:</span>
                    <span><?php echo htmlspecialchars($venta['forma_pago'] ?: 'Efectivo'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Detalle de Items -->
        <div class="detalle-section">
            <table>
                <thead>
                    <tr>
                        <th width="80">Código</th>
                        <th>Descripción</th>
                        <th width="60" class="text-center">Cant.</th>
                        <th width="80" class="text-right">P. Unit.</th>
                        <th width="80" class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalle as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['producto_codigo']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($item['producto_nombre']); ?>
                            <?php if ($item['descuento'] > 0): ?>
                            <br><small style="color: #ff6b6b;">Descuento: <?php echo formatearMoneda($item['descuento']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo formatearNumero($item['cantidad']); ?>
                            <?php echo htmlspecialchars($item['unidad_medida']); ?>
                        </td>
                        <td class="text-right"><?php echo formatearMoneda($item['precio_unitario']); ?></td>
                        <td class="text-right"><?php echo formatearMoneda($item['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (count($detalle) < 10): ?>
                        <?php for ($i = count($detalle); $i < 10; $i++): ?>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                        <?php endfor; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totales -->
        <div class="totales-section">
            <div class="totales">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?php echo formatearMoneda($subtotal_sin_desc); ?></span>
                </div>
                
                <?php if ($total_descuentos > 0): ?>
                <div class="total-row" style="color: #ff6b6b;">
                    <span>Descuentos:</span>
                    <span>- <?php echo formatearMoneda($total_descuentos); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($iva > 0): ?>
                <div class="total-row">
                    <span>IVA (21%):</span>
                    <span><?php echo formatearMoneda($iva); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="total-row final">
                    <span>TOTAL:</span>
                    <span><?php echo formatearMoneda($total); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <!-- CAE si existe -->
            <?php if (!empty($venta['cae'])): ?>
            <div class="cae-section">
                <div class="cae-info">
                    <span>CAE N°: <?php echo htmlspecialchars($venta['cae']); ?></span>
                    <span>Vencimiento: <?php echo formatearFecha($venta['vencimiento_cae']); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Observaciones -->
            <?php if (!empty($venta['observaciones'])): ?>
            <div class="observaciones">
                <strong>Observaciones:</strong><br>
                <?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px; text-align: center; font-size: 9px; color: #666;">
                <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($venta['vendedor']); ?></p>
                <p style="margin-top: 5px;">Este comprobante fue emitido electrónicamente y es válido como factura original.</p>
                <p>Sistema de Gestión Comercial v2.0 - Documento generado el <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            
            <!-- QR Code Placeholder -->
            <?php if (!empty($venta['cae'])): ?>
            <div class="qr-code no-print">
                <small>Código QR AFIP</small><br>
                <div style="width: 80px; height: 80px; background: #f0f0f0; margin: 5px auto; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd;">
                    QR
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-imprimir al cargar (opcional - comentar si no se desea)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>