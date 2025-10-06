<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('stock_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Filtros
$filtroFechaDesde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$filtroProducto = isset($_GET['producto']) ? sanitize($_GET['producto']) : '';
$filtroTipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';

try {
    $db = getDB();
    
    $sql = "SELECT 
                ms.*,
                p.nombre as producto_nombre,
                p.codigo_producto,
                v.usuario as usuario_nombre
            FROM Movimientos_Stock ms
            INNER JOIN Productos p ON ms.id_producto = p.id_producto
            LEFT JOIN Vendedores v ON ms.id_usuario = v.id_vendedor
            WHERE DATE(ms.fecha) BETWEEN :fecha_desde AND :fecha_hasta";
    
    $params = [
        ':fecha_desde' => $filtroFechaDesde,
        ':fecha_hasta' => $filtroFechaHasta
    ];
    
    if (!empty($filtroProducto)) {
        $sql .= " AND p.nombre LIKE :producto";
        $params[':producto'] = "%{$filtroProducto}%";
    }
    
    if (!empty($filtroTipo)) {
        $sql .= " AND ms.tipo_movimiento = :tipo";
        $params[':tipo'] = $filtroTipo;
    }
    
    $sql .= " ORDER BY ms.fecha DESC LIMIT 200";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll();
    
    // Estadísticas
    $totalMovimientos = count($movimientos);
    $ingresos = count(array_filter($movimientos, function($m) { return $m['tipo_movimiento'] === 'INGRESO'; }));
    $ventas = count(array_filter($movimientos, function($m) { return $m['tipo_movimiento'] === 'VENTA'; }));
    $ajustes = count(array_filter($movimientos, function($m) { return $m['tipo_movimiento'] === 'AJUSTE'; }));
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $movimientos = [];
    $totalMovimientos = 0;
    $ingresos = 0;
    $ventas = 0;
    $ajustes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Stock - Sistema de Gestión</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
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
        .stat-card.ingreso { border-color: #48bb78; }
        .stat-card.venta { border-color: #4299e1; }
        .stat-card.ajuste { border-color: #ed8936; }

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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f7fafc;
        }

        th, td {
            padding: 15px;
            text-align: left;
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

        .badge-ingreso { background: #c6f6d5; color: #22543d; }
        .badge-venta { background: #bee3f8; color: #2c5282; }
        .badge-ajuste { background: #feebc8; color: #744210; }
        .badge-devolucion { background: #e6f0ff; color: #2d3aa3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Historial de Movimientos de Stock</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-label">Total Movimientos</div>
                    <div class="stat-value"><?php echo $totalMovimientos; ?></div>
                </div>
                <div class="stat-card ingreso">
                    <div class="stat-label">Ingresos</div>
                    <div class="stat-value"><?php echo $ingresos; ?></div>
                </div>
                <div class="stat-card venta">
                    <div class="stat-label">Ventas</div>
                    <div class="stat-value"><?php echo $ventas; ?></div>
                </div>
                <div class="stat-card ajuste">
                    <div class="stat-label">Ajustes</div>
                    <div class="stat-value"><?php echo $ajustes; ?></div>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px;">🔍 Filtros</h3>
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
                        <label>Producto</label>
                        <input type="text" name="producto" value="<?php echo htmlspecialchars($filtroProducto); ?>" placeholder="Nombre...">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <option value="INGRESO" <?php echo $filtroTipo === 'INGRESO' ? 'selected' : ''; ?>>Ingreso</option>
                            <option value="VENTA" <?php echo $filtroTipo === 'VENTA' ? 'selected' : ''; ?>>Venta</option>
                            <option value="AJUSTE" <?php echo $filtroTipo === 'AJUSTE' ? 'selected' : ''; ?>>Ajuste</option>
                            <option value="DEVOLUCION" <?php echo $filtroTipo === 'DEVOLUCION' ? 'selected' : ''; ?>>Devolución</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="movimientos.php" class="btn btn-secondary">Limpiar</a>
            </form>

            <!-- Tabla -->
            <div style="overflow-x: auto; border: 2px solid #e2e8f0; border-radius: 12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Detalle</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #a0aec0;">
                                    No se encontraron movimientos
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?php echo formatearFecha($mov['fecha']); ?></td>
                                    <td>
                                        <?php
                                            $badge = '';
                                            switch($mov['tipo_movimiento']) {
                                                case 'INGRESO':
                                                    $badge = '<span class="badge badge-ingreso">↑ Ingreso</span>';
                                                    break;
                                                case 'VENTA':
                                                    $badge = '<span class="badge badge-venta">↓ Venta</span>';
                                                    break;
                                                case 'AJUSTE':
                                                    $badge = '<span class="badge badge-ajuste">⚙ Ajuste</span>';
                                                    break;
                                                case 'DEVOLUCION':
                                                    $badge = '<span class="badge badge-devolucion">↩ Devolución</span>';
                                                    break;
                                            }
                                            echo $badge;
                                        ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mov['producto_nombre']); ?></strong>
                                        <?php if ($mov['codigo_producto']): ?>
                                            <br><small><?php echo $mov['codigo_producto']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo $mov['cantidad']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($mov['detalle'] ?: '-'); ?></td>
                                    <td><?php echo $mov['usuario_nombre'] ?: 'Sistema'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>