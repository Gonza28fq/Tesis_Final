<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('usuarios_gestionar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$errores = [];
$datos = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombre' => sanitize($_POST['nombre'] ?? ''),
        'apellido' => sanitize($_POST['apellido'] ?? ''),
        'usuario' => sanitize($_POST['usuario'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'contraseña' => $_POST['contraseña'] ?? '',
        'contraseña_confirm' => $_POST['contraseña_confirm'] ?? '',
        'id_rol' => intval($_POST['id_rol'] ?? 0),
        'estado' => isset($_POST['estado']) ? 1 : 0
    ];

    // Validaciones
    if (empty($datos['nombre'])) $errores[] = "El nombre es obligatorio";
    if (empty($datos['apellido'])) $errores[] = "El apellido es obligatorio";
    if (empty($datos['usuario'])) $errores[] = "El usuario es obligatorio";
    if (empty($datos['contraseña'])) $errores[] = "La contraseña es obligatoria";
    if ($datos['contraseña'] !== $datos['contraseña_confirm']) $errores[] = "Las contraseñas no coinciden";
    if (strlen($datos['contraseña']) < 6) $errores[] = "La contraseña debe tener al menos 6 caracteres";
    if ($datos['id_rol'] <= 0) $errores[] = "Debe seleccionar un rol";
    
    if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email no es válido";
    }

    // Verificar usuario único
    if (empty($errores)) {
        try {
            $db = getDB();
            $sqlCheck = "SELECT COUNT(*) FROM Vendedores WHERE usuario = :usuario";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute([':usuario' => $datos['usuario']]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = "El nombre de usuario ya existe";
            }
        } catch (PDOException $e) {
            $errores[] = "Error al verificar el usuario";
        }
    }

    // Insertar si no hay errores
    if (empty($errores)) {
        try {
            // Preparar el hash de la contraseña
            $password_hash = password_hash($datos['contraseña'], PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO Vendedores (nombre, apellido, usuario, contraseña, email, id_rol, estado) 
                    VALUES (:nombre, :apellido, :usuario, :password, :email, :rol, :estado)";
            
            $stmt = $db->prepare($sql);
            $resultado = $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':usuario' => $datos['usuario'],
                ':password' => $password_hash,
                ':email' => $datos['email'] ?: null,
                ':rol' => $datos['id_rol'],
                ':estado' => $datos['estado']
            ]);

            if ($resultado) {
                header('Location: index.php?success=created');
                exit;
            } else {
                $errores[] = "Error al guardar el usuario en la base de datos";
            }
        } catch (PDOException $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            $errores[] = "Error al guardar el usuario: " . $e->getMessage();
        }
    }
}

// Obtener roles
try {
    $db = getDB();
    $sqlRoles = "SELECT * FROM Roles ORDER BY nombre_rol";
    $stmtRoles = $db->query($sqlRoles);
    $roles = $stmtRoles->fetchAll();
} catch (PDOException $e) {
    $roles = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario - Sistema de Gestión</title>
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fed7d7;
            border: 2px solid #fc8181;
            color: #742a2a;
        }

        .alert-error ul {
            margin-left: 20px;
            margin-top: 10px;
        }

        .form-section {
            background: #f7fafc;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #4a5568;
        }

        .form-group .required {
            color: #e53e3e;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Nuevo Usuario</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <?php if (!empty($errores)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Por favor corrija los siguientes errores:</strong>
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Datos Personales -->
                <div class="form-section">
                    <div class="form-section-title">👤 Datos Personales</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre <span class="required">*</span></label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($datos['nombre'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Apellido <span class="required">*</span></label>
                            <input type="text" name="apellido" value="<?php echo htmlspecialchars($datos['apellido'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group full-width">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($datos['email'] ?? ''); ?>" placeholder="usuario@ejemplo.com">
                        </div>
                    </div>
                </div>

                <!-- Credenciales -->
                <div class="form-section">
                    <div class="form-section-title">🔐 Credenciales de Acceso</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombre de Usuario <span class="required">*</span></label>
                            <input type="text" name="usuario" value="<?php echo htmlspecialchars($datos['usuario'] ?? ''); ?>" required>
                            <small style="color: #718096; margin-top: 5px;">Debe ser único en el sistema</small>
                        </div>

                        <div class="form-group">
                            <label>Contraseña <span class="required">*</span></label>
                            <input type="password" name="contraseña" id="password" required minlength="6">
                            <small class="password-strength" id="strength"></small>
                        </div>

                        <div class="form-group">
                            <label>Confirmar Contraseña <span class="required">*</span></label>
                            <input type="password" name="contraseña_confirm" required minlength="6">
                        </div>
                    </div>
                </div>

                <!-- Rol y Estado -->
                <div class="form-section">
                    <div class="form-section-title">⚙️ Configuración</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Rol <span class="required">*</span></label>
                            <select name="id_rol" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo $rol['id_rol']; ?>" <?php echo (isset($datos['id_rol']) && $datos['id_rol'] == $rol['id_rol']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #718096; margin-top: 5px;">Define los permisos del usuario</small>
                        </div>

                        <div class="form-group">
                            <label style="margin-bottom: 15px;">Estado</label>
                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="estado" id="estado" value="1" <?php echo (!isset($datos['estado']) || $datos['estado']) ? 'checked' : ''; ?>>
                                <label for="estado" style="margin: 0;">Usuario activo</label>
                            </div>
                            <small style="color: #718096; margin-top: 5px;">Los usuarios inactivos no pueden iniciar sesión</small>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">💾 Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Validar fortaleza de contraseña
        const passwordInput = document.getElementById('password');
        const strengthDisplay = document.getElementById('strength');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let message = '';
            let color = '';

            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;

            if (password.length === 0) {
                message = '';
            } else if (strength <= 2) {
                message = '⚠️ Contraseña débil';
                color = '#f56565';
            } else if (strength <= 3) {
                message = '⚡ Contraseña media';
                color = '#ed8936';
            } else {
                message = '✓ Contraseña fuerte';
                color = '#48bb78';
            }

            strengthDisplay.textContent = message;
            strengthDisplay.style.color = color;
        });
    </script>
</body>
</html>