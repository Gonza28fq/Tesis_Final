<?php
// =============================================
// modules/compras/index.php
// Listado de Compras
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('compras', 'ver');

$titulo_pagina = 'Gestión de Compras';
$db = getDB();

// Filtros
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Construir filtros
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(c.numero_compra LIKE ? OR c.numero_factura LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($proveedor > 0) {
    $where[] = 'c.id_proveedor = ?';
    $params[] = $proveedor;
}

if (!empty($fecha_desde)) {
    $where[] = 'c.fecha_compra >= ?';
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where[] = 'c.fecha_compra <= ?';
    $params[] = $fecha_hasta;
}

if (!empty($estado)) {
    $where[] = 'c.estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);

// Contar total
$sql_count = "SELECT COUNT(*) FROM compras c WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);

// Paginación
$paginacion = calcularPaginacion($total_registros, $pagina);

// Consulta principal
$sql = "SELECT c.*, 
        p.nombre_proveedor, 
        u.nombre_completo as usuario,
        np.numero_nota,
        (SELECT COUNT(*) FROM compras_detalle WHERE id_compra = c.id_compra) as total_productos
        FROM compras c
        INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
        LEFT JOIN notas_pedido np ON c.id_nota_pedido = np.id_nota_pedido
        WHERE $where_clause
        ORDER BY c.fecha_compra DESC, c.id_compra DESC
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$compras = $db->query($sql, $params)->fetchAll();

// Proveedores para filtro
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();

// Estadísticas
$sql_stats = "SELECT 
              COUNT(*) as total_compras,
              COALESCE(SUM(CASE WHEN c.estado = 'pendiente' THEN 1 ELSE 0 END), 0) as pendientes,
              COALESCE(SUM(CASE WHEN c.estado = 'recibida' THEN 1 ELSE 0 END), 0) as recibidas,
              COALESCE(SUM(CASE WHEN c.estado = 'recibida' THEN c.total ELSE 0 END), 0) as monto_total
              FROM compras c
              WHERE $where_clause";
$stats = $db->query($sql_stats, $params)->fetch();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shopping-bag"></i> Gestión de Compras</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Compras</li>
        </ol>
    </nav>
</div>

<!-- Alertas de pendientes -->
<?php if ($stats['pendientes'] > 0): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <strong><i data-feather="alert-circle"></i> Atención:</strong> 
    Hay <?php echo $stats['pendientes']; ?> compra(s) pendiente(s) de recepción.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Compras</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['total_compras']); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i data-feather="shopping-bag" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Pendientes</h6>
                        <h3 class="mb-0 text-warning"><?php echo number_format($stats['pendientes']); ?></h3>
                    </div>
                    <div class="text-warning">
                        <i data-feather="clock" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Recibidas</h6>
                        <h3 class="mb-0 text-success"><?php echo number_format($stats['recibidas']); ?></h3>
                    </div>
                    <div class="text-success">
                        <i data-feather="check-circle" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Monto Total</h6>
                        <h3 class="mb-0 text-info"><?php echo formatearMoneda($stats['monto_total']); ?></h3>
                    </div>
                    <div class="text-info">
                        <i data-feather="dollar-sign" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Botones de Acción -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div>
                <?php if (tienePermiso('notas_pedido', 'ver')): ?>
                <a href="<?php echo MODULES_URL; ?>notas_pedido/index.php" class="btn btn-outline-primary">
                    <i data-feather="file-text"></i> Notas de Pedido
                </a>
                <?php endif; ?>
                
                <?php if (tienePermiso('compras', 'crear')): ?>
                <a href="nueva.php" class="btn btn-primary">
                    <i data-feather="plus-circle"></i> Nueva Compra Directa
                </a>
                <?php endif; ?>
                 <?php if (tienePermiso('compras', 'crear')): ?>
                <a href="estadisticas.php" class="btn btn-info">
                    <i data-feather="bar-chart"></i> Estadísticas
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (tienePermiso('reportes', 'compras')): ?>
            <a href="reportes.php" class="btn btn-info">
                <i data-feather="bar-chart"></i> Reportes
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="filter"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="buscar" 
                           placeholder="N° compra, factura..." 
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select" name="proveedor">
                        <option value="0">Todos</option>
                        <?php foreach ($proveedores as $prov): ?>
                        <option value="<?php echo $prov['id_proveedor']; ?>" 
                                <?php echo $proveedor == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="recibida" <?php echo $estado == 'recibida' ? 'selected' : ''; ?>>Recibida</option>
                        <option value="cancelada" <?php echo $estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i data-feather="x"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Compras -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Listado de Compras (<?php echo number_format($total_registros); ?> registros)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N° Compra</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>N° Factura</th>
                        <th>Nota Pedido</th>
                        <th>Productos</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th width="150">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compras)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i data-feather="inbox" width="48"></i>
                            <p class="text-muted mt-2">No se encontraron compras</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($compras as $compra): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($compra['numero_compra']); ?></strong></td>
                            <td><?php echo formatearFecha($compra['fecha_compra']); ?></td>
                            <td><?php echo htmlspecialchars($compra['nombre_proveedor']); ?></td>
                            <td><?php echo $compra['numero_factura'] ? htmlspecialchars($compra['numero_factura']) : '-'; ?></td>
                            <td>
                                <?php if ($compra['numero_nota']): ?>
                                    <a href="<?php echo MODULES_URL; ?>notas_pedido/ver.php?id=<?php echo $compra['id_nota_pedido']; ?>" 
                                       class="badge bg-info text-decoration-none">
                                        <?php echo htmlspecialchars($compra['numero_nota']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Directa</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $compra['total_productos']; ?></td>
                            <td><strong><?php echo formatearMoneda($compra['total']); ?></strong></td>
                            <td>
                                <?php
                                $badges = [
                                    'pendiente' => 'warning',
                                    'recibida' => 'success',
                                    'cancelada' => 'danger'
                                ];
                                $badgeClass = $badges[$compra['estado']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                    <?php echo ucfirst($compra['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $compra['id_compra']; ?>" 
                                       class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if ($compra['estado'] == 'pendiente'): ?>
                                        <?php if (tienePermiso('compras', 'crear')): ?>
                                        <button type="button" class="btn btn-success" 
                                                title="Confirmar Recepción"
                                                onclick="confirmarRecepcion(<?php echo $compra['id_compra']; ?>, '<?php echo htmlspecialchars($compra['numero_compra'], ENT_QUOTES); ?>')">
                                            <i class="bi bi-check-circle"></i>

                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (tienePermiso('compras', 'anular')): ?>
                                        <button type="button" class="btn btn-danger" 
                                                title="Cancelar"
                                                onclick="cancelarCompra(<?php echo $compra['id_compra']; ?>, '<?php echo htmlspecialchars($compra['numero_compra'], ENT_QUOTES); ?>')">
                                            <i class="bi bi-x-octagon"></i>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($paginacion['total_paginas'] > 1): ?>
        <div class="mt-3">
            <?php
            $url_base = 'index.php?buscar=' . urlencode($buscar) . 
                        '&proveedor=' . $proveedor .
                        '&fecha_desde=' . $fecha_desde .
                        '&fecha_hasta=' . $fecha_hasta .
                        '&estado=' . urlencode($estado);
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
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
                    <input type="hidden" id="id_compra_recepcion" name="id_compra">
                    
                    <div class="alert alert-info">
                        <i data-feather="info"></i>
                        <strong>Compra:</strong> <span id="numero_compra_recepcion"></span>
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
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="actualizar_precio_costo" 
                               id="actualizar_precio_costo" value="1" checked>
                        <label class="form-check-label" for="actualizar_precio_costo">
                            Actualizar precio de costo de los productos
                        </label>
                        <small class="form-text text-muted d-block">
                            Si está activado, se actualizarán los precios de costo con los valores de esta compra
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones_recepcion" rows="3" 
                                  placeholder="Observaciones sobre la recepción..."></textarea>
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
function confirmarRecepcion(id, numero) {
    document.getElementById('id_compra_recepcion').value = id;
    document.getElementById('numero_compra_recepcion').textContent = numero;
    
    const modal = new bootstrap.Modal(document.getElementById('modalRecepcion'));
    modal.show();
}

// Procesar recepción
document.getElementById('formRecepcion').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('accion', 'confirmar_recepcion');
    
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
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
});

// Cancelar compra
function cancelarCompra(id, numero) {
    const motivo = prompt('¿Por qué desea cancelar la compra "' + numero + '"?\n\nIngrese el motivo:');
    
    if (motivo && motivo.trim() !== '') {
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=cancelar&id=' + id + '&motivo=' + encodeURIComponent(motivo)
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

// Inicializar Feather Icons
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>