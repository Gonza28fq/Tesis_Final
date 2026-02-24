<?php
// =============================================
// modules/usuarios/nuevo.php
// Formulario para Crear Usuario
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

$es_edicion = basename($_SERVER['PHP_SELF']) == 'editar.php';

if ($es_edicion) {
    requierePermiso('usuarios', 'editar');
    $titulo_pagina = 'Editar Usuario';
    $id_usuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id_usuario == 0) {
        setAlerta('error', 'Usuario no válido');
        redirigir('index.php');
    }
} else {
    requierePermiso('usuarios', 'crear');
    $titulo_pagina = 'Nuevo Usuario';
    $id_usuario = 0;
}

$db = getDB();
$pdo = $db->getConexion();
$errores = [];
$usuario = [
    'usuario' => '',
    'nombre_completo' => '',
    'email' => '',
    'telefono' => '',
    'dni' => '',
    'id_rol' => 0,
    'estado' => 'activo'
];

// Si es edición, cargar datos
if ($es_edicion) {
    $sql = "SELECT * FROM usuarios WHERE id_usuario = ?";
    $stmt = $db->query($sql, [$id_usuario]);
    $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario_db) {
        setAlerta('error', 'Usuario no encontrado');
        redirigir('index.php');
    }
    
    $usuario = $usuario_db;
}

