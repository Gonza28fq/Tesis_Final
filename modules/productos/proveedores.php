<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('productos_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$errores = [];
$datos = [];
$editando = false;
$id_editar = 0;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear' || $accion === 'editar') {
        if (!tienePermiso('productos_crear')) {
            $errores[] = "No tiene permisos para esta acción";
        } else {
            $datos = [
                'nombre' => sanitize($_POST['nombre'] ?? ''),
                'contacto' => sanitize($_POST['contacto'] ?? ''),
                'email' => sanitize($_POST['email'] ?? ''),
                'telefono' => sanitize($_POST['telefono'] ?? ''),
                'direccion' => sanitize($_POST['direccion'] ?? ''),
                'cuit' => sanitize($_POST['cuit'] ?? ''),
                'activo' => isset($_POST['activo']) ? 1 : 0
            ];
            
            // Validaciones
            if (empty($datos['nombre'])) {
                $errores[] = "El nombre del proveedor es obligatorio";
            }
            
            if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = "El email no es válido";
            }
            
            // Verificar nombre único
            if (empty($errores)) {
                try {
                    $db = getDB();
                    
                    if ($accion === 'crear') {
                        $sqlCheck = "SELECT COUNT(*) FROM Proveedores WHERE nombre = :nombre";
                        $stmtCheck = $db->prepare($sqlCheck);
                        $stmtCheck->execute([':nombre' => $datos['nombre']]);
                    } else {
                        $id_editar = intval($_POST['id_proveedor']);
                        $sqlCheck = "SELECT COUNT(*) FROM Proveedores WHERE nombre = :nombre AND id_proveedor != :id";
                        $stmtCheck = $db->prepare($sqlCheck);
                        $stmtCheck->execute([
                            ':nombre' => $datos['nombre'],
                            ':id' => $id_editar
                        ]);
                    }
                    
                    if ($stmtCheck->fetchColumn() > 0) {
                        $errores[] = "Ya existe un proveedor con ese nombre";
                    }
                } catch (PDOException $e) {
                    $errores[] = "Error al verificar el nombre";
                }
            }
            
            // Guardar si no hay errores
            if (empty($errores)) {
                try {
                    if ($accion === 'crear') {
                        $sql = "INSERT INTO Proveedores (nombre, contacto, email, telefono, direccion, cuit, activo) 
                                VALUES (:nombre, :contacto, :email, :telefono, :direccion, :cuit, :activo)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':nombre' => $datos['nombre'],
                            ':contacto' => $datos['contacto'],
                            ':email' => $datos['email'],
                            ':telefono' => $datos['telefono'],
                            ':direccion' => $datos['direccion'],
                            ':cuit' => $datos['cuit'],
                            ':activo' => $datos['activo']
                        ]);
                        header('Location: proveedores.php?success=created');
                        exit;
                    } else {
                        $sql = "UPDATE Proveedores SET 
                                nombre = :nombre,
                                contacto = :contacto,
                                email = :email,
                                telefono = :telefono,
                                direccion = :direccion,
                                cuit = :cuit,
                                activo = :activo
                                WHERE id_proveedor = :id";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':nombre' => $datos['nombre'],
                            ':contacto' => $datos['contacto'],
                            ':email' => $datos['email'],
                            ':telefono' => $datos['telefono'],
                            ':direccion' => $datos['direccion'],
                            ':cuit' => $datos['cuit'],
                            ':activo' => $datos['activo'],
                            ':id' => $id_editar
                        ]);
                        header('Location: proveedores.php?success=updated');
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Error al guardar proveedor: " . $e->getMessage());
                    $errores[] = "Error al guardar el proveedor";
                }
            }
        }
    }
}

