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
                'nombre_categoria' => sanitize($_POST['nombre_categoria'] ?? ''),
                'descripcion' => sanitize($_POST['descripcion'] ?? ''),
                'activo' => isset($_POST['activo']) ? 1 : 0
            ];
            
            // Validaciones
            if (empty($datos['nombre_categoria'])) {
                $errores[] = "El nombre de la categoría es obligatorio";
            }
            
            // Verificar nombre único
            if (empty($errores)) {
                try {
                    $db = getDB();
                    
                    if ($accion === 'crear') {
                        $sqlCheck = "SELECT COUNT(*) FROM Categorias WHERE nombre_categoria = :nombre";
                        $stmtCheck = $db->prepare($sqlCheck);
                        $stmtCheck->execute([':nombre' => $datos['nombre_categoria']]);
                    } else {
                        $id_editar = intval($_POST['id_categoria']);
                        $sqlCheck = "SELECT COUNT(*) FROM Categorias WHERE nombre_categoria = :nombre AND id_categoria != :id";
                        $stmtCheck = $db->prepare($sqlCheck);
                        $stmtCheck->execute([
                            ':nombre' => $datos['nombre_categoria'],
                            ':id' => $id_editar
                        ]);
                    }
                    
                    if ($stmtCheck->fetchColumn() > 0) {
                        $errores[] = "Ya existe una categoría con ese nombre";
                    }
                } catch (PDOException $e) {
                    $errores[] = "Error al verificar el nombre";
                }
            }
            
            // Guardar si no hay errores
            if (empty($errores)) {
                try {
                    if ($accion === 'crear') {
                        $sql = "INSERT INTO Categorias (nombre_categoria, descripcion, activo) 
                                VALUES (:nombre, :descripcion, :activo)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':nombre' => $datos['nombre_categoria'],
                            ':descripcion' => $datos['descripcion'],
                            ':activo' => $datos['activo']
                        ]);
                        header('Location: categorias.php?success=created');
                        exit;
                    } else {
                        $sql = "UPDATE Categorias SET 
                                nombre_categoria = :nombre,
                                descripcion = :descripcion,
                                activo = :activo
                                WHERE id_categoria = :id";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':nombre' => $datos['nombre_categoria'],
                            ':descripcion' => $datos['descripcion'],
                            ':activo' => $datos['activo'],
                            ':id' => $id_editar
                        ]);
                        header('Location: categorias.php?success=updated');
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Error al guardar categoría: " . $e->getMessage());
                    $errores[] = "Error al guardar la categoría";
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
        $sql = "SELECT * FROM Categorias WHERE id_categoria = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id_editar]);
        $datos = $stmt->fetch();
        if (!$datos) {
            header('Location: categorias.php?error=not_found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: categorias.php?error=db_error');
        exit;
    }
}

// Obtener todas las categorías con conteo de productos
try {
    $db = getDB();
    $sql = "SELECT 
                c.*,
                COUNT(p.id_producto) as total_productos
            FROM Categorias c
            LEFT JOIN Productos p ON c.id_categoria = p.id_categoria AND p.activo = 1
            GROUP BY c.id_categoria
            ORDER BY c.nombre_categoria";
    $stmt = $db->query($sql);
    $categorias = $stmt->fetchAll();
    
    // Estadísticas
    $sqlStats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activas,
                    SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivas
                 FROM Categorias";
    $stmtStats = $db->query($sqlStats);
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $categorias = [];
    $stats = ['total' => 0, 'activas' => 0, 'inactivas' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - Sistema de Gestión</title>
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
            max-width: 1400px;
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
            grid-template-columns: 400px 1fr;
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
        .stat-card.activas { border-color: #48bb78; }
        .stat-card.inactivas { border-color: #a0aec0; }

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
            margin-bottom: 20px;
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
            padding: 12px;
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
            min-height: 80px;
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

        .form-actions button {
            flex: 1;
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

        @media (max-width: 1024px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
            .form-card {
                position: relative;
                top: 0;
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
            <h1>🏷️ Gestión de Categorías</h1>
            <a href="index.php" class="btn btn-secondary">← Volver a Productos</a>
        </div>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✓ <?php 
                        if ($_GET['success'] === 'created') echo 'Categoría creada exitosamente';
                        elseif ($_GET['success'] === 'updated') echo 'Categoría actualizada exitosamente';
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
                    <div class="stat-label">Total Categorías</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>

                <div class="stat-card activas">
                    <div class="stat-label">Activas</div>
                    <div class="stat-value"><?php echo $stats['activas']; ?></div>
                </div>

                <div class="stat-card inactivas">
                    <div class="stat-label">Inactivas</div>
                    <div class="stat-value"><?php echo $stats['inactivas']; ?></div>
                </div>
            </div>

            <div class="layout-grid">
                <!-- Formulario -->
                <div class="form-card">
                    <div class="form-title">
                        <?php echo $editando ? '✏️ Editar Categoría' : '➕ Nueva Categoría'; ?>
                    </div>

                    <?php if (!tienePermiso('productos_crear')): ?>
                        <div class="alert alert-error">
                            No tiene permisos para crear/editar categorías
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_categoria" value="<?php echo $id_editar; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Nombre <span class="required">*</span></label>
                                <input type="text" name="nombre_categoria" value="<?php echo htmlspecialchars($datos['nombre_categoria'] ?? ''); ?>" required placeholder="Ej: Electrónica">
                            </div>

                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea name="descripcion" placeholder="Descripción opcional..."><?php echo htmlspecialchars($datos['descripcion'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" name="activo" id="activo" value="1" <?php echo (!isset($datos['activo']) || $datos['activo']) ? 'checked' : ''; ?>>
                                    <label for="activo" style="margin: 0;">Categoría activa</label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <?php if ($editando): ?>
                                    <a href="categorias.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">💾 Actualizar</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-success">💾 Guardar</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Lista de Categorías -->
                <div>
                    <div class="table-container">
                        <?php if (empty($categorias)): ?>
                            <div class="empty-state">
                                <h3>No hay categorías registradas</h3>
                                <p>Cree la primera categoría usando el formulario</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th style="text-align: center;">Productos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $cat): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($cat['nombre_categoria']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($cat['descripcion'] ?: '-'); ?></td>
                                            <td style="text-align: center;">
                                                <strong style="color: #ed8936;"><?php echo $cat['total_productos']; ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $cat['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo $cat['activo'] ? 'Activa' : 'Inactiva'; ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <?php if (tienePermiso('productos_editar')): ?>
                                                    <a href="categorias.php?editar=<?php echo $cat['id_categoria']; ?>" class="btn btn-primary btn-small">✏️ Editar</a>
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