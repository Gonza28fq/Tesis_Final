<?php
// =============================================
// recuperar.php (en raíz)
// Sistema de Recuperación de Contraseña
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

$paso = 1;
$mensaje = '';
$error = '';

// PASO 1: Solicitar email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar'])) {
    $email = limpiarInput($_POST['email']);
    
    if (empty($email) || !validarEmail($email)) {
        $error = 'Email no válido';
    } else {
        $db = getDB();
        $sql = "SELECT id_usuario, usuario, nombre_completo FROM usuarios WHERE email = ? AND estado = 'activo'";
        $stmt = $db->query($sql, [$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Generar token
            $token = generarToken(32);
            $expiracion = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Guardar token
            $sql_update = "UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id_usuario = ?";
            $db->execute($sql_update, [$token, $expiracion, $usuario['id_usuario']]);
            
            // Enviar email
            require_once 'includes/email.php';
            $email_service = new Email();
            $enviado = $email_service->enviarRecuperacionPassword($email, $usuario['nombre_completo'], $token);
            
            if ($enviado) {
                $mensaje = 'Se ha enviado un enlace de recuperación a tu email';
            } else {
                $error = 'Error al enviar el email. Contacta al administrador.';
            }
        } else {
            // Por seguridad, mostramos el mismo mensaje aunque el email no exista
            $mensaje = 'Si el email existe, recibirás un enlace de recuperación';
        }
    }
}

// PASO 2: Validar token y cambiar contraseña
$token = isset($_GET['token']) ? limpiarInput($_GET['token']) : '';

if (!empty($token)) {
    $db = getDB();
    $sql = "SELECT id_usuario, usuario, nombre_completo FROM usuarios 
            WHERE token_recuperacion = ? AND token_expiracion > NOW() AND estado = 'activo'";
    $stmt = $db->query($sql, [$token]);
    $usuario_token = $stmt->fetch();
    
    if ($usuario_token) {
        $paso = 2;
        
        // Procesar cambio de contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar'])) {
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            
            if (empty($password) || strlen($password) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres';
            } elseif ($password !== $password_confirm) {
                $error = 'Las contraseñas no coinciden';
            } else {
                // Cambiar contraseña
                $password_hash = encriptarPassword($password);
                $sql_update = "UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expiracion = NULL 
                              WHERE id_usuario = ?";
                $db->execute($sql_update, [$password_hash, $usuario_token['id_usuario']]);
                
                registrarAuditoria('usuarios', 'recuperar_password', "Usuario: {$usuario_token['usuario']}");
                
                $paso = 3;
                $mensaje = 'Contraseña cambiada exitosamente';
            }
        }
    } else {
        $error = 'El enlace es inválido o ha expirado';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo NOMBRE_SISTEMA; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card-recuperar {
            max-width: 450px;
            margin: 0 auto;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card card-recuperar">
            <div class="card-header bg-primary text-white text-center py-4">
                <i data-feather="lock" width="48" height="48"></i>
                <h4 class="mt-2">Recuperar Contraseña</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    <i data-feather="check-circle"></i> <?php echo $mensaje; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i data-feather="alert-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($paso == 1): ?>
                <!-- PASO 1: Solicitar email -->
                <p class="text-muted">Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required autofocus>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="solicitar" class="btn btn-primary">
                            <i data-feather="send"></i> Enviar Enlace
                        </button>
                        <a href="index.php" class="btn btn-link">Volver </a>
                    </div>
                </form>
                
                <?php elseif ($paso == 2): ?>
                <!-- PASO 2: Cambiar contraseña -->
                <p class="text-muted">Hola <strong><?php echo $usuario_token['nombre_completo']; ?></strong>, ingresa tu nueva contraseña.</p>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" name="password" id="password" minlength="6" required autofocus>
                        <small class="text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" name="password_confirm" required>
                    </div>
                    
                    <div id="password-strength" class="mb-3" style="display: none;">
                        <small>Fortaleza: <span id="strength-text"></span></small>
                        <div class="progress" style="height: 5px;">
                            <div id="strength-bar" class="progress-bar"></div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="cambiar" class="btn btn-primary">
                            <i data-feather="check"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
                
                <?php elseif ($paso == 3): ?>
                <!-- PASO 3: Éxito -->
                <div class="text-center py-4">
                    <i data-feather="check-circle" width="64" height="64" class="text-success"></i>
                    <h5 class="mt-3">¡Contraseña Cambiada!</h5>
                    <p class="text-muted">Ya puedes iniciar sesión con tu nueva contraseña.</p>
                    <a href="login.php" class="btn btn-primary mt-3">
                        <i data-feather="log-in"></i> Ir al Login
                    </a>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <script>
        feather.replace();
        
        // Indicador de fortaleza
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
                    text = 'Débil'; color = 'bg-danger'; percent = 20;
                } else if (strength === 2) {
                    text = 'Regular'; color = 'bg-warning'; percent = 40;
                } else if (strength === 3) {
                    text = 'Buena'; color = 'bg-info'; percent = 60;
                } else if (strength === 4) {
                    text = 'Fuerte'; color = 'bg-success'; percent = 80;
                } else {
                    text = 'Muy Fuerte'; color = 'bg-success'; percent = 100;
                }
                
                strengthText.textContent = text;
                strengthBar.className = 'progress-bar ' + color;
                strengthBar.style.width = percent + '%';
            });
        }
    </script>
</body>
</html>