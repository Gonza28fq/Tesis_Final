<?php
require_once 'config/constantes.php';
require_once 'config/conexion.php';
require_once 'includes/funciones.php';

iniciarSesion();

if (!estaLogueado()) {
    redirigir('login.php');
}

$titulo_pagina = 'Mi Perfil';
$breadcrumb = ['Mi Perfil' => 'perfil.php'];

$db = getDB();
$errores = [];
$success = '';

// Obtener datos del usuario
$sql = "SELECT u.*, r.nombre_rol 
        FROM usuarios u 
        INNER JOIN roles r ON u.id_rol = r.id_rol 
        WHERE u.id_usuario = ?";
$usuario = $db->selectOne($sql, [$_SESSION['usuario_id']]);

if (!$usuario) {
    redirigir('logout.php');
}

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    if ($_POST['accion'] === 'actualizar_datos') {
        $nombre_completo = limpiarInput($_POST['nombre_completo']);
        $email = limpiarInput($_POST['email']);
        
        // Validaciones
        if (empty($nombre_completo)) {
            $errores[] = 'El nombre completo es obligatorio';
        }
        
        if (empty($email)) {
            $errores[] = 'El email es obligatorio';
        } elseif (!validarEmail($email)) {
            $errores[] = 'El email no es válido';
        }
        
        // Verificar email único
        $sql_check = "SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?";
        $existe = $db->count("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id_usuario != ?", 
                            [$email, $_SESSION['usuario_id']]);
        
        if ($existe > 0) {
            $errores[] = 'El email ya está en uso por otro usuario';
        }
        
        if (empty($errores)) {
            try {
                $sql = "UPDATE usuarios SET nombre_completo = ?, email = ? WHERE id_usuario = ?";
                $db->execute($sql, [$nombre_completo, $email, $_SESSION['usuario_id']]);
                
                $_SESSION['usuario_nombre'] = $nombre_completo;
                $_SESSION['usuario_email'] = $email;
                
                registrarAuditoria('usuarios', 'actualizar_perfil', 'Actualización de datos de perfil');
                
                $success = 'Perfil actualizado correctamente';
                $usuario['nombre_completo'] = $nombre_completo;
                $usuario['email'] = $email;
                
            } catch (Exception $e) {
                $errores[] = 'Error al actualizar el perfil: ' . $e->getMessage();
            }
        }
    }
    
    // Cambiar contraseña
    if ($_POST['accion'] === 'cambiar_password') {
        $password_actual = $_POST['password_actual'];
        $password_nueva = $_POST['password_nueva'];
        $password_confirmar = $_POST['password_confirmar'];
        
        // Validaciones
        if (empty($password_actual)) {
            $errores[] = 'Debe ingresar su contraseña actual';
        }
        
        if (empty($password_nueva)) {
            $errores[] = 'Debe ingresar la nueva contraseña';
        } elseif (strlen($password_nueva) < 6) {
            $errores[] = 'La nueva contraseña debe tener al menos 6 caracteres';
        }
        
        if ($password_nueva !== $password_confirmar) {
            $errores[] = 'Las contraseñas nuevas no coinciden';
        }
        
        // Verificar contraseña actual
        if (empty($errores) && !verificarPassword($password_actual, $usuario['password'])) {
            $errores[] = 'La contraseña actual es incorrecta';
        }
        
        if (empty($errores)) {
            try {
                $password_hash = hashPassword($password_nueva);
                $sql = "UPDATE usuarios SET password = ? WHERE id_usuario = ?";
                $db->execute($sql, [$password_hash, $_SESSION['usuario_id']]);
                
                registrarAuditoria('usuarios', 'cambiar_password', 'Cambio de contraseña');
                
                $success = 'Contraseña actualizada correctamente';
                
            } catch (Exception $e) {
                $errores[] = 'Error al cambiar la contraseña: ' . $e->getMessage();
            }
        }
    }
}

// Obtener últimos accesos
$sql_accesos = "SELECT * FROM auditoria 
                WHERE id_usuario = ? AND accion = 'login' 
                ORDER BY fecha_hora DESC LIMIT 10";
$ultimos_accesos = $db->select($sql_accesos, [$_SESSION['usuario_id']]);

require_once 'includes/header.php';
?>

<!-- Alertas -->
<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="bi bi-exclamation-triangle"></i> Errores encontrados:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Columna Principal -->
    <div class="col-lg-8">
        <!-- Información Personal -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person"></i> Información Personal
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="actualizar_datos">
                    
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" readonly>
                        <small class="text-muted">El nombre de usuario no se puede modificar</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['nombre_rol']); ?>" readonly>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Cambiar Contraseña -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-lock"></i> Cambiar Contraseña
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="cambiar_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password_actual" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password_nueva" minlength="6" required>
                        <small class="text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nueva Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password_confirmar" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Columna Lateral -->
    <div class="col-lg-4">
        <!-- Información de Cuenta -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Información de Cuenta
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-6">Usuario:</dt>
                    <dd class="col-sm-6"><?php echo htmlspecialchars($usuario['usuario']); ?></dd>
                    
                    <dt class="col-sm-6">Rol:</dt>
                    <dd class="col-sm-6">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($usuario['nombre_rol']); ?></span>
                    </dd>
                    
                    <dt class="col-sm-6">Estado:</dt>
                    <dd class="col-sm-6">
                        <span class="badge bg-<?php echo $usuario['estado'] == 'activo' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($usuario['estado']); ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-6">Fecha Registro:</dt>
                    <dd class="col-sm-6"><?php echo formatearFecha($usuario['fecha_creacion']); ?></dd>
                    
                    <dt class="col-sm-6">Último Acceso:</dt>
                    <dd class="col-sm-6">
                        <?php echo $usuario['ultimo_acceso'] ? formatearFechaHora($usuario['ultimo_acceso']) : 'Nunca'; ?>
                    </dd>
                </dl>
            </div>
        </div>
        
        <!-- Últimos Accesos -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> Últimos Accesos
            </div>
            <div class="card-body">
                <?php if (empty($ultimos_accesos)): ?>
                    <p class="text-muted text-center mb-0">No hay registros de acceso</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ultimos_accesos as $acceso): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <i class="bi bi-calendar3 text-muted"></i>
                                    <?php echo formatearFechaHora($acceso['fecha_hora']); ?>
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt"></i> IP: <?php echo htmlspecialchars($acceso['ip_address']); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>