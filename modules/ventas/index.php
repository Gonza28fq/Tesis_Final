<?php
// =============================================
// modules/ventas/index.php
// Gestión de Ventas - Con restricciones por rol
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('ventas', 'ver');

$titulo_pagina = 'Gestión de Ventas';
$db = getDB();

// Verificar si el usuario es vendedor (solo ve sus propias ventas)
$es_vendedor = (isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 4); // 4 = VENDEDOR
$puede_ver_estadisticas = tienePermiso('ventas', 'ver_estadisticas');

// Parámetros de filtros
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : '';
$cliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;
$vendedor = isset($_GET['vendedor']) ? (int)$_GET['vendedor'] : 0;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Construir filtros
$where = ['1=1'];
$params = [];

// Si es vendedor, solo ver sus propias ventas
if ($es_vendedor) {
    $where[] = 'v.id_usuario = ?';
    $params[] = $_SESSION['usuario_id'];
}

if (!empty($buscar)) {
    $where[] = '(v.numero_venta LIKE ? OR c.nombre LIKE ? OR v.numero_comprobante LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if (!empty($fecha_desde)) {
    $where[] = 'v.fecha_venta >= ?';
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where[] = 'v.fecha_venta <= ?';
    $params[] = $fecha_hasta;
}

if (!empty($estado)) {
    $where[] = 'v.estado = ?';
    $params[] = $estado;
}

if ($cliente > 0) {
    $where[] = 'v.id_cliente = ?';
    $params[] = $cliente;
}

// Solo supervisores pueden filtrar por vendedor
if ($vendedor > 0 && !$es_vendedor) {
    $where[] = 'v.id_usuario = ?';
    $params[] = $vendedor;
}

$where_clause = implode(' AND ', $where);

// Contar total
$sql_count = "SELECT COUNT(*) FROM ventas v WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);

// Paginación
$paginacion = calcularPaginacion($total_registros, $pagina);

// Consulta de ventas
$sql = "SELECT v.*, 
        c.nombre as cliente_nombre, c.documento,
        tc.nombre as tipo_comprobante_nombre, tc.codigo as tipo_comprobante_codigo,
        u.nombre_completo as vendedor,
        pv.nombre as punto_venta_nombre,
        (SELECT COUNT(*) FROM ventas_detalle WHERE id_venta = v.id_venta) as total_items
        FROM ventas v
        INNER JOIN clientes c ON v.id_cliente = c.id_cliente
        INNER JOIN tipos_comprobante tc ON v.id_tipo_comprobante = tc.id_tipo_comprobante
        INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
        INNER JOIN puntos_venta pv ON v.id_punto_venta = pv.id_punto_venta
        WHERE $where_clause
        ORDER BY v.fecha_venta DESC, v.id_venta DESC
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$ventas = $db->query($sql, $params)->fetchAll();

// Estadísticas del período (SOLO SI TIENE PERMISO)
$stats = null;
if ($puede_ver_estadisticas) {
    $sql_stats = "SELECT 
                  COUNT(*) as total_ventas,
                  COALESCE(SUM(CASE WHEN v.estado = 'completada' THEN 1 ELSE 0 END), 0) as completadas,
                  COALESCE(SUM(CASE WHEN v.estado = 'pendiente' THEN 1 ELSE 0 END), 0) as pendientes,
                  COALESCE(SUM(CASE WHEN v.estado = 'devuelta' THEN 1 ELSE 0 END), 0) as devueltas,
                  COALESCE(SUM(CASE WHEN v.estado = 'completada' THEN v.total ELSE 0 END), 0) as monto_total,
                  COALESCE(AVG(CASE WHEN v.estado = 'completada' THEN v.total ELSE NULL END), 0) as ticket_promedio
                  FROM ventas v
                  WHERE $where_clause";
    $stats = $db->query($sql_stats, $params)->fetch();
}

// Clientes para filtro (top 50 con más compras)
// Si es vendedor, solo sus clientes
$sql_clientes = "SELECT c.id_cliente, c.nombre, COUNT(v.id_venta) as total_compras
                FROM clientes c
                LEFT JOIN ventas v ON c.id_cliente = v.id_cliente";
if ($es_vendedor) {
    $sql_clientes .= " AND v.id_usuario = " . (int)$_SESSION['usuario_id'];
}
$sql_clientes .= " WHERE c.estado = 'activo'
                  GROUP BY c.id_cliente, c.nombre
                  ORDER BY total_compras DESC, c.nombre
                  LIMIT 50";
