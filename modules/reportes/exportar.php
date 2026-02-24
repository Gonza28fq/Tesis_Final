<?php
// =============================================
// modules/reportes/exportar.php
// Exportar Reportes a Excel y PDF
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'financiero';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'excel';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

$db = getDB();

// ============================================
// OBTENER DATOS SEGÚN TIPO DE REPORTE
// ============================================
if ($tipo == 'financiero') {
    requierePermiso('reportes', 'financieros');
    
    // Ventas
    $sql_ventas = "SELECT 
        COUNT(*) as cantidad,
        COALESCE(SUM(subtotal), 0) as subtotal,
        COALESCE(SUM(descuento), 0) as descuentos,
        COALESCE(SUM(impuestos), 0) as impuestos,
        COALESCE(SUM(total), 0) as total
        FROM ventas 
        WHERE fecha_venta BETWEEN ? AND ? AND estado = 'completada'";
    $ventas = $db->query($sql_ventas, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);
    
    // Compras
    $sql_compras = "SELECT 
        COUNT(*) as cantidad,
        COALESCE(SUM(total), 0) as total
        FROM compras 
        WHERE fecha_compra BETWEEN ? AND ? AND estado = 'recibida'";
    $compras = $db->query($sql_compras, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);
    
    // Costo de ventas
    $sql_costo = "SELECT 
        COALESCE(SUM(vd.cantidad * p.precio_costo), 0) as costo_total
        FROM ventas_detalle vd
        INNER JOIN ventas v ON vd.id_venta = v.id_venta
        INNER JOIN productos p ON vd.id_producto = p.id_producto
        WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado = 'completada'";
    $costo_ventas = $db->query($sql_costo, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);
    
    $utilidad_bruta = $ventas['total'] - $costo_ventas['costo_total'];
    $margen_ganancia = $ventas['total'] > 0 ? ($utilidad_bruta / $ventas['total']) * 100 : 0;
    
} elseif ($tipo == 'stock') {
    requierePermiso('reportes', 'stock');
    
    $sql_inventario = "SELECT 
        p.codigo, p.nombre, p.unidad_medida,
        c.nombre_categoria,
        p.precio_costo, p.precio_base,
        p.stock_minimo, p.stock_maximo,
        u.nombre_ubicacion,
        COALESCE(s.cantidad, 0) as stock_actual,
        (COALESCE(s.cantidad, 0) * p.precio_costo) as valor_stock
        FROM productos p
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        LEFT JOIN stock s ON p.id_producto = s.id_producto
        LEFT JOIN ubicaciones u ON s.id_ubicacion = u.id_ubicacion
        WHERE p.estado = 'activo'
        ORDER BY p.nombre";
    $inventario = $db->query($sql_inventario)->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// EXPORTAR A EXCEL
// ============================================
if ($formato == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    if ($tipo == 'financiero') {
        ?>
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #4CAF50; color: white; font-weight: bold; }
                .header { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                .total { background-color: #f0f0f0; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>REPORTE FINANCIERO</h1>
            <p><strong>Período:</strong> <?php echo formatearFecha($fecha_desde); ?> al <?php echo formatearFecha($fecha_hasta); ?></p>
            <p><strong>Generado:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            
            <h2>RESUMEN EJECUTIVO</h2>
            <table>
                <tr>
                    <th>Concepto</th>
                    <th>Cantidad</th>
                    <th>Monto</th>
                </tr>
                <tr>
                    <td>Ventas Realizadas</td>
                    <td><?php echo $ventas['cantidad']; ?></td>
                    <td><?php echo formatearMoneda($ventas['total']); ?></td>
                </tr>
                <tr>
                    <td>Subtotal de Ventas</td>
                    <td></td>
                    <td><?php echo formatearMoneda($ventas['subtotal']); ?></td>
                </tr>
                <tr>
                    <td>Descuentos Otorgados</td>
                    <td></td>
                    <td><?php echo formatearMoneda($ventas['descuentos']); ?></td>
                </tr>
                <tr>
                    <td>IVA Recaudado</td>
                    <td></td>
                    <td><?php echo formatearMoneda($ventas['impuestos']); ?></td>
                </tr>
                <tr class="total">
                    <td><strong>TOTAL INGRESOS</strong></td>
                    <td><strong><?php echo $ventas['cantidad']; ?></strong></td>
                    <td><strong><?php echo formatearMoneda($ventas['total']); ?></strong></td>
                </tr>
                <tr>
                    <td colspan="3">&nbsp;</td>
                </tr>
                <tr>
                    <td>Costo de Ventas</td>
                    <td></td>
                    <td><?php echo formatearMoneda($costo_ventas['costo_total']); ?></td>
                </tr>
                <tr>
                    <td>Compras a Proveedores</td>
                    <td><?php echo $compras['cantidad']; ?></td>
                    <td><?php echo formatearMoneda($compras['total']); ?></td>
                </tr>
                <tr class="total">
                    <td><strong>TOTAL EGRESOS</strong></td>
                    <td><strong><?php echo $compras['cantidad']; ?></strong></td>
                    <td><strong><?php echo formatearMoneda($compras['total'] + $costo_ventas['costo_total']); ?></strong></td>
                </tr>
                <tr>
                    <td colspan="3">&nbsp;</td>
                </tr>
                <tr class="total">
                    <td><strong>UTILIDAD BRUTA</strong></td>
                    <td></td>
                    <td><strong><?php echo formatearMoneda($utilidad_bruta); ?></strong></td>
                </tr>
                <tr class="total">
                    <td><strong>MARGEN DE GANANCIA</strong></td>
                    <td></td>
                    <td><strong><?php echo number_format($margen_ganancia, 2); ?>%</strong></td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        
    } elseif ($tipo == 'stock') {
        ?>
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #2196F3; color: white; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>REPORTE DE INVENTARIO</h1>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            
            <table>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Ubicación</th>
                    <th>Stock Actual</th>
                    <th>Mín/Máx</th>
                    <th>Precio Costo</th>
                    <th>Valor Stock</th>
                </tr>
                <?php 
                $total_valor = 0;
                foreach ($inventario as $item): 
                    $total_valor += $item['valor_stock'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($item['nombre_categoria'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($item['nombre_ubicacion'] ?? 'N/A'); ?></td>
                    <td><?php echo $item['stock_actual']; ?></td>
                    <td><?php echo $item['stock_minimo'] . ' / ' . $item['stock_maximo']; ?></td>
                    <td><?php echo formatearMoneda($item['precio_costo']); ?></td>
                    <td><?php echo formatearMoneda($item['valor_stock']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f0f0f0; font-weight: bold;">
                    <td colspan="7" style="text-align: right;">TOTAL VALORIZACIÓN:</td>
                    <td><?php echo formatearMoneda($total_valor); ?></td>
                </tr>
            </table>
        </body>
        </html>
        <?php
    }
    
    // Registrar en auditoría
    registrarAuditoria('reportes', 'exportar_excel', "Tipo: $tipo, Período: $fecha_desde a $fecha_hasta");
    
// ============================================
// EXPORTAR A PDF (Versión simple con HTML)
// ============================================
} elseif ($formato == 'pdf') {
    header('Content-Type: text/html; charset=UTF-8');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reporte <?php echo ucfirst($tipo); ?></title>
        <style>
            @media print {
                .no-print { display: none; }
                body { margin: 0; padding: 20px; }
            }
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4CAF50; color: white; }
            .total { background-color: #f0f0f0; font-weight: bold; }
            .info { background-color: #e3f2fd; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; cursor: pointer; font-size: 14px;">
                🖨️ Imprimir / Guardar como PDF
            </button>
            <button onclick="window.close()" style="padding: 10px 20px; background-color: #f44336; color: white; border: none; cursor: pointer; font-size: 14px; margin-left: 10px;">
                ❌ Cerrar
            </button>
        </div>
        
        <h1>REPORTE <?php echo strtoupper($tipo); ?></h1>
        
        <div class="info">
            <strong>Período:</strong> <?php echo formatearFecha($fecha_desde); ?> al <?php echo formatearFecha($fecha_hasta); ?><br>
            <strong>Generado:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
            <strong>Usuario:</strong> <?php echo $_SESSION['usuario_nombre']; ?>
        </div>
        
        <?php if ($tipo == 'financiero'): ?>
            <h2>RESUMEN EJECUTIVO</h2>
            <table>
                <tr>
                    <th>Concepto</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Monto</th>
                </tr>
                <tr>
                    <td><strong>INGRESOS</strong></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td>Ventas Realizadas</td>
                    <td style="text-align: right;"><?php echo $ventas['cantidad']; ?></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($ventas['total']); ?></td>
                </tr>
                <tr>
                    <td>Subtotal de Ventas</td>
                    <td></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($ventas['subtotal']); ?></td>
                </tr>
                <tr>
                    <td>Descuentos Otorgados</td>
                    <td></td>
                    <td style="text-align: right;">(<?php echo formatearMoneda($ventas['descuentos']); ?>)</td>
                </tr>
                <tr>
                    <td>IVA Recaudado</td>
                    <td></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($ventas['impuestos']); ?></td>
                </tr>
                <tr class="total">
                    <td><strong>TOTAL INGRESOS</strong></td>
                    <td style="text-align: right;"><strong><?php echo $ventas['cantidad']; ?></strong></td>
                    <td style="text-align: right;"><strong><?php echo formatearMoneda($ventas['total']); ?></strong></td>
                </tr>
                <tr>
                    <td colspan="3">&nbsp;</td>
                </tr>
                <tr>
                    <td><strong>EGRESOS</strong></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td>Costo de Ventas</td>
                    <td></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($costo_ventas['costo_total']); ?></td>
                </tr>
                <tr>
                    <td>Compras a Proveedores</td>
                    <td style="text-align: right;"><?php echo $compras['cantidad']; ?></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($compras['total']); ?></td>
                </tr>
                <tr class="total">
                    <td><strong>TOTAL EGRESOS</strong></td>
                    <td style="text-align: right;"><strong><?php echo $compras['cantidad']; ?></strong></td>
                    <td style="text-align: right;"><strong><?php echo formatearMoneda($compras['total'] + $costo_ventas['costo_total']); ?></strong></td>
                </tr>
                <tr>
                    <td colspan="3">&nbsp;</td>
                </tr>
                <tr class="total" style="background-color: #c8e6c9;">
                    <td><strong>UTILIDAD BRUTA</strong></td>
                    <td></td>
                    <td style="text-align: right;"><strong><?php echo formatearMoneda($utilidad_bruta); ?></strong></td>
                </tr>
                <tr class="total" style="background-color: #c8e6c9;">
                    <td><strong>MARGEN DE GANANCIA</strong></td>
                    <td></td>
                    <td style="text-align: right;"><strong><?php echo number_format($margen_ganancia, 2); ?>%</strong></td>
                </tr>
            </table>
            
        <?php elseif ($tipo == 'stock'): ?>
            <h2>INVENTARIO ACTUAL</h2>
            <table>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Ubicación</th>
                    <th style="text-align: center;">Stock</th>
                    <th style="text-align: center;">Mín/Máx</th>
                    <th style="text-align: right;">P. Costo</th>
                    <th style="text-align: right;">Valor</th>
                </tr>
                <?php 
                $total_valor = 0;
                foreach ($inventario as $item): 
                    $total_valor += $item['valor_stock'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($item['nombre_categoria'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($item['nombre_ubicacion'] ?? 'N/A'); ?></td>
                    <td style="text-align: center;"><?php echo $item['stock_actual']; ?></td>
                    <td style="text-align: center;"><?php echo $item['stock_minimo'] . '/' . $item['stock_maximo']; ?></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($item['precio_costo']); ?></td>
                    <td style="text-align: right;"><?php echo formatearMoneda($item['valor_stock']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td colspan="7" style="text-align: right;"><strong>TOTAL VALORIZACIÓN:</strong></td>
                    <td style="text-align: right;"><strong><?php echo formatearMoneda($total_valor); ?></strong></td>
                </tr>
            </table>
        <?php endif; ?>
        
        <script>
            // Auto-imprimir al cargar (opcional)
            // window.onload = function() { window.print(); };
        </script>
    </body>
    </html>
    <?php
    
    // Registrar en auditoría
    registrarAuditoria('reportes', 'exportar_pdf', "Tipo: $tipo, Período: $fecha_desde a $fecha_hasta");
}
?>