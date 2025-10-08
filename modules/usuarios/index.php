<?php
require_once '../../config/conexion.php';
validarSesion();

// Solo administradores pueden acceder
if (!tienePermiso('usuarios_gestionar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Obtener usuarios
try {
    $db = getDB();
    
    $sql = "SELECT 
                v.id_vendedor,
                v.nombre,
                v.apellido,
                v.usuario,
                v.email,
                v.estado,
                v.fecha_ultimo_acceso,
                v.fecha_creacion,
                r.nombre_rol
            FROM Vendedores v
            INNER JOIN Roles r ON v.id_rol = r.id_rol
            ORDER BY v.nombre";
    
    $stmt = $db->query($sql);
    $usuarios = $stmt->fetchAll();
    
    // Obtener roles
    $sqlRoles = "SELECT * FROM Roles ORDER BY nombre_rol";
    $stmtRoles = $db->query($sqlRoles);
    $roles = $stmtRoles->fetchAll();
    
    // Estadísticas
    $sqlStats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos
                 FROM Vendedores";
    $stmtStats = $db->query($sqlStats);
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $usuarios = [];
    $roles = [];
    $stats = ['total' => 0, 'activos' => 0, 'inactivos' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema de Gestión</title>
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
            max-width: 1600px;
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 10px;
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

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card.total { border-color: #667eea; }
        .stat-card.activos { border-color: #48bb78; }
        .stat-card.inactivos { border-color: #a0aec0; }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
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

        .alert-warning {
            background: #feebc8;
            border: 2px solid #f6ad55;
            color: #7c2d12;
        }

        .table-container {
            overflow-x: auto;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: #f7fafc;
            position: sticky;
            top: 0;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-activo {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-inactivo {
            background: #e2e8f0;
            color: #718096;
        }

        .badge-admin {
            background: #fbb6ce;
            color: #702459;
        }

        .badge-vendedor {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge-supervisor {
            background: #feebc8;
            color: #7c2d12;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Gestión de Usuarios</h1>
            <div class="header-actions">
                <a href="nuevo.php" class="btn btn-success">➕ Nuevo Usuario</a>
                <a href="roles.php" class="btn btn-primary">🔐 Roles y Permisos</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✓ <?php 
                        if ($_GET['success'] === 'created') echo 'Usuario creado exitosamente';
                        elseif ($_GET['success'] === 'updated') echo 'Usuario actualizado exitosamente';
                        elseif ($_GET['success'] === 'deleted') echo 'Usuario eliminado exitosamente';
                    ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-warning">
                <strong>⚠️ Importante:</strong> Solo los administradores pueden gestionar usuarios y permisos.
            </div>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-label">Total Usuarios</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>

                <div class="stat-card activos">
                    <div class="stat-label">Usuarios Activos</div>
                    <div class="stat-value"><?php echo $stats['activos']; ?></div>
                </div>

                <div class="stat-card inactivos">
                    <div class="stat-label">Usuarios Inactivos</div>
                    <div class="stat-value"><?php echo $stats['inactivos']; ?></div>
                </div>
            </div>

            <!-- Tabla de Usuarios -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Último Acceso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['usuario']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?: '-'); ?></td>
                                <td>
                                    <?php
                                    $badgeRol = '';
                                    if ($user['nombre_rol'] === 'Administrador') $badgeRol = 'badge-admin';
                                    elseif ($user['nombre_rol'] === 'Vendedor') $badgeRol = 'badge-vendedor';
                                    else $badgeRol = 'badge-supervisor';
                                    ?>
                                    <span class="badge <?php echo $badgeRol; ?>"><?php echo $user['nombre_rol']; ?></span>
                                </td>
                                <td><?php echo $user['fecha_ultimo_acceso'] ? date('d/m/Y H:i', strtotime($user['fecha_ultimo_acceso'])) : 'Nunca'; ?></td>
                                <td>
                                    <span class="badge <?php echo $user['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                        <?php echo $user['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="editar.php?id=<?php echo $user['id_vendedor']; ?>" class="btn btn-primary btn-small">✏️ Editar</a>
                                    <?php if ($user['usuario'] !== 'admin'): ?>
                                        <a href="eliminar.php?id=<?php echo $user['id_vendedor']; ?>" class="btn btn-danger btn-small" onclick="return confirm('¿Está seguro de eliminar este usuario?')">🗑️ Eliminar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>