<?php
// =============================================
// modules/productos/listas_precios_nueva.php
// Crear Nueva Lista de Precios
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'crear');

$titulo_pagina = 'Nueva Lista de Precios';
$db = getDB();
$pdo = $db->getConexion();
$errores = [];

// Obtener tipos de clientes
$tipos_cliente = $db->query("SELECT * FROM tipos_cliente WHERE estado = 'activo' ORDER BY nombre_tipo")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_lista = limpiarInput($_POST['nombre_lista']);
    $descripcion = limpiarInput($_POST['descripcion'] ?? '');
    $id_tipo_cliente = !empty($_POST['id_tipo_cliente']) ? (int)$_POST['id_tipo_cliente'] : null;
    $porcentaje_incremento = (float)$_POST['porcentaje_incremento'];
    $estado = limpiarInput($_POST['estado']);
    
    // Validaciones
    if (empty($nombre_lista)) {
        $errores[] = 'El nombre de la lista es obligatorio';
    }
    
    if ($porcentaje_incremento < -100 || $porcentaje_incremento > 500) {
        $errores[] = 'El porcentaje debe estar entre -100% y 500%';
    }
    
    // Verificar que no exista una lista con el mismo nombre
    $sql_check = "SELECT id_lista_precio FROM listas_precios WHERE nombre_lista = ?";
    $existe = $db->query($sql_check, [$nombre_lista])->fetch();
    
    if ($existe) {
        $errores[] = 'Ya existe una lista de precios con ese nombre';
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // Insertar lista
            $sql = "INSERT INTO listas_precios 
                    (nombre_lista, descripcion, id_tipo_cliente, porcentaje_incremento, estado)
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre_lista,
                $descripcion,
                $id_tipo_cliente,
                $porcentaje_incremento,
                $estado
            ]);
            
            $id_lista = $pdo->lastInsertId();
            
            // Generar precios para todos los productos activos
            $productos = $db->query("SELECT id_producto, precio_base FROM productos WHERE estado = 'activo'")->fetchAll();
            
            if (!empty($productos)) {
                $sql_precio = "INSERT INTO productos_precios (id_producto, id_lista_precio, precio) VALUES (?, ?, ?)";
                $stmt_precio = $pdo->prepare($sql_precio);
                
                foreach ($productos as $prod) {
                    $precio = $prod['precio_base'] * (1 + ($porcentaje_incremento / 100));
                    $stmt_precio->execute([$prod['id_producto'], $id_lista, $precio]);
                }
            }
            
            $pdo->commit();
            
            registrarAuditoria('listas_precios', 'crear', "Lista de precios creada: $nombre_lista");
            
            setAlerta('success', 'Lista de precios creada correctamente');
            redirigir('modules/productos/listas_precios.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="plus-circle"></i> Nueva Lista de Precios</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item"><a href="listas_precios.php">Listas de Precios</a></li>
            <li class="breadcrumb-item active">Nueva</li>
        </ol>
    </nav>
</div>

<!-- Alertas -->
<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <strong><i data-feather="alert-triangle"></i> Errores:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i data-feather="file-text"></i> Datos de la Lista</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Lista <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_lista" 
                               placeholder="Ej: Lista Minorista, Lista Mayorista" required
                               value="<?php echo isset($_POST['nombre_lista']) ? htmlspecialchars($_POST['nombre_lista']) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3" 
                                  placeholder="Descripción opcional de la lista..."><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Cliente Asociado</label>
                            <select class="form-select" name="id_tipo_cliente">
                                <option value="">General (Sin asociar)</option>
                                <?php foreach ($tipos_cliente as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo_cliente']; ?>"
                                        <?php echo (isset($_POST['id_tipo_cliente']) && $_POST['id_tipo_cliente'] == $tipo['id_tipo_cliente']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre_tipo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Los clientes de este tipo tendrán esta lista por defecto</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Porcentaje de Ajuste (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="porcentaje_incremento" 
                                   step="0.01" min="-100" max="500" value="<?php echo isset($_POST['porcentaje_incremento']) ? $_POST['porcentaje_incremento'] : '0'; ?>" required>
                            <small class="text-muted">
                                Positivo = incremento | Negativo = descuento
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado <span class="text-danger">*</span></label>
                        <select class="form-select" name="estado" required>
                            <option value="activa" <?php echo (!isset($_POST['estado']) || $_POST['estado'] == 'activa') ? 'selected' : ''; ?>>Activa</option>
                            <option value="inactiva" <?php echo (isset($_POST['estado']) && $_POST['estado'] == 'inactiva') ? 'selected' : ''; ?>>Inactiva</option>
                        </select>
                    </div>

                    <div class="alert alert-info">
                        <i data-feather="info"></i>
                        <strong>Información:</strong> Al crear esta lista, se generarán automáticamente los precios para todos los productos activos aplicando el porcentaje configurado sobre el precio base.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="save"></i> Guardar Lista
                        </button>
                        <a href="listas_precios.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i data-feather="help-circle"></i> Ayuda</h6>
            </div>
            <div class="card-body">
                <h6>Porcentaje de Ajuste:</h6>
                <ul class="small">
                    <li><strong>Incremento:</strong> Use valores positivos<br>
                        Ej: <code>21</code> = +21% (IVA incluido)</li>
                    <li><strong>Descuento:</strong> Use valores negativos<br>
                        Ej: <code>-10</code> = -10% (descuento)</li>
                    <li><strong>Precio Base:</strong> Use <code>0</code></li>
                </ul>
                
                <hr>
                
                <h6>Ejemplos:</h6>
                <div class="small">
                    <p class="mb-2">
                        <strong>Precio Base:</strong> $100<br>
                        <strong>Con +21%:</strong> $121<br>
                        <strong>Con -10%:</strong> $90
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-warning">
                <h6 class="mb-0"><i data-feather="alert-triangle"></i> Importante</h6>
            </div>
            <div class="card-body">
                <p class="small mb-0">
                    Los precios se calcularán automáticamente para todos los productos activos. Podrá ajustar precios individuales posteriormente desde el detalle de cada producto.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>