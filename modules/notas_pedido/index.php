<?php
// =============================================
// modules/notas_pedido/index.php
// Listado de Notas de Pedido
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('notas_pedido', 'ver');

$titulo_pagina = 'Notas de Pedido';
$db = getDB();

// Filtros
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : '';
$proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Construir filtros
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = 'np.numero_nota LIKE ?';
    $params[] = "%$buscar%";
}

if (!empty($estado)) {
    $where[] = 'np.estado = ?';
    $params[] = $estado;
}

if ($proveedor > 0) {
    $where[] = 'np.id_proveedor = ?';
    $params[] = $proveedor;
}

if (!empty($fecha_desde)) {
    $where[] = 'np.fecha_solicitud >= ?';
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where[] = 'np.fecha_solicitud <= ?';
    $params[] = $fecha_hasta;
}

$where_clause = implode(' AND ', $where);

// Contar total
$sql_count = "SELECT COUNT(*) FROM notas_pedido np WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);

// Paginación
$paginacion = calcularPaginacion($total_registros, $pagina);

// Consulta principal
$sql = "SELECT np.*, 
        p.nombre_proveedor,
        u1.nombre_completo as solicitante,
        u2.nombre_completo as aprobador,
        (SELECT COUNT(*) FROM notas_pedido_detalle WHERE id_nota_pedido = np.id_nota_pedido) as total_productos
        FROM notas_pedido np
        INNER JOIN proveedores p ON np.id_proveedor = p.id_proveedor
        INNER JOIN usuarios u1 ON np.id_usuario_solicitante = u1.id_usuario
        LEFT JOIN usuarios u2 ON np.id_usuario_aprobador = u2.id_usuario
        WHERE $where_clause
        ORDER BY np.fecha_solicitud DESC, np.id_nota_pedido DESC
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$notas = $db->query($sql, $params)->fetchAll();

// Proveedores para filtro
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();

// Estadísticas
$sql_stats = "SELECT 
              COUNT(*) as total,
              SUM(CASE WHEN np.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
              SUM(CASE WHEN np.estado = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
              SUM(CASE WHEN np.estado = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
              SUM(CASE WHEN np.estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
              FROM notas_pedido np
              WHERE $where_clause";
$stats = $db->query($sql_stats, $params)->fetch();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="file-text"></i> Notas de Pedido</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Notas de Pedido</li>
        </ol>
    </nav>
</div>

<!-- Alertas de pendientes -->
<?php if ($stats['pendientes'] > 0 && tienePermiso('notas_pedido', 'aprobar')): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <strong><i data-feather="alert-circle"></i> Atención:</strong> 
    Hay <?php echo $stats['pendientes']; ?> nota(s) de pedido pendiente(s) de aprobación.
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
                        <h6 class="text-muted mb-1">Total Notas</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i data-feather="file-text" width="36"></i>
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
                        <h6 class="text-muted mb-1">Aprobadas</h6>
                        <h3 class="mb-0 text-success"><?php echo number_format($stats['aprobadas']); ?></h3>
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
                        <h6 class="text-muted mb-1">Convertidas</h6>
                        <h3 class="mb-0 text-info"><?php echo number_format($stats['convertidas']); ?></h3>
                    </div>
                    <div class="text-info">
                        <i data-feather="shopping-bag" width="36"></i>
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
                <?php if (tienePermiso('notas_pedido', 'crear')): ?>
                <a href="nueva.php" class="btn btn-primary">
                    <i data-feather="plus-circle"></i> Nueva Nota de Pedido
                </a>
                <?php endif; ?>
            </div>
            
            <div>
                <?php if (tienePermiso('compras', 'ver')): ?>
                <a href="<?php echo MODULES_URL; ?>compras/index.php" class="btn btn-outline-primary">
                    <i data-feather="shopping-bag"></i> Ver Compras
                </a>
                <?php endif; ?>
            </div>
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
                    <label class="form-label">Buscar N° Nota</label>
                    <input type="text" class="form-control" name="buscar" 
                           placeholder="NP-000001..." 
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="aprobada" <?php echo $estado == 'aprobada' ? 'selected' : ''; ?>>Aprobada</option>
                        <option value="rechazada" <?php echo $estado == 'rechazada' ? 'selected' : ''; ?>>Rechazada</option>
                        <option value="convertida" <?php echo $estado == 'convertida' ? 'selected' : ''; ?>>Convertida</option>
                    </select>
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
                
                <div class="col-md-3">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
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

<!-- Tabla de Notas -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Listado de Notas (<?php echo number_format($total_registros); ?> registros)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N° Nota</th>
                        <th>Fecha Solicitud</th>
                        <th>Proveedor</th>
                        <th>Solicitante</th>
                        <th>Productos</th>
                        <th>Estado</th>
                        <th width="180">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notas)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i data-feather="inbox" width="48"></i>
                            <p class="text-muted mt-2">No se encontraron notas de pedido</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($nota['numero_nota']); ?></strong></td>
                            <td>
                                <?php echo formatearFecha($nota['fecha_solicitud']); ?>
                                <?php if ($nota['fecha_necesidad']): ?>
                                <br><small class="text-muted">Necesidad: <?php echo formatearFecha($nota['fecha_necesidad']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($nota['nombre_proveedor']); ?></td>
                            <td><?php echo htmlspecialchars($nota['solicitante']); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $nota['total_productos']; ?> items</span>
                            </td>
                            <td>
                                <?php
                                $badges = [
                                    'pendiente' => 'warning',
                                    'aprobada' => 'success',
                                    'rechazada' => 'danger',
                                    'convertida' => 'info'
                                ];
                                $badgeClass = $badges[$nota['estado']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                    <?php echo ucfirst($nota['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $nota['id_nota_pedido']; ?>" 
                                       class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if ($nota['estado'] == 'pendiente' && tienePermiso('notas_pedido', 'aprobar')): ?>
                                    <button type="button" class="btn btn-success" title="Aprobar"
                                            onclick="aprobarNota(<?php echo $nota['id_nota_pedido']; ?>, '<?php echo htmlspecialchars($nota['numero_nota'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" title="Rechazar"
                                            onclick="rechazarNota(<?php echo $nota['id_nota_pedido']; ?>, '<?php echo htmlspecialchars($nota['numero_nota'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-x-octagon"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($nota['estado'] == 'aprobada' && tienePermiso('notas_pedido', 'convertir')): ?>
                                    <a href="<?php echo MODULES_URL; ?>compras/nueva.php?nota=<?php echo $nota['id_nota_pedido']; ?>" 
                                       class="btn btn-primary" title="Convertir a Compra">
                                        <i class="bi bi-bag"></i>
                                    </a>
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
                        '&estado=' . urlencode($estado) .
                        '&proveedor=' . $proveedor .
                        '&fecha_desde=' . $fecha_desde .
                        '&fecha_hasta=' . $fecha_hasta;
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Aprobar nota
function aprobarNota(id, numero) {
    if (confirm('¿Aprobar la nota de pedido "' + numero + '"?\n\nUna vez aprobada, podrá convertirse en compra.')) {
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=aprobar&id=' + id
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
function rechazarNota(id, numero) {
    const motivo = prompt('¿Por qué desea rechazar la nota "' + numero + '"?\n\nIngrese el motivo:');
    
    if (motivo && motivo.trim() !== '') {
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=rechazar&id=' + id + '&motivo=' + encodeURIComponent(motivo)
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

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>