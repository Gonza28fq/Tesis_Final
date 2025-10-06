<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('stock_ajustar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$error = '';
$success = '';

// Procesar transferencia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $idProducto = intval($_POST['id_producto']);
        $ubicacionOrigen = intval($_POST['ubicacion_origen']);
        $ubicacionDestino = intval($_POST['ubicacion_destino']);
        $cantidad = intval($_POST['cantidad']);
        $motivo = sanitize($_POST['motivo']);
        
        if ($ubicacionOrigen === $ubicacionDestino) {
            throw new Exception('Las ubicaciones de origen y destino no pueden ser iguales');
        }
        
        if ($cantidad <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0');
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Verificar stock en origen
        $sqlCheckOrigen = "SELECT cantidad FROM Stock 
                          WHERE id_producto = :id_producto 
                          AND id_ubicacion = :ubicacion";
        $stmtCheck = $db->prepare($sqlCheckOrigen);
        $stmtCheck->execute([
            ':id_producto' => $idProducto,
            ':ubicacion' => $ubicacionOrigen
        ]);
        $stockOrigen = $stmtCheck->fetch();
        
        if (!$stockOrigen || $stockOrigen['cantidad'] < $cantidad) {
            throw new Exception('Stock insuficiente en la ubicación de origen');
        }
        
        // Restar del origen
        $sqlRestar = "UPDATE Stock 
                     SET cantidad = cantidad - :cantidad,
                         fecha_actualizacion = NOW()
                     WHERE id_producto = :id_producto 
                     AND id_ubicacion = :ubicacion";
        $stmtRestar = $db->prepare($sqlRestar);
        $stmtRestar->execute([
            ':cantidad' => $cantidad,
            ':id_producto' => $idProducto,
            ':ubicacion' => $ubicacionOrigen
        ]);
        
        // Verificar si existe en destino
        $sqlCheckDestino = "SELECT cantidad FROM Stock 
                           WHERE id_producto = :id_producto 
                           AND id_ubicacion = :ubicacion";
        $stmtCheckDest = $db->prepare($sqlCheckDestino);
        $stmtCheckDest->execute([
            ':id_producto' => $idProducto,
            ':ubicacion' => $ubicacionDestino
        ]);
        $stockDestino = $stmtCheckDest->fetch();
        
        if ($stockDestino) {
            // Sumar al destino existente
            $sqlSumar = "UPDATE Stock 
                        SET cantidad = cantidad + :cantidad,
                            fecha_actualizacion = NOW()
                        WHERE id_producto = :id_producto 
                        AND id_ubicacion = :ubicacion";
            $stmtSumar = $db->prepare($sqlSumar);
            $stmtSumar->execute([
                ':cantidad' => $cantidad,
                ':id_producto' => $idProducto,
                ':ubicacion' => $ubicacionDestino
            ]);
        } else {
            // Crear en destino
            $sqlCrear = "INSERT INTO Stock (id_producto, cantidad, id_ubicacion) 
                        VALUES (:id_producto, :cantidad, :ubicacion)";
            $stmtCrear = $db->prepare($sqlCrear);
            $stmtCrear->execute([
                ':id_producto' => $idProducto,
                ':cantidad' => $cantidad,
                ':ubicacion' => $ubicacionDestino
            ]);
        }
        
        // Obtener nombres de ubicaciones para el detalle
        $sqlUbicaciones = "SELECT id_ubicacion, nombre FROM Ubicaciones 
                          WHERE id_ubicacion IN (:origen, :destino)";
        $stmtUb = $db->prepare($sqlUbicaciones);
        $stmtUb->execute([':origen' => $ubicacionOrigen, ':destino' => $ubicacionDestino]);
        $ubicaciones = $stmtUb->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $nombreOrigen = $ubicaciones[$ubicacionOrigen] ?? 'Ubicación ' . $ubicacionOrigen;
        $nombreDestino = $ubicaciones[$ubicacionDestino] ?? 'Ubicación ' . $ubicacionDestino;
        
        // Registrar movimientos
        $detalleMovimiento = "Transferencia: {$nombreOrigen} → {$nombreDestino}" . 
                            ($motivo ? " - {$motivo}" : "");
        
        $sqlMovimiento = "INSERT INTO Movimientos_Stock 
                         (id_producto, tipo_movimiento, cantidad, detalle, id_usuario) 
                         VALUES (:id_producto, 'AJUSTE', :cantidad, :detalle, :id_usuario)";
        $stmtMov = $db->prepare($sqlMovimiento);
        $stmtMov->execute([
            ':id_producto' => $idProducto,
            ':cantidad' => $cantidad,
            ':detalle' => $detalleMovimiento,
            ':id_usuario' => $_SESSION['id_vendedor']
        ]);
        
        $db->commit();
        $success = 'Transferencia realizada exitosamente';
        
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
    <title>Transferir Stock - Sistema de Gestión</title>
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

        .stock-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .stock-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }

        .stock-card h4 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .transfer-arrow {
            text-align: center;
            font-size: 32px;
            color: #667eea;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Transferir Stock entre Ubicaciones</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="formTransferir">
                <div class="form-group search-box">
                    <label>Buscar Producto *</label>
                    <input type="text" id="buscar-producto" placeholder="Nombre o código...">
                    <input type="hidden" id="id_producto" name="id_producto" required>
                    <div id="resultados-productos" class="search-results"></div>
                </div>

                <div id="producto-info" style="display: none;"></div>

                <div class="form-group">
                    <label>Ubicación de Origen *</label>
                    <select name="ubicacion_origen" id="ubicacion-origen" required onchange="actualizarStockDisponible()">
                        <option value="">Seleccione origen...</option>
                    </select>
                </div>

                <div class="transfer-arrow">↓</div>

                <div class="form-group">
                    <label>Ubicación de Destino *</label>
                    <select name="ubicacion_destino" id="ubicacion-destino" required>
                        <option value="">Seleccione destino...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Cantidad a Transferir *</label>
                    <input type="number" name="cantidad" id="cantidad" min="1" required>
                    <div style="font-size: 13px; color: #718096; margin-top: 5px;">
                        Stock disponible en origen: <strong id="stock-disponible">-</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label>Motivo (Opcional)</label>
                    <textarea name="motivo" placeholder="Describa el motivo de la transferencia..."></textarea>
                </div>

                <button type="submit" class="btn btn-success">✓ Confirmar Transferencia</button>
            </form>
        </div>
    </div>

    <script>
        let productoSeleccionado = null;
        let stockPorUbicacion = {};
        let timeoutBusqueda = null;

        // Cargar ubicaciones
        fetch('cargar_ubicaciones.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const selectOrigen = document.getElementById('ubicacion-origen');
                    const selectDestino = document.getElementById('ubicacion-destino');
                    
                    data.ubicaciones.forEach(ub => {
                        const option1 = document.createElement('option');
                        option1.value = ub.id_ubicacion;
                        option1.textContent = ub.nombre;
                        selectOrigen.appendChild(option1);
                        
                        const option2 = document.createElement('option');
                        option2.value = ub.id_ubicacion;
                        option2.textContent = ub.nombre;
                        selectDestino.appendChild(option2);
                    });
                }
            });

        // Búsqueda de productos
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
                const response = await fetch(`buscar_producto_transfer.php?q=${encodeURIComponent(query)}`);
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
                        <small>Stock total: ${producto.stock_total}</small>
                    </div>
                `;
            });
            
            contenedor.innerHTML = html;
            contenedor.classList.add('active');
        }

        async function seleccionarProducto(producto) {
            productoSeleccionado = producto;
            document.getElementById('buscar-producto').value = producto.nombre;
            document.getElementById('id_producto').value = producto.id_producto;
            document.getElementById('resultados-productos').classList.remove('active');
            
            // Obtener stock por ubicación
            try {
                const response = await fetch(`obtener_stock_ubicaciones.php?id=${producto.id_producto}`);
                const data = await response.json();
                
                if (data.success) {
                    stockPorUbicacion = data.stock;
                    mostrarInfoProducto(producto, data.stock);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function mostrarInfoProducto(producto, stock) {
            const info = document.getElementById('producto-info');
            
            let stockHtml = '';
            stock.forEach(s => {
                stockHtml += `
                    <div class="stock-card">
                        <h4>${s.ubicacion_nombre}</h4>
                        <p style="font-size: 20px; font-weight: 700; color: #2d3748; margin: 0;">
                            ${s.cantidad} unidades
                        </p>
                    </div>
                `;
            });
            
            info.innerHTML = `
                <div class="producto-info">
                    <strong>${producto.nombre}</strong><br>
                    <small>Stock total: ${producto.stock_total} unidades</small>
                    <div class="stock-info">
                        ${stockHtml || '<p style="color: #a0aec0;">Sin stock en ubicaciones</p>'}
                    </div>
                </div>
            `;
            info.style.display = 'block';
        }

        function actualizarStockDisponible() {
            const ubicacionId = document.getElementById('ubicacion-origen').value;
            const stockSpan = document.getElementById('stock-disponible');
            
            if (!ubicacionId || !stockPorUbicacion) {
                stockSpan.textContent = '-';
                return;
            }
            
            const stock = stockPorUbicacion.find(s => s.id_ubicacion == ubicacionId);
            stockSpan.textContent = stock ? stock.cantidad + ' unidades' : '0 unidades';
            
            // Actualizar máximo del input
            const cantidadInput = document.getElementById('cantidad');
            cantidadInput.max = stock ? stock.cantidad : 0;
        }

        // Validar formulario
        document.getElementById('formTransferir').addEventListener('submit', function(e) {
            const origen = document.getElementById('ubicacion-origen').value;
            const destino = document.getElementById('ubicacion-destino').value;
            const cantidad = parseInt(document.getElementById('cantidad').value);
            
            if (origen === destino) {
                e.preventDefault();
                alert('Las ubicaciones de origen y destino no pueden ser iguales');
                return false;
            }
            
            const stock = stockPorUbicacion.find(s => s.id_ubicacion == origen);
            if (!stock || cantidad > stock.cantidad) {
                e.preventDefault();
                alert('Cantidad mayor al stock disponible en origen');
                return false;
            }
            
            return confirm('¿Confirmar la transferencia de stock?');
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