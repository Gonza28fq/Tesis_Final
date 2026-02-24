<?php
// =============================================
// modules/productos/lista_precios_detalle.php
// Detalle de Productos en una Lista de Precios
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'ver');

$titulo_pagina = 'Detalle de Lista de Precios';
$db = getDB();

// Obtener ID de la lista
$id_lista = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_lista == 0) {
    setAlerta('error', 'Lista de precios no válida');
    redirigir('listas_precios.php');
}

// Obtener información de la lista
$sql_lista = "SELECT lp.*, tc.nombre_tipo,
              (SELECT COUNT(*) FROM productos_precios WHERE id_lista_precio = lp.id_lista_precio) as total_productos
              FROM listas_precios lp
              LEFT JOIN tipos_cliente tc ON lp.id_tipo_cliente = tc.id_tipo_cliente
              WHERE lp.id_lista_precio = ?";
$stmt = $db->query($sql_lista, [$id_lista]);
$lista = $stmt->fetch();

if (!$lista) {
    setAlerta('error', 'Lista de precios no encontrada');
    redirigir('listas_precios.php');
}

// Parámetros de búsqueda
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;

// Construir filtros
$where = ['p.estado = "activo"'];
$params = [$id_lista];

if (!empty($buscar)) {
    $where[] = '(p.codigo LIKE ? OR p.nombre LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($categoria > 0) {
    $where[] = 'p.id_categoria = ?';
    $params[] = $categoria;
}

$where_clause = implode(' AND ', $where);

// Obtener productos con precios
$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.precio_base, p.precio_costo,
        pp.precio, pp.id_producto_precio,
        c.nombre_categoria,
        CASE 
            WHEN pp.precio IS NOT NULL THEN ((pp.precio - p.precio_base) / p.precio_base * 100)
            ELSE 0
        END as porcentaje_real
        FROM productos p
        LEFT JOIN productos_precios pp ON p.id_producto = pp.id_producto AND pp.id_lista_precio = ?
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        WHERE $where_clause
        ORDER BY p.nombre";

$productos = $db->query($sql, $params)->fetchAll();

// Obtener categorías para filtros
$categorias = $db->query("SELECT * FROM categorias_producto WHERE estado = 'activa' ORDER BY nombre_categoria")->fetchAll();

// Estadísticas
$total_con_precio = 0;
$total_sin_precio = 0;
$suma_precios_base = 0;
$suma_precios_lista = 0;

