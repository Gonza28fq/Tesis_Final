<?php
// =============================================
// modules/compras/ver.php
// Ver Detalle de Compra
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('compras', 'ver');

$id_compra = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_compra <= 0) {
    setAlerta('danger', 'ID de compra inválido');
    redirigir('index.php');
}

$db = getDB();

// Consultar compra
$sql = "SELECT c.*, 
        p.nombre_proveedor, p.cuit, p.direccion as direccion_proveedor,
        p.telefono as telefono_proveedor, p.email as email_proveedor,
        u.nombre_completo as usuario,
        np.numero_nota, np.id_nota_pedido
        FROM compras c
        INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        LEFT JOIN notas_pedido np ON c.id_nota_pedido = np.id_nota_pedido
        WHERE c.id_compra = ?";

$compra = $db->query($sql, [$id_compra])->fetch();

if (!$compra) {
    setAlerta('danger', 'Compra no encontrada');
    redirigir('index.php');
}

// Consultar detalle
$sql_detalle = "SELECT cd.*, p.codigo, p.nombre, p.unidad_medida
                FROM compras_detalle cd
                INNER JOIN productos p ON cd.id_producto = p.id_producto
                WHERE cd.id_compra = ?
                ORDER BY p.nombre";

$detalles = $db->query($sql_detalle, [$id_compra])->fetchAll();

// Si está recibida, consultar movimientos de stock
$movimientos = [];
if ($compra['estado'] == 'recibida') {
    $sql_movimientos = "SELECT ms.*, u.nombre_ubicacion
                        FROM movimientos_stock ms
                        INNER JOIN ubicaciones u ON ms.id_ubicacion_destino = u.id_ubicacion
                        WHERE ms.referencia = ? AND ms.tipo_movimiento = 'entrada'
                        ORDER BY ms.fecha_movimiento DESC";
    $movimientos = $db->query($sql_movimientos, [$compra['numero_compra']])->fetchAll();
}

$titulo_pagina = 'Detalle de Compra';

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shopping-bag"></i> Detalle de Compra</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Compras</a></li>
            <li class="breadcrumb-item active">Detalle</li>
        </ol>
    </nav>
</div>

