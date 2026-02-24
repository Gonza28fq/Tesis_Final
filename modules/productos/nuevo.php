<?php
// =============================================
// modules/productos/nuevo.php y editar.php
// Formulario para Crear/Editar Producto
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

$es_edicion = basename($_SERVER['PHP_SELF']) == 'editar.php';

if ($es_edicion) {
    requierePermiso('productos', 'editar');
    $titulo_pagina = 'Editar Producto';
    $id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id_producto == 0) {
        setAlerta('error', 'Producto no válido');
        redirigir('index.php');
    }
} else {
    requierePermiso('productos', 'crear');
    $titulo_pagina = 'Nuevo Producto';
    $id_producto = 0;
}

$db = getDB();
$pdo = $db->getConexion();
$errores = [];
$producto = [
    'codigo' => '',
    'nombre' => '',
    'descripcion' => '',
    'id_categoria' => 0,
    'id_proveedor' => 0,
    'precio_costo' => 0,
    'precio_base' => 0,
    'unidad_medida' => 'unidad',
    'stock_minimo' => 0,
    'stock_maximo' => 0,
    'requiere_serie' => false,
    'requiere_lote' => false,
    'estado' => 'activo'
];

// Si es edición, cargar datos del producto
if ($es_edicion) {
    $sql = "SELECT * FROM productos WHERE id_producto = ?";
    $stmt = $db->query($sql, [$id_producto]);
    $producto_db = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto_db) {
        setAlerta('error', 'Producto no encontrado');
        redirigir('index.php');
    }
    
    $producto = $producto_db;
}

// Obtener categorías
$categorias = $db->query("SELECT * FROM categorias_producto WHERE estado = 'activa' ORDER BY nombre_categoria")->fetchAll(PDO::FETCH_ASSOC);

// Obtener proveedores
$proveedores = $db->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre_proveedor")->fetchAll(PDO::FETCH_ASSOC);

