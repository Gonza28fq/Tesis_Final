<?php
// =============================================
// modules/productos/listas_precios_editar.php
// Editar Lista de Precios
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'editar');

$titulo_pagina = 'Editar Lista de Precios';
$db = getDB();
$pdo = $db->getConexion();
$errores = [];

// Verificar ID
$id_lista = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_lista <= 0) {
    setAlerta('danger', 'ID de lista inválido');
    redirigir('modules/productos/listas_precios.php');
}

// Obtener datos de la lista
$sql = "SELECT * FROM listas_precios WHERE id_lista_precio = ?";
$lista = $db->query($sql, [$id_lista])->fetch();

if (!$lista) {
    setAlerta('danger', 'Lista de precios no encontrada');
    redirigir('modules/productos/listas_precios.php');
}

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
    // Verificar que no exista otra lista con el mismo nombre
    $sql_check = "SELECT id_lista_precio FROM listas_precios 
                  WHERE nombre_lista = ? AND id_lista_precio != ?";
    $existe = $db->query($sql_check, [$nombre_lista, $id_lista])->fetch();
    
    if ($existe) {
        $errores[] = 'Ya existe una lista de precios con ese nombre';
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            $sql = "UPDATE listas_precios 
                    SET nombre_lista = ?, 
                        descripcion = ?, 
                        id_tipo_cliente = ?, 
                        porcentaje_incremento = ?, 
                        estado = ?
                    WHERE id_lista_precio = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nombre_lista,
                $descripcion,
                $id_tipo_cliente,
                $porcentaje_incremento,
                $estado,
                $id_lista
            ]);
            
            $pdo->commit();
            
            registrarAuditoria('listas_precios', 'editar', "Lista de precios editada: $nombre_lista");
            
            setAlerta('success', 'Lista de precios actualizada correctamente');
            redirigir('modules/productos/listas_precios.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-pencil-square"></i> Editar Lista de Precios</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item"><a href="listas_precios.php">Listas de Precios</a></li>
            <li class="breadcrumb-item active">Editar</li>
        </ol>
    </nav>
</div>

<!-- Alertas -->
<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <strong><i class="bi bi-exclamation-triangle"></i> Errores:</strong>
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
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Datos de la Lista</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Lista <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_lista" 
                               value="<?php echo htmlspecialchars($lista['nombre_lista']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"><?php echo htmlspecialchars($lista['descripcion'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Cliente</label>
                            <select class="form-select" name="id_tipo_cliente">
                                <option value="">General (Sin asociar)</option>
                                <?php foreach ($tipos_cliente as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo_cliente']; ?>"
                                        <?php echo (isset($_POST['id_tipo_cliente']) && $_POST['id_tipo_cliente'] == $tipo['id_tipo_cliente']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre_tipo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Esta lista se aplicará a clientes de este tipo</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Porcentaje de Incremento (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="porcentaje_incremento" 
                                   step="0.01" min="0" 
                                   value="<?php echo $lista['porcentaje_incremento']; ?>" required>
                            <small class="text-muted">Incremento sobre el precio base del producto</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado <span class="text-danger">*</span></label>
                        <select class="form-select" name="estado" required>
                            <option value="activa" <?php echo $lista['estado'] == 'activa' ? 'selected' : ''; ?>>Activa</option>
                            <option value="inactiva" <?php echo $lista['estado'] == 'inactiva' ? 'selected' : ''; ?>>Inactiva</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Guardar Cambios
                        </button>
                        <a href="listas_precios.php" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Información</h6>
            </div>
            <div class="card-body">
                <p><strong>Fecha de creación:</strong><br>
                <?php echo formatearFechaHora($lista['fecha_creacion']); ?></p>
                
                <?php if ($lista['fecha_modificacion']): ?>
                <p><strong>Última modificación:</strong><br>
                <?php echo formatearFechaHora($lista['fecha_modificacion']); ?></p>
                <?php endif; ?>
                
                <hr>
                
                <p class="mb-0"><small class="text-muted">
                    <i class="bi bi-lightbulb"></i> El porcentaje de incremento se aplica sobre el precio base de cada producto.
                </small></p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>