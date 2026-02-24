<?php
require_once 'config/constantes.php';
require_once 'config/conexion.php';
require_once 'includes/funciones.php';

iniciarSesion();

// Si ya está logueado, redirigir al dashboard
if (estaLogueado()) {
    redirigir('index.php');
}

$error = '';
$mensaje = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = limpiarDatos($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        try {
            $db = getDB();
            
            // Buscar usuario
            $sql = "SELECT u.*, r.nombre_rol 
                    FROM usuarios u 
                    INNER JOIN roles r ON u.id_rol = r.id_rol 
                    WHERE u.usuario = ? AND u.estado = 'activo'";
            
            $usuarioDB = $db->selectOne($sql, [$usuario]);
            
            if ($usuarioDB && verificarPassword($password, $usuarioDB['password'])) {
                // Login exitoso - Crear sesión
                $_SESSION['usuario_id'] = $usuarioDB['id_usuario'];
                $_SESSION['usuario_nombre'] = $usuarioDB['nombre_completo'];
                $_SESSION['usuario_user'] = $usuarioDB['usuario'];
                $_SESSION['usuario_email'] = $usuarioDB['email'];
                $_SESSION['rol_id'] = $usuarioDB['id_rol'];
                $_SESSION['rol_nombre'] = $usuarioDB['nombre_rol'];
                
                // Cargar permisos del usuario
                $sqlPermisos = "SELECT CONCAT(p.modulo, '.', p.accion) as permiso
                               FROM roles_permisos rp
                               INNER JOIN permisos p ON rp.id_permiso = p.id_permiso
                               WHERE rp.id_rol = ?";
                
                $permisos = $db->select($sqlPermisos, [$usuarioDB['id_rol']]);
                $_SESSION['permisos'] = array_column($permisos, 'permiso');
                
                // Actualizar último acceso
                $sqlUpdate = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?";
                $db->update($sqlUpdate, [$usuarioDB['id_usuario']]);
                
                // Registrar en auditoría
                registrarAuditoria('usuarios', 'login', 'Inicio de sesión exitoso');
                
                // Redirigir al dashboard
                redirigir('index.php');
                
            } else {
                $error = 'Usuario o contraseña incorrectos';
                
                // Registrar intento fallido
                if ($usuarioDB) {
                    $ip = obtenerIP();
                    $sqlLog = "INSERT INTO auditoria (id_usuario, modulo, accion, descripcion, ip_address) 
                              VALUES (?, 'usuarios', 'login_fallido', 'Intento de login fallido', ?)";
                    $db->insert($sqlLog, [$usuarioDB['id_usuario'], $ip]);
                }
            }
            
        } catch (Exception $e) {
            $error = 'Error al procesar la solicitud. Intente nuevamente.';
            if (MODO_DEBUG) {
                $error .= '<br>' . $e->getMessage();
            }
        }
    }
}

// Verificar si hay mensaje de sesión expirada
if (isset($_GET['sesion_expirada'])) {
    $mensaje = 'Su sesión ha expirado. Por favor inicie sesión nuevamente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo NOMBRE_SISTEMA; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 15px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3.5rem;
            margin-bottom: 15px;
        }
        
        .login-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .input-group-text {
            background: white;
            border: 2px solid #e0e0e0;
            border-right: none;
            border-radius: 8px 0 0 8px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-shop"></i>
                <h3><?php echo NOMBRE_SISTEMA; ?></h3>
                <p>Ingrese sus credenciales</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div><?php echo $mensaje; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   name="usuario" 
                                   placeholder="Ingrese su usuario"
                                   value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                                   required 
                                   autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   name="password" 
                                   placeholder="Ingrese su contraseña"
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Iniciar Sesión
                    </button>
                </form>
                
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Usuario por defecto: <strong>admin</strong> / Contraseña: <strong>admin123</strong>
                    </small>
                </div>
            </div>
            
            <div class="login-footer">
                © <?php echo date('Y'); ?> <?php echo EMPRESA; ?>. Todos los derechos reservados.
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>