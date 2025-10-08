<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('usuarios_gestionar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$id_vendedor = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errores = [];
$usuario = null;

// Obtener datos del usuario
try {
    $db = getDB();
    $sql = "SELECT * FROM Vendedores WHERE id_vendedor = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id_vendedor]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: index.php?error=usuario_no_encontrado');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}

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
    if ($datos['id_rol'] <= 0) $errores[] = "Debe seleccionar un rol";
    
    // Validar contraseña solo si se ingresó una nueva
    if (!empty($datos['contraseña'])) {
        if ($datos['contraseña'] !== $datos['contraseña_confirm']) {
            $errores[] = "Las contraseñas no coinciden";
        }
        if (strlen($datos['contraseña']) < 6) {
            $errores[] = "La contraseña debe tener al menos 6 caracteres";
        }
    }
    
    if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El email no es válido";
    }

    // Verificar usuario único (excepto el actual)
    if (empty($errores) && $datos['usuario'] != $usuario['usuario']) {
        try {
            $sqlCheck = "SELECT COUNT(*) FROM Vendedores WHERE usuario = :usuario AND id_vendedor != :id";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute([':usuario' => $datos['usuario'], ':id' => $id_vendedor]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = "El nombre de usuario ya existe";
            }
        } catch (PDOException $e) {
            $errores[] = "Error al verificar el usuario";
        }
    }

    // Actualizar si no hay errores
    if (empty($errores)) {
        try {
            if (!empty($datos['contraseña'])) {
                // Actualizar con nueva contraseña
                $sql = "UPDATE Vendedores SET 
                        nombre = :nombre,
                        apellido = :apellido,
                        usuario = :usuario,
                        contraseña = :contraseña,
                        email = :email,
                        id_rol = :rol,
                        estado = :estado
                        WHERE id_vendedor = :id";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':nombre' => $datos['nombre'],
                    ':apellido' => $datos['apellido'],
                    ':usuario' => $datos['usuario'],
                    ':contraseña' => password_hash($datos['contraseña'], PASSWORD_DEFAULT),
                    ':email' => $datos['email'],
                    ':rol' => $datos['id_rol'],
                    ':estado' => $datos['estado'],
                    ':id' => $id_vendedor
                ]);
            } else {
                // Actualizar sin cambiar contraseña
                $sql = "UPDATE Vendedores SET 
                        nombre = :nombre,
                        apellido = :apellido,
                        usuario = :usuario,
                        email = :email,
                        id_rol = :rol,
                        estado = :estado
                        WHERE id_vendedor = :id";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':nombre' => $datos['nombre'],
                    ':apellido' => $datos['apellido'],
                    ':usuario' => $datos['usuario'],
                    ':email' => $datos['email'],
                    ':rol' => $datos['id_rol'],
                    ':estado' => $datos['estado'],
                    ':id' => $id_vendedor
                ]);
            }

            header('Location: index.php?success=updated');
            exit;
        } catch (PDOException $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            $errores[] = "Error al actualizar el usuario";
        }
    } else {
        // Mantener datos ingresados
        foreach ($datos as $key => $value) {
            $usuario[$key] = $value;
        }
    }
}

// Obtener roles
try {
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
    <title>Editar Usuario - Sistema de Gestión</title>
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

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #f56565;
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

        .alert-info {
            background: #bee3f8;
            border: 2px solid #90cdf4;
            color: #2c5282;
        }

        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 700;
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
            justify-content: space-between;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .danger-zone {
            background: #fff5f5;
            border: 2px solid #feb2b2;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .danger-zone h3 {
            color: #c53030;
            margin-bottom: 10px;
        }

        .danger-zone p {
            color: #742a2a;
            margin-bottom: 15px;
            font-size: 14px;
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
            <h1>✏️ Editar Usuario</h1>
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

            <?php if ($usuario['usuario'] === 'admin'): ?>
                <div class="alert alert-info">
                    <strong>ℹ️ Información:</strong> Este es el usuario administrador principal. No puede ser eliminado.
                </div>
            <?php endif; ?>

            <!-- Info Card -->
            <div class="info-card">
                <div class="info-item">
                    <div class="info-label">Usuario</div>
                    <div class="info-value"><?php echo htmlspecialchars($usuario['usuario']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Último Acceso</div>
                    <div class="info-value"><?php echo $usuario['fecha_ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['fecha_ultimo_acceso'])) : 'Nunca'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Fecha Creación</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?></div>
                </div>
            </div>

            <form method="POST" action="">
                <!-- Datos Personales -->
                <div class="form-section">
                    <div class="form-section-title">👤 Datos Personales</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre <span class="required">*</span></label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Apellido <span class="required">*</span></label>
                            <input type="text" name="apellido" value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                        </div>

                        <div class="form-group full-width">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" placeholder="usuario@ejemplo.com">
                        </div>
                    </div>
                </div>

                <!-- Credenciales -->
                <div class="form-section">
                    <div class="form-section-title">🔐 Credenciales de Acceso</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombre de Usuario <span class="required">*</span></label>
                            <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Nueva Contraseña</label>
                            <input type="password" name="contraseña" minlength="6" placeholder="Dejar en blanco para no cambiar">
                            <small style="color: #718096; margin-top: 5px;">Solo completar si desea cambiar la contraseña</small>
                        </div>

                        <div class="form-group">
                            <label>Confirmar Nueva Contraseña</label>
                            <input type="password" name="contraseña_confirm" minlength="6">
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
                                    <option value="<?php echo $rol['id_rol']; ?>" <?php echo $usuario['id_rol'] == $rol['id_rol'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rol['nombre_rol']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="margin-bottom: 15px;">Estado</label>
                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="estado" id="estado" value="1" <?php echo $usuario['estado'] ? 'checked' : ''; ?>>
                                <label for="estado" style="margin: 0;">Usuario activo</label>
                            </div>
                            <small style="color: #718096; margin-top: 5px;">Los usuarios inactivos no pueden iniciar sesión</small>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
                </div>
            </form>

            <!-- Zona de Peligro -->
            <?php if ($usuario['usuario'] !== 'admin'): ?>
                <div class="danger-zone">
                    <h3>⚠️ Zona de Peligro</h3>
                    <p>
                        Al eliminar este usuario, se perderá todo el acceso al sistema.
                        Esta acción no se puede deshacer.
                    </p>
                    <a href="eliminar.php?id=<?php echo $id_vendedor; ?>" class="btn btn-danger" onclick="return confirm('¿Está seguro de que desea eliminar este usuario?\n\nEsta acción NO se puede deshacer.')">
                        🗑️ Eliminar Usuario
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>