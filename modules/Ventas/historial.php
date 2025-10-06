<?php
require_once '../../config/conexion.php';
validarSesion();

// Verificar permiso
if (!tienePermiso('ventas_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Obtener filtros
$filtroFechaDesde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$filtroCliente = isset($_GET['cliente']) ? sanitize($_GET['cliente']) : '';
$filtroComprobante = isset($_GET['comprobante']) ? sanitize($_GET['comprobante']) : '';

try {
    $db = getDB();
    
    // Construir consulta con filtros
    $sql = "SELECT 
                v.id_venta,
                v.fecha,
                v.numero_comprobante,
                v.tipo_comprobante,
                CONCAT(c.nombre, ' ', c.apellido) as cliente,
                c.email as cliente_email,
                CONCAT(vend.nombre, ' ', vend.apellido) as vendedor,
                v.forma_pago,
                v.total,
                COUNT(dv.id_detalle) as cantidad_items
            FROM Ventas v
            INNER JOIN Clientes c ON v.id_cliente = c.id_cliente
            INNER JOIN Vendedores vend ON v.id_vendedor = vend.id_vendedor
            LEFT JOIN Detalle_Venta dv ON v.id_venta = dv.id_venta
            WHERE DATE(v.fecha) BETWEEN :fecha_desde AND :fecha_hasta";
    
    $params = [
        ':fecha_desde' => $filtroFechaDesde,
        ':fecha_hasta' => $filtroFechaHasta
    ];
    
    if (!empty($filtroCliente)) {
        $sql .= " AND (c.nombre LIKE :cliente OR c.apellido LIKE :cliente OR c.email LIKE :cliente)";
        $params[':cliente'] = "%{$filtroCliente}%";
    }
    
    if (!empty($filtroComprobante)) {
        $sql .= " AND v.numero_comprobante LIKE :comprobante";
        $params[':comprobante'] = "%{$filtroComprobante}%";
    }
    
    $sql .= " GROUP BY v.id_venta ORDER BY v.fecha DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll();
    
    // Calcular totales
    $totalVentas = count($ventas);
    $sumaTotal = array_sum(array_column($ventas, 'total'));
    
} catch (PDOException $e) {
    error_log("Error en historial.php: " . $e->getMessage());
    $ventas = [];
    $totalVentas = 0;
    $sumaTotal = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - Sistema de Gestión</title>
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
            max-width: 1400px;
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

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }

        .content {
            padding: 30px;
        }

        .filters {
            background: #f7fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
            color: #4a5568;
        }

        .form-group input {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
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
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
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

        .badge-a { background: #c6f6d5; color: #22543d; }
        .badge-b { background: #bee3f8; color: #2c5282; }
        .badge-c { background: #feebc8; color: #744210; }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Historial de Ventas</h1>
            <div>
                <a href="index.php" class="btn btn-secondary">← Nueva Venta</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros de Búsqueda</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo $filtroFechaDesde; ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo $filtroFechaHasta; ?>">
                    </div>
                    <div class="form-group">
                        <label>Cliente</label>
                        <input type="text" name="cliente" value="<?php echo htmlspecialchars($filtroCliente); ?>" placeholder="Nombre, email...">
                    </div>
                    <div class="form-group">
                        <label>N° Comprobante</label>
                        <input type="text" name="comprobante" value="<?php echo htmlspecialchars($filtroComprobante); ?>" placeholder="Ej: B-00000001">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="historial.php" class="btn btn-secondary">Limpiar</a>
            </form>

            <!-- Estadísticas -->
            <div class="stats">
                <div class="stat-card">
                    <h3>Total de Ventas</h3>
                    <div class="value"><?php echo $totalVentas; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Monto Total</h3>
                    <div class="value"><?php echo formatearMoneda($sumaTotal); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Promedio</h3>
                    <div class="value"><?php echo formatearMoneda($totalVentas > 0 ? $sumaTotal / $totalVentas : 0); ?></div>
                </div>
            </div>

            <!-- Tabla de ventas -->
            <div class="table-container">
                <?php if (empty($ventas)): ?>
                    <div class="empty-state">
                        <h3>No se encontraron ventas</h3>
                        <p>Ajuste los filtros o realice una nueva venta</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Items</th>
                                <th>Forma Pago</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td><?php echo formatearFecha($venta['fecha']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($venta['tipo_comprobante']); ?>">
                                            <?php echo $venta['numero_comprobante']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($venta['cliente']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($venta['cliente_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($venta['vendedor']); ?></td>
                                    <td><?php echo $venta['cantidad_items']; ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $venta['forma_pago'])); ?></td>
                                    <td><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                                    <td class="actions">
                                        <button class="btn btn-primary btn-small" onclick="verDetalle(<?php echo $venta['id_venta']; ?>)">
                                            👁 Ver
                                        </button>
                                        <a href="generar_pdf.php?id_venta=<?php echo $venta['id_venta']; ?>" 
                                           target="_blank" class="btn btn-secondary btn-small">
                                            📄 PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para ver detalle (implementar con JavaScript) -->
    <script>
        function verDetalle(idVenta) {
            // Implementar modal con AJAX para ver detalle de venta
            alert('Función de detalle - ID: ' + idVenta);
            // Aquí puedes hacer una petición AJAX para obtener el detalle
        }
    </script>
</body>
</html>