// Obtener roles
$roles = $db->query("SELECT * FROM roles WHERE estado = 'activo' ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario['usuario'] = limpiarInput($_POST['usuario']);
    $usuario['nombre_completo'] = limpiarInput($_POST['nombre_completo']);
    $usuario['email'] = limpiarInput($_POST['email']);
    $usuario['telefono'] = limpiarInput($_POST['telefono']);
    $usuario['dni'] = limpiarInput($_POST['dni']);
    $usuario['id_rol'] = (int)$_POST['id_rol'];
    $usuario['estado'] = $_POST['estado'];
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validaciones
    if (empty($usuario['usuario'])) {
        $errores[] = 'El nombre de usuario es obligatorio';
    } elseif (strlen($usuario['usuario']) < 4) {
        $errores[] = 'El nombre de usuario debe tener al menos 4 caracteres';
    }
    elseif (strlen($usuario['usuario']) > 50) {
    $errores[] = 'El nombre de usuario no puede tener más de 50 caracteres';
    } 
    
    if (empty($usuario['nombre_completo'])) {
        $errores[] = 'El nombre completo es obligatorio';
    }
    
    if (empty($usuario['email']) || !filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido';
    }
    
    if (empty($usuario['telefono'])) {
        $errores[] = 'El teléfono es obligatorio';
    } elseif (!preg_match('/^[0-9+\-\s()]{8,20}$/', $usuario['telefono'])) {
        $errores[] = 'El formato del teléfono no es válido';
    }
    
    if (empty($usuario['dni'])) {
        $errores[] = 'El DNI es obligatorio';
    } elseif (!preg_match('/^[0-9]{7,8}$/', $usuario['dni'])) {
        $errores[] = 'El DNI debe tener entre 7 y 8 dígitos';
    }
    
    if ($usuario['id_rol'] == 0) {
        $errores[] = 'Debe seleccionar un rol';
    }
    
    // Validar contraseña
    if (!$es_edicion) {
        if (empty($password)) {
            $errores[] = 'La contraseña es obligatoria';
        } elseif (strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres';
        } elseif ($password !== $password_confirm) {
            $errores[] = 'Las contraseñas no coinciden';
        }
    } else {
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errores[] = 'La contraseña debe tener al menos 6 caracteres';
            } elseif ($password !== $password_confirm) {
                $errores[] = 'Las contraseñas no coinciden';
            }
        }
    }
    
    // Verificar usuario único
    if ($es_edicion) {
        $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = ? AND id_usuario != ?";
        $stmt = $db->query($sql_check, [$usuario['usuario'], $id_usuario]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    } else {
        $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = ?";
        $stmt = $db->query($sql_check, [$usuario['usuario']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    }
    
    if ($existe > 0) {
        $errores[] = 'El nombre de usuario ya existe';
    }
    
    // Verificar email único
    if ($es_edicion) {
        $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE email = ? AND id_usuario != ?";
        $stmt = $db->query($sql_check, [$usuario['email'], $id_usuario]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    } else {
        $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE email = ?";
        $stmt = $db->query($sql_check, [$usuario['email']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    }
    
    if ($existe > 0) {
        $errores[] = 'El email ya está registrado';
    }
    
    // Verificar DNI único
    if ($es_edicion) {
        $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE dni = ? AND id_usuario != ?";
        $stmt = $db->query($sql_check, [$usuario['dni'], $id_usuario]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    } else {
        $sql_check = "SELECT COUNT(*) as total FROM usuarios WHERE dni = ?";
        $stmt = $db->query($sql_check, [$usuario['dni']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $existe = $resultado['total'];
    }
    
    if ($existe > 0) {
        $errores[] = 'El DNI ya está registrado';
    }
    
    // Si no hay errores, guardar
    if (empty($errores)) {
        try {
            if ($es_edicion) {
                // Actualizar usuario
                if (!empty($password)) {
                    // Con cambio de contraseña
                    $sql = "UPDATE usuarios SET 
                            usuario = ?, nombre_completo = ?, email = ?, telefono = ?, dni = ?, id_rol = ?,
                            password = ?, estado = ?
                            WHERE id_usuario = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $usuario['usuario'], $usuario['nombre_completo'], $usuario['email'],
                        $usuario['telefono'], $usuario['dni'], $usuario['id_rol'], 
                        password_hash($password, PASSWORD_DEFAULT), $usuario['estado'],
                        $id_usuario
                    ]);
                } else {
                    // Sin cambio de contraseña
                    $sql = "UPDATE usuarios SET 
                            usuario = ?, nombre_completo = ?, email = ?, telefono = ?, dni = ?, id_rol = ?, estado = ?
                            WHERE id_usuario = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $usuario['usuario'], $usuario['nombre_completo'], $usuario['email'],
                        $usuario['telefono'], $usuario['dni'], $usuario['id_rol'], $usuario['estado'],
                        $id_usuario
                    ]);
                }
                
                $usuario_id = $id_usuario;
                $mensaje = 'Usuario actualizado correctamente';
                $accion_auditoria = 'actualizar_usuario';
                
            } else {
                // Insertar nuevo usuario
                $sql = "INSERT INTO usuarios (usuario, password, nombre_completo, email, telefono, dni, id_rol, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $usuario['usuario'], password_hash($password, PASSWORD_DEFAULT), $usuario['nombre_completo'],
                    $usuario['email'], $usuario['telefono'], $usuario['dni'], $usuario['id_rol'], $usuario['estado']
                ]);
                
                $usuario_id = $pdo->lastInsertId();
                $mensaje = 'Usuario creado correctamente';
                $accion_auditoria = 'crear_usuario';
            }
            
            registrarAuditoria('usuarios', $accion_auditoria, 
                "Usuario: {$usuario['usuario']} (ID: $usuario_id)");
            
            setAlerta('success', $mensaje);
            redirigir('index.php');
            
        } catch (Exception $e) {
            $errores[] = 'Error al guardar el usuario: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>
        <i data-feather="user"></i> 
        <?php echo $es_edicion ? 'Editar Usuario' : 'Nuevo Usuario'; ?>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
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

<div class="row">
    <div class="col-md-8 mx-auto">
        <form method="POST" action="" id="form-usuario">
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="user"></i> Información del Usuario
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="usuario" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required <?php echo $es_edicion && $id_usuario == 1 ? 'readonly' : ''; ?>>
                            <small class="text-muted">Nombre para iniciar sesión (min. 4 caracteres)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>" required placeholder="Ej: 3814567890">
                            <small class="text-muted">Solo números (8-20 caracteres)</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DNI <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="dni" value="<?php echo htmlspecialchars($usuario['dni']); ?>" required pattern="[0-9]{7,8}" placeholder="Ej: 12345678">
                            <small class="text-muted">7 u 8 dígitos sin puntos</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_rol" required <?php echo $es_edicion && $id_usuario == 1 ? 'disabled' : ''; ?>>
                                <option value="0">Seleccione un rol...</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?php echo $rol['id_rol']; ?>" <?php echo $usuario['id_rol'] == $rol['id_rol'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $rol['nombre_rol'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($es_edicion && $id_usuario == 1): ?>
                            <input type="hidden" name="id_rol" value="1">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado" <?php echo $es_edicion && $id_usuario == 1 ? 'disabled' : ''; ?>>
                            <option value="activo" <?php echo $usuario['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $usuario['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                        <?php if ($es_edicion && $id_usuario == 1): ?>
                        <input type="hidden" name="estado" value="activo">
                        <small class="text-muted">El usuario administrador no puede ser desactivado</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="lock"></i> Contraseña
                </div>
                <div class="card-body">
                    <?php if ($es_edicion): ?>
                    <div class="alert alert-info">
                        <i data-feather="info" width="16"></i> Deje los campos en blanco si no desea cambiar la contraseña
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Contraseña <?php echo !$es_edicion ? '<span class="text-danger">*</span>' : ''; ?>
                            </label>
                            <input type="password" class="form-control" name="password" id="password" <?php echo !$es_edicion ? 'required' : ''; ?>>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Confirmar Contraseña <?php echo !$es_edicion ? '<span class="text-danger">*</span>' : ''; ?>
                            </label>
                            <input type="password" class="form-control" name="password_confirm" id="password_confirm" <?php echo !$es_edicion ? 'required' : ''; ?>>
                        </div>
                    </div>
                    
                    <div id="password-strength" class="mt-2" style="display: none;">
                        <small>Fortaleza: <span id="strength-text"></span></small>
                        <div class="progress" style="height: 5px;">
                            <div id="strength-bar" class="progress-bar" role="progressbar"></div>
                        </div>
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
                            <i data-feather="save"></i> <?php echo $es_edicion ? 'Actualizar' : 'Crear'; ?> Usuario
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación de DNI
    const dniInput = document.querySelector('input[name="dni"]');
    if (dniInput) {
        dniInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length > 8) {
                this.value = this.value.slice(0, 8);
            }
        });
    }
    
    // Validación de teléfono
    const telefonoInput = document.querySelector('input[name="telefono"]');
    if (telefonoInput) {
        telefonoInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
        });
    }
    
    // Validación de contraseñas
    const passwordConfirm = document.getElementById('password_confirm');
    if (passwordConfirm) {
        passwordConfirm.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Indicador de fortaleza de contraseña
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            const strengthBar = document.getElementById('strength-bar');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            strengthDiv.style.display = 'block';
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            let text, color, percent;
            
            if (strength <= 1) {
                text = 'Débil';
                color = 'bg-danger';
                percent = 20;
            } else if (strength === 2) {
                text = 'Regular';
                color = 'bg-warning';
                percent = 40;
            } else if (strength === 3) {
                text = 'Buena';
                color = 'bg-info';
                percent = 60;
            } else if (strength === 4) {
                text = 'Fuerte';
                color = 'bg-success';
                percent = 80;
            } else {
                text = 'Muy Fuerte';
                color = 'bg-success';
                percent = 100;
            }
            
            strengthText.textContent = text;
            strengthBar.className = 'progress-bar ' + color;
            strengthBar.style.width = percent + '%';
        });
    }
    
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>