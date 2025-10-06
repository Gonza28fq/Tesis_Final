<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('productos_crear')) {
    header('Location: index.php?error=sin_permiso');
    exit;
}

$errores = [];
$datos = [];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'codigo_producto' => sanitize($_POST['codigo_producto'] ?? ''),
        'nombre' => sanitize($_POST['nombre'] ?? ''),
        'descripcion' => sanitize($_POST['descripcion'] ?? ''),
        'id_categoria' => intval($_POST['id_categoria'] ?? 0),
        'id_proveedor' => intval($_POST['id_proveedor'] ?? 0),
        'precio_unitario' => floatval($_POST['precio_unitario'] ?? 0),
        'stock_minimo' => intval($_POST['stock_minimo'] ?? 0),
        'activo' => isset($_POST['activo']) ? 1 : 0
    ];

    // Validaciones
    if (empty($datos['nombre'])) {
        $errores[] = "El nombre del producto es obligatorio";
    }
    
    if ($datos['id_categoria'] <= 0) {
        $errores[] = "Debe seleccionar una categoría";
    }
    
    if ($datos['id_proveedor'] <= 0) {
        $errores[] = "Debe seleccionar un proveedor";
    }
    
    if ($datos['precio_unitario'] <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }

    // Verificar código único si se proporcionó
    if (!empty($datos['codigo_producto'])) {
        try {
            $db = getDB();
            $sqlCheck = "SELECT COUNT(*) FROM Productos WHERE codigo_producto = :codigo";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute([':codigo' => $datos['codigo_producto']]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = "El código de producto ya existe";
            }
        } catch (PDOException $e) {
            $errores[] = "Error al verificar el código";
        }
    }

    // Si no hay errores, insertar
    if (empty($errores)) {
        try {
            $db = getDB();
            $sql = "INSERT INTO Productos (
                        codigo_producto, nombre, descripcion, id_categoria, 
                        id_proveedor, precio_unitario, stock_minimo, activo
                    ) VALUES (
                        :codigo, :nombre, :descripcion, :categoria, 
                        :proveedor, :precio, :stock_min, :activo
                    )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':codigo' => $datos['codigo_producto'],
                ':nombre' => $datos['nombre'],
                ':descripcion' => $datos['descripcion'],
                ':categoria' => $datos['id_categoria'],
                ':proveedor' => $datos['id_proveedor'],
                ':precio' => $datos['precio_unitario'],
                ':stock_min' => $datos['stock_minimo'],
                ':activo' => $datos['activo']
            ]);

            header('Location: index.php?success=created');
            exit;
        } catch (PDOException $e) {
            error_log("Error al crear producto: " . $e->getMessage());
            $errores[] = "Error al guardar el producto";
        }
    }
}

// Obtener categorías
try {
    $db = getDB();
    $sqlCategorias = "SELECT * FROM Categorias WHERE activo = 1 ORDER BY nombre_categoria";
    $stmtCat = $db->query($sqlCategorias);
    $categorias = $stmtCat->fetchAll();
} catch (PDOException $e) {
    $categorias = [];
}

// Obtener proveedores
try {
    $sqlProveedores = "SELECT * FROM Proveedores WHERE activo = 1 ORDER BY nombre";
    $stmtProv = $db->query($sqlProveedores);
    $proveedores = $stmtProv->fetchAll();
} catch (PDOException $e) {
    $proveedores = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto - Sistema de Gestión</title>
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
            max-width: 900px;
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
            display: flex;
            align-items: center;
            gap: 10px;
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

        .form-group label .required {
            color: #e53e3e;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ed8936;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-group small {
            margin-top: 5px;
            font-size: 12px;
            color: #718096;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-wrapper label {
            margin: 0;
            cursor: pointer;
            user-select: none;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .info-box {
            background: #bee3f8;
            border: 2px solid #90cdf4;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #2c5282;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Nuevo Producto</h1>
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

            <div class="info-box">
                <strong>💡 Información Importante</strong>
                Complete los datos del nuevo producto. Los campos marcados con * son obligatorios.
            </div>

            <form method="POST" action="">
                <!-- Información Básica -->
                <div class="form-section">
                    <div class="form-section-title">
                        📝 Información Básica
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Código/SKU</label>
                            <input type="text" name="codigo_producto" value="<?php echo htmlspecialchars($datos['codigo_producto'] ?? ''); ?>" placeholder="Ej: PROD-001">
                            <small>Opcional. Si no se ingresa, se generará automáticamente</small>
                        </div>

                        <div class="form-group">
                            <label>Nombre del Producto <span class="required">*</span></label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($datos['nombre'] ?? ''); ?>" required placeholder="Ej: Laptop HP Core i5">
                        </div>

                        <div class="form-group full-width">
                            <label>Descripción</label>
                            <textarea name="descripcion" placeholder="Descripción detallada del producto..."><?php echo htmlspecialchars($datos['descripcion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Clasificación -->
                <div class="form-section">
                    <div class="form-section-title">
                        🏷️ Clasificación
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Categoría <span class="required">*</span></label>
                            <select name="id_categoria" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" <?php echo (isset($datos['id_categoria']) && $datos['id_categoria'] == $cat['id_categoria']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($categorias)): ?>
                                <small style="color: #e53e3e;">⚠️ No hay categorías activas. <a href="categorias.php">Crear categoría</a></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Proveedor <span class="required">*</span></label>
                            <select name="id_proveedor" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo (isset($datos['id_proveedor']) && $datos['id_proveedor'] == $prov['id_proveedor']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prov['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($proveedores)): ?>
                                <small style="color: #e53e3e;">⚠️ No hay proveedores activos. <a href="proveedores.php">Crear proveedor</a></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Precios y Stock -->
                <div class="form-section">
                    <div class="form-section-title">
                        💰 Precios y Stock
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Precio Unitario <span class="required">*</span></label>
                            <input type="number" name="precio_unitario" value="<?php echo $datos['precio_unitario'] ?? ''; ?>" step="0.01" min="0" required placeholder="0.00">
                            <small>Precio de venta al público</small>
                        </div>

                        <div class="form-group">
                            <label>Stock Mínimo</label>
                            <input type="number" name="stock_minimo" value="<?php echo $datos['stock_minimo'] ?? 0; ?>" min="0" placeholder="0">
                            <small>Alerta cuando el stock sea menor a este valor</small>
                        </div>
                    </div>
                </div>

                <!-- Estado -->
                <div class="form-section">
                    <div class="form-section-title">
                        ⚙️ Estado
                    </div>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" name="activo" id="activo" value="1" <?php echo (!isset($datos['activo']) || $datos['activo']) ? 'checked' : ''; ?>>
                        <label for="activo">Producto activo</label>
                    </div>
                    <small style="display: block; margin-top: 10px; color: #718096;">
                        Los productos inactivos no aparecerán en las ventas
                    </small>
                </div>

                <!-- Botones -->
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">💾 Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>