<?php
// =============================================
// modules/roles/index.php
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';
iniciarSesion();

requierePermiso('roles', 'ver');

$titulo_pagina = 'Roles y Permisos';
$db = getDB();

// Obtener todos los roles
$roles = $db->query("SELECT * FROM roles ORDER BY id_rol")->fetchAll();

// Obtener todos los permisos agrupados por módulo
$sql_permisos = "SELECT * FROM permisos ORDER BY modulo, accion";
$permisos = $db->query($sql_permisos)->fetchAll();

// Agrupar permisos por módulo
$permisos_por_modulo = [];
foreach ($permisos as $permiso) {
    $permisos_por_modulo[$permiso['modulo']][] = $permiso;
}

// Obtener permisos asignados a cada rol
$permisos_roles = [];
foreach ($roles as $rol) {
    $sql = "SELECT id_permiso FROM roles_permisos WHERE id_rol = ?";
    $stmt = $db->query($sql, [$rol['id_rol']]);
    $permisos_roles[$rol['id_rol']] = array_column($stmt->fetchAll(), 'id_permiso');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shield"></i> Gestión de Roles y Permisos</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="../usuarios/index.php">Usuarios</a></li>
            <li class="breadcrumb-item active">Roles y Permisos</li>
        </ol>
    </nav>
</div>

<div class="alert alert-info">
    <h5 class="alert-heading"><i data-feather="info"></i> Sistema de Roles y Permisos</h5>
    <p class="mb-0">Seleccione los permisos para cada rol y haga clic en <strong>"Guardar Cambios"</strong> para aplicarlos.</p>
</div>

<!-- Botones -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-primary" id="btnNuevoRol">
                <i data-feather="plus-circle"></i> Nuevo Rol
            </button>
            
            <a href="../usuarios/index.php" class="btn btn-secondary">
                <i data-feather="arrow-left"></i> Volver a Usuarios
            </a>
        </div>
    </div>
</div>

<!-- Roles y Permisos -->
<div class="row">
    <div class="col-md-3">
        <!-- Lista de Roles -->
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header">
                <i data-feather="list"></i> Roles del Sistema
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($roles as $rol): ?>
                <a href="#rol-<?php echo $rol['id_rol']; ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars(str_replace('_', ' ', $rol['nombre_rol'])); ?></strong>
                            <br><small class="text-muted"><?php echo count($permisos_roles[$rol['id_rol']]); ?> permisos</small>
                        </div>
                        <?php if ($rol['id_rol'] != 1): ?>
                        <button type="button" class="btn btn-sm btn-link btn-editar-rol" 
                                data-rol='<?php echo htmlspecialchars(json_encode($rol)); ?>'>
                            <i data-feather="edit" width="16"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <?php foreach ($roles as $rol): ?>
        <div class="card mb-4" id="rol-<?php echo $rol['id_rol']; ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i data-feather="shield"></i> <?php echo htmlspecialchars(str_replace('_', ' ', $rol['nombre_rol'])); ?>
                    </h5>
                    <small class="text-muted"><?php echo htmlspecialchars($rol['descripcion']); ?></small>
                </div>
                
                <?php if ($rol['id_rol'] == 1): ?>
                <span class="badge bg-danger">ACCESO TOTAL</span>
                <?php else: ?>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-primary btn-todos" data-rol="<?php echo $rol['id_rol']; ?>">
                        <i data-feather="check-square" width="16"></i> Todos
                    </button>
                    <button type="button" class="btn btn-secondary btn-ninguno" data-rol="<?php echo $rol['id_rol']; ?>">
                        <i data-feather="square" width="16"></i> Ninguno
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <?php if ($rol['id_rol'] == 1): ?>
                    <div class="alert alert-warning mb-0">
                        <i data-feather="alert-triangle"></i> El rol de Administrador tiene acceso completo a todo el sistema y no puede ser modificado.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($permisos_por_modulo as $modulo => $perms): ?>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="mb-3">
                                    <i data-feather="folder" width="16"></i> 
                                    <strong><?php echo htmlspecialchars(ucfirst($modulo)); ?></strong>
                                </h6>
                                
                                <?php foreach ($perms as $permiso): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input permiso-check" 
                                           type="checkbox" 
                                           id="perm_<?php echo $rol['id_rol']; ?>_<?php echo $permiso['id_permiso']; ?>"
                                           data-rol="<?php echo $rol['id_rol']; ?>"
                                           data-permiso="<?php echo $permiso['id_permiso']; ?>"
                                           <?php echo in_array($permiso['id_permiso'], $permisos_roles[$rol['id_rol']]) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_<?php echo $rol['id_rol']; ?>_<?php echo $permiso['id_permiso']; ?>">
                                        <?php echo htmlspecialchars(ucfirst($permiso['accion'])); ?>
                                        <?php if ($permiso['descripcion']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($permiso['descripcion']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Botón Guardar Cambios para este Rol -->
                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-success btn-guardar-cambios" 
                                data-rol="<?php echo $rol['id_rol']; ?>"
                                style="display: none;">
                            <i data-feather="save"></i> Guardar Cambios de este Rol
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Nuevo/Editar Rol -->
<div class="modal fade" id="modalRol" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRol">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id_rol" id="id_rol">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Rol <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_rol" id="nombre_rol" required>
                        <small class="text-muted">Use mayúsculas y guiones bajos (ej: JEFE_VENTAS)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado" id="estado">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarRol">
                        <i data-feather="save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modal
    const modalRol = new bootstrap.Modal(document.getElementById('modalRol'));
    
    // Objeto para rastrear cambios por rol
    const cambiosPorRol = {};
    
    // Botón Nuevo Rol
    const btnNuevoRol = document.getElementById('btnNuevoRol');
    if (btnNuevoRol) {
        btnNuevoRol.addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Nuevo Rol';
            document.getElementById('accion').value = 'crear';
            document.getElementById('formRol').reset();
            document.getElementById('id_rol').value = '';
            document.getElementById('estado').value = 'activo';
            modalRol.show();
        });
    }
    
    // Botones Editar Rol
    document.querySelectorAll('.btn-editar-rol').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const rol = JSON.parse(this.dataset.rol);
            document.getElementById('modalTitle').textContent = 'Editar Rol';
            document.getElementById('accion').value = 'editar';
            document.getElementById('id_rol').value = rol.id_rol;
            document.getElementById('nombre_rol').value = rol.nombre_rol;
            document.getElementById('descripcion').value = rol.descripcion || '';
            document.getElementById('estado').value = rol.estado;
            modalRol.show();
        });
    });
    
    // Guardar Rol
    document.getElementById('formRol').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnGuardar = document.getElementById('btnGuardarRol');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        
        const formData = new FormData(this);
        
        fetch('roles_acciones.php', {
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
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        });
    });
    
    // Detectar cambios en checkboxes
    document.querySelectorAll('.permiso-check').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const idRol = this.dataset.rol;
            
            // Inicializar array de cambios para este rol si no existe
            if (!cambiosPorRol[idRol]) {
                cambiosPorRol[idRol] = {
                    asignar: [],
                    quitar: []
                };
            }
            
            const idPermiso = parseInt(this.dataset.permiso);
            
            if (this.checked) {
                // Agregar a lista de asignar
                if (!cambiosPorRol[idRol].asignar.includes(idPermiso)) {
                    cambiosPorRol[idRol].asignar.push(idPermiso);
                }
                // Quitar de lista de quitar si estaba
                const index = cambiosPorRol[idRol].quitar.indexOf(idPermiso);
                if (index > -1) {
                    cambiosPorRol[idRol].quitar.splice(index, 1);
                }
            } else {
                // Agregar a lista de quitar
                if (!cambiosPorRol[idRol].quitar.includes(idPermiso)) {
                    cambiosPorRol[idRol].quitar.push(idPermiso);
                }
                // Quitar de lista de asignar si estaba
                const index = cambiosPorRol[idRol].asignar.indexOf(idPermiso);
                if (index > -1) {
                    cambiosPorRol[idRol].asignar.splice(index, 1);
                }
            }
            
            // Mostrar/ocultar botón de guardar
            const btnGuardar = document.querySelector(`.btn-guardar-cambios[data-rol="${idRol}"]`);
            if (btnGuardar) {
                const hayCambios = cambiosPorRol[idRol].asignar.length > 0 || 
                                   cambiosPorRol[idRol].quitar.length > 0;
                btnGuardar.style.display = hayCambios ? 'inline-block' : 'none';
                
                // Actualizar texto del botón
                const totalCambios = cambiosPorRol[idRol].asignar.length + cambiosPorRol[idRol].quitar.length;
                btnGuardar.innerHTML = `<i data-feather="save"></i> Guardar ${totalCambios} Cambio${totalCambios !== 1 ? 's' : ''}`;
                feather.replace();
            }
        });
    });
    
    // Botones "Todos"
    document.querySelectorAll('.btn-todos').forEach(btn => {
        btn.addEventListener('click', function() {
            const idRol = this.dataset.rol;
            const checkboxes = document.querySelectorAll(`input[data-rol="${idRol}"]`);
            
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change'));
                }
            });
        });
    });
    
    // Botones "Ninguno"
    document.querySelectorAll('.btn-ninguno').forEach(btn => {
        btn.addEventListener('click', function() {
            const idRol = this.dataset.rol;
            
            if (confirm('¿Está seguro de desmarcar todos los permisos de este rol?')) {
                const checkboxes = document.querySelectorAll(`input[data-rol="${idRol}"]`);
                
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        cb.checked = false;
                        cb.dispatchEvent(new Event('change'));
                    }
                });
            }
        });
    });
    
    // Botones "Guardar Cambios"
    document.querySelectorAll('.btn-guardar-cambios').forEach(btn => {
        btn.addEventListener('click', function() {
            const idRol = this.dataset.rol;
            const cambios = cambiosPorRol[idRol];
            
            if (!cambios || (cambios.asignar.length === 0 && cambios.quitar.length === 0)) {
                alert('No hay cambios para guardar');
                return;
            }
            
            const totalCambios = cambios.asignar.length + cambios.quitar.length;
            
            if (!confirm(`¿Guardar ${totalCambios} cambio${totalCambios !== 1 ? 's' : ''} para este rol?`)) {
                return;
            }
            
            // Deshabilitar botón
            const textoOriginal = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
            
            // Preparar datos
            const formData = new FormData();
            formData.append('accion', 'guardar_cambios_rol');
            formData.append('id_rol', idRol);
            formData.append('asignar', JSON.stringify(cambios.asignar));
            formData.append('quitar', JSON.stringify(cambios.quitar));
            
            // Enviar cambios
            fetch('roles_acciones.php', {
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
                    this.disabled = false;
                    this.innerHTML = textoOriginal;
                    feather.replace();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar los cambios');
                this.disabled = false;
                this.innerHTML = textoOriginal;
                feather.replace();
            });
        });
    });
    
    // Reemplazar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>