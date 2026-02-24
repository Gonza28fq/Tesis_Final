<?php
// =============================================
// modules/reportes/financiero.php
// Reporte Financiero Completo
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('reportes', 'financieros');

$titulo_pagina = 'Reporte Financiero';
$db = getDB();

// Parámetros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

// ============================================
// VENTAS
// ============================================
$sql_ventas = "SELECT 
    COUNT(*) as cantidad,
    COALESCE(SUM(subtotal), 0) as subtotal,
    COALESCE(SUM(descuento), 0) as descuentos,
    COALESCE(SUM(impuestos), 0) as impuestos,
    COALESCE(SUM(total), 0) as total
    FROM ventas 
    WHERE fecha_venta BETWEEN ? AND ? AND estado = 'completada'";
$ventas = $db->query($sql_ventas, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);

// Ventas por forma de pago
$sql_ventas_forma = "SELECT 
    COALESCE(forma_pago, 'Sin especificar') as forma_pago,
    COUNT(*) as cantidad,
    COALESCE(SUM(total), 0) as total
    FROM ventas
    WHERE fecha_venta BETWEEN ? AND ? AND estado = 'completada'
    GROUP BY forma_pago
    ORDER BY total DESC";
$ventas_forma_pago = $db->query($sql_ventas_forma, [$fecha_desde, $fecha_hasta])->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// COMPRAS
// ============================================
$sql_compras = "SELECT 
    COUNT(*) as cantidad,
    COALESCE(SUM(subtotal), 0) as subtotal,
    COALESCE(SUM(impuestos), 0) as impuestos,
    COALESCE(SUM(total), 0) as total
    FROM compras 
    WHERE fecha_compra BETWEEN ? AND ? AND estado = 'recibida'";
$compras = $db->query($sql_compras, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);

// ============================================
// COSTO DE VENTAS
// ============================================
$sql_costo = "SELECT 
    COALESCE(SUM(vd.cantidad * p.precio_costo), 0) as costo_total
    FROM ventas_detalle vd
    INNER JOIN ventas v ON vd.id_venta = v.id_venta
    INNER JOIN productos p ON vd.id_producto = p.id_producto
    WHERE v.fecha_venta BETWEEN ? AND ? AND v.estado = 'completada'";
$costo_ventas = $db->query($sql_costo, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);

// ============================================
// CÁLCULOS
// ============================================
$utilidad_bruta = $ventas['total'] - $costo_ventas['costo_total'];
$margen_ganancia = $ventas['total'] > 0 ? ($utilidad_bruta / $ventas['total']) * 100 : 0;
$resultado_operativo = $utilidad_bruta - $compras['total'];

// ============================================
// EVOLUCIÓN MENSUAL (últimos 6 meses) - CORREGIDO
// ============================================
$sql_mensual = "SELECT 
    DATE_FORMAT(fecha_venta, '%Y-%m') as mes,
    COALESCE(SUM(total), 0) as total_ventas,
    COUNT(*) as cantidad_ventas
    FROM ventas
    WHERE fecha_venta >= DATE_SUB(?, INTERVAL 6 MONTH)
    AND fecha_venta <= ?
    AND estado = 'completada'
    GROUP BY DATE_FORMAT(fecha_venta, '%Y-%m')
    ORDER BY mes";
$evolucion_mensual = $db->query($sql_mensual, [$fecha_hasta, $fecha_hasta])->fetchAll(PDO::FETCH_ASSOC);

// Formatear nombres de meses después de la consulta - SIN strftime() deprecado
$meses_es = ['Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr', 'May' => 'May', 'Jun' => 'Jun',
             'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'];

