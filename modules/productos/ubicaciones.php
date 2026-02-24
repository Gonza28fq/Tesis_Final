<?php
// =============================================
// modules/productos/ubicaciones.php
// Gestión de Ubicaciones (Depósitos/Almacenes)
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'ver');

$titulo_pagina = 'Ubicaciones / Depósitos';
$db = getDB();
$pdo = $db->getConexion();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear' && tienePermiso('productos', 'crear')) {
        $nombre = limpiarInput($_POST['nombre_ubicacion']);
        $descripcion = limpiarInput($_POST['descripcion']);
        $direccion = limpiarInput($_POST['direccion']);
        $responsable = limpiarInput($_POST['responsable']);
        $tipo = $_POST['tipo'];
        
        if (!empty($nombre)) {
            try {
                $sql = "INSERT INTO ubicaciones (nombre_ubicacion, descripcion, direccion, responsable, tipo) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $direccion, $responsable, $tipo]);
                
                registrarAuditoria('productos', 'crear_ubicacion', "Ubicación: $nombre");
                setAlerta('success', 'Ubicación creada correctamente');
            } catch (Exception $e) {
                setAlerta('error', 'Error al crear la ubicación: ' . $e->getMessage());
            }
        }
        redirigir('modules/productos/ubicaciones.php');
    }
    
    if ($accion === 'editar' && tienePermiso('productos', 'editar')) {
        $id = (int)$_POST['id_ubicacion'];
        $nombre = limpiarInput($_POST['nombre_ubicacion']);
        $descripcion = limpiarInput($_POST['descripcion']);
        $direccion = limpiarInput($_POST['direccion']);
        $responsable = limpiarInput($_POST['responsable']);
        $tipo = $_POST['tipo'];
        $estado = $_POST['estado'];
        
        try {
            $sql = "UPDATE ubicaciones 
                    SET nombre_ubicacion = ?, descripcion = ?, direccion = ?, responsable = ?, tipo = ?, estado = ? 
                    WHERE id_ubicacion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $descripcion, $direccion, $responsable, $tipo, $estado, $id]);
            
            registrarAuditoria('productos', 'editar_ubicacion', "Ubicación ID: $id");
            setAlerta('success', 'Ubicación actualizada correctamente');
        } catch (Exception $e) {
            setAlerta('error', 'Error al actualizar la ubicación: ' . $e->getMessage());
        }
        redirigir('modules/productos/ubicaciones.php');
    }
    
    if ($accion === 'eliminar' && tienePermiso('productos', 'eliminar')) {
        $id = (int)$_POST['id_ubicacion'];
        
        // Verificar si tiene stock asociado
        $sql_count = "SELECT COUNT(*) as total FROM stock WHERE id_ubicacion = ? AND cantidad > 0";
        $stmt = $db->query($sql_count, [$id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $resultado['total'];
        
        if ($count > 0) {
            setAlerta('warning', "No se puede eliminar. Hay $count productos con stock en esta ubicación");
        } else {
            try {
                $sql = "UPDATE ubicaciones SET estado = 'inactivo' WHERE id_ubicacion = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                
                registrarAuditoria('productos', 'eliminar_ubicacion', "Ubicación ID: $id");
                setAlerta('success', 'Ubicación eliminada correctamente');
            } catch (Exception $e) {
                setAlerta('error', 'Error al eliminar la ubicación: ' . $e->getMessage());
            }
        }
        redirigir('ubicaciones.php');
    }
}

// Obtener todas las ubicaciones
$sql = "SELECT u.*, 
        (SELECT COUNT(DISTINCT s.id_producto) FROM stock s WHERE s.id_ubicacion = u.id_ubicacion AND s.cantidad > 0) as total_productos,
        (SELECT COALESCE(SUM(s.cantidad), 0) FROM stock s WHERE s.id_ubicacion = u.id_ubicacion) as total_stock
        FROM ubicaciones u
        ORDER BY u.nombre_ubicacion";
$ubicaciones = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="map-pin"></i> Ubicaciones / Depósitos</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item active">Ubicaciones</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i data-feather="plus-circle"></i> Nueva Ubicación
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_ubicacion" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" name="tipo" required>
                            <option value="deposito">Depósito</option>
                            <option value="sucursal">Sucursal</option>
                            <option value="almacen">Almacén</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección<span class="text-danger">*</label>
                        <input type="text" class="form-control" name="direccion" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Responsable</label>
                        <input type="text" class="form-control" name="responsable">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <?php if (tienePermiso('productos', 'crear')): ?>
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="save"></i> Guardar Ubicación
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i data-feather="list"></i> Listado de Ubicaciones (<?php echo count($ubicaciones); ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Responsable</th>
                                <th>Productos</th>
                                <th>Stock Total</th>
                                <th>Estado</th>
                                <th width="120">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ubicaciones)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay ubicaciones registradas</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($ubicaciones as $ubic): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ubic['nombre_ubicacion']); ?></strong>
                                        <?php if (!empty($ubic['direccion'])): ?>
                                        <br><small class="text-muted"><i data-feather="map-pin" width="12"></i> <?php echo htmlspecialchars($ubic['direccion']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $tipo_badges = [
                                            'deposito' => 'primary',
                                            'sucursal' => 'success',
                                            'almacen' => 'info'
                                        ];
                                        $badge_color = $tipo_badges[$ubic['tipo']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                            <?php echo ucfirst($ubic['tipo']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ubic['responsable'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $ubic['total_productos']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo formatearNumero($ubic['total_stock']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $ubic['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($ubic['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-info btn-ver" 
                                                    data-ubicacion='<?php echo htmlspecialchars(json_encode($ubic)); ?>'>
                                                <i data-feather="eye"></i>
                                            </button>
                                            
                                            <?php if (tienePermiso('productos', 'editar')): ?>
                                            <button type="button" class="btn btn-warning btn-editar" 
                                                    data-ubicacion='<?php echo htmlspecialchars(json_encode($ubic)); ?>'>
                                                <i data-feather="edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (tienePermiso('productos', 'eliminar') && $ubic['total_productos'] == 0): ?>
                                            <button type="button" class="btn btn-danger btn-eliminar" 
                                                    data-id="<?php echo $ubic['id_ubicacion']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($ubic['nombre_ubicacion']); ?>">
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

<!-- Modal Ver Ubicación -->
<div class="modal fade" id="modalVer" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de la Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoVer">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Ubicación -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_ubicacion" id="edit_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_ubicacion" id="edit_nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" name="tipo" id="edit_tipo" required>
                            <option value="deposito">Depósito</option>
                            <option value="sucursal">Sucursal</option>
                            <option value="almacen">Almacén</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control" name="direccion" id="edit_direccion">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Responsable</label>
                        <input type="text" class="form-control" name="responsable" id="edit_responsable">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="edit_descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado" id="edit_estado">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
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
                <input type="hidden" name="id_ubicacion" id="del_id">
                
                <div class="modal-body">
                    <p>¿Está seguro de eliminar la ubicación <strong id="del_nombre"></strong>?</p>
                    <p class="text-muted mb-0">Esta acción cambiará su estado a inactivo.</p>
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
    const modalVer = new bootstrap.Modal(document.getElementById('modalVer'));
    const modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
    const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));
    
    // Botones Ver
    document.querySelectorAll('.btn-ver').forEach(btn => {
        btn.addEventListener('click', function() {
            const ubicacion = JSON.parse(this.dataset.ubicacion);
            
            const tipoBadges = {
                'deposito': 'primary',
                'sucursal': 'success',
                'almacen': 'info'
            };
            const badgeColor = tipoBadges[ubicacion.tipo] || 'secondary';
            
            const html = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Nombre</label>
                        <p class="mb-0"><strong>${ubicacion.nombre_ubicacion}</strong></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Tipo</label>
                        <p class="mb-0"><span class="badge bg-${badgeColor}">${ubicacion.tipo.charAt(0).toUpperCase() + ubicacion.tipo.slice(1)}</span></p>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="text-muted">Dirección</label>
                        <p class="mb-0">${ubicacion.direccion || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Responsable</label>
                        <p class="mb-0">${ubicacion.responsable || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Estado</label>
                        <p class="mb-0"><span class="badge bg-${ubicacion.estado == 'activo' ? 'success' : 'secondary'}">${ubicacion.estado}</span></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Productos</label>
                        <p class="mb-0"><span class="badge bg-primary">${ubicacion.total_productos}</span></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Stock Total</label>
                        <p class="mb-0"><span class="badge bg-info">${parseInt(ubicacion.total_stock).toLocaleString()}</span></p>
                    </div>
                    ${ubicacion.descripcion ? `
                    <div class="col-12 mb-3">
                        <label class="text-muted">Descripción</label>
                        <p class="mb-0">${ubicacion.descripcion}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('contenidoVer').innerHTML = html;
            modalVer.show();
        });
    });
    
    // Botones Editar
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function() {
            const ubicacion = JSON.parse(this.dataset.ubicacion);
            document.getElementById('edit_id').value = ubicacion.id_ubicacion;
            document.getElementById('edit_nombre').value = ubicacion.nombre_ubicacion;
            document.getElementById('edit_tipo').value = ubicacion.tipo;
            document.getElementById('edit_direccion').value = ubicacion.direccion || '';
            document.getElementById('edit_responsable').value = ubicacion.responsable || '';
            document.getElementById('edit_descripcion').value = ubicacion.descripcion || '';
            document.getElementById('edit_estado').value = ubicacion.estado;
            
            modalEditar.show();
        });
    });
    
    // Botones Eliminar
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            document.getElementById('del_id').value = id;
            document.getElementById('del_nombre').textContent = nombre;
            
            modalEliminar.show();
        });
    });
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>