// Obtener listas de precios activas
$listas_precios = $db->query("SELECT * FROM listas_precios WHERE estado = 'activa' ORDER BY nombre_lista")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto['codigo'] = limpiarInput($_POST['codigo']);
    $producto['nombre'] = limpiarInput($_POST['nombre']);
    $producto['descripcion'] = limpiarInput($_POST['descripcion']);
    $producto['id_categoria'] = (int)$_POST['id_categoria'];
    $producto['id_proveedor'] = (int)$_POST['id_proveedor'];
    $producto['precio_costo'] = (float)$_POST['precio_costo'];
    $producto['precio_base'] = (float)$_POST['precio_base'];
    $producto['unidad_medida'] = limpiarInput($_POST['unidad_medida']);
    $producto['stock_minimo'] = (int)$_POST['stock_minimo'];
    $producto['stock_maximo'] = (int)$_POST['stock_maximo'];
    $producto['requiere_serie'] = isset($_POST['requiere_serie']) ? 1 : 0;
    $producto['requiere_lote'] = isset($_POST['requiere_lote']) ? 1 : 0;
    $producto['estado'] = $_POST['estado'];
    
    // Validaciones
    if (empty($producto['codigo'])) {
        $errores[] = 'El código es obligatorio';
    }
    
    if (empty($producto['nombre'])) {
        $errores[] = 'El nombre es obligatorio';
    }
    
    if ($producto['precio_base'] <= 0) {
        $errores[] = 'El precio base debe ser mayor a 0';
    }
    // Validar stock mínimo vs stock máximo
    if ($producto['stock_maximo'] > 0 && $producto['stock_minimo'] > $producto['stock_maximo']) {
        $errores[] = 'El stock mínimo no puede ser mayor al stock máximo';
    }
    
    // Verificar código único
    if ($es_edicion) {
        $sql_check = "SELECT COUNT(*) as total FROM productos WHERE codigo = ? AND id_producto != ?";
        $stmt = $db->query($sql_check, [$producto['codigo'], $id_producto]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    } else {
        $sql_check = "SELECT COUNT(*) as total FROM productos WHERE codigo = ?";
        $stmt = $db->query($sql_check, [$producto['codigo']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    }
    
    if ($existe > 0) {
        $errores[] = 'El código ya existe en otro producto';
    }
    
    // Si no hay errores, guardar
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            if ($es_edicion) {
                // Actualizar producto
                $sql = "UPDATE productos SET 
                        codigo = ?, nombre = ?, descripcion = ?, id_categoria = ?,
                        id_proveedor = ?, precio_costo = ?, precio_base = ?,
                        unidad_medida = ?, stock_minimo = ?, stock_maximo = ?,
                        requiere_serie = ?, requiere_lote = ?, estado = ?
                        WHERE id_producto = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $producto['codigo'], $producto['nombre'], $producto['descripcion'],
                    $producto['id_categoria'] ?: null, $producto['id_proveedor'] ?: null,
                    $producto['precio_costo'], $producto['precio_base'],
                    $producto['unidad_medida'], $producto['stock_minimo'], $producto['stock_maximo'],
                    $producto['requiere_serie'], $producto['requiere_lote'], $producto['estado'],
                    $id_producto
                ]);
                
                $producto_id = $id_producto;
                $mensaje = 'Producto actualizado correctamente';
                $accion_auditoria = 'actualizar';
                
            } else {
                // Insertar nuevo producto
                $sql = "INSERT INTO productos 
                        (codigo, nombre, descripcion, id_categoria, id_proveedor,
                         precio_costo, precio_base, unidad_medida, stock_minimo,
                         stock_maximo, requiere_serie, requiere_lote, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $producto['codigo'], $producto['nombre'], $producto['descripcion'],
                    $producto['id_categoria'] ?: null, $producto['id_proveedor'] ?: null,
                    $producto['precio_costo'], $producto['precio_base'],
                    $producto['unidad_medida'], $producto['stock_minimo'], $producto['stock_maximo'],
                    $producto['requiere_serie'], $producto['requiere_lote'], $producto['estado']
                ]);
                
                $producto_id = $pdo->lastInsertId();
                $mensaje = 'Producto creado correctamente';
                $accion_auditoria = 'crear';
            }
            
            // Generar precios automáticos para todas las listas activas
            if (!empty($listas_precios)) {
                // Eliminar precios anteriores si es edición
                if ($es_edicion) {
                    $stmt = $pdo->prepare("DELETE FROM productos_precios WHERE id_producto = ?");
                    $stmt->execute([$producto_id]);
                }
                
                foreach ($listas_precios as $lista) {
                    $precio_lista = $producto['precio_base'];
                    
                    // Aplicar porcentaje de incremento de la lista
                    if ($lista['porcentaje_incremento'] > 0) {
                        $precio_lista = $precio_lista * (1 + ($lista['porcentaje_incremento'] / 100));
                    }
                    
                    $sql_precio = "INSERT INTO productos_precios 
                                  (id_producto, id_lista_precio, precio, fecha_vigencia_desde)
                                  VALUES (?, ?, ?, CURRENT_DATE)";
                    
                    $stmt = $pdo->prepare($sql_precio);
                    $stmt->execute([$producto_id, $lista['id_lista_precio'], $precio_lista]);
                }
            }
            
            // Crear stock inicial en la ubicación por defecto (si es nuevo)
            if (!$es_edicion) {
                $sql_ubicacion = "SELECT id_ubicacion FROM ubicaciones WHERE estado = 'activo' LIMIT 1";
                $stmt = $db->query($sql_ubicacion);
                $ubicacion = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ubicacion) {
                    $sql_stock = "INSERT INTO stock (id_producto, id_ubicacion, cantidad) VALUES (?, ?, 0)";
                    $stmt = $pdo->prepare($sql_stock);
                    $stmt->execute([$producto_id, $ubicacion['id_ubicacion']]);
                }
            }
            
            $pdo->commit();

            // Registrar en auditoría
            registrarAuditoria('productos', $accion_auditoria, 
                "Producto: {$producto['nombre']} (ID: $producto_id)");

            setAlerta('success', $mensaje);

            // Redirección correcta
            header('Location: ver.php?id=' . $producto_id);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = 'Error al guardar el producto: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>
        <i data-feather="package"></i> 
        <?php echo $es_edicion ? 'Editar Producto' : 'Nuevo Producto'; ?>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item active"><?php echo $es_edicion ? 'Editar' : 'Nuevo'; ?></li>
        </ol>
    </nav>
