<?php
// =============================================
// modules/stock/index.php
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

requierePermiso('stock', 'ver');
$titulo_pagina = 'Gestión de Stock';
$db = getDB();

// Parámetros de búsqueda
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$ubicacion = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;
$filtro_stock = isset($_GET['filtro_stock']) ? limpiarInput($_GET['filtro_stock']) : '';

// Construir filtros
$where = ['p.estado = "activo"'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($categoria > 0) {
    $where[] = 'p.id_categoria = ?';
    $params[] = $categoria;
}

$where_clause = implode(' AND ', $where);

// Consulta principal con stock agrupado
$sql = "SELECT p.*,
        c.nombre_categoria,
        COALESCE(SUM(s.cantidad), 0) as stock_total,
        GROUP_CONCAT(CONCAT(u.nombre_ubicacion, ':', s.cantidad) SEPARATOR '|') as detalle_stock
        FROM productos p
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        LEFT JOIN stock s ON p.id_producto = s.id_producto
        LEFT JOIN ubicaciones u ON s.id_ubicacion = u.id_ubicacion
        WHERE $where_clause";

if ($ubicacion > 0) {
    $sql .= " AND s.id_ubicacion = " . (int)$ubicacion;
}

$sql .= " GROUP BY p.id_producto";

// Aplicar filtro de stock
if ($filtro_stock === 'critico') {
    $sql .= " HAVING stock_total <= p.stock_minimo";
} elseif ($filtro_stock === 'bajo') {
    $sql .= " HAVING stock_total > p.stock_minimo AND stock_total <= (p.stock_minimo * 1.5)";
} elseif ($filtro_stock === 'sin_stock') {
    $sql .= " HAVING stock_total = 0";
}

$sql .= " ORDER BY p.nombre";

$productos = $db->query($sql, $params)->fetchAll();

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias_producto WHERE estado = 'activa' ORDER BY nombre_categoria")->fetchAll();

// Obtener ubicaciones
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE estado = 'activo' ORDER BY nombre_ubicacion")->fetchAll();

// Estadísticas
$stock_critico = 0;
$stock_bajo = 0;
$sin_stock = 0;
$stock_normal = 0;

foreach ($productos as $prod) {
    if ($prod['stock_total'] == 0) {
        $sin_stock++;
    } elseif ($prod['stock_total'] <= $prod['stock_minimo']) {
        $stock_critico++;
    } elseif ($prod['stock_total'] <= $prod['stock_minimo'] * 1.5) {
        $stock_bajo++;
    } else {
        $stock_normal++;
    }
}

// Calcular valorización total
$sql_valor = "SELECT SUM(p.precio_costo * COALESCE(s.cantidad, 0)) as valor_total
              FROM productos p
              LEFT JOIN stock s ON p.id_producto = s.id_producto
              WHERE p.estado = 'activo'";
$valor_stock = $db->query($sql_valor)->fetchColumn() ?? 0;

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="archive"></i> Gestión de Stock</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Stock</li>
        </ol>
    </nav>
</div>

<!-- Alertas de Stock -->
<?php if ($stock_critico > 0): ?>
<div class="alert alert-danger d-flex align-items-center" role="alert">
    <i data-feather="alert-triangle" class="me-2"></i>
    <div>
        <strong>¡Atención!</strong> Hay <?php echo $stock_critico; ?> producto(s) con stock crítico o sin stock.
        <a href="?filtro_stock=critico" class="alert-link">Ver productos →</a>
    </div>
</div>
<?php endif; ?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Stock Crítico</h6>
                        <h3 class="mb-0 text-danger"><?php echo $stock_critico; ?></h3>
                    </div>
                    <div class="text-danger">
                        <i data-feather="alert-triangle" width="40" height="40"></i>
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
                        <h6 class="text-muted mb-1">Stock Bajo</h6>
                        <h3 class="mb-0 text-warning"><?php echo $stock_bajo; ?></h3>
                    </div>
                    <div class="text-warning">
                        <i data-feather="alert-circle" width="40" height="40"></i>
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
                        <h6 class="text-muted mb-1">Stock Normal</h6>
                        <h3 class="mb-0 text-success"><?php echo $stock_normal; ?></h3>
                    </div>
                    <div class="text-success">
                        <i data-feather="check-circle" width="40" height="40"></i>
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
                        <h6 class="text-muted mb-1">Valorización Total</h6>
                        <h4 class="mb-0 text-info"><?php echo formatearMoneda($valor_stock); ?></h4>
                    </div>
                    <div class="text-info">
                        <i data-feather="dollar-sign" width="40" height="40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Botones de Acción -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="btn-group" role="group">
                <?php if (tienePermiso('stock', 'ajustar')): ?>
                <a href="ajustar.php" class="btn btn-primary">
                    <i data-feather="edit-3"></i> Ajustar Stock
                </a>
                <?php endif; ?>
                
                <?php if (tienePermiso('stock', 'transferir')): ?>
                <a href="transferir.php" class="btn btn-info">
                    <i data-feather="shuffle"></i> Transferir
                </a>
                <?php endif; ?>
                
                <?php if (tienePermiso('stock', 'ver_movimientos')): ?>
                <a href="movimientos.php" class="btn btn-warning">
                    <i data-feather="activity"></i> Movimientos
                </a>
                <?php endif; ?>
                <?php if (tienePermiso('stock', 'ver_movimientos')): ?>
                <a href="reportes.php" class="btn btn-secondary">
                    <i data-feather="activity"></i> Reportes
                </a>
                <?php endif; ?>
                
            </div>
                <a href="exportar.php" class="btn btn-outline-success">
                    <i data-feather="download"></i> Exportar
                </a>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="search"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="" id="form-filtros">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar Producto</label>
                    <input type="text" class="form-control" name="buscar" placeholder="Nombre o código..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria">
                        <option value="0">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo $cat['nombre_categoria']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Ubicación</label>
                    <select class="form-select" name="ubicacion">
                        <option value="0">Todas</option>
                        <?php foreach ($ubicaciones as $ubi): ?>
                        <option value="<?php echo $ubi['id_ubicacion']; ?>" <?php echo $ubicacion == $ubi['id_ubicacion'] ? 'selected' : ''; ?>>
                            <?php echo $ubi['nombre_ubicacion']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Filtro de Stock</label>
                    <select class="form-select" name="filtro_stock">
                        <option value="">Todos</option>
                        <option value="critico" <?php echo $filtro_stock == 'critico' ? 'selected' : ''; ?>>Stock Crítico</option>
                        <option value="bajo" <?php echo $filtro_stock == 'bajo' ? 'selected' : ''; ?>>Stock Bajo</option>
                        <option value="sin_stock" <?php echo $filtro_stock == 'sin_stock' ? 'selected' : ''; ?>>Sin Stock</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="search"></i> Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Stock -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Estado de Stock (<?php echo count($productos); ?> productos)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Stock Total</th>
                        <th>Stock Mín/Máx</th>
                        <th>Ubicaciones</th>
                        <th>Estado</th>
                        <th width="120">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No se encontraron productos</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $prod): ?>
                        <?php
                        $stock = (int)$prod['stock_total'];
                        $stock_min = (int)$prod['stock_minimo'];
                        
                        if ($stock == 0) {
                            $clase_badge = 'danger';
                            $estado_text = 'Sin Stock';
                            $icono = 'x-circle';
                        } elseif ($stock <= $stock_min) {
                            $clase_badge = 'danger';
                            $estado_text = 'Crítico';
                            $icono = 'alert-triangle';
                        } elseif ($stock <= $stock_min * 1.5) {
                            $clase_badge = 'warning';
                            $estado_text = 'Bajo';
                            $icono = 'alert-circle';
                        } else {
                            $clase_badge = 'success';
                            $estado_text = 'Normal';
                            $icono = 'check-circle';
                        }
                        ?>
                        <tr>
                            <td><code><?php echo $prod['codigo']; ?></code></td>
                            <td><strong><?php echo $prod['nombre']; ?></strong></td>
                            <td><?php echo $prod['nombre_categoria'] ?? '-'; ?></td>
                            <td>
                                <h5 class="mb-0">
                                    <span class="badge bg-<?php echo $clase_badge; ?>"><?php echo $stock; ?></span>
                                </h5>
                            </td>
                            <td>
                                <small class="text-muted">
                                    Mín: <?php echo $stock_min; ?> / Máx: <?php echo $prod['stock_maximo']; ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($prod['detalle_stock']): ?>
                                <small>
                                    <?php
                                    $detalles = explode('|', $prod['detalle_stock']);
                                    foreach ($detalles as $detalle) {
                                        list($ubi, $cant) = explode(':', $detalle);
                                        echo "<span class='badge bg-secondary me-1'>$ubi: $cant</span>";
                                    }
                                    ?>
                                </small>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $clase_badge; ?>">
                                    <i data-feather="<?php echo $icono; ?>" width="14"></i>
                                    <?php echo $estado_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if (tienePermiso('stock', 'ajustar')): ?>
                                    <a href="ajustar.php?producto=<?php echo $prod['id_producto']; ?>" class="btn btn-primary" title="Ajustar">
                                        <i class="bi bi-gear"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (tienePermiso('stock', 'ver_movimientos')): ?>
                                    <a href="movimientos.php?producto=<?php echo $prod['id_producto']; ?>" class="btn btn-info" title="Movimientos">
                                        <i class="bi bi-arrow-left-right"></i>
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
    </div>
</div>

<?php
$custom_js = <<<'JS'
<script>
function exportarStock() {
    const form = document.getElementById('form-filtros');
    const params = new URLSearchParams(new FormData(form));
    window.open('exportar.php?' + params.toString(), '_blank');
}

feather.replace();
</script>
JS;

include '../../includes/footer.php';
?>