foreach ($productos as $prod) {
    if ($prod['precio']) {
        $total_con_precio++;
        $suma_precios_lista += $prod['precio'];
    } else {
        $total_sin_precio++;
    }
    $suma_precios_base += $prod['precio_base'];
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="list"></i> <?php echo htmlspecialchars($lista['nombre_lista']); ?></h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item"><a href="listas_precios.php">Listas de Precios</a></li>
            <li class="breadcrumb-item active">Detalle</li>
        </ol>
    </nav>
</div>

<!-- Información de la Lista -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h6 class="text-muted mb-1">Lista</h6>
                <h5><?php echo htmlspecialchars($lista['nombre_lista']); ?></h5>
                <?php if ($lista['descripcion']): ?>
                <small class="text-muted"><?php echo htmlspecialchars($lista['descripcion']); ?></small>
                <?php endif; ?>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-muted mb-1">Tipo de Cliente</h6>
                <p class="mb-0"><strong><?php echo htmlspecialchars($lista['nombre_tipo'] ?? 'General'); ?></strong></p>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-muted mb-1">Ajuste</h6>
                <p class="mb-0">
                    <?php if ($lista['porcentaje_incremento'] > 0): ?>
                        <span class="badge bg-success fs-6">
                            <i data-feather="trending-up" width="14"></i> +<?php echo number_format($lista['porcentaje_incremento'], 2); ?>%
                        </span>
                    <?php elseif ($lista['porcentaje_incremento'] < 0): ?>
                        <span class="badge bg-danger fs-6">
                            <i data-feather="trending-down" width="14"></i> <?php echo number_format($lista['porcentaje_incremento'], 2); ?>%
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Base</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="col-md-2">
                <h6 class="text-muted mb-1">Estado</h6>
                <span class="badge bg-<?php echo $lista['estado'] == 'activa' ? 'success' : 'secondary'; ?>">
                    <?php echo ucfirst($lista['estado']); ?>
                </span>
            </div>
            
            <div class="col-md-3 text-end">
                <?php if (tienePermiso('productos', 'gestionar_listas')): ?>
                <a href="listas_precios.php" class="btn btn-secondary">
                    <i data-feather="arrow-left"></i> Volver
                </a>
                <button type="button" class="btn btn-success" id="btnRegenerarLista">
                    <i data-feather="refresh-cw"></i> Regenerar
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Productos</h6>
                <h3 class="mb-0"><?php echo count($productos); ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Con Precio</h6>
                <h3 class="mb-0 text-success"><?php echo $total_con_precio; ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Sin Precio</h6>
                <h3 class="mb-0 text-warning"><?php echo $total_sin_precio; ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <h6 class="text-muted mb-1">Valor Total Lista</h6>
                <h3 class="mb-0 text-info">$<?php echo number_format($suma_precios_lista, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="search"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="id" value="<?php echo $id_lista; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Buscar Producto</label>
                    <input type="text" class="form-control" name="buscar" placeholder="Código o nombre..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria">
                        <option value="0">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $categoria == $cat['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                        </option>
                        <?php endforeach; ?>
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

<!-- Tabla de Productos -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i data-feather="package"></i> Productos y Precios</span>
        <?php if (tienePermiso('productos', 'exportar')): ?>
        <button type="button" class="btn btn-sm btn-success" id="btnExportar">
            <i data-feather="download"></i> Exportar a Excel
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="tablaProductos">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="text-end">Precio Costo</th>
                        <th class="text-end">Precio Base</th>
                        <th class="text-end">Precio Lista</th>
                        <th class="text-end">Diferencia %</th>
                        <th class="text-end">Margen</th>
                        <th width="100">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No se encontraron productos</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $prod): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($prod['codigo']); ?></code></td>
                            <td>
                                <strong><?php echo htmlspecialchars($prod['nombre']); ?></strong>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo htmlspecialchars($prod['nombre_categoria'] ?? 'Sin categoría'); ?></small>
                            </td>
                            <td class="text-end">
                                <small class="text-muted">$<?php echo number_format($prod['precio_costo'], 2, ',', '.'); ?></small>
                            </td>
                            <td class="text-end">
                                <strong>$<?php echo number_format($prod['precio_base'], 2, ',', '.'); ?></strong>
                            </td>
                            <td class="text-end">
                                <?php if ($prod['precio']): ?>
                                <strong class="text-primary">$<?php echo number_format($prod['precio'], 2, ',', '.'); ?></strong>
                                <?php else: ?>
                                <span class="badge bg-warning">Sin precio</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($prod['precio']): ?>
                                    <?php 
                                    $diferencia = $prod['porcentaje_real'];
                                    $color = $diferencia > 0 ? 'success' : ($diferencia < 0 ? 'danger' : 'secondary');
                                    $icono = $diferencia > 0 ? 'trending-up' : ($diferencia < 0 ? 'trending-down' : 'minus');
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <i data-feather="<?php echo $icono; ?>" width="12"></i>
                                        <?php echo $diferencia > 0 ? '+' : ''; ?><?php echo number_format($diferencia, 2); ?>%
                                    </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($prod['precio'] && $prod['precio_costo'] > 0): ?>
                                    <?php 
                                    $margen = (($prod['precio'] - $prod['precio_costo']) / $prod['precio_costo']) * 100;
                                    $color_margen = $margen >= 30 ? 'success' : ($margen >= 15 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $color_margen; ?>">
                                        <?php echo number_format($margen, 1); ?>%
                                    </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if (tienePermiso('productos', 'editar')): ?>
                                    <button type="button" class="btn btn-warning btn-editar-precio" 
                                            data-id="<?php echo $prod['id_producto']; ?>"
                                            data-codigo="<?php echo htmlspecialchars($prod['codigo']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                                            data-precio-base="<?php echo $prod['precio_base']; ?>"
                                            data-precio="<?php echo $prod['precio'] ?? 0; ?>"
                                            title="Editar precio">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <a href="ver.php?id=<?php echo $prod['id_producto']; ?>" class="btn btn-info" title="Ver producto">
                                        <i class="bi bi-eye"></i>
                                    </a>
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

<!-- Modal Editar Precio Individual -->
<div class="modal fade" id="modalEditarPrecio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Precio Individual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarPrecio">
                <input type="hidden" name="accion" value="editar_precio_individual">
                <input type="hidden" name="id_producto" id="modal_id_producto">
                <input type="hidden" name="id_lista_precio" value="<?php echo $id_lista; ?>">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong id="modal_producto_nombre"></strong><br>
                        <small>Código: <code id="modal_producto_codigo"></code></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Precio Base</label>
                        <input type="text" class="form-control" id="modal_precio_base" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Precio en esta Lista <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="precio" id="modal_precio" step="0.01" min="0" required>
                        <small class="text-muted">Ingrese el precio específico para este producto en esta lista</small>
                    </div>
                    
                    <div id="preview_diferencia" class="alert alert-secondary" style="display: none;">
                        Diferencia respecto al precio base: <strong id="preview_porcentaje"></strong>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save"></i> Guardar Precio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEditarPrecio = new bootstrap.Modal(document.getElementById('modalEditarPrecio'));
    
    // Botones Editar Precio
    document.querySelectorAll('.btn-editar-precio').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const codigo = this.dataset.codigo;
            const nombre = this.dataset.nombre;
            const precioBase = parseFloat(this.dataset.precioBase);
            const precio = parseFloat(this.dataset.precio);
            
            document.getElementById('modal_id_producto').value = id;
            document.getElementById('modal_producto_codigo').textContent = codigo;
            document.getElementById('modal_producto_nombre').textContent = nombre;
            document.getElementById('modal_precio_base').value = '$' + precioBase.toFixed(2);
            document.getElementById('modal_precio').value = precio > 0 ? precio.toFixed(2) : precioBase.toFixed(2);
            
            modalEditarPrecio.show();
        });
    });
    
    // Preview de diferencia al cambiar precio
    document.getElementById('modal_precio').addEventListener('input', function() {
        const precioBase = parseFloat(document.getElementById('modal_precio_base').value.replace('$', ''));
        const precioNuevo = parseFloat(this.value);
        
        if (precioNuevo > 0 && precioBase > 0) {
            const diferencia = ((precioNuevo - precioBase) / precioBase * 100).toFixed(2);
            const preview = document.getElementById('preview_diferencia');
            const porcentaje = document.getElementById('preview_porcentaje');
            
            porcentaje.textContent = (diferencia > 0 ? '+' : '') + diferencia + '%';
            porcentaje.className = diferencia > 0 ? 'text-success' : (diferencia < 0 ? 'text-danger' : 'text-secondary');
            preview.style.display = 'block';
        }
    });
    
    // Guardar Precio Individual
    document.getElementById('formEditarPrecio').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('listas_precios_acciones.php', {
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
            alert('Error al guardar el precio');
        });
    });
    
    // Regenerar precios de esta lista
    const btnRegenerarLista = document.getElementById('btnRegenerarLista');
    if (btnRegenerarLista) {
        btnRegenerarLista.addEventListener('click', function() {
            if (confirm('¿Regenerar todos los precios de esta lista?\n\nEsto recalculará los precios de TODOS los productos basándose en el precio base y el porcentaje configurado.')) {
                const formData = new FormData();
                formData.append('accion', 'regenerar_lista');
                formData.append('id_lista_precio', <?php echo $id_lista; ?>);
                
                fetch('listas_precios_acciones.php', {
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
                    alert('Error al regenerar precios');
                });
            }
        });
    }
    
    // Exportar a Excel (simplificado)
    const btnExportar = document.getElementById('btnExportar');
    if (btnExportar) {
        btnExportar.addEventListener('click', function() {
            window.location.href = 'listas_precios_exportar.php?id=<?php echo $id_lista; ?>';
        });
    }
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>