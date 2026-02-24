<?php
// =============================================
// modules/productos/editar.php
// Editar Producto
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'editar');

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setAlerta('error', 'Producto no válido');
    redirigir('index.php');
}

$id_producto = (int)$_GET['id'];
$titulo_pagina = 'Editar Producto';

$db = getDB();
$errores = [];

// Cargar datos del producto
$sql = "SELECT * FROM productos WHERE id_producto = ?";
$stmt = $db->query($sql, [$id_producto]);
$producto = $stmt->fetch();

if (!$producto) {
    setAlerta('error', 'Producto no encontrado');
    redirigir('modules/productos/index.php');
}

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias_producto WHERE estado = 'activa' ORDER BY nombre_categoria")->fetchAll();

// Obtener proveedores
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll();

// Obtener listas de precios activas
$listas_precios = $db->query("SELECT * FROM listas_precios WHERE estado = 'activa' ORDER BY nombre_lista")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $codigo = limpiarInput($_POST['codigo']);
    $nombre = limpiarInput($_POST['nombre']);
    $descripcion = limpiarInput($_POST['descripcion'] ?? '');
    $id_categoria = (int)($_POST['id_categoria'] ?? 0);
    $id_proveedor = (int)($_POST['id_proveedor'] ?? 0);
    $precio_costo = (float)($_POST['precio_costo'] ?? 0);
    $precio_base = (float)($_POST['precio_base'] ?? 0);
    $unidad_medida = limpiarInput($_POST['unidad_medida'] ?? 'unidad');
    $stock_minimo = (int)($_POST['stock_minimo'] ?? 0);
    $stock_maximo = (int)($_POST['stock_maximo'] ?? 0);
    $requiere_serie = isset($_POST['requiere_serie']) ? 1 : 0;
    $requiere_lote = isset($_POST['requiere_lote']) ? 1 : 0;
    $estado = $_POST['estado'] ?? 'activo';
    
    // Validaciones
    if (empty($codigo)) {
        $errores[] = 'El código es obligatorio';
    }
    
    if (empty($nombre)) {
        $errores[] = 'El nombre es obligatorio';
    }
    
    if ($precio_base <= 0) {
        $errores[] = 'El precio base debe ser mayor a 0';
    }
    // Validar stock mínimo vs stock máximo
    if ($producto['stock_maximo'] > 0 && $producto['stock_minimo'] > $producto['stock_maximo']) {
        $errores[] = 'El stock mínimo no puede ser mayor al stock máximo';
    }
    
    // Verificar código único (excluir el producto actual)
    $sql_check = "SELECT id_producto FROM productos WHERE codigo = ? AND id_producto != ?";
    $existe = $db->count($sql_check, [$codigo, $id_producto]);
    
    if ($existe > 0) {
        $errores[] = 'El código ya existe en otro producto';
    }
    
    // Si no hay errores, actualizar
    // Si no hay errores, actualizar
