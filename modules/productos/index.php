<?php
// =============================================
// modules/productos/index.php
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

requierePermiso('productos', 'ver');

$titulo_pagina = 'Gestión de Productos';
$db = getDB();


$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : 'activo';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;


$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(p.codigo LIKE ? OR p.nombre LIKE ? OR p.descripcion LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($categoria > 0) {
    $where[] = 'p.id_categoria = ?';
    $params[] = $categoria;
}

if ($proveedor > 0) {
    $where[] = 'p.id_proveedor = ?';
    $params[] = $proveedor;
}

if (!empty($estado)) {
    $where[] = 'p.estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);


$sql_count = "SELECT COUNT(*) FROM productos p WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);


$paginacion = calcularPaginacion($total_registros, $pagina);


$sql = "SELECT p.*, 
        c.nombre_categoria,
        prov.nombre_proveedor,
        COALESCE(SUM(s.cantidad), 0) as stock_total
        FROM productos p
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        LEFT JOIN proveedores prov ON p.id_proveedor = prov.id_proveedor
        LEFT JOIN stock s ON p.id_producto = s.id_producto
        WHERE $where_clause
        GROUP BY p.id_producto
        ORDER BY p.fecha_creacion DESC
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$productos = $db->query($sql, $params)->fetchAll();


$categorias = $db->query("SELECT * FROM categorias_producto WHERE estado = 'activa' ORDER BY nombre_categoria")->fetchAll();


$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();


$total_productos = $db->count("SELECT COUNT(*) FROM productos WHERE estado = 'activo'");
$productos_sin_stock = $db->count("SELECT COUNT(DISTINCT p.id_producto) 
                                   FROM productos p 
                                   LEFT JOIN stock s ON p.id_producto = s.id_producto 
                                   WHERE p.estado = 'activo' AND (s.cantidad IS NULL OR s.cantidad = 0)");
$productos_stock_bajo = $db->count("SELECT COUNT(DISTINCT p.id_producto) 
                                    FROM productos p 
                                    INNER JOIN stock s ON p.id_producto = s.id_producto 
                                    WHERE p.estado = 'activo' AND s.cantidad > 0 AND s.cantidad <= p.stock_minimo");

$breadcrumb = [
    'Productos' => 'modules/productos/index.php'
];

include '../../includes/header.php';
?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Productos</h6>
                        <h3 class="mb-0"><?php echo formatearNumero($total_productos); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-box-seam fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Stock Bajo</h6>
                        <h3 class="mb-0 text-warning"><?php echo formatearNumero($productos_stock_bajo); ?></h3>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Sin Stock</h6>
                        <h3 class="mb-0 text-danger"><?php echo formatearNumero($productos_sin_stock); ?></h3>
                    </div>
                    <div class="text-danger">
                        <i class="bi bi-x-circle fs-1"></i>
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
                <?php if (tienePermiso('productos', 'crear')): ?>
                <a href="nuevo.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo Producto
                </a>
                <?php endif; ?>
                <?php if (tienePermiso('productos', 'gestionar_listas')): ?>
                <a href="categorias.php" class="btn btn-info" title="Gestionar Categorías de Productos">
                    <i class="bi bi-folder"></i> Categorías
                </a>
                <?php endif; ?>
                <?php if (tienePermiso('productos', 'gestionar_listas')): ?>
                <a href="ubicaciones.php" class="btn btn-info" title="Gestionar Ubicaciones/Depósitos">
                    <i class="bi bi-geo-alt"></i> Ubicaciones
                </a>
                <?php endif; ?>
                <?php if (tienePermiso('productos', 'gestionar_listas')): ?>
                <a href="listas_precios.php" class="btn btn-warning" title="Gestionar Listas de Precios">
                    <i class="bi bi-currency-dollar"></i> Listas de Precios
                </a>
                <?php endif; ?>
                
                <?php if (tienePermiso('proveedores', 'ver')): ?>
                <a href="proveedores.php" class="btn btn-secondary" title="Gestionar Proveedores">
                    <i class="bi bi-truck"></i> Proveedores
                </a>
                <?php endif; ?>
                
                <?php if (tienePermiso('productos', 'gestionar_listas')): ?>
                <a href="estadisticas.php" class="btn btn-info">
                    <i class="bi bi-graph-up"></i> Estadísticas
                </a>
                <?php endif; ?>
                
            </div>
            
            <?php if (tienePermiso('productos', 'exportar')): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalExportar">
                <i class="bi bi-download"></i> Exportar
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-funnel"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="" id="form-filtros">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="buscar" placeholder="Código, nombre, descripción..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria">
                        <option value="0">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select" name="proveedor">
                        <option value="0">Todos</option>
                        <?php foreach ($proveedores as $prov): ?>
                        <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $proveedor == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo $estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo $estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Productos -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list"></i> Listado de Productos (<?php echo $total_registros; ?> registros)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio Base</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th width="120">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No se encontraron productos</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($producto['codigo']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                <?php if ($producto['descripcion']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($producto['nombre_categoria']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($producto['nombre_categoria']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">Sin categoría</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo formatearMoneda($producto['precio_base']); ?></strong></td>
                            <td>
                                <?php
                                $stock = (int)$producto['stock_total'];
                                $stockMin = (int)$producto['stock_minimo'];
                                
                                if ($stock == 0) {
                                    $badgeColor = 'danger';
                                } elseif ($stock <= $stockMin) {
                                    $badgeColor = 'warning';
                                } else {
                                    $badgeColor = 'success';
                                }
                                ?>
                                <span class="badge bg-<?php echo $badgeColor; ?>">
                                    <?php echo formatearNumero($stock); ?> <?php echo $producto['unidad_medida']; ?>
                                </span>
                                <?php if ($stock <= $stockMin && $stock > 0): ?>
                                <br><small class="text-warning">⚠️ Stock bajo</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $producto['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($producto['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $producto['id_producto']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if (tienePermiso('productos', 'editar')): ?>
                                    <a href="editar.php?id=<?php echo $producto['id_producto']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (tienePermiso('productos', 'eliminar')): ?>
                                    <button type="button" class="btn btn-danger" title="Eliminar" onclick="eliminarProducto(<?php echo $producto['id_producto']; ?>, '<?php echo htmlspecialchars($producto['nombre'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-trash"></i>
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
                        '&categoria=' . $categoria . 
                        '&proveedor=' . $proveedor . 
                        '&estado=' . urlencode($estado);
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Exportar -->
<div class="modal fade" id="modalExportar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download"></i> Exportar Productos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Seleccione el formato de exportación:</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success btn-lg" onclick="exportarProductos('excel')">
                        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="exportarProductos('csv')">
                        <i class="bi bi-filetype-csv"></i> Exportar a CSV
                    </button>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <small>
                        <i class="bi bi-info-circle"></i> 
                        Se exportarán los productos según los filtros aplicados actualmente.
                        Total de registros: <strong><?php echo $total_registros; ?></strong>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function eliminarProducto(id, nombre) {
    if (confirm('¿Está seguro de eliminar el producto "' + nombre + '"?\n\nEsta acción cambiará su estado a inactivo.')) {
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=eliminar&id=' + id
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

function exportarProductos(formato) {
    const form = document.getElementById('form-filtros');
    const params = new URLSearchParams(new FormData(form));
    params.append('formato', formato);
    
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalExportar'));
    if (modal) {
        modal.hide();
    }
    
    // Abrir en nueva ventana
    window.open('exportar.php?' + params.toString(), '_blank');
}
</script>

<?php
include '../../includes/footer.php';
?>