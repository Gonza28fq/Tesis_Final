<?php
// =============================================
// modules/notas_pedido/ver.php
// Ver Detalle de Nota de Pedido
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('notas_pedido', 'ver');

$id_nota = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_nota <= 0) {
    setAlerta('danger', 'ID de nota inválido');
    redirigir('index.php');
}

$db = getDB();

// Consultar nota
$sql = "SELECT np.*, 
        p.nombre_proveedor, p.cuit, p.direccion as direccion_proveedor,
        p.telefono as telefono_proveedor, p.email as email_proveedor,
        u1.nombre_completo as solicitante,
        u2.nombre_completo as aprobador,
        c.numero_compra, c.id_compra
        FROM notas_pedido np
        INNER JOIN proveedores p ON np.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u1 ON np.id_usuario_solicitante = u1.id_usuario
        LEFT JOIN usuarios u2 ON np.id_usuario_aprobador = u2.id_usuario
        LEFT JOIN compras c ON c.id_nota_pedido = np.id_nota_pedido
        WHERE np.id_nota_pedido = ?";

$nota = $db->query($sql, [$id_nota])->fetch();

if (!$nota) {
    setAlerta('danger', 'Nota de pedido no encontrada');
    redirigir('index.php');
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

$titulo_pagina = 'Detalle de Nota de Pedido';

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="file-text"></i> Detalle de Nota de Pedido</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Notas de Pedido</a></li>
            <li class="breadcrumb-item active">Detalle</li>
        </ol>
    </nav>
</div>

<!-- Estado de la nota -->
<div class="row mb-4">
    <div class="col-12">
        <?php
        $alertClass = [
            'pendiente' => 'warning',
            'aprobada' => 'success',
            'rechazada' => 'danger',
            'convertida' => 'info'
        ];
        $iconClass = [
            'pendiente' => 'clock',
            'aprobada' => 'check-circle',
            'rechazada' => 'x-circle',
            'convertida' => 'shopping-bag'
        ];
        $mensajes = [
            'pendiente' => 'Esta nota está pendiente de aprobación',
            'aprobada' => 'Esta nota fue aprobada y puede convertirse en compra',
            'rechazada' => 'Esta nota fue rechazada',
            'convertida' => 'Esta nota fue convertida en compra'
        ];
        ?>
        <div class="alert alert-<?php echo $alertClass[$nota['estado']]; ?> d-flex justify-content-between align-items-center">
            <div>
                <i data-feather="<?php echo $iconClass[$nota['estado']]; ?>"></i>
                <strong>Estado: <?php echo ucfirst($nota['estado']); ?></strong>
                <span class="ms-2"><?php echo $mensajes[$nota['estado']]; ?></span>
                
                <?php if ($nota['estado'] == 'convertida' && $nota['numero_compra']): ?>
                <br>
                <small>Compra generada: 
                    <a href="<?php echo MODULES_URL; ?>compras/ver.php?id=<?php echo $nota['id_compra']; ?>" 
                       class="alert-link"><?php echo $nota['numero_compra']; ?></a>
                </small>
                <?php endif; ?>
            </div>
            
            <div class="btn-group">
                <?php if ($nota['estado'] == 'pendiente' && tienePermiso('notas_pedido', 'aprobar')): ?>
                    <?php if ($nota['id_usuario_solicitante'] != $_SESSION['usuario_id']): ?>
                    <button type="button" class="btn btn-success" onclick="aprobarNota()">
                        <i data-feather="check"></i> Aprobar
                    </button>
                    <button type="button" class="btn btn-danger" onclick="rechazarNota()">
                        <i data-feather="x"></i> Rechazar
                    </button>
                    <?php else: ?>
                    <span class="text-muted">No puede aprobar/rechazar su propia nota</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($nota['estado'] == 'aprobada' && tienePermiso('notas_pedido', 'convertir')): ?>
                <a href="<?php echo MODULES_URL; ?>compras/nueva.php?nota=<?php echo $id_nota; ?>" 
                   class="btn btn-primary">
                    <i data-feather="shopping-bag"></i> Convertir a Compra
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Columna Principal -->
    <div class="col-lg-8">
        <!-- Información General -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i data-feather="info"></i> Información de la Nota</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h4 class="text-primary"><?php echo htmlspecialchars($nota['numero_nota']); ?></h4>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-<?php echo $alertClass[$nota['estado']]; ?> fs-6">
                            <?php echo ucfirst($nota['estado']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Fecha de Solicitud</label>
                        <div class="fw-bold"><?php echo formatearFecha($nota['fecha_solicitud']); ?></div>
                    </div>
                    
                    <?php if ($nota['fecha_necesidad']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Fecha de Necesidad</label>
                        <div class="fw-bold"><?php echo formatearFecha($nota['fecha_necesidad']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Solicitado por</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($nota['solicitante']); ?></div>
                    </div>
                    
                    <?php if ($nota['aprobador']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small"><?php echo $nota['estado'] == 'rechazada' ? 'Rechazado' : 'Aprobado'; ?> por</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($nota['aprobador']); ?></div>
                        <?php if ($nota['fecha_aprobacion']): ?>
                        <small class="text-muted"><?php echo formatearFechaHora($nota['fecha_aprobacion']); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($nota['observaciones']): ?>
                <div class="mt-3">
                    <label class="text-muted small">Observaciones</label>
                    <div class="alert alert-light mb-0">
                        <?php echo nl2br(htmlspecialchars($nota['observaciones'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($nota['estado'] == 'rechazada' && $nota['motivo_rechazo']): ?>
                <div class="mt-3">
                    <label class="text-muted small">Motivo del Rechazo</label>
                    <div class="alert alert-danger mb-0">
                        <i data-feather="alert-triangle"></i>
                        <?php echo nl2br(htmlspecialchars($nota['motivo_rechazo'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detalle de Productos -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i data-feather="box"></i> Productos Solicitados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Stock Actual</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Est.</th>
                                <th>Observaciones</th>
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
                                    <br><small class="text-muted"><?php echo htmlspecialchars($detalle['unidad_medida']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $detalle['stock_actual'] == 0 ? 'danger' : ($stock_bajo ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($detalle['stock_actual']); ?>
                                    </span>
                                    <?php if ($stock_bajo): ?>
                                    <br><small class="text-muted">Mín: <?php echo $detalle['stock_minimo']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary fs-6"><?php echo number_format($detalle['cantidad']); ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($detalle['precio_estimado'] > 0): ?>
                                        <?php echo formatearMoneda($detalle['precio_estimado']); ?>
                                        <br><small class="text-muted">Sub: <?php echo formatearMoneda($subtotal); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($detalle['observaciones']): ?>
                                    <small><?php echo htmlspecialchars($detalle['observaciones']); ?></small>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($monto_total_estimado > 0): ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Monto Total Estimado:</strong></td>
                                <td class="text-end"><strong class="text-primary"><?php echo formatearMoneda($monto_total_estimado); ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Columna Lateral -->
    <div class="col-lg-4">
        <!-- Información del Proveedor -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i data-feather="user"></i> Proveedor</h5>
            </div>
            <div class="card-body">
                <h5 class="mb-3"><?php echo htmlspecialchars($nota['nombre_proveedor']); ?></h5>
                
                <?php if ($nota['cuit']): ?>
                <p class="mb-2">
                    <i data-feather="file-text" width="16"></i>
                    <strong>CUIT:</strong> <?php echo htmlspecialchars($nota['cuit']); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($nota['direccion_proveedor']): ?>
                <p class="mb-2">
                    <i data-feather="map-pin" width="16"></i>
                    <strong>Dirección:</strong><br>
                    <?php echo nl2br(htmlspecialchars($nota['direccion_proveedor'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($nota['telefono_proveedor']): ?>
                <p class="mb-2">
                    <i data-feather="phone" width="16"></i>
                    <strong>Teléfono:</strong> <?php echo htmlspecialchars($nota['telefono_proveedor']); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($nota['email_proveedor']): ?>
                <p class="mb-0">
                    <i data-feather="mail" width="16"></i>
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($nota['email_proveedor']); ?>">
                        <?php echo htmlspecialchars($nota['email_proveedor']); ?>
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
                    
                    <?php if ($monto_total_estimado > 0): ?>
                    <dt class="col-12"><hr></dt>
                    <dt class="col-7">Monto Estimado:</dt>
                    <dd class="col-5 text-end fw-bold text-primary"><?php echo formatearMoneda($monto_total_estimado); ?></dd>
                    <dt class="col-12">
                        <small class="text-muted">
                            <i data-feather="info" width="12"></i> Valor referencial
                        </small>
                    </dt>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <!-- Acciones -->
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($nota['estado'] == 'pendiente' && tienePermiso('notas_pedido', 'aprobar')): ?>
                        <?php if ($nota['id_usuario_solicitante'] != $_SESSION['usuario_id']): ?>
                        <button type="button" class="btn btn-success btn-lg" onclick="aprobarNota()">
                            <i data-feather="check-circle"></i> Aprobar Nota
                        </button>
                        <button type="button" class="btn btn-danger" onclick="rechazarNota()">
                            <i data-feather="x-circle"></i> Rechazar Nota
                        </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($nota['estado'] == 'aprobada' && tienePermiso('notas_pedido', 'convertir')): ?>
                    <a href="<?php echo MODULES_URL; ?>compras/nueva.php?nota=<?php echo $id_nota; ?>" 
                       class="btn btn-primary btn-lg">
                        <i data-feather="shopping-bag"></i> Convertir a Compra
                    </a>
                    <?php endif; ?>
                    
                    <a href="imprimir.php?id=<?php echo $id_nota; ?>" target="_blank" class="btn btn-info">
                        <i data-feather="printer"></i> Imprimir
                    </a>
                                        
                    <a href="index.php" class="btn btn-secondary">
                        <i data-feather="arrow-left"></i> Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Aprobar nota
function aprobarNota() {
    if (confirm('¿Aprobar esta nota de pedido?\n\nUna vez aprobada, podrá convertirse en compra.')) {
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=aprobar&id=<?php echo $id_nota; ?>'
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

// Rechazar nota
function rechazarNota() {
    const motivo = prompt('¿Por qué desea rechazar esta nota de pedido?\n\nIngrese el motivo:');
    
    if (motivo && motivo.trim() !== '') {
        if (!confirm('¿Está seguro de rechazar esta nota?\n\nEsta acción no se puede deshacer.')) {
            return;
        }
        
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=rechazar&id=<?php echo $id_nota; ?>&motivo=' + encodeURIComponent(motivo)
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

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<style media="print">
    .btn, .breadcrumb, nav, .page-header nav, .card-header .btn, .btn-group {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    
    .alert {
        border: 1px solid #000 !important;
    }
</style>

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>