if (empty($errores)) {
    try {
        $db->beginTransaction();
        
        // Actualizar producto
        $sql = "UPDATE productos SET 
                codigo = ?, 
                nombre = ?, 
                descripcion = ?, 
                id_categoria = ?,
                id_proveedor = ?, 
                precio_costo = ?, 
                precio_base = ?,
                unidad_medida = ?, 
                stock_minimo = ?, 
                stock_maximo = ?,
                requiere_serie = ?, 
                requiere_lote = ?, 
                estado = ?
                WHERE id_producto = ?";
        
        // USAR query() en lugar de execute()
        $db->query($sql, [
            $codigo,
            $nombre,
            $descripcion,
            $id_categoria > 0 ? $id_categoria : null,
            $id_proveedor > 0 ? $id_proveedor : null,
            $precio_costo,
            $precio_base,
            $unidad_medida,
            $stock_minimo,
            $stock_maximo,
            $requiere_serie,
            $requiere_lote,
            $estado,
            $id_producto
        ]);
        
        // Actualizar precios en las listas
        if (!empty($listas_precios)) {
            // Eliminar precios anteriores
            $db->query("DELETE FROM productos_precios WHERE id_producto = ?", [$id_producto]);
            
            // Insertar nuevos precios
            foreach ($listas_precios as $lista) {
                $precio_lista = $precio_base;
                
                // Aplicar porcentaje de incremento
                if ($lista['porcentaje_incremento'] > 0) {
                    $precio_lista = $precio_lista * (1 + ($lista['porcentaje_incremento'] / 100));
                }
                
                $sql_precio = "INSERT INTO productos_precios 
                              (id_producto, id_lista_precio, precio, fecha_vigencia_desde)
                              VALUES (?, ?, ?, CURRENT_DATE)";
                
                $db->query($sql_precio, [$id_producto, $lista['id_lista_precio'], $precio_lista]);
            }
        }
        
        $db->commit();
        
        // Registrar auditoría
        registrarAuditoria('productos', 'actualizar', "Producto actualizado: $nombre (ID: $id_producto)");
        
        setAlerta('success', 'Producto actualizado correctamente');
        header('Location: ver.php?id=' . $id_producto);
        exit;
        
        } catch (Exception $e) {
            $db->rollback();
            $errores[] = 'Error al actualizar el producto: ' . $e->getMessage();
        }
}
    // Si hay errores, actualizar el array $producto con los valores del formulario
    if (!empty($errores)) {
        $producto['codigo'] = $codigo;
        $producto['nombre'] = $nombre;
        $producto['descripcion'] = $descripcion;
        $producto['id_categoria'] = $id_categoria;
        $producto['id_proveedor'] = $id_proveedor;
        $producto['precio_costo'] = $precio_costo;
        $producto['precio_base'] = $precio_base;
        $producto['unidad_medida'] = $unidad_medida;
        $producto['stock_minimo'] = $stock_minimo;
        $producto['stock_maximo'] = $stock_maximo;
        $producto['requiere_serie'] = $requiere_serie;
        $producto['requiere_lote'] = $requiere_lote;
        $producto['estado'] = $estado;
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>
        <i class="bi bi-pencil-square"></i> Editar Producto
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item"><a href="ver.php?id=<?php echo $id_producto; ?>"><?php echo htmlspecialchars($producto['nombre']); ?></a></li>
            <li class="breadcrumb-item active">Editar</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="bi bi-exclamation-triangle"></i> Errores encontrados:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="" id="form-producto">
    <div class="row">
        <!-- Información Básica -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> Información Básica
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo" value="<?php echo htmlspecialchars($producto['codigo']); ?>" required>
                            <small class="text-muted">Código único del producto</small>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" name="id_categoria">
                                <option value="0">Sin categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $producto['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proveedor</label>
                            <select class="form-select" name="id_proveedor">
                                <option value="0">Sin proveedor</option>
                                <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $producto['id_proveedor'] == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unidad de Medida</label>
                            <select class="form-select" name="unidad_medida">
                                <option value="unidad" <?php echo $producto['unidad_medida'] == 'unidad' ? 'selected' : ''; ?>>Unidad</option>
                                <option value="kg" <?php echo $producto['unidad_medida'] == 'kg' ? 'selected' : ''; ?>>Kilogramo</option>
                                <option value="gramo" <?php echo $producto['unidad_medida'] == 'gramo' ? 'selected' : ''; ?>>Gramo</option>
                                <option value="litro" <?php echo $producto['unidad_medida'] == 'litro' ? 'selected' : ''; ?>>Litro</option>
                                <option value="metro" <?php echo $producto['unidad_medida'] == 'metro' ? 'selected' : ''; ?>>Metro</option>
                                <option value="caja" <?php echo $producto['unidad_medida'] == 'caja' ? 'selected' : ''; ?>>Caja</option>
                                <option value="paquete" <?php echo $producto['unidad_medida'] == 'paquete' ? 'selected' : ''; ?>>Paquete</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" class="form-control" name="stock_minimo" value="<?php echo $producto['stock_minimo']; ?>" min="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Máximo</label>
                            <input type="number" class="form-control" name="stock_maximo" value="<?php echo $producto['stock_maximo']; ?>" min="0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requiere_serie" id="requiere_serie" <?php echo $producto['requiere_serie'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requiere_serie">
                                    Requiere Número de Serie
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="requiere_lote" id="requiere_lote" <?php echo $producto['requiere_lote'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requiere_lote">
                                    Requiere Número de Lote
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Precios -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-currency-dollar"></i> Precios
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio de Costo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio_costo" id="precio_costo" value="<?php echo $producto['precio_costo']; ?>" step="0.01" min="0">
                            </div>
                            <small class="text-muted">Precio de compra del producto</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Base <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio_base" id="precio_base" value="<?php echo $producto['precio_base']; ?>" step="0.01" min="0.01" required>
                            </div>
                            <small class="text-muted">Precio base para calcular listas</small>
                        </div>
                    </div>
                    
                    <div id="margen_info" class="alert alert-light" style="display: none;">
                        <strong>Margen de Ganancia:</strong> <span id="margen_porcentaje">0</span>%
                    </div>
                    
                    <?php if (!empty($listas_precios)): ?>
                    <div class="alert alert-info">
                        <strong><i class="bi bi-info-circle"></i> Listas de Precios Automáticas</strong>
                        <p class="mb-0 mt-2">Se actualizarán automáticamente los precios para las siguientes listas:</p>
                        <ul class="mt-2 mb-0">
                            <?php foreach ($listas_precios as $lista): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($lista['nombre_lista']); ?></strong>
                                <?php if ($lista['porcentaje_incremento'] > 0): ?>
                                    (+<?php echo $lista['porcentaje_incremento']; ?>%)
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Panel Lateral -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-gear"></i> Configuración
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="activo" <?php echo $producto['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $producto['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fecha de Creación</label>
                        <input type="text" class="form-control-plaintext" value="<?php echo formatearFechaHora($producto['fecha_creacion']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Última Modificación</label>
                        <input type="text" class="form-control-plaintext" value="<?php echo formatearFechaHora($producto['fecha_modificacion']); ?>" readonly>
                    </div>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Actualizar Producto
                        </button>
                        
                        <a href="ver.php?id=<?php echo $id_producto; ?>" class="btn btn-info">
                            <i class="bi bi-eye"></i> Ver Detalles
                        </a>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver al Listado
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formProducto = document.getElementById('form-producto');
    const precioCostoInput = document.getElementById('precio_costo');
    const precioBaseInput = document.getElementById('precio_base');
    const margenInfo = document.getElementById('margen_info');
    const margenPorcentaje = document.getElementById('margen_porcentaje');
    
    // Validación del formulario
    formProducto.addEventListener('submit', function(e) {
        const precioCosto = parseFloat(precioCostoInput.value) || 0;
        const precioBase = parseFloat(precioBaseInput.value) || 0;
        
        if (precioCosto > 0 && precioBase < precioCosto) {
            if (!confirm('⚠️ El precio base es MENOR al precio de costo.\n\n¿Está seguro de continuar?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Calcular margen en tiempo real
    function calcularMargen() {
        const costo = parseFloat(precioCostoInput.value) || 0;
        const base = parseFloat(precioBaseInput.value) || 0;
        
        if (costo > 0 && base > 0) {
            const margen = ((base - costo) / costo * 100).toFixed(2);
            margenPorcentaje.textContent = margen;
            margenInfo.style.display = 'block';
            
            // Cambiar color según el margen
            if (margen < 0) {
                margenInfo.className = 'alert alert-danger';
            } else if (margen < 20) {
                margenInfo.className = 'alert alert-warning';
            } else {
                margenInfo.className = 'alert alert-success';
            }
        } else {
            margenInfo.style.display = 'none';
        }
    }
    
    precioCostoInput.addEventListener('input', calcularMargen);
    precioBaseInput.addEventListener('input', calcularMargen);
    
    // Calcular al cargar
    calcularMargen();
});
</script>

<?php include '../../includes/footer.php'; ?>