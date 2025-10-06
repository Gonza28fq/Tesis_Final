<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['id_vendedor']) && isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/conexion.php';

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $usuario = sanitize($_POST['usuario']);
    $password = $_POST['password'];
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        try {
            $db = getDB();
            
            // Buscar usuario
            $sql = "SELECT v.*, r.nombre_rol 
                    FROM Vendedores v
                    INNER JOIN Roles r ON v.id_rol = r.id_rol
                    WHERE v.usuario = :usuario AND v.estado = 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':usuario' => $usuario]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['contraseña'])) {
                // Login exitoso
                
                // Obtener permisos del usuario
                $sqlPermisos = "SELECT p.nombre_permiso 
                               FROM Rol_Permisos rp
                               INNER JOIN Permisos p ON rp.id_permiso = p.id_permiso
                               WHERE rp.id_rol = :id_rol";
                $stmtPermisos = $db->prepare($sqlPermisos);
                $stmtPermisos->execute([':id_rol' => $user['id_rol']]);
                $permisos = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);
                
                // Guardar datos en sesión
                $_SESSION['id_vendedor'] = $user['id_vendedor'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['apellido'] = $user['apellido'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['rol'] = $user['nombre_rol'];
                $_SESSION['id_rol'] = $user['id_rol'];
                $_SESSION['permisos'] = $permisos;
                
                // Actualizar último acceso
                $sqlUpdate = "UPDATE Vendedores SET fecha_ultimo_acceso = NOW() WHERE id_vendedor = :id";
                $stmtUpdate = $db->prepare($sqlUpdate);
                $stmtUpdate->execute([':id' => $user['id_vendedor']]);
                
                // Registrar en auditoría
                registrarAuditoria($user['id_vendedor'], 'login_exitoso');
                
                // Redirigir al dashboard
                header('Location: index.php');
                exit;
                
            } else {
                $error = 'Usuario o contraseña incorrectos';
                
                // Registrar intento fallido si el usuario existe
                if ($user) {
                    registrarAuditoria($user['id_vendedor'], 'login_fallido');
                }
            }
            
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error en el servidor. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gestión Comercial</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-left h1 {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .login-left p {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .login-right {
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #718096;
            font-size: 14px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: shake 0.5s;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 2px solid #fc8181;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 2px solid #9ae6b4;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
            font-size: 18px;
            user-select: none;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #718096;
            font-size: 13px;
        }

        .demo-credentials {
            background: #edf2f7;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 13px;
            color: #4a5568;
        }

        .demo-credentials strong {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
            }

            .login-left {
                display: none;
            }

            .login-right {
                padding: 40px 30px;
            }
        }

        .loading {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        .btn-login.loading .loading {
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Lado izquierdo - Información -->
        <div class="login-left">
            <h1>🏪 Sistema de Gestión Comercial</h1>
            <p>Administra tu negocio de forma eficiente con nuestro sistema integral de gestión.</p>
            
            <div class="feature">
                <div class="feature-icon">💰</div>
                <div>
                    <strong>Gestión de Ventas</strong><br>
                    Control completo de tus operaciones
                </div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">📦</div>
                <div>
                    <strong>Control de Stock</strong><br>
                    Inventario actualizado en tiempo real
                </div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">👥</div>
                <div>
                    <strong>Gestión de Clientes</strong><br>
                    Base de datos completa de clientes
                </div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">📊</div>
                <div>
                    <strong>Reportes Detallados</strong><br>
                    Análisis y estadísticas de tu negocio
                </div>
            </div>
        </div>

        <!-- Lado derecho - Formulario -->
        <div class="login-right">
            <div class="login-header">
                <h2>Iniciar Sesión</h2>
                <p>Ingresa tus credenciales para acceder</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✓ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input 
                            type="text" 
                            id="usuario" 
                            name="usuario" 
                            placeholder="Ingrese su usuario"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Ingrese su contraseña"
                            required
                        >
                        <span class="password-toggle" onclick="togglePassword()">👁️</span>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login" id="btnLogin">
                    <span class="btn-text">Iniciar Sesión</span>
                    <div class="loading"></div>
                </button>
            </form>

            <div class="demo-credentials">
                <strong>🔑 Credenciales de Prueba:</strong>
                <strong>Usuario:</strong> admin<br>
                <strong>Contraseña:</strong> admin123
            </div>

            <div class="login-footer">
                Sistema de Gestión Comercial v1.0<br>
                © 2025 - Todos los derechos reservados
            </div>
        </div>
    </div>

    <script>
        // Mostrar/ocultar contraseña
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggle = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggle.textContent = '👁️‍🗨️';
            } else {
                passwordInput.type = 'password';
                toggle.textContent = '👁️';
            }
        }

        // Animación de loading al enviar
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnLogin');
            btn.classList.add('loading');
        });

        // Enter en usuario pasa a contraseña
        document.getElementById('usuario').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>