</div>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i data-feather="alert-circle"></i> Errores encontrados:</strong>
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
                    <i data-feather="info"></i> Información Básica
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
                        <textarea class="form-control" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría<span class="text-danger">*</label>
                            <select class="form-select" name="id_categoria" required>
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $producto['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><a href="categorias.php" target="_blank">Gestionar categorías</a></small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proveedor<span class="text-danger">*</label>
                            <select class="form-select" name="id_proveedor" required>
                                <option value="">Sin proveedor</option>
                                <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $producto['id_proveedor'] == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre_proveedor']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><a href="proveedores.php" target="_blank">Gestionar proveedores</a></small>
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
                    <i data-feather="dollar-sign"></i> Precios
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio de Costo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="precio_costo" value="<?php echo $producto['precio_costo']; ?>" step="0.01" min="0">
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
                    
                    <?php if (!empty($listas_precios)): ?>
                    <div class="alert alert-info">
                        <strong><i data-feather="info" width="16"></i> Listas de Precios Automáticas</strong>
                        <p class="mb-0 mt-2">Se generarán automáticamente los precios para las siguientes listas:</p>
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
                    <i data-feather="settings"></i> Configuración
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="activo" <?php echo $producto['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $producto['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <?php if ($es_edicion && isset($producto['fecha_creacion'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Fecha de Creación</label>
                        <input type="text" class="form-control" value="<?php echo formatearFechaHora($producto['fecha_creacion']); ?>" readonly>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($es_edicion && isset($producto['fecha_modificacion'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Última Modificación</label>
                        <input type="text" class="form-control" value="<?php echo formatearFechaHora($producto['fecha_modificacion']); ?>" readonly>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="save"></i> <?php echo $es_edicion ? 'Actualizar' : 'Guardar'; ?> Producto
                        </button>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                        
                        <?php if ($es_edicion): ?>
                        <a href="modules/productos/ver.php?id=<?php echo $id_producto; ?>" class="btn btn-info">
                            <i data-feather="eye"></i> Ver Detalles
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación del formulario
    const formProducto = document.getElementById('form-producto');
    if (formProducto) {
        formProducto.addEventListener('submit', function(e) {
            const precioCostoInput = document.querySelector('[name="precio_costo"]');
            const precioBaseInput = document.querySelector('[name="precio_base"]');
            
            if (precioCostoInput && precioBaseInput) {
                const precioCosto = parseFloat(precioCostoInput.value);
                const precioBase = parseFloat(precioBaseInput.value);
                
                if (precioCosto > 0 && precioBase < precioCosto) {
                    if (!confirm('El precio base es menor al precio de costo. ¿Desea continuar?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    }
    
    // Calcular margen en tiempo real
    const precioCostoInput = document.querySelector('[name="precio_costo"]');
    const precioBaseInput = document.querySelector('[name="precio_base"]');
    
    if (precioCostoInput && precioBaseInput) {
        precioCostoInput.addEventListener('input', calcularMargen);
        precioBaseInput.addEventListener('input', calcularMargen);
    }
    
    function calcularMargen() {
        const costo = parseFloat(precioCostoInput.value) || 0;
        const base = parseFloat(precioBaseInput.value) || 0;
        
        if (costo > 0 && base > 0) {
            const margen = ((base - costo) / costo * 100).toFixed(2);
            console.log('Margen de ganancia: ' + margen + '%');
        }
    }
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>