$clientes = $db->query($sql_clientes)->fetchAll();

// Vendedores activos (SOLO PARA SUPERVISORES)
$vendedores = [];
if (!$es_vendedor) {
    $vendedores = $db->query("SELECT id_usuario, nombre_completo 
                             FROM usuarios 
                             WHERE estado = 'activo' 
                             ORDER BY nombre_completo")->fetchAll();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shopping-cart"></i> Gestión de Ventas</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Ventas</li>
        </ol>
    </nav>
</div>

<?php if ($puede_ver_estadisticas && $stats): ?>
<!-- Estadísticas del Período (SOLO SUPERVISORES Y SUPERIORES) -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Ventas</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['total_ventas']); ?></h3>
                        <small class="text-muted">Completadas: <?php echo $stats['completadas']; ?></small>
                    </div>
                    <div class="text-primary">
                        <i data-feather="shopping-cart" width="36"></i>
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
                        <h6 class="text-muted mb-1">Monto Total</h6>
                        <h3 class="mb-0 text-success"><?php echo formatearMoneda($stats['monto_total']); ?></h3>
                        <small class="text-muted">Solo completadas</small>
                    </div>
                    <div class="text-success">
                        <i data-feather="dollar-sign" width="36"></i>
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
                        <h6 class="text-muted mb-1">Ticket Promedio</h6>
                        <h3 class="mb-0 text-info"><?php echo formatearMoneda($stats['ticket_promedio']); ?></h3>
                        <small class="text-muted">Por venta</small>
                    </div>
                    <div class="text-info">
                        <i data-feather="trending-up" width="36"></i>
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
                        <small class="text-muted">Sin completar</small>
                    </div>
                    <div class="text-warning">
                        <i data-feather="clock" width="36"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Panel simplificado para Vendedores -->
<div class="alert alert-info mb-4">
    <div class="d-flex align-items-center">
        <i data-feather="info" class="me-3" width="24"></i>
        <div>
            <strong>Mis Ventas</strong><br>
            <small>Mostrando solo las ventas registradas por ti. Total: <?php echo number_format($total_registros); ?> ventas en el período seleccionado.</small>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Botones de Acción -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <?php if (tienePermiso('ventas', 'crear')): ?>
            <a href="nueva.php" class="btn btn-primary btn-lg">
                <i data-feather="plus-circle"></i> Nueva Venta
            </a>
            <?php endif; ?>
            
            <div class="btn-group">
                <?php if ($puede_ver_estadisticas): ?>
                <a href="estadisticas.php" class="btn btn-info">
                    <i data-feather="trending-up"></i> Estadísticas
                </a>
                <a href="exportar.php" class="btn btn-success">
                    <i data-feather="bar-chart"></i> Exportar
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
        <form method="GET" action="" id="form-filtros">
            <div class="row g-3">
                <div class="col-md-<?php echo $es_vendedor ? '4' : '3'; ?>">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="buscar" 
                           placeholder="N° venta, cliente, comprobante..." 
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-<?php echo $es_vendedor ? '2' : '2'; ?>">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                </div>
                
                <div class="col-md-<?php echo $es_vendedor ? '2' : '2'; ?>">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todas</option>
                        <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="completada" <?php echo $estado == 'completada' ? 'selected' : ''; ?>>Completada</option>
                        <option value="cancelada" <?php echo $estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        <option value="devuelta" <?php echo $estado == 'devuelta' ? 'selected' : ''; ?>>Devuelta</option>
                    </select>
                </div>
                
                <div class="col-md-<?php echo $es_vendedor ? '4' : '3'; ?>">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" name="cliente">
                        <option value="0">Todos</option>
                        <?php foreach ($clientes as $cli): ?>
                        <option value="<?php echo $cli['id_cliente']; ?>" 
                                <?php echo $cliente == $cli['id_cliente'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cli['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row g-3 mt-1">
                <?php if (!$es_vendedor): ?>
                <div class="col-md-3">
                    <label class="form-label">Vendedor</label>
                    <select class="form-select" name="vendedor">
                        <option value="0">Todos</option>
                        <?php foreach ($vendedores as $vend): ?>
                        <option value="<?php echo $vend['id_usuario']; ?>" 
                                <?php echo $vendedor == $vend['id_usuario'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vend['nombre_completo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-9 d-flex align-items-end gap-2">
                <?php else: ?>
                <div class="col-md-12 d-flex align-items-end gap-2">
                <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="search"></i> Buscar
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i data-feather="x"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Ventas -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Listado de Ventas (<?php echo number_format($total_registros); ?> registros)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N° Venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Comprobante</th>
                        <th>Items</th>
                        <th>Total</th>
                        <?php if (!$es_vendedor): ?>
                        <th>Vendedor</th>
                        <?php endif; ?>
                        <th>Estado</th>
                        <th width="140">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                    <tr>
                        <td colspan="<?php echo $es_vendedor ? '8' : '9'; ?>" class="text-center py-4">
                            <i data-feather="inbox" width="48"></i>
                            <p class="text-muted mt-2">No se encontraron ventas</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($venta['numero_venta']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo $venta['punto_venta_nombre']; ?></small>
                            </td>
                            <td>
                                <?php echo formatearFecha($venta['fecha_venta']); ?>
                                <br>
                                <small class="text-muted"><?php echo date('H:i', strtotime($venta['fecha_creacion'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($venta['cliente_nombre']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($venta['documento']); ?></small>
                            </td>
                            <td>
                                <?php if ($venta['numero_comprobante']): ?>
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars($venta['tipo_comprobante_codigo']); ?>
                                </span>
                                <br>
                                <small><?php echo htmlspecialchars($venta['numero_comprobante']); ?></small>
                                <?php if ($venta['cae']): ?>
                                <br><small class="text-success">CAE: <?php echo substr($venta['cae'], 0, 8); ?>...</small>
                                <?php endif; ?>
                                <?php else: ?>
                                <small class="text-muted">Sin comprobante</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $venta['total_items']; ?></span>
                            </td>
                            <td>
                                <strong class="text-success"><?php echo formatearMoneda($venta['total']); ?></strong>
                                <?php if ($venta['descuento'] > 0): ?>
                                <br><small class="text-warning">Desc: <?php echo formatearMoneda($venta['descuento']); ?></small>
                                <?php endif; ?>
                            </td>
                            <?php if (!$es_vendedor): ?>
                            <td>
                                <small><?php echo htmlspecialchars($venta['vendedor']); ?></small>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php
                                $badges_estado = [
                                    'pendiente' => 'warning',
                                    'completada' => 'success',
                                    'cancelada' => 'danger',
                                    'devuelta' => 'secondary'
                                ];
                                $badge_class = $badges_estado[$venta['estado']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo ucfirst($venta['estado']); ?>
                                </span>
                                <?php if ($venta['forma_pago']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($venta['forma_pago']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $venta['id_venta']; ?>" 
                                       class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if ($venta['estado'] == 'completada'): ?>
                                    <a href="imprimir.php?id=<?php echo $venta['id_venta']; ?>" 
                                       class="btn btn-secondary" title="Imprimir" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (tienePermiso('ventas', 'anular') && $venta['estado'] == 'completada'): ?>
                                    <button type="button" class="btn btn-danger" title="Anular venta" 
                                            onclick="anularVenta(<?php echo $venta['id_venta']; ?>, '<?php echo htmlspecialchars($venta['numero_venta'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-x-octagon"></i>
                                    </button>
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
                        '&fecha_desde=' . $fecha_desde .
                        '&fecha_hasta=' . $fecha_hasta .
                        '&estado=' . urlencode($estado) .
                        '&cliente=' . $cliente;
            if (!$es_vendedor) {
                $url_base .= '&vendedor=' . $vendedor;
            }
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function anularVenta(id, numero) {
    const motivo = prompt('¿Por qué desea anular la venta ' + numero + '?\n\nIngrese el motivo:');
    
    if (motivo && motivo.trim() !== '') {
        if (confirm('¿Está seguro de anular esta venta?\n\n- Se generará una Nota de Crédito\n- Se devolverá el stock\n- Esta acción no se puede deshacer')) {
            fetch('acciones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=anular&id=' + id + '&motivo=' + encodeURIComponent(motivo)
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

function exportarVentas() {
    const form = document.getElementById('form-filtros');
    const params = new URLSearchParams(new FormData(form));
    window.open('exportar.php?' + params.toString(), '_blank');
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