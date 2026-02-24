<?php
// =============================================
// modules/stock/ajustar.php
// Ajustar Stock de Productos
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('stock', 'ajustar');

$titulo_pagina = 'Ajustar Stock';
$db = getDB();

$id_producto = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;
$errores = [];

// Obtener ubicaciones
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE estado = 'activo' ORDER BY nombre_ubicacion")->fetchAll();

// Si viene producto específico, cargar datos
$producto_seleccionado = null;
if ($id_producto > 0) {
    $sql = "SELECT p.*, COALESCE(SUM(s.cantidad), 0) as stock_total
            FROM productos p
            LEFT JOIN stock s ON p.id_producto = s.id_producto
            WHERE p.id_producto = ? AND p.estado = 'activo'
            GROUP BY p.id_producto";
    $stmt = $db->query($sql, [$id_producto]);
    $producto_seleccionado = $stmt->fetch();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto = (int)$_POST['id_producto'];
    $id_ubicacion = (int)$_POST['id_ubicacion'];
    $tipo_ajuste = $_POST['tipo_ajuste'];
    $cantidad = (int)$_POST['cantidad'];
    $motivo = limpiarInput($_POST['motivo']);
    $observaciones = limpiarInput($_POST['observaciones']);
    
    // Validaciones
    if ($id_producto <= 0) {
        $errores[] = 'Debe seleccionar un producto';
    }
    
    if ($id_ubicacion <= 0) {
        $errores[] = 'Debe seleccionar una ubicación';
    }
    
    if ($cantidad <= 0) {
        $errores[] = 'La cantidad debe ser mayor a 0';
    }
    
    if (empty($motivo)) {
        $errores[] = 'Debe especificar el motivo del ajuste';
    }
    
    // Si no hay errores, procesar
    if (empty($errores)) {
        try {
            $db->beginTransaction();
            
            // Obtener stock actual
            $sql = "SELECT cantidad FROM stock WHERE id_producto = ? AND id_ubicacion = ?";
            $stmt = $db->query($sql, [$id_producto, $id_ubicacion]);
            $stock_actual = $stmt->fetch();
            
            $cantidad_actual = $stock_actual ? (int)$stock_actual['cantidad'] : 0;
            
            // Calcular nueva cantidad según el tipo de ajuste
            $cantidad_movimiento = $cantidad;
            if ($tipo_ajuste === 'sumar') {
                $nueva_cantidad = $cantidad_actual + $cantidad;
                $tipo_movimiento = 'entrada';
            } elseif ($tipo_ajuste === 'restar') {
                $nueva_cantidad = $cantidad_actual - $cantidad;
                $tipo_movimiento = 'salida';
                
                if ($nueva_cantidad < 0) {
                    throw new Exception('No hay suficiente stock para restar');
                }
            } else { // establecer
                $nueva_cantidad = $cantidad;
                $tipo_movimiento = 'ajuste';
                $cantidad_movimiento = abs($nueva_cantidad - $cantidad_actual);
            }
            
            // Actualizar o insertar stock
            if ($stock_actual) {
                $sql = "UPDATE stock SET cantidad = ? WHERE id_producto = ? AND id_ubicacion = ?";
                $db->update($sql, [$nueva_cantidad, $id_producto, $id_ubicacion]);
            } else {
                $sql = "INSERT INTO stock (id_producto, id_ubicacion, cantidad) VALUES (?, ?, ?)";
                $db->insert($sql, [$id_producto, $id_ubicacion, $nueva_cantidad]);
            }
            
            // Registrar movimiento
            $sql_mov = "INSERT INTO movimientos_stock 
                       (id_producto, id_ubicacion_destino, tipo_movimiento, cantidad, motivo, id_usuario, observaciones)
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            $db->insert($sql_mov, [
                $id_producto, $id_ubicacion, $tipo_movimiento, 
                $cantidad_movimiento, $motivo, $_SESSION['usuario_id'], $observaciones
            ]);
            
            $db->commit();
            
            // Obtener nombre del producto
            $sql_prod = "SELECT nombre FROM productos WHERE id_producto = ?";
            $prod = $db->query($sql_prod, [$id_producto])->fetch();
            
            registrarAuditoria('stock', 'ajustar', 
                "Producto: {$prod['nombre']} - Tipo: $tipo_ajuste - Cantidad: $cantidad");
            
            setAlerta('success', 'Stock ajustado correctamente');
            redirigir('modules/stock/index.php');
            
        } catch (Exception $e) {
            $db->rollback();
            $errores[] = 'Error al ajustar stock: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="edit-3"></i> Ajustar Stock</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Stock</a></li>
            <li class="breadcrumb-item active">Ajustar</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i data-feather="alert-circle"></i> Errores encontrados:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <form method="POST" action="" id="form-ajuste">
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="package"></i> Seleccionar Producto
                </div>
                <div class="card-body">
                    <?php if ($producto_seleccionado): ?>
                    <!-- Producto ya seleccionado -->
                    <input type="hidden" name="id_producto" value="<?php echo $producto_seleccionado['id_producto']; ?>">
                    
                    <div class="alert alert-info">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <strong>Producto:</strong> <?php echo $producto_seleccionado['nombre']; ?><br>
                                <strong>Código:</strong> <?php echo $producto_seleccionado['codigo']; ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <strong>Stock Total:</strong> 
                                <span class="badge bg-primary fs-5"><?php echo (int)$producto_seleccionado['stock_total']; ?></span>
                                <button type="button" class="btn btn-sm btn-info ms-2" onclick="verStockModal(<?php echo $producto_seleccionado['id_producto']; ?>)">
                                    <i data-feather="map-pin"></i> Ver Ubicaciones
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Buscar producto -->
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Buscar Producto <span class="text-danger">*</span></label>
                            <input type="hidden" name="id_producto" id="id_producto" required>
                            <input type="text" class="form-control" id="buscar_producto" 
                                   placeholder="Escribe para buscar..." 
                                   autocomplete="off">
                            <div id="resultados_busqueda" class="list-group mt-2" 
                                 style="position: absolute; z-index: 1000; max-height: 400px; overflow-y: auto; display: none; width: calc(100% - 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filtrar por</label>
                            <select class="form-select" id="tipo_busqueda">
                                <option value="todos">Nombre y Código</option>
                                <option value="nombre">Solo Nombre</option>
                                <option value="codigo">Solo Código</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="info_producto" style="display: none;" class="alert alert-success">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <strong>Producto:</strong> <span id="prod_nombre"></span><br>
                                <small class="text-muted">
                                    <strong>Código:</strong> <span id="prod_codigo"></span> | 
                                    <strong>Categoría:</strong> <span id="prod_categoria"></span>
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="mb-2">
                                    <strong>Stock Total:</strong><br>
                                    <span id="prod_stock" class="badge bg-primary fs-5"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-info" onclick="verStockModal()">
                                    <i data-feather="map-pin"></i> Ver Ubicaciones
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="map-pin"></i> Ubicación y Ajuste
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ubicación <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_ubicacion" id="id_ubicacion" required>
                                <option value="">Seleccione ubicación...</option>
                                <?php foreach ($ubicaciones as $ubi): ?>
                                <option value="<?php echo $ubi['id_ubicacion']; ?>"><?php echo $ubi['nombre_ubicacion']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Ajuste <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_ajuste" id="tipo_ajuste" required>
                                <option value="sumar">Sumar (+) - Entrada de mercadería</option>
                                <option value="restar">Restar (-) - Salida de mercadería</option>
                                <option value="establecer">Establecer (=) - Fijar cantidad exacta</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="cantidad" id="cantidad" min="1" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Motivo <span class="text-danger">*</span></label>
                            <select class="form-select" name="motivo" required>
                                <option value="">Seleccione motivo...</option>
                                <option value="Ajuste de inventario">Ajuste de inventario</option>
                                <option value="Merma">Merma</option>
                                <option value="Devolución">Devolución</option>
                                <option value="Producto dañado">Producto dañado</option>
                                <option value="Corrección de error">Corrección de error</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" placeholder="Detalles adicionales del ajuste..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i data-feather="alert-triangle"></i> <strong>Importante:</strong> Este ajuste modificará el stock del producto y quedará registrado en el historial de movimientos.
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                        
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="check"></i> Confirmar Ajuste
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Stock por Ubicación -->
<div class="modal fade" id="modalStockUbicacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Stock por Ubicación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenido_stock_ubicacion">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Cargando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let timeoutBusqueda = null;
let productoSeleccionadoId = null;

// Elementos del DOM
const inputBuscar = document.getElementById('buscar_producto');
const selectTipo = document.getElementById('tipo_busqueda');
const divResultados = document.getElementById('resultados_busqueda');

// Event listeners
if (inputBuscar) {
    inputBuscar.addEventListener('input', buscarProductos);
    
    if (selectTipo) {
        selectTipo.addEventListener('change', () => {
            if (inputBuscar.value.length >= 2) {
                buscarProductos();
            }
        });
    }
}

// Función de búsqueda
function buscarProductos() {
    clearTimeout(timeoutBusqueda);
    
    const query = inputBuscar.value.trim();
    
    if (query.length < 2) {
        divResultados.style.display = 'none';
        return;
    }
    
    divResultados.innerHTML = '<div class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div> Buscando...</div>';
    divResultados.style.display = 'block';
    
    timeoutBusqueda = setTimeout(() => {
        const tipo = selectTipo ? selectTipo.value : 'todos';
        const url = 'buscar_producto.php?q=' + encodeURIComponent(query) + '&tipo=' + tipo;
        
        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error('Error en servidor');
                return res.json();
            })
            .then(data => {
                console.log('Respuesta:', data);
                mostrarResultados(data);
            })
            .catch(err => {
                console.error('Error:', err);
                divResultados.innerHTML = '<div class="list-group-item list-group-item-danger">Error: ' + err.message + '</div>';
            });
    }, 300);
}

// Mostrar resultados
function mostrarResultados(data) {
    if (!data.success || !data.productos || data.productos.length === 0) {
        divResultados.innerHTML = '<div class="list-group-item text-center text-muted">No se encontraron productos</div>';
        return;
    }
    
    let html = '';
    data.productos.forEach(prod => {
        html += `
            <a href="#" class="list-group-item list-group-item-action" onclick="seleccionarProducto(${prod.id_producto}, '${escape(prod.nombre)}', '${escape(prod.codigo)}', ${prod.stock_total}, '${escape(prod.nombre_categoria || 'Sin categoría')}'); return false;">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${prod.nombre}</strong><br>
                        <small class="text-muted">
                            <span class="badge bg-secondary">${prod.codigo}</span>
                            ${prod.nombre_categoria ? '<span class="badge bg-info ms-1">' + prod.nombre_categoria + '</span>' : ''}
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary">${prod.stock_total}</span>
                    </div>
                </div>
            </a>
        `;
    });
    
    divResultados.innerHTML = html;
}

// Función escape para cadenas
function escape(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Seleccionar producto
function seleccionarProducto(id, nombre, codigo, stock, categoria) {
    productoSeleccionadoId = id;
    
    document.getElementById('id_producto').value = id;
    document.getElementById('buscar_producto').value = nombre;
    document.getElementById('prod_nombre').textContent = nombre;
    document.getElementById('prod_codigo').textContent = codigo;
    document.getElementById('prod_categoria').textContent = categoria;
    document.getElementById('prod_stock').textContent = stock;
    
    document.getElementById('info_producto').style.display = 'block';
    divResultados.style.display = 'none';
    
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

// Ver stock modal
function verStockModal(id) {
    const prodId = id || productoSeleccionadoId;
    if (!prodId) return;
    
    const modal = new bootstrap.Modal(document.getElementById('modalStockUbicacion'));
    modal.show();
    
    document.getElementById('contenido_stock_ubicacion').innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div><p class="mt-3">Cargando...</p></div>';
    
    fetch('consultar_stock.php?producto=' + prodId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                mostrarStockUbicaciones(data);
            } else {
                document.getElementById('contenido_stock_ubicacion').innerHTML = '<div class="alert alert-danger">' + (data.mensaje || 'Error') + '</div>';
            }
        })
        .catch(err => {
            document.getElementById('contenido_stock_ubicacion').innerHTML = '<div class="alert alert-danger">Error de conexión</div>';
        });
}

// Mostrar stock por ubicaciones
function mostrarStockUbicaciones(data) {
    let html = `
        <div class="mb-3">
            <h6><strong>${data.producto.nombre}</strong></h6>
            <p class="text-muted mb-0">Código: ${data.producto.codigo}</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Ubicación</th>
                        <th>Tipo</th>
                        <th class="text-end">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (data.ubicaciones && data.ubicaciones.length > 0) {
        data.ubicaciones.forEach(ubi => {
            html += `
                <tr>
                    <td><strong>${ubi.nombre_ubicacion}</strong></td>
                    <td><span class="badge bg-info">${ubi.tipo}</span></td>
                    <td class="text-end"><span class="badge bg-primary">${ubi.cantidad}</span></td>
                </tr>
            `;
        });
        html += `
                </tbody>
                <tfoot class="table-info">
                    <tr>
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td class="text-end"><strong>${data.stock_total}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        `;
    } else {
        html += '<tr><td colspan="3" class="text-center text-muted py-4">No hay stock registrado</td></tr></tbody></table>';
    }
    
    document.getElementById('contenido_stock_ubicacion').innerHTML = html;
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', (e) => {
    if (divResultados && !e.target.closest('#buscar_producto') && !e.target.closest('#resultados_busqueda')) {
        divResultados.style.display = 'none';
    }
});

// Inicializar iconos si está disponible
if (typeof feather !== 'undefined') {
    feather.replace();
}
</script>

<?php include '../../includes/footer.php'; ?>