// Cargar datos para editar
if (isset($_GET['editar'])) {
    $editando = true;
    $id_editar = intval($_GET['editar']);
    try {
        $db = getDB();
        $sql = "SELECT * FROM Proveedores WHERE id_proveedor = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id_editar]);
        $datos = $stmt->fetch();
        if (!$datos) {
            header('Location: proveedores.php?error=not_found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: proveedores.php?error=db_error');
        exit;
    }
}

// Obtener todos los proveedores con conteo de productos
try {
    $db = getDB();
    $sql = "SELECT 
                p.*,
                COUNT(prod.id_producto) as total_productos
            FROM Proveedores p
            LEFT JOIN Productos prod ON p.id_proveedor = prod.id_proveedor AND prod.activo = 1
            GROUP BY p.id_proveedor
            ORDER BY p.nombre";
    $stmt = $db->query($sql);
    $proveedores = $stmt->fetchAll();
    
    // Estadísticas
    $sqlStats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
                 FROM Proveedores";
    $stmtStats = $db->query($sqlStats);
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $proveedores = [];
    $stats = ['total' => 0, 'activos' => 0, 'inactivos' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores - Sistema de Gestión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ed8936 0%, #f5a623 100%);
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
            background: linear-gradient(135deg, #ed8936 0%, #f5a623 100%);
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

        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }

        .btn-primary {
            background: #ed8936;
            color: white;
        }

        .btn-primary:hover {
            background: #dd6b20;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .content {
            padding: 30px;
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 450px 1fr;
            gap: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card.total { border-color: #ed8936; }
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

        .form-card {
            background: #f7fafc;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        .form-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #4a5568;
        }

        .form-group .required {
            color: #e53e3e;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ed8936;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
            font-family: inherit;
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
            gap: 10px;
            margin-top: 20px;
        }

        .form-actions button,
        .form-actions a {
            flex: 1;
            text-align: center;
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

        .alert-error ul {
            margin-left: 20px;
            margin-top: 10px;
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

        .actions {
            display: flex;
            gap: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .contact-info {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }

        @media (max-width: 1200px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
            .form-card {
                position: relative;
                top: 0;
                max-height: none;
            }
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
            <h1>🏭 Gestión de Proveedores</h1>
            <a href="index.php" class="btn btn-secondary">← Volver a Productos</a>
        </div>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✓ <?php 
                        if ($_GET['success'] === 'created') echo 'Proveedor creado exitosamente';
                        elseif ($_GET['success'] === 'updated') echo 'Proveedor actualizado exitosamente';
                    ?>
                </div>
            <?php endif; ?>

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

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-label">Total Proveedores</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>

                <div class="stat-card activos">
                    <div class="stat-label">Activos</div>
                    <div class="stat-value"><?php echo $stats['activos']; ?></div>
                </div>

                <div class="stat-card inactivos">
                    <div class="stat-label">Inactivos</div>
                    <div class="stat-value"><?php echo $stats['inactivos']; ?></div>
                </div>
            </div>

            <div class="layout-grid">
                <!-- Formulario -->
                <div class="form-card">
                    <div class="form-title">
                        <?php echo $editando ? '✏️ Editar Proveedor' : '➕ Nuevo Proveedor'; ?>
                    </div>

                    <?php if (!tienePermiso('productos_crear')): ?>
                        <div class="alert alert-error">
                            No tiene permisos para crear/editar proveedores
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_proveedor" value="<?php echo $id_editar; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Nombre <span class="required">*</span></label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($datos['nombre'] ?? ''); ?>" required placeholder="Ej: Distribuidora ABC">
                            </div>

                            <div class="form-group">
                                <label>Persona de Contacto</label>
                                <input type="text" name="contacto" value="<?php echo htmlspecialchars($datos['contacto'] ?? ''); ?>" placeholder="Ej: Juan Pérez">
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($datos['email'] ?? ''); ?>" placeholder="ejemplo@correo.com">
                            </div>

                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" value="<?php echo htmlspecialchars($datos['telefono'] ?? ''); ?>" placeholder="Ej: +54 381 4123456">
                            </div>

                            <div class="form-group">
                                <label>CUIT</label>
                                <input type="text" name="cuit" value="<?php echo htmlspecialchars($datos['cuit'] ?? ''); ?>" placeholder="Ej: 20-12345678-9">
                            </div>

                            <div class="form-group">
                                <label>Dirección</label>
                                <textarea name="direccion" placeholder="Dirección completa..."><?php echo htmlspecialchars($datos['direccion'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" name="activo" id="activo" value="1" <?php echo (!isset($datos['activo']) || $datos['activo']) ? 'checked' : ''; ?>>
                                    <label for="activo" style="margin: 0;">Proveedor activo</label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <?php if ($editando): ?>
                                    <a href="proveedores.php" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">💾 Actualizar</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-success">💾 Guardar</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Lista de Proveedores -->
                <div>
                    <div class="table-container">
                        <?php if (empty($proveedores)): ?>
                            <div class="empty-state">
                                <h3>No hay proveedores registrados</h3>
                                <p>Cree el primer proveedor usando el formulario</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Contacto</th>
                                        <th style="text-align: center;">Productos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($prov['nombre']); ?></strong>
                                                <?php if ($prov['cuit']): ?>
                                                    <div class="contact-info">CUIT: <?php echo htmlspecialchars($prov['cuit']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($prov['contacto']): ?>
                                                    <div><strong><?php echo htmlspecialchars($prov['contacto']); ?></strong></div>
                                                <?php endif; ?>
                                                <?php if ($prov['telefono']): ?>
                                                    <div class="contact-info">📞 <?php echo htmlspecialchars($prov['telefono']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($prov['email']): ?>
                                                    <div class="contact-info">✉️ <?php echo htmlspecialchars($prov['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!$prov['contacto'] && !$prov['telefono'] && !$prov['email']): ?>
                                                    <span style="color: #a0aec0;">Sin información</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <strong style="color: #ed8936;"><?php echo $prov['total_productos']; ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $prov['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo $prov['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <?php if (tienePermiso('productos_editar')): ?>
                                                    <a href="proveedores.php?editar=<?php echo $prov['id_proveedor']; ?>" class="btn btn-primary btn-small">✏️ Editar</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>