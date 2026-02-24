<?php
// =============================================
// modules/ventas/ver.php
// Ver Detalle de Venta
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('ventas', 'ver');

$id_venta = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_venta <= 0) {
    setAlerta('danger', 'ID de venta inválido');
    redirigir('index.php');
}

$db = getDB();

// Consultar venta
$sql = "SELECT v.*, 
        c.nombre as cliente_nombre, c.documento, c.cuit_cuil, c.direccion as cliente_direccion,
        c.telefono as cliente_telefono, c.email as cliente_email,
        tc.nombre as tipo_comprobante_nombre, tc.codigo as tipo_comprobante_codigo,
        u.nombre_completo as vendedor,
        pv.nombre as punto_venta_nombre, pv.numero_punto
        FROM ventas v
        INNER JOIN clientes c ON v.id_cliente = c.id_cliente
        INNER JOIN tipos_comprobante tc ON v.id_tipo_comprobante = tc.id_tipo_comprobante
        INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
        INNER JOIN puntos_venta pv ON v.id_punto_venta = pv.id_punto_venta
        WHERE v.id_venta = ?";

$venta = $db->query($sql, [$id_venta])->fetch();

if (!$venta) {
    setAlerta('danger', 'Venta no encontrada');
    redirigir('index.php');
}

// Consultar detalle
$sql_detalle = "SELECT vd.*, p.codigo, p.nombre, p.unidad_medida
                FROM ventas_detalle vd
                INNER JOIN productos p ON vd.id_producto = p.id_producto
                WHERE vd.id_venta = ?
                ORDER BY p.nombre";

$detalles = $db->query($sql_detalle, [$id_venta])->fetchAll();

$titulo_pagina = 'Detalle de Venta';

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shopping-cart"></i> Detalle de Venta</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Ventas</a></li>
            <li class="breadcrumb-item active">Detalle</li>
        </ol>
    </nav>
</div>

