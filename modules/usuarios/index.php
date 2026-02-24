<?php
// =============================================
// modules/usuarios/index.php
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

requierePermiso('usuarios', 'ver');
$titulo_pagina = 'Gestión de Usuarios';
$db = getDB();

// Parámetros de búsqueda
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$rol = isset($_GET['rol']) ? (int)$_GET['rol'] : 0;
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : '';

// Construir filtros
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(u.usuario LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($rol > 0) {
    $where[] = 'u.id_rol = ?';
    $params[] = $rol;
}

if (!empty($estado)) {
    $where[] = 'u.estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);

// Consulta de usuarios
$sql = "SELECT u.*, r.nombre_rol,
        (SELECT COUNT(*) FROM auditoria WHERE id_usuario = u.id_usuario) as total_acciones
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE $where_clause
        ORDER BY u.fecha_creacion DESC";

$usuarios = $db->query($sql, $params)->fetchAll();

// Obtener roles para filtros
$roles = $db->query("SELECT * FROM roles WHERE estado = 'activo' ORDER BY nombre_rol")->fetchAll();

// Estadísticas
$total_usuarios = $db->count("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'");
$usuarios_inactivos = $db->count("SELECT COUNT(*) FROM usuarios WHERE estado = 'inactivo'");

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="users"></i> Gestión de Usuarios</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Usuarios</li>
        </ol>
    </nav>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Usuarios</h6>
                        <h3 class="mb-0"><?php echo $total_usuarios + $usuarios_inactivos; ?></h3>
                    </div>
                    <div class="text-primary">
                        <i data-feather="users" width="40" height="40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Usuarios Activos</h6>
                        <h3 class="mb-0 text-success"><?php echo $total_usuarios; ?></h3>
                    </div>
                    <div class="text-success">
                        <i data-feather="user-check" width="40" height="40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Roles Disponibles</h6>
                        <h3 class="mb-0 text-info"><?php echo count($roles); ?></h3>
                    </div>
                    <div class="text-info">
                        <i data-feather="shield" width="40" height="40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Botones de Acción -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div class="btn-group" role="group">
                <?php if (tienePermiso('usuarios', 'crear')): ?>
                <a href="nuevo.php" class="btn btn-primary">
                    <i data-feather="user-plus"></i> Nuevo Usuario
                </a>
                <?php endif; ?>
                
                <?php if (tienePermiso('roles', 'gestionar')): ?>
                <a href="roles.php" class="btn btn-warning">
                    <i data-feather="shield"></i> Roles y Permisos
                </a>
                <?php endif; ?>

                <?php if (true): // O la condición que necesites ?>
                <a href="recuperar.php" class="btn btn-secondary">
                    <i data-feather="shield"></i> Recuperar Contraseña
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (tienePermiso('auditoria', 'ver')): ?>
            <a href="auditoria.php" class="btn btn-info">
                <i data-feather="activity"></i> Ver Auditoría
            </a>
            <?php endif; ?>
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
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="buscar" placeholder="Usuario, nombre o email..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Rol</label>
                    <select class="form-select" name="rol">
                        <option value="0">Todos</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id_rol']; ?>" <?php echo $rol == $r['id_rol'] ? 'selected' : ''; ?>>
                            <?php echo $r['nombre_rol']; ?>
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
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="search"></i> Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Usuarios -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Listado de Usuarios
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Último Acceso</th>
                        <th>Estado</th>
                        <th width="150">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No se encontraron usuarios</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo $user['usuario']; ?></strong>
                                <?php if ($user['id_usuario'] == 1): ?>
                                <span class="badge bg-danger ms-1">ADMIN</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['nombre_completo']; ?></td>
                            <td>
                                <small><i data-feather="mail" width="14"></i> <?php echo $user['email']; ?></small>
                            </td>
                            <td>
                                <?php
                                $badges_roles = [
                                    'ADMINISTRADOR' => 'danger',
                                    'SUPERVISOR' => 'warning',
                                    'JEFE_VENTAS' => 'primary',
                                    'JEFE_COMPRAS' => 'info',
                                    'VENDEDOR' => 'success',
                                    'ALMACENERO' => 'secondary'
                                ];
                                $badge_class = $badges_roles[$user['nombre_rol']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo str_replace('_', ' ', $user['nombre_rol']); ?></span>
                            </td>
                            <td>
                                <?php if ($user['ultimo_acceso']): ?>
                                <small><?php echo formatearFechaHora($user['ultimo_acceso']); ?></small>
                                <?php else: ?>
                                <small class="text-muted">Nunca</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $user['id_usuario']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if (tienePermiso('usuarios', 'editar')): ?>
                                    <a href="editar.php?id=<?php echo $user['id_usuario']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (tienePermiso('usuarios', 'eliminar') && $user['id_usuario'] != 1 && $user['id_usuario'] != $_SESSION['usuario_id']): ?>
                                    <button type="button" class="btn btn-danger" title="Eliminar" onclick="eliminarUsuario(<?php echo $user['id_usuario']; ?>, '<?php echo htmlspecialchars($user['usuario']); ?>')">
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
    </div>
</div>

<?php
$custom_js = <<<'JS'
<script>
function eliminarUsuario(id, usuario) {
    if (confirm('¿Está seguro de eliminar el usuario "' + usuario + '"?\n\nEsta acción cambiará su estado a inactivo.')) {
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

feather.replace();
</script>
JS;

echo $custom_js; // ⚠️ AGREGAR ESTA LÍNEA

include '../../includes/footer.php';
?>