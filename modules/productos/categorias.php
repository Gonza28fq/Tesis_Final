<?php
// =============================================
// modules/productos/categorias.php
// Gestión de Categorías de Productos
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'ver');

$titulo_pagina = 'Categorías de Productos';
$db = getDB();
$pdo = $db->getConexion();

// Procesar acciones
// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear' && tienePermiso('productos', 'crear')) {
        $nombre = limpiarInput($_POST['nombre_categoria'] ?? '');
        $descripcion = limpiarInput($_POST['descripcion'] ?? '');
        
        if (!empty($nombre)) {
            try {
                // Verificar si ya existe una categoría con ese nombre
                $sql_check = "SELECT COUNT(*) as total FROM categorias_producto WHERE nombre_categoria = ?";
                $stmt_check = $db->query($sql_check, [$nombre]);
                $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
                
                if ($existe['total'] > 0) {
                    setAlerta('warning', 'Ya existe una categoría con ese nombre');
                } else {
                    $sql = "INSERT INTO categorias_producto (nombre_categoria, descripcion) VALUES (?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $descripcion]);
                    
                    registrarAuditoria('productos', 'crear_categoria', "Categoría: $nombre");
                    setAlerta('success', 'Categoría creada correctamente');
                }
            } catch (Exception $e) {
                setAlerta('error', 'Error al crear la categoría: ' . $e->getMessage());
            }
        } else {
            setAlerta('error', 'El nombre de la categoría es obligatorio');
        }
        redirigir('modules/productos/categorias.php');
        exit;
    }
    
    if ($accion === 'editar' && tienePermiso('productos', 'editar')) {
        $id = (int)($_POST['id_categoria'] ?? 0);
        $nombre = limpiarInput($_POST['nombre_categoria'] ?? '');
        $descripcion = limpiarInput($_POST['descripcion'] ?? '');
        $estado = $_POST['estado'] ?? 'activa';
        
        if ($id > 0 && !empty($nombre)) {
            try {
                // Verificar si ya existe otra categoría con ese nombre
                $sql_check = "SELECT COUNT(*) as total FROM categorias_producto WHERE nombre_categoria = ? AND id_categoria != ?";
                $stmt_check = $db->query($sql_check, [$nombre, $id]);
                $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
                
                if ($existe['total'] > 0) {
                    setAlerta('warning', 'Ya existe otra categoría con ese nombre');
                } else {
                    $sql = "UPDATE categorias_producto SET nombre_categoria = ?, descripcion = ?, estado = ? WHERE id_categoria = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $descripcion, $estado, $id]);
                    
                    registrarAuditoria('productos', 'editar_categoria', "Categoría ID: $id - $nombre");
                    setAlerta('success', 'Categoría actualizada correctamente');
                }
            } catch (Exception $e) {
                setAlerta('error', 'Error al actualizar la categoría: ' . $e->getMessage());
            }
        } else {
            setAlerta('error', 'Datos inválidos para actualizar la categoría');
        }
        redirigir('modules/productos/categorias.php');
        exit;
    }
    
    if ($accion === 'eliminar' && tienePermiso('productos', 'eliminar')) {
        $id = (int)($_POST['id_categoria'] ?? 0);
        
        if ($id > 0) {
            try {
                // Verificar si tiene productos asociados
                $sql_count = "SELECT COUNT(*) as total FROM productos WHERE id_categoria = ?";
                $stmt = $db->query($sql_count, [$id]);
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = $resultado['total'];
                
                if ($count > 0) {
                    setAlerta('warning', "No se puede eliminar. Hay $count productos asociados a esta categoría");
                } else {
                    $sql = "UPDATE categorias_producto SET estado = 'inactiva' WHERE id_categoria = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);
                    
                    registrarAuditoria('productos', 'eliminar_categoria', "Categoría ID: $id");
                    setAlerta('success', 'Categoría eliminada correctamente');
                }
            } catch (Exception $e) {
                setAlerta('error', 'Error al eliminar la categoría: ' . $e->getMessage());
            }
        } else {
            setAlerta('error', 'ID de categoría inválido');
        }
        redirigir('categorias.php');
        exit;
    }
}
// Obtener todas las categorías
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM productos WHERE id_categoria = c.id_categoria AND estado = 'activo') as total_productos
        FROM categorias_producto c
        ORDER BY c.nombre_categoria";
$categorias = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="tag"></i> Categorías de Productos</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item active">Categorías</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i data-feather="plus-circle"></i> Nueva Categoría
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_categoria" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <?php if (tienePermiso('productos', 'crear')): ?>
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="save"></i> Guardar Categoría
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i data-feather="list"></i> Listado de Categorías (<?php echo count($categorias); ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Productos</th>
                                <th>Estado</th>
                                <th width="120">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay categorías registradas</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['nombre_categoria']); ?></strong></td>
                                    <td>
                                        <?php 
                                        $desc = htmlspecialchars($cat['descripcion']);
                                        echo substr($desc, 0, 50); 
                                        echo strlen($desc) > 50 ? '...' : ''; 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $cat['total_productos']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $cat['estado'] == 'activa' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($cat['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (tienePermiso('productos', 'editar')): ?>
                                            <button type="button" class="btn btn-warning btn-editar" 
                                                    data-categoria='<?php echo htmlspecialchars(json_encode($cat)); ?>'>
                                                <i data-feather="edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (tienePermiso('productos', 'eliminar') && $cat['total_productos'] == 0): ?>
                                            <button type="button" class="btn btn-danger btn-eliminar" 
                                                    data-id="<?php echo $cat['id_categoria']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($cat['nombre_categoria']); ?>">
                                                <i data-feather="trash-2"></i>
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
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Categoría -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_categoria" id="edit_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_categoria" id="edit_nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado" id="edit_estado">
                            <option value="activa">Activa</option>
                            <option value="inactiva">Inactiva</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id_categoria" id="del_id">
                
                <div class="modal-body">
                    <p>¿Está seguro de eliminar la categoría <strong id="del_nombre"></strong>?</p>
                    <p class="text-muted mb-0">Esta acción cambiará su estado a inactiva.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Botones Editar
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function() {
            const categoria = JSON.parse(this.dataset.categoria);
            document.getElementById('edit_id').value = categoria.id_categoria;
            document.getElementById('edit_nombre').value = categoria.nombre_categoria;
            document.getElementById('edit_descripcion').value = categoria.descripcion || '';
            document.getElementById('edit_estado').value = categoria.estado;
            
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        });
    });
    
    // Botones Eliminar
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            document.getElementById('del_id').value = id;
            document.getElementById('del_nombre').textContent = nombre;
            
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        });
    });
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>