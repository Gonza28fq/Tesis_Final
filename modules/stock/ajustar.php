<?php
require_once '../../config/conexion.php';
validarSesion();

// Verificar permiso
if (!tienePermiso('stock_ajustar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$error = '';
$success = '';
$productoPreseleccionado = null;

// Si viene un ID de producto, precargarlo
if (isset($_GET['id'])) {
    $idProducto = intval($_GET['id']);
    try {
        $db = getDB();
        $sql = "SELECT p.*, 
                       COALESCE(SUM(s.cantidad), 0) as stock_total,
                       c.nombre_categoria
                FROM Productos p
                LEFT JOIN Stock s ON p.id_producto = s.id_producto
                LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
                WHERE p.id_producto = :id
                GROUP BY p.id_producto";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $idProducto]);
        $productoPreseleccionado = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
    }
}

// Procesar ajuste
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $idProducto = intval($_POST['id_producto']);
        $idUbicacion = intval($_POST['id_ubicacion']);
        $tipoAjuste = sanitize($_POST['tipo_ajuste']);
        $cantidad = intval($_POST['cantidad']);
        $motivo = sanitize($_POST['motivo']);
        
        if ($cantidad <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0');
        }
        
        if (empty($motivo)) {
            throw new Exception('Debe especificar un motivo');
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Verificar si existe stock en esa ubicación
        $sqlCheck = "SELECT cantidad FROM Stock 
                    WHERE id_producto = :id_producto 
                    AND id_ubicacion = :id_ubicacion";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([
            ':id_producto' => $idProducto,
            ':id_ubicacion' => $idUbicacion
        ]);
        $stockActual = $stmtCheck->fetch();
        
        if ($tipoAjuste === 'sumar') {
            // Sumar al stock
            if ($stockActual) {
                $sqlUpdate = "UPDATE Stock 
                             SET cantidad = cantidad + :cantidad,
                                 fecha_actualizacion = NOW()
                             WHERE id_producto = :id_producto 
                             AND id_ubicacion = :id_ubicacion";
                $stmtUpdate = $db->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':cantidad' => $cantidad,
                    ':id_producto' => $idProducto,
                    ':id_ubicacion' => $idUbicacion
                ]);
            } else {
                $sqlInsert = "INSERT INTO Stock (id_producto, cantidad, id_ubicacion) 
                             VALUES (:id_producto, :cantidad, :id_ubicacion)";
                $stmtInsert = $db->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':id_producto' => $idProducto,
                    ':cantidad' => $cantidad,
                    ':id_ubicacion' => $idUbicacion
                ]);
            }
            $cantidadMovimiento = $cantidad;
        } else {
            // Restar del stock
            if (!$stockActual || $stockActual['cantidad'] < $cantidad) {
                throw new Exception('Stock insuficiente para realizar el ajuste');
            }
            
            $sqlUpdate = "UPDATE Stock 
                         SET cantidad = cantidad - :cantidad,
                             fecha_actualizacion = NOW()
                         WHERE id_producto = :id_producto 
                         AND id_ubicacion = :id_ubicacion";
            $stmtUpdate = $db->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':cantidad' => $cantidad,
                ':id_producto' => $idProducto,
                ':id_ubicacion' => $idUbicacion
            ]);
            $cantidadMovimiento = -$cantidad;
        }
        
        // Registrar movimiento
        $sqlMovimiento = "INSERT INTO Movimientos_Stock 
                         (id_producto, tipo_movimiento, cantidad, detalle, id_usuario) 
                         VALUES (:id_producto, 'AJUSTE', :cantidad, :detalle, :id_usuario)";
        $stmtMovimiento = $db->prepare($sqlMovimiento);
        $stmtMovimiento->execute([
            ':id_producto' => $idProducto,
            ':cantidad' => abs($cantidadMovimiento),
            ':detalle' => "Ajuste manual: {$motivo}",
            ':id_usuario' => $_SESSION['id_vendedor']
        ]);
        
        $db->commit();
        $success = 'Ajuste realizado exitosamente';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustar Stock - Sistema de Gestión</title>
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
            font-size: 24px;
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
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-success {
            background: #48bb78;
            color: white;
            width: 100%;
            padding: 15px;
            font-size: 16px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .search-box {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 10;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .search-results.active {
            display: block;
        }

        .search-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #e2e8f0;
        }

        .search-item:hover {
            background: #edf2f7;
        }

        .producto-info {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .help-text {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Ajustar Stock Manualmente</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group search-box">
                    <label>Buscar Producto *</label>
                    <input type="text" id="buscar-producto" 
                           value="<?php echo $productoPreseleccionado ? $productoPreseleccionado['nombre'] : ''; ?>" 
                           placeholder="Nombre o código..." 
                           <?php echo $productoPreseleccionado ? 'readonly' : ''; ?>>
                    <input type="hidden" id="id_producto" name="id_producto" 
                           value="<?php echo $productoPreseleccionado ? $productoPreseleccionado['id_producto'] : ''; ?>" 
                           required>
                    <div id="resultados-productos" class="search-results"></div>
                </div>

                <div id="producto-info" style="display: <?php echo $productoPreseleccionado ? 'block' : 'none'; ?>;">
                    <?php if ($productoPreseleccionado): ?>
                        <div class="producto-info">
                            <strong><?php echo $productoPreseleccionado['nombre']; ?></strong><br>
                            <small>Categoría: <?php echo $productoPreseleccionado['nombre_categoria']; ?></small><br>
                            <small>Stock actual: <strong><?php echo $productoPreseleccionado['stock_total']; ?></strong> unidades</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Ubicación *</label>
                    <select name="id_ubicacion" id="id_ubicacion" required>
                        <option value="">Seleccione ubicación...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de Ajuste *</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="tipo_ajuste" value="sumar" checked>
                            ➕ Sumar al Stock
                        </label>
                        <label>
                            <input type="radio" name="tipo_ajuste" value="restar">
                            ➖ Restar del Stock
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Cantidad *</label>
                    <input type="number" name="cantidad" min="1" required>
                    <div class="help-text">Cantidad a ajustar (positivo)</div>
                </div>

                <div class="form-group">
                    <label>Motivo del Ajuste *</label>
                    <select name="motivo" id="motivo-select" onchange="toggleMotivoOtro()">
                        <option value="">Seleccione...</option>
                        <option value="Corrección de inventario">Corrección de inventario</option>
                        <option value="Pérdida">Pérdida</option>
                        <option value="Robo">Robo</option>
                        <option value="Daño/Deterioro">Daño/Deterioro</option>
                        <option value="Error de registro">Error de registro</option>
                        <option value="Devolución">Devolución</option>
                        <option value="Otro">Otro (especificar)</option>
                    </select>
                </div>

                <div class="form-group" id="motivo-otro-group" style="display: none;">
                    <label>Especifique el Motivo *</label>
                    <textarea name="motivo_otro" id="motivo-otro" placeholder="Describa el motivo del ajuste..."></textarea>
                </div>

                <button type="submit" class="btn btn-success">✓ Confirmar Ajuste</button>
            </form>
        </div>
    </div>

    <script>
        let productoSeleccionado = <?php echo $productoPreseleccionado ? 'true' : 'false'; ?>;
        let timeoutBusqueda = null;

        // Cargar ubicaciones
        fetch('cargar_ubicaciones.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('id_ubicacion');
                    data.ubicaciones.forEach(ub => {
                        const option = document.createElement('option');
                        option.value = ub.id_ubicacion;
                        option.textContent = ub.nombre;
                        select.appendChild(option);
                    });
                    if (data.ubicaciones.length > 0) {
                        select.selectedIndex = 1;
                    }
                }
            });

        // Búsqueda de productos
        <?php if (!$productoPreseleccionado): ?>
        document.getElementById('buscar-producto').addEventListener('input', function() {
            clearTimeout(timeoutBusqueda);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                timeoutBusqueda = setTimeout(() => buscarProducto(query), 300);
            } else {
                document.getElementById('resultados-productos').classList.remove('active');
            }
        });

        async function buscarProducto(query) {
            try {
                const response = await fetch(`buscar_producto_stock.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success) {
                    mostrarResultados(data.productos);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function mostrarResultados(productos) {
            const contenedor = document.getElementById('resultados-productos');
            
            if (productos.length === 0) {
                contenedor.innerHTML = '<div class="search-item">No se encontraron productos</div>';
                contenedor.classList.add('active');
                return;
            }
            
            let html = '';
            productos.forEach(producto => {
                html += `
                    <div class="search-item" onclick='seleccionarProducto(${JSON.stringify(producto)})'>
                        <strong>${producto.nombre}</strong><br>
                        <small>Stock: ${producto.stock_total} - ${producto.nombre_categoria}</small>
                    </div>
                `;
            });
            
            contenedor.innerHTML = html;
            contenedor.classList.add('active');
        }

        function seleccionarProducto(producto) {
            document.getElementById('buscar-producto').value = producto.nombre;
            document.getElementById('id_producto').value = producto.id_producto;
            document.getElementById('resultados-productos').classList.remove('active');
            
            const info = document.getElementById('producto-info');
            info.innerHTML = `
                <div class="producto-info">
                    <strong>${producto.nombre}</strong><br>
                    <small>Categoría: ${producto.nombre_categoria}</small><br>
                    <small>Stock actual: <strong>${producto.stock_total}</strong> unidades</small>
                </div>
            `;
            info.style.display = 'block';
            productoSeleccionado = true;
        }
        <?php endif; ?>

        // Toggle motivo otro
        function toggleMotivoOtro() {
            const select = document.getElementById('motivo-select');
            const otroGroup = document.getElementById('motivo-otro-group');
            const otroInput = document.getElementById('motivo-otro');
            
            if (select.value === 'Otro') {
                otroGroup.style.display = 'block';
                otroInput.required = true;
            } else {
                otroGroup.style.display = 'none';
                otroInput.required = false;
            }
        }

        // Validar formulario antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const motivoSelect = document.getElementById('motivo-select');
            const motivoOtro = document.getElementById('motivo-otro');
            
            if (motivoSelect.value === 'Otro' && !motivoOtro.value.trim()) {
                e.preventDefault();
                alert('Debe especificar el motivo del ajuste');
                return false;
            }
            
            if (!productoSeleccionado) {
                e.preventDefault();
                alert('Debe seleccionar un producto');
                return false;
            }
            
            return confirm('¿Confirmar el ajuste de stock?');
        });

        // Cerrar resultados al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) {
                document.getElementById('resultados-productos').classList.remove('active');
            }
        });
    </script>
</body>
</html>