foreach ($evolucion_mensual as &$mes) {
    $fecha = $mes['mes'] . '-01';
    $mes_ingles = date('M Y', strtotime($fecha));
    $mes_parts = explode(' ', $mes_ingles);
    $mes['mes_nombre'] = ($meses_es[$mes_parts[0]] ?? $mes_parts[0]) . ' ' . $mes_parts[1];
}
unset($mes);

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="dollar-sign"></i> Reporte Financiero</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Reportes</a></li>
            <li class="breadcrumb-item active">Financiero</li>
        </ol>
    </nav>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="search"></i> Generar
                </button>
                <button type="button" class="btn btn-success" onclick="exportarPDF()">
                    <i data-feather="file"></i> PDF
                </button>
                <button type="button" class="btn btn-info" onclick="exportarExcel()">
                    <i data-feather="file-text"></i> Excel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumen Ejecutivo -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Total Ingresos</h6>
                <h2 class="mb-0"><?php echo formatearMoneda($ventas['total']); ?></h2>
                <small><?php echo $ventas['cantidad']; ?> ventas</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Total Egresos</h6>
                <h2 class="mb-0"><?php echo formatearMoneda($compras['total']); ?></h2>
                <small><?php echo $compras['cantidad']; ?> compras</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Utilidad Bruta</h6>
                <h2 class="mb-0"><?php echo formatearMoneda($utilidad_bruta); ?></h2>
                <small>Margen: <?php echo number_format($margen_ganancia, 1); ?>%</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-<?php echo $resultado_operativo >= 0 ? 'info' : 'warning'; ?> text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50">Resultado Operativo</h6>
                <h2 class="mb-0"><?php echo formatearMoneda($resultado_operativo); ?></h2>
                <small><?php echo $resultado_operativo >= 0 ? 'Positivo' : 'Negativo'; ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Detalle de Ingresos -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i data-feather="trending-up"></i> Detalle de Ingresos (Ventas)</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr>
                            <td><strong>Subtotal de Ventas:</strong></td>
                            <td class="text-end"><?php echo formatearMoneda($ventas['subtotal']); ?></td>
                        </tr>
                        <tr>
                            <td>Descuentos Otorgados:</td>
                            <td class="text-end text-danger">(<?php echo formatearMoneda($ventas['descuentos']); ?>)</td>
                        </tr>
                        <tr>
                            <td>IVA Recaudado:</td>
                            <td class="text-end"><?php echo formatearMoneda($ventas['impuestos']); ?></td>
                        </tr>
                        <tr class="table-success">
                            <td><strong>Total Ingresos:</strong></td>
                            <td class="text-end"><strong><?php echo formatearMoneda($ventas['total']); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <hr>
                
                <h6>Ingresos por Forma de Pago</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Forma de Pago</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ventas_forma_pago)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">No hay datos</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($ventas_forma_pago as $forma): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($forma['forma_pago']); ?></td>
                                    <td class="text-center"><?php echo $forma['cantidad']; ?></td>
                                    <td class="text-end"><?php echo formatearMoneda($forma['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i data-feather="trending-down"></i> Detalle de Egresos</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tbody>
                        <tr>
                            <td><strong>Costo de Ventas:</strong></td>
                            <td class="text-end"><?php echo formatearMoneda($costo_ventas['costo_total']); ?></td>
                        </tr>
                        <tr>
                            <td>Compras a Proveedores (Subtotal):</td>
                            <td class="text-end"><?php echo formatearMoneda($compras['subtotal']); ?></td>
                        </tr>
                        <tr>
                            <td>IVA en Compras:</td>
                            <td class="text-end"><?php echo formatearMoneda($compras['impuestos']); ?></td>
                        </tr>
                        <tr class="table-danger">
                            <td><strong>Total Egresos:</strong></td>
                            <td class="text-end"><strong><?php echo formatearMoneda($compras['total'] + $costo_ventas['costo_total']); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <hr>
                
                <h6>Análisis de Costos</h6>
                <div class="progress mb-2" style="height: 30px;">
                    <?php 
                    $porcentaje_utilidad = $ventas['total'] > 0 ? ($utilidad_bruta / $ventas['total']) * 100 : 0;
                    $porcentaje_costo = $ventas['total'] > 0 ? ($costo_ventas['costo_total'] / $ventas['total']) * 100 : 0;
                    ?>
                    <div class="progress-bar bg-success" style="width: <?php echo $porcentaje_utilidad; ?>%">
                        Utilidad: <?php echo number_format($margen_ganancia, 1); ?>%
                    </div>
                    <div class="progress-bar bg-danger" style="width: <?php echo $porcentaje_costo; ?>%">
                        Costos: <?php echo number_format($porcentaje_costo, 1); ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Evolución Mensual - TABLA SIMPLE -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i data-feather="bar-chart"></i> Evolución Mensual de Ventas (Últimos 6 Meses)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($evolucion_mensual)): ?>
        <div class="text-center py-4 text-muted">
            <i data-feather="inbox"></i>
            <p>No hay datos suficientes para mostrar</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Mes</th>
                        <th class="text-center">Cantidad Ventas</th>
                        <th class="text-end">Total Vendido</th>
                        <th class="text-end">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_general = 0;
                    $total_ventas_count = 0;
                    foreach ($evolucion_mensual as $mes): 
                        $promedio = $mes['cantidad_ventas'] > 0 ? $mes['total_ventas'] / $mes['cantidad_ventas'] : 0;
                        $total_general += $mes['total_ventas'];
                        $total_ventas_count += $mes['cantidad_ventas'];
                    ?>
                    <tr>
                        <td><strong><?php echo $mes['mes_nombre']; ?></strong></td>
                        <td class="text-center"><?php echo $mes['cantidad_ventas']; ?></td>
                        <td class="text-end"><?php echo formatearMoneda($mes['total_ventas']); ?></td>
                        <td class="text-end text-muted"><?php echo formatearMoneda($promedio); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTALES</td>
                        <td class="text-center"><?php echo $total_ventas_count; ?></td>
                        <td class="text-end"><?php echo formatearMoneda($total_general); ?></td>
                        <td class="text-end"><?php echo formatearMoneda($total_ventas_count > 0 ? $total_general / $total_ventas_count : 0); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resumen de Impuestos -->
<div class="card">
    <div class="card-header bg-warning">
        <h5 class="mb-0"><i data-feather="file-text"></i> Resumen de Impuestos</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>IVA Débito Fiscal (Ventas)</h6>
                <table class="table table-sm">
                    <tr>
                        <td>IVA cobrado en ventas:</td>
                        <td class="text-end"><strong><?php echo formatearMoneda($ventas['impuestos']); ?></strong></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>IVA Crédito Fiscal (Compras)</h6>
                <table class="table table-sm">
                    <tr>
                        <td>IVA pagado en compras:</td>
                        <td class="text-end"><strong><?php echo formatearMoneda($compras['impuestos']); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-12">
                <table class="table">
                    <tr class="table-<?php echo ($ventas['impuestos'] - $compras['impuestos']) >= 0 ? 'warning' : 'success'; ?>">
                        <td><strong>Saldo de IVA a Pagar/Favor:</strong></td>
                        <td class="text-end">
                            <strong class="fs-5">
                                <?php 
                                $saldo_iva = $ventas['impuestos'] - $compras['impuestos'];
                                echo formatearMoneda(abs($saldo_iva));
                                echo $saldo_iva >= 0 ? ' (a Pagar)' : ' (a Favor)';
                                ?>
                            </strong>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportarPDF() {
    const params = new URLSearchParams(window.location.search);
    const url = 'exportar.php?tipo=financiero&formato=pdf&' + params.toString();
    window.open(url, '_blank');
}

function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    const url = 'exportar.php?tipo=financiero&formato=excel&' + params.toString();
    window.location.href = url;
}

feather.replace();
</script>

<?php include '../../includes/footer.php'; ?>