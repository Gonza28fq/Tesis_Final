<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('productos_editar')) {
    header('Location: index.php?error=sin_permiso');
    exit;
}

$id_producto = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errores = [];
$producto = null;

// Obtener datos del producto
try {
    $db = getDB();
    $sql = "SELECT * FROM Productos WHERE id_producto = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id_producto]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        header('Location: index.php?error=producto_no_encontrado');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error al obtener producto: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}

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

    // Verificar código único si cambió
    if (!empty($datos['codigo_producto']) && $datos['codigo_producto'] != $producto['codigo_producto']) {
        try {
            $sqlCheck = "SELECT COUNT(*) FROM Productos WHERE codigo_producto = :codigo AND id_producto != :id";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute([
                ':codigo' => $datos['codigo_producto'],
                ':id' => $id_producto
            ]);
            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = "El código de producto ya existe";
            }
        } catch (PDOException $e) {
            $errores[] = "Error al verificar el código";
        }
    }

    // Si no hay errores, actualizar
    if (empty($errores)) {
        try {
            $sql = "UPDATE Productos SET
                        codigo_producto = :codigo,
                        nombre = :nombre,
                        descripcion = :descripcion,
                        id_categoria = :categoria,
                        id_proveedor = :proveedor,
                        precio_unitario = :precio,
                        stock_minimo = :stock_min,
                        activo = :activo
                    WHERE id_producto = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':codigo' => $datos['codigo_producto'],
                ':nombre' => $datos['nombre'],
                ':descripcion' => $datos['descripcion'],
                ':categoria' => $datos['id_categoria'],
                ':proveedor' => $datos['id_proveedor'],
                ':precio' => $datos['precio_unitario'],
                ':stock_min' => $datos['stock_minimo'],
                ':activo' => $datos['activo'],
                ':id' => $id_producto
            ]);

            header('Location: index.php?success=updated');
            exit;
        } catch (PDOException $e) {
            error_log("Error al actualizar producto: " . $e->getMessage());
            $errores[] = "Error al actualizar el producto";
        }
    } else {
        // Mantener los datos ingresados
        foreach ($datos as $key => $value) {
            $producto[$key] = $value;
        }
    }
}

// Obtener categorías
try {
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

// Obtener stock actual
try {
    $sqlStock = "SELECT COALESCE(SUM(cantidad), 0) as stock_total FROM Stock WHERE id_producto = :id";
    $stmtStock = $db->prepare($sqlStock);
    $stmtStock->execute([':id' => $id_producto]);
    $stockInfo = $stmtStock->fetch();
    $stockActual = $stockInfo['stock_total'];
} catch (PDOException $e) {
    $stockActual = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Sistema de Gestión</title>
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

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background: #e53e3e;
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

        .alert-warning {
            background: #feebc8;
            border: 2px solid #f6ad55;
            color: #7c2d12;
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
            font-size: 20px;
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
            justify-content: space-between;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .actions-left {
            display: flex;
            gap: 10px;
        }

        .actions-right {
            display: flex;
            gap: 10px;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .danger-zone p {
            color: #742a2a;
            margin-bottom: 15px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .actions-left, .actions-right { width: 100%; }
            .btn { width: 100%; text-align: center; }
            .info-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Producto</h1>
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

            <?php if ($stockActual < $producto['stock_minimo']): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Alerta de Stock Bajo</strong><br>
                    El stock actual (<?php echo $stockActual; ?>) está por debajo del mínimo configurado (<?php echo $producto['stock_minimo']; ?>)
                </div>
            <?php endif; ?>

            <!-- Info Card -->
            <div class="info-card">
                <div class="info-item">
                    <div class="info-label">Código del Producto</div>
                    <div class="info-value"><?php echo $producto['codigo_producto'] ?: 'Sin código'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Stock Actual</div>
                    <div class="info-value"><?php echo $stockActual; ?> unidades</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Estado</div>
                    <div class="info-value"><?php echo $producto['activo'] ? '✓ Activo' : '⊗ Inactivo'; ?></div>
                </div>
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
                            <input type="text" name="codigo_producto" value="<?php echo htmlspecialchars($producto['codigo_producto']); ?>" placeholder="Ej: PROD-001">
                            <small>Identificador único del producto</small>
                        </div>

                        <div class="form-group">
                            <label>Nombre del Producto <span class="required">*</span></label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required placeholder="Ej: Laptop HP Core i5">
                        </div>

                        <div class="form-group full-width">
                            <label>Descripción</label>
                            <textarea name="descripcion" placeholder="Descripción detallada del producto..."><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
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
                                    <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $producto['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Proveedor <span class="required">*</span></label>
                            <select name="id_proveedor" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id_proveedor']; ?>" <?php echo $producto['id_proveedor'] == $prov['id_proveedor'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prov['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                            <input type="number" name="precio_unitario" value="<?php echo $producto['precio_unitario']; ?>" step="0.01" min="0" required placeholder="0.00">
                            <small>Precio de venta al público</small>
                        </div>

                        <div class="form-group">
                            <label>Stock Mínimo</label>
                            <input type="number" name="stock_minimo" value="<?php echo $producto['stock_minimo']; ?>" min="0" placeholder="0">
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
                        <input type="checkbox" name="activo" id="activo" value="1" <?php echo $producto['activo'] ? 'checked' : ''; ?>>
                        <label for="activo">Producto activo</label>
                    </div>
                    <small style="display: block; margin-top: 10px; color: #718096;">
                        Los productos inactivos no aparecerán en las ventas
                    </small>
                </div>

                <!-- Botones -->
                <div class="form-actions">
                    <div class="actions-left">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                    <div class="actions-right">
                        <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
                    </div>
                </div>
            </form>

            <!-- Zona de Peligro -->
            <?php if (tienePermiso('productos_eliminar')): ?>
                <div class="danger-zone">
                    <h3>⚠️ Zona de Peligro</h3>
                    <p>
                        Al desactivar este producto, ya no estará disponible para nuevas ventas.
                        El historial de ventas anteriores se mantendrá intacto.
                    </p>
                    <button type="button" class="btn btn-danger" onclick="confirmarDesactivar()">
                        🗑️ Desactivar Producto
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmarDesactivar() {
            if (confirm('¿Está seguro de que desea desactivar este producto?\n\nEsta acción se puede revertir posteriormente.')) {
                window.location.href = 'eliminar.php?id=<?php echo $id_producto; ?>';
            }
        }
    </script>
</body>
</html>