<!-- Estado de la compra -->
<div class="row mb-4">
    <div class="col-12">
        <?php
        $alertClass = [
            'pendiente' => 'warning',
            'recibida' => 'success',
            'cancelada' => 'danger'
        ];
        $iconClass = [
            'pendiente' => 'clock',
            'recibida' => 'check-circle',
            'cancelada' => 'x-circle'
        ];
        $mensajes = [
            'pendiente' => 'Esta compra está pendiente de recepción de mercadería',
            'recibida' => 'Esta compra ya fue recibida y el stock fue actualizado',
            'cancelada' => 'Esta compra fue cancelada'
        ];
        ?>
        <div class="alert alert-<?php echo $alertClass[$compra['estado']]; ?> d-flex justify-content-between align-items-center">
            <div>
                <i data-feather="<?php echo $iconClass[$compra['estado']]; ?>"></i>
                <strong>Estado: <?php echo ucfirst($compra['estado']); ?></strong>
                <span class="ms-2"><?php echo $mensajes[$compra['estado']]; ?></span>
            </div>
            
            <?php if ($compra['estado'] == 'pendiente'): ?>
            <div class="btn-group">
                <?php if (tienePermiso('compras', 'crear')): ?>
                <button type="button" class="btn btn-success" onclick="confirmarRecepcion()">
                    <i data-feather="check"></i> Confirmar Recepción
                </button>
                <?php endif; ?>
                
                <?php if (tienePermiso('compras', 'anular')): ?>
                <button type="button" class="btn btn-danger" onclick="cancelarCompra()">
                    <i data-feather="x-circle"></i> Cancelar
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Columna Principal -->
    <div class="col-lg-8">
        <!-- Información General -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i data-feather="file-text"></i> Información de Compra</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h4 class="text-primary"><?php echo htmlspecialchars($compra['numero_compra']); ?></h4>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-<?php echo $alertClass[$compra['estado']]; ?> fs-6">
                            <?php echo ucfirst($compra['estado']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Fecha de Compra</label>
                        <div class="fw-bold"><?php echo formatearFecha($compra['fecha_compra']); ?></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Usuario</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($compra['usuario']); ?></div>
                    </div>
                    
                    <?php if ($compra['tipo_comprobante']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Tipo Comprobante</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($compra['tipo_comprobante']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($compra['numero_factura']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">N° Factura</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($compra['numero_factura']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($compra['fecha_factura']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Fecha Factura</label>
                        <div class="fw-bold"><?php echo formatearFecha($compra['fecha_factura']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($compra['numero_nota']): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Nota de Pedido</label>
                        <div>
                            <a href="<?php echo MODULES_URL; ?>notas_pedido/ver.php?id=<?php echo $compra['id_nota_pedido']; ?>" 
                               class="badge bg-info text-decoration-none">
                                <i data-feather="file-text"></i> <?php echo htmlspecialchars($compra['numero_nota']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($compra['observaciones']): ?>
                <div class="mt-3">
                    <label class="text-muted small">Observaciones</label>
                    <div class="alert alert-light mb-0">
                        <?php echo nl2br(htmlspecialchars($compra['observaciones'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detalle de Productos -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i data-feather="box"></i> Productos Comprados</h5>
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
                                <td class="text-end"><strong><?php echo formatearMoneda($detalle['subtotal']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end"><strong><?php echo formatearMoneda($compra['subtotal']); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-end">IVA:</td>
                                <td class="text-end"><?php echo formatearMoneda($compra['impuestos']); ?></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="5" class="text-end"><strong class="fs-5">TOTAL:</strong></td>
                                <td class="text-end"><strong class="fs-5 text-primary"><?php echo formatearMoneda($compra['total']); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Movimientos de Stock (si está recibida) -->
        <?php if ($compra['estado'] == 'recibida' && !empty($movimientos)): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i data-feather="package"></i> Movimientos de Stock</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ubicación</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td><?php echo formatearFechaHora($mov['fecha_movimiento']); ?></td>
                                <td><i data-feather="map-pin"></i> <?php echo htmlspecialchars($mov['nombre_ubicacion']); ?></td>
                                <td><?php echo htmlspecialchars($mov['observaciones']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Columna Lateral -->
    <div class="col-lg-4">
        <!-- Información del Proveedor -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i data-feather="user"></i> Proveedor</h5>
            </div>
            <div class="card-body">
                <h5 class="mb-3"><?php echo htmlspecialchars($compra['nombre_proveedor']); ?></h5>
                
                <?php if ($compra['cuit']): ?>
                <p class="mb-2">
                    <i data-feather="file-text" width="16"></i>
                    <strong>CUIT:</strong> <?php echo htmlspecialchars($compra['cuit']); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($compra['direccion_proveedor']): ?>
                <p class="mb-2">
                    <i data-feather="map-pin" width="16"></i>
                    <strong>Dirección:</strong><br>
                    <?php echo nl2br(htmlspecialchars($compra['direccion_proveedor'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($compra['telefono_proveedor']): ?>
                <p class="mb-2">
                    <i data-feather="phone" width="16"></i>
                    <strong>Teléfono:</strong> <?php echo htmlspecialchars($compra['telefono_proveedor']); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($compra['email_proveedor']): ?>
                <p class="mb-0">
                    <i data-feather="mail" width="16"></i>
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($compra['email_proveedor']); ?>">
                        <?php echo htmlspecialchars($compra['email_proveedor']); ?>
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
                    <dd class="col-5 text-end"><?php echo formatearMoneda($compra['subtotal']); ?></dd>
                    
                    <dt class="col-7">Impuestos:</dt>
                    <dd class="col-5 text-end"><?php echo formatearMoneda($compra['impuestos']); ?></dd>
                    
                    <dt class="col-7 fw-bold fs-5">Total:</dt>
                    <dd class="col-5 text-end fw-bold text-primary fs-5"><?php echo formatearMoneda($compra['total']); ?></dd>
                </dl>
            </div>
        </div>
        
        <!-- Acciones -->
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($compra['estado'] == 'pendiente' && tienePermiso('compras', 'crear')): ?>
                    <button type="button" class="btn btn-success btn-lg" onclick="confirmarRecepcion()">
                        <i data-feather="check-circle"></i> Confirmar Recepción
                    </button>
                    <?php endif; ?>
                    
                    <a href="imprimir.php?id=<?php echo $id_compra; ?>" target="_blank" class="btn btn-info">
                        <i data-feather="printer"></i> Imprimir
                    </a>
                    
                    <a href="index.php" class="btn btn-secondary">
                        <i data-feather="arrow-left"></i> Volver al Listado
                    </a>
                    
                    <?php if ($compra['estado'] == 'pendiente' && tienePermiso('compras', 'anular')): ?>
                    <button type="button" class="btn btn-danger" onclick="cancelarCompra()">
                        <i data-feather="x-circle"></i> Cancelar Compra
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmar Recepción -->
<div class="modal fade" id="modalRecepcion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Recepción de Mercadería</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRecepcion">
                <div class="modal-body">
                    <input type="hidden" name="id_compra" value="<?php echo $id_compra; ?>">
                    
                    <div class="alert alert-info">
                        <i data-feather="info"></i>
                        <strong>Compra:</strong> <?php echo htmlspecialchars($compra['numero_compra']); ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ubicación de Destino <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_ubicacion" required>
                            <?php
                            $ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE estado = 'activo' ORDER BY nombre_ubicacion")->fetchAll();
                            foreach ($ubicaciones as $ubi):
                            ?>
                            <option value="<?php echo $ubi['id_ubicacion']; ?>">
                                <?php echo htmlspecialchars($ubi['nombre_ubicacion']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Seleccione dónde se almacenará la mercadería</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="actualizar_precio_costo" 
                               id="actualizar_precio_costo" value="1" checked>
                        <label class="form-check-label" for="actualizar_precio_costo">
                            <strong>Actualizar precio de costo de los productos</strong>
                        </label>
                        <small class="form-text text-muted d-block mt-1">
                            Si está activado, se actualizarán los precios de costo con los valores de esta compra. 
                            Se generará una alerta si la diferencia es mayor al 10%.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones de Recepción</label>
                        <textarea class="form-control" name="observaciones_recepcion" rows="3" 
                                  placeholder="Observaciones sobre el estado de la mercadería recibida..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning mb-0">
                        <i data-feather="alert-triangle"></i>
                        <strong>Importante:</strong> Al confirmar la recepción:
                        <ul class="mb-0 mt-2">
                            <li>El stock se actualizará automáticamente</li>
                            <li>Se registrará el movimiento de entrada</li>
                            <li>La compra pasará a estado "Recibida"</li>
                            <li>Esta acción no se puede deshacer</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i data-feather="check"></i> Confirmar Recepción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Confirmar recepción
function confirmarRecepcion() {
    const modal = new bootstrap.Modal(document.getElementById('modalRecepcion'));
    modal.show();
}

// Procesar recepción
document.getElementById('formRecepcion').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!confirm('¿Está seguro de confirmar la recepción de esta compra?\n\nEsta acción actualizará el stock y no podrá deshacerse.')) {
        return;
    }
    
    const formData = new FormData(this);
    formData.append('accion', 'confirmar_recepcion');
    
    // Deshabilitar botón
    const btnSubmit = this.querySelector('button[type="submit"]');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
    
    fetch('acciones.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.mensaje);
            location.reload();
        } else {
            alert('Error: ' + data.mensaje);
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i data-feather="check"></i> Confirmar Recepción';
            feather.replace();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i data-feather="check"></i> Confirmar Recepción';
        feather.replace();
    });
});

// Cancelar compra
function cancelarCompra() {
    const motivo = prompt('¿Por qué desea cancelar esta compra?\n\nIngrese el motivo:');
    
    if (motivo && motivo.trim() !== '') {
        if (!confirm('¿Está seguro de cancelar esta compra?\n\nEsta acción no se puede deshacer.')) {
            return;
        }
        
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=cancelar&id=<?php echo $id_compra; ?>&motivo=' + encodeURIComponent(motivo)
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
    .btn, .breadcrumb, nav, .page-header nav, .card-header .btn {
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