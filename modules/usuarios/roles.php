<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('usuarios_gestionar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$mensaje = '';
$errores = [];

// Procesar asignación de permisos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_rol'])) {
    $id_rol = intval($_POST['id_rol']);
    $permisos = $_POST['permisos'] ?? [];
    
    try {
        $db = getDB();
        
        // Eliminar permisos actuales del rol
        $sqlDelete = "DELETE FROM Rol_Permisos WHERE id_rol = :rol";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->execute([':rol' => $id_rol]);
        
        // Insertar nuevos permisos
        if (!empty($permisos)) {
            $sqlInsert = "INSERT INTO Rol_Permisos (id_rol, id_permiso) VALUES (:rol, :permiso)";
            $stmtInsert = $db->prepare($sqlInsert);
            
            foreach ($permisos as $id_permiso) {
                $stmtInsert->execute([
                    ':rol' => $id_rol,
                    ':permiso' => intval($id_permiso)
                ]);
            }
        }
        
        $mensaje = 'Permisos actualizados correctamente';
        
    } catch (PDOException $e) {
        error_log("Error al actualizar permisos: " . $e->getMessage());
        $errores[] = "Error al actualizar permisos";
    }
}

// Obtener roles
try {
    $db = getDB();
    
    $sqlRoles = "SELECT * FROM Roles ORDER BY nombre_rol";
    $stmtRoles = $db->query($sqlRoles);
    $roles = $stmtRoles->fetchAll();
    
    // Obtener todos los permisos agrupados por módulo
    $sqlPermisos = "SELECT * FROM Permisos ORDER BY modulo, nombre_permiso";
    $stmtPermisos = $db->query($sqlPermisos);
    $permisos = $stmtPermisos->fetchAll();
    
    // Agrupar permisos por módulo
    $permisosPorModulo = [];
    foreach ($permisos as $permiso) {
        $permisosPorModulo[$permiso['modulo']][] = $permiso;
    }
    
    // Obtener permisos de cada rol
    $permisosPorRol = [];
    foreach ($roles as $rol) {
        $sqlRolPermisos = "SELECT id_permiso FROM Rol_Permisos WHERE id_rol = :rol";
        $stmtRolPermisos = $db->prepare($sqlRolPermisos);
        $stmtRolPermisos->execute([':rol' => $rol['id_rol']]);
        $permisosPorRol[$rol['id_rol']] = $stmtRolPermisos->fetchAll(PDO::FETCH_COLUMN);
    }
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $roles = [];
    $permisos = [];
    $permisosPorModulo = [];
    $permisosPorRol = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles y Permisos - Sistema de Gestión</title>
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
            max-width: 1200px;
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

        .alert-success {
            background: #c6f6d5;
            border: 2px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: #fed7d7;
            border: 2px solid #fc8181;
            color: #742a2a;
        }

        .alert-info {
            background: #bee3f8;
            border: 2px solid #90cdf4;
            color: #2c5282;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }

        .roles-list {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e2e8f0;
        }

        .role-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .role-item:hover {
            background: white;
        }

        .role-item.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .role-name {
            font-weight: 600;
            font-size: 14px;
        }

        .role-desc {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 3px;
        }

        .permisos-container {
            background: #f7fafc;
            border-radius: 12px;
            padding: 25px;
            border: 2px solid #e2e8f0;
        }

        .permisos-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .modulo-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 2px solid #e2e8f0;
        }

        .modulo-title {
            font-size: 16px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .permisos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }

        .permiso-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .permiso-item:hover {
            background: #f7fafc;
        }

        .permiso-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .permiso-item label {
            cursor: pointer;
            font-size: 14px;
            flex: 1;
        }

        .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: right;
        }

        .no-role-selected {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .roles-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Roles y Permisos</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <?php if ($mensaje): ?>
                <div class="alert alert-success">
                    ✓ <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Errores:</strong>
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <strong>ℹ️ Información:</strong> Seleccione un rol para gestionar sus permisos. Los cambios se aplican inmediatamente al guardar.
            </div>

            <div class="roles-grid">
                <!-- Lista de Roles -->
                <div class="roles-list">
                    <h3 style="margin-bottom: 15px; color: #2d3748;">📋 Roles del Sistema</h3>
                    <?php foreach ($roles as $rol): ?>
                        <div class="role-item" onclick="seleccionarRol(<?php echo $rol['id_rol']; ?>)" id="role-<?php echo $rol['id_rol']; ?>">
                            <div class="role-name"><?php echo htmlspecialchars($rol['nombre_rol']); ?></div>
                            <div class="role-desc"><?php echo htmlspecialchars($rol['descripcion']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Permisos del Rol Seleccionado -->
                <div class="permisos-container">
                    <?php foreach ($roles as $rol): ?>
                        <form method="POST" class="permisos-form" id="form-<?php echo $rol['id_rol']; ?>" style="display: none;">
                            <input type="hidden" name="id_rol" value="<?php echo $rol['id_rol']; ?>">
                            
                            <div class="permisos-title">
                                Permisos para: <strong><?php echo htmlspecialchars($rol['nombre_rol']); ?></strong>
                            </div>

                            <?php foreach ($permisosPorModulo as $modulo => $permisosModulo): ?>
                                <div class="modulo-section">
                                    <div class="modulo-title">
                                        📦 <?php echo ucfirst($modulo); ?>
                                    </div>
                                    <div class="permisos-grid">
                                        <?php foreach ($permisosModulo as $permiso): ?>
                                            <div class="permiso-item">
                                                <input 
                                                    type="checkbox" 
                                                    name="permisos[]" 
                                                    value="<?php echo $permiso['id_permiso']; ?>"
                                                    id="permiso-<?php echo $rol['id_rol']; ?>-<?php echo $permiso['id_permiso']; ?>"
                                                    <?php echo in_array($permiso['id_permiso'], $permisosPorRol[$rol['id_rol']]) ? 'checked' : ''; ?>
                                                >
                                                <label for="permiso-<?php echo $rol['id_rol']; ?>-<?php echo $permiso['id_permiso']; ?>">
                                                    <?php echo htmlspecialchars($permiso['descripcion']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">💾 Guardar Permisos</button>
                            </div>
                        </form>
                    <?php endforeach; ?>

                    <div class="no-role-selected" id="no-role-selected">
                        <h3>Seleccione un rol</h3>
                        <p>Haga clic en un rol de la izquierda para gestionar sus permisos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function seleccionarRol(idRol) {
            // Ocultar todos los formularios
            document.querySelectorAll('.permisos-form').forEach(form => {
                form.style.display = 'none';
            });
            
            // Ocultar mensaje de "no seleccionado"
            document.getElementById('no-role-selected').style.display = 'none';
            
            // Mostrar formulario del rol seleccionado
            document.getElementById('form-' + idRol).style.display = 'block';
            
            // Marcar rol como activo
            document.querySelectorAll('.role-item').forEach(item => {
                item.classList.remove('active');
            });
            document.getElementById('role-' + idRol).classList.add('active');
        }
    </script>
</body>
</html>