<!-- Estado de la venta -->
<div class="row mb-4">
    <div class="col-12">
        <?php
        $alertClass = [
            'pendiente' => 'warning',
            'completada' => 'success',
            'cancelada' => 'danger',
            'devuelta' => 'secondary'
        ];
        $iconClass = [
            'pendiente' => 'clock',
            'completada' => 'check-circle',
            'cancelada' => 'x-circle',
            'devuelta' => 'rotate-ccw'
        ];
        $mensajes = [
            'pendiente' => 'Esta venta está pendiente de completar',
            'completada' => 'Esta venta fue completada exitosamente',
            'cancelada' => 'Esta venta fue cancelada',
            'devuelta' => 'Esta venta fue anulada con Nota de Crédito'
        ];
        ?>
        <div class="alert alert-<?php echo $alertClass[$venta['estado']]; ?> d-flex justify-content-between align-items-center">
            <div>
                <i data-feather="<?php echo $iconClass[$venta['estado']]; ?>"></i>
                <strong>Estado: <?php echo ucfirst($venta['estado']); ?></strong>
                <span class="ms-2"><?php echo $mensajes[$venta['estado']]; ?></span>
            </div>
            
            <?php if ($venta['estado'] == 'completada' && tienePermiso('ventas', 'anular')): ?>
            <div class="btn-group">
                <a href="imprimir.php?id=<?php echo $id_venta; ?>" class="btn btn-secondary" target="_blank">
                    <i data-feather="printer"></i> Imprimir
                </a>
                <button type="button" class="btn btn-danger" onclick="anularVenta()">
                    <i data-feather="x-circle"></i> Anular Venta
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Columna Principal -->
    <div class="col-lg-8">
        <!-- Información de la Venta -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i data-feather="file-text"></i> Información de la Venta</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h4 class="text-primary"><?php echo htmlspecialchars($venta['numero_venta']); ?></h4>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-<?php echo $alertClass[$venta['estado']]; ?> fs-6">
                            <?php echo ucfirst($venta['estado']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Fecha de Venta</label>
                        <div class="fw-bold"><?php echo formatearFecha($venta['fecha_venta']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Vendedor</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($venta['vendedor']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Punto de Venta</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($venta['punto_venta_nombre']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Tipo de Comprobante</label>
                        <div>
                            <span class="badge bg-secondary fs-6">
                                <?php echo htmlspecialchars($venta['tipo_comprobante_codigo']); ?>
                            </span>
                            <?php echo htmlspecialchars($venta['tipo_comprobante_nombre']); ?>
                        </div>
                    </div>
                    
                    <?php if ($venta['numero_comprobante']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Número de Comprobante</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($venta['numero_comprobante']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($venta['cae']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">CAE</label>
                        <div class="fw-bold text-success"><?php echo htmlspecialchars($venta['cae']); ?></div>
                        <?php if ($venta['vencimiento_cae']): ?>
                        <small class="text-muted">Vence: <?php echo formatearFecha($venta['vencimiento_cae']); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($venta['forma_pago']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Forma de Pago</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($venta['forma_pago']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($venta['observaciones']): ?>
                <div class="mt-3">
                    <label class="text-muted small">Observaciones</label>
                    <div class="alert alert-light mb-0">
                        <?php echo nl2br(htmlspecialchars($venta['observaciones'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detalle de Productos -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i data-feather="box"></i> Productos Vendidos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Unidad</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Descuento</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $detalle): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($detalle['codigo']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($detalle['nombre']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($detalle['unidad_medida']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo number_format($detalle['cantidad']); ?></span>
                                </td>
                                <td class="text-end"><?php echo formatearMoneda($detalle['precio_unitario']); ?></td>
                                <td class="text-end">
                                    <?php if ($detalle['descuento'] > 0): ?>
                                        <span class="text-warning"><?php echo formatearMoneda($detalle['descuento']); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><strong><?php echo formatearMoneda($detalle['subtotal']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end"><strong><?php echo formatearMoneda($venta['subtotal']); ?></strong></td>
                            </tr>
                            <?php if ($venta['descuento'] > 0): ?>
                            <tr>
                                <td colspan="6" class="text-end">Descuento General:</td>
                                <td class="text-end text-warning"><?php echo formatearMoneda($venta['descuento']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="6" class="text-end">IVA (21%):</td>
                                <td class="text-end"><?php echo formatearMoneda($venta['impuestos']); ?></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="6" class="text-end"><strong class="fs-5">TOTAL:</strong></td>
                                <td class="text-end"><strong class="fs-5 text-primary"><?php echo formatearMoneda($venta['total']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Columna Lateral -->
    <div class="col-lg-4">
        <!-- Información del Cliente -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i data-feather="user"></i> Cliente</h5>
            </div>
            <div class="card-body">
                <h5 class="mb-3"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></h5>
                
                <p class="mb-2">
                    <i data-feather="file-text" width="16"></i>
                    <strong>Documento:</strong> <?php echo htmlspecialchars($venta['documento']); ?>
                </p>
                
                <?php if ($venta['cuit_cuil']): ?>
                <p class="mb-2">
                    <i data-feather="file-text" width="16"></i>
                    <strong>CUIT:</strong> <?php echo htmlspecialchars($venta['cuit_cuil']); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($venta['cliente_direccion']): ?>
                <p class="mb-2">
                    <i data-feather="map-pin" width="16"></i>
                    <strong>Dirección:</strong><br>
                    <?php echo nl2br(htmlspecialchars($venta['cliente_direccion'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($venta['cliente_telefono']): ?>
                <p class="mb-2">
                    <i data-feather="phone" width="16"></i>
                    <strong>Teléfono:</strong> <?php echo htmlspecialchars($venta['cliente_telefono']); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($venta['cliente_email']): ?>
                <p class="mb-0">
                    <i data-feather="mail" width="16"></i>
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($venta['cliente_email']); ?>">
                        <?php echo htmlspecialchars($venta['cliente_email']); ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Resumen -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i data-feather="bar-chart-2"></i> Resumen</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7">Total Productos:</dt>
                    <dd class="col-5 text-end"><?php echo count($detalles); ?></dd>
                    
                    <dt class="col-7">Total Unidades:</dt>
                    <dd class="col-5 text-end">
                        <?php 
                        $total_unidades = 0;
                        foreach ($detalles as $d) {
                            $total_unidades += $d['cantidad'];
                        }
                        echo number_format($total_unidades);
                        ?>
                    </dd>
                    
                    <dt class="col-12"><hr></dt>
                    
                    <dt class="col-7">Subtotal:</dt>
                    <dd class="col-5 text-end"><?php echo formatearMoneda($venta['subtotal']); ?></dd>
                    
                    <?php if ($venta['descuento'] > 0): ?>
                    <dt class="col-7">Descuento:</dt>
                    <dd class="col-5 text-end text-warning"><?php echo formatearMoneda($venta['descuento']); ?></dd>
                    <?php endif; ?>
                    
                    <dt class="col-7">Impuestos:</dt>
                    <dd class="col-5 text-end"><?php echo formatearMoneda($venta['impuestos']); ?></dd>
                    
                    <dt class="col-7 fw-bold fs-5">Total:</dt>
                    <dd class="col-5 text-end fw-bold text-primary fs-5"><?php echo formatearMoneda($venta['total']); ?></dd>
                </dl>
            </div>
        </div>
        
        <!-- Acciones -->
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($venta['estado'] == 'completada'): ?>
                    <a href="imprimir.php?id=<?php echo $id_venta; ?>" class="btn btn-secondary" target="_blank">
                        <i data-feather="printer"></i> Imprimir Comprobante
                    </a>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-primary">
                        <i data-feather="arrow-left"></i> Volver al Listado
                    </a>
                    
                    <?php if ($venta['estado'] == 'completada' && tienePermiso('ventas', 'anular')): ?>
                    <button type="button" class="btn btn-danger" onclick="anularVenta()">
                        <i data-feather="x-circle"></i> Anular Venta
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function anularVenta() {
    const motivo = prompt('¿Por qué desea anular esta venta?\n\nIngrese el motivo:');
    
    if (motivo && motivo.trim() !== '') {
        if (confirm('¿Está seguro de anular esta venta?\n\n- Se generará una Nota de Crédito\n- Se reintegrará el stock\n- Esta acción no se puede deshacer')) {
            fetch('acciones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=anular&id=<?php echo $id_venta; ?>&motivo=' + encodeURIComponent(motivo)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.mensaje);
                    location.reload();
                } else {
                    alert('Error: ' + data.mensaje);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<style media="print">
    .btn, .breadcrumb, nav, .page-header nav {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
</style>

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>