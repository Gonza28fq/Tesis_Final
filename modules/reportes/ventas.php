<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('reportes_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Obtener filtros
$fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : date('Y-m-d');
$id_vendedor = isset($_GET['vendedor']) ? intval($_GET['vendedor']) : 0;
$forma_pago = isset($_GET['forma_pago']) ? sanitize($_GET['forma_pago']) : '';

try {
    $db = getDB();
    
    // Obtener vendedores para filtro
    $sqlVendedores = "SELECT id_vendedor, nombre, apellido FROM Vendedores WHERE estado = 1 ORDER BY nombre";
    $stmtVendedores = $db->query($sqlVendedores);
    $vendedores = $stmtVendedores->fetchAll();
    
    // === ESTADÍSTICAS DEL PERÍODO ===
    $sql = "SELECT 
                COUNT(*) as total_ventas,
                COALESCE(SUM(total), 0) as monto_total,
                COALESCE(AVG(total), 0) as ticket_promedio,
                COALESCE(MAX(total), 0) as venta_mayor,
                COALESCE(MIN(total), 0) as venta_menor
            FROM Ventas
            WHERE DATE(fecha) BETWEEN :desde AND :hasta";
    
    $params = [':desde' => $fecha_desde, ':hasta' => $fecha_hasta];
    
    if ($id_vendedor > 0) {
        $sql .= " AND id_vendedor = :vendedor";
        $params[':vendedor'] = $id_vendedor;
    }
    
    if (!empty($forma_pago)) {
        $sql .= " AND forma_pago = :forma_pago";
        $params[':forma_pago'] = $forma_pago;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
    // === VENTAS POR DÍA ===
    $sqlDiarias = "SELECT 
                        DATE(fecha) as fecha,
                        COUNT(*) as cantidad,
                        SUM(total) as monto
                   FROM Ventas
                   WHERE DATE(fecha) BETWEEN :desde AND :hasta";
    
    if ($id_vendedor > 0) {
        $sqlDiarias .= " AND id_vendedor = :vendedor";
    }
    if (!empty($forma_pago)) {
        $sqlDiarias .= " AND forma_pago = :forma_pago";
    }
    
    $sqlDiarias .= " GROUP BY DATE(fecha) ORDER BY fecha ASC";
    
    $stmtDiarias = $db->prepare($sqlDiarias);
    $stmtDiarias->execute($params);
    $ventasDiarias = $stmtDiarias->fetchAll();
    
    // === VENTAS POR VENDEDOR ===
    $sqlVendedor = "SELECT 
                        CONCAT(vend.nombre, ' ', vend.apellido) as vendedor,
                        COUNT(*) as cantidad,
                        SUM(v.total) as monto
                    FROM Ventas v
                    INNER JOIN Vendedores vend ON v.id_vendedor = vend.id_vendedor
                    WHERE DATE(v.fecha) BETWEEN :desde AND :hasta";
    
    if ($id_vendedor > 0) {
        $sqlVendedor .= " AND v.id_vendedor = :vendedor";
    }
    if (!empty($forma_pago)) {
        $sqlVendedor .= " AND v.forma_pago = :forma_pago";
    }
    
    $sqlVendedor .= " GROUP BY v.id_vendedor ORDER BY monto DESC";
    
    $stmtVendedor = $db->prepare($sqlVendedor);
    $stmtVendedor->execute($params);
    $ventasPorVendedor = $stmtVendedor->fetchAll();
    
    // === VENTAS POR FORMA DE PAGO ===
    $sqlFormaPago = "SELECT 
                        forma_pago,
                        COUNT(*) as cantidad,
                        SUM(total) as monto
                     FROM Ventas
                     WHERE DATE(fecha) BETWEEN :desde AND :hasta
                     AND forma_pago IS NOT NULL";
    
    if ($id_vendedor > 0) {
        $sqlFormaPago .= " AND id_vendedor = :vendedor";
    }
    
    $sqlFormaPago .= " GROUP BY forma_pago ORDER BY monto DESC";
    
    $stmtFormaPago = $db->prepare($sqlFormaPago);
    $stmtFormaPago->execute($params);
    $ventasPorFormaPago = $stmtFormaPago->fetchAll();
    
    // === DETALLE DE VENTAS ===
    $sqlDetalle = "SELECT 
                        v.id_venta,
                        v.fecha,
                        v.numero_comprobante,
                        v.tipo_comprobante,
                        CONCAT(c.nombre, ' ', c.apellido) as cliente,
                        CONCAT(vend.nombre, ' ', vend.apellido) as vendedor,
                        v.forma_pago,
                        v.total
                   FROM Ventas v
                   INNER JOIN Clientes c ON v.id_cliente = c.id_cliente
                   INNER JOIN Vendedores vend ON v.id_vendedor = vend.id_vendedor
                   WHERE DATE(v.fecha) BETWEEN :desde AND :hasta";
    
    if ($id_vendedor > 0) {
        $sqlDetalle .= " AND v.id_vendedor = :vendedor";
    }
    if (!empty($forma_pago)) {
        $sqlDetalle .= " AND v.forma_pago = :forma_pago";
    }
    
    $sqlDetalle .= " ORDER BY v.fecha DESC LIMIT 100";
    
    $stmtDetalle = $db->prepare($sqlDetalle);
    $stmtDetalle->execute($params);
    $detalleVentas = $stmtDetalle->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error en reporte de ventas: " . $e->getMessage());
    $vendedores = [];
    $stats = ['total_ventas' => 0, 'monto_total' => 0, 'ticket_promedio' => 0, 'venta_mayor' => 0, 'venta_menor' => 0];
    $ventasDiarias = [];
    $ventasPorVendedor = [];
    $ventasPorFormaPago = [];
    $detalleVentas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas - Sistema de Gestión</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #48bb78;
            color: white;
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
            font-size: 13px;
            color: #4a5568;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
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

        .stat-card.total { border-color: #48bb78; }
        .stat-card.promedio { border-color: #4299e1; }
        .stat-card.mayor { border-color: #9f7aea; }
        .stat-card.menor { border-color: #ed8936; }
        .stat-card.cantidad { border-color: #f56565; }

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

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .table-header {
            background: #f7fafc;
            padding: 15px 20px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 16px;
            color: #2d3748;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f7fafc;
        }

        th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .charts-grid { grid-template-columns: 1fr; }
            .filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 Reporte de Ventas</h1>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="content">
            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo $fecha_desde; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Vendedor</label>
                        <select name="vendedor">
                            <option value="0">Todos</option>
                            <?php foreach ($vendedores as $v): ?>
                                <option value="<?php echo $v['id_vendedor']; ?>" <?php echo $id_vendedor == $v['id_vendedor'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['nombre'] . ' ' . $v['apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Forma de Pago</label>
                        <select name="forma_pago">
                            <option value="">Todas</option>
                            <option value="efectivo" <?php echo $forma_pago === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                            <option value="tarjeta_debito" <?php echo $forma_pago === 'tarjeta_debito' ? 'selected' : ''; ?>>Tarjeta Débito</option>
                            <option value="tarjeta_credito" <?php echo $forma_pago === 'tarjeta_credito' ? 'selected' : ''; ?>>Tarjeta Crédito</option>
                            <option value="transferencia" <?php echo $forma_pago === 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                            <option value="otro" <?php echo $forma_pago === 'otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="ventas.php" class="btn btn-secondary">Limpiar</a>
                    <button type="button" class="btn btn-success" onclick="exportarExcel()">📊 Exportar Excel</button>
                </div>
            </form>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-label">Monto Total</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['monto_total']); ?></div>
                </div>

                <div class="stat-card cantidad">
                    <div class="stat-label">Cantidad de Ventas</div>
                    <div class="stat-value"><?php echo $stats['total_ventas']; ?></div>
                </div>

                <div class="stat-card promedio">
                    <div class="stat-label">Ticket Promedio</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['ticket_promedio']); ?></div>
                </div>

                <div class="stat-card mayor">
                    <div class="stat-label">Venta Mayor</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['venta_mayor']); ?></div>
                </div>

                <div class="stat-card menor">
                    <div class="stat-label">Venta Menor</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['venta_menor']); ?></div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-title">📊 Ventas por Día</div>
                    <canvas id="chartVentasDiarias"></canvas>
                </div>

                <div class="chart-card">
                    <div class="chart-title">👤 Ventas por Vendedor</div>
                    <canvas id="chartVendedor"></canvas>
                </div>

                <div class="chart-card">
                    <div class="chart-title">💳 Ventas por Forma de Pago</div>
                    <canvas id="chartFormaPago"></canvas>
                </div>
            </div>

            <!-- Tabla de Ventas -->
            <div class="table-container">
                <div class="table-header">
                    <h3>📋 Detalle de Ventas (Últimas 100)</h3>
                    <span><?php echo count($detalleVentas); ?> registros</span>
                </div>
                <?php if (empty($detalleVentas)): ?>
                    <div class="empty-state">
                        <h3>No se encontraron ventas</h3>
                        <p>Ajuste los filtros para ver resultados</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Forma Pago</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalleVentas as $venta): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                    <td><?php echo $venta['tipo_comprobante'] . ' ' . ($venta['numero_comprobante'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                    <td><?php echo htmlspecialchars($venta['vendedor']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $venta['forma_pago'] ?: '-')); ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Ventas Diarias
        const ventasDiarias = <?php echo json_encode($ventasDiarias); ?>;
        const labelsDiarias = ventasDiarias.map(v => {
            const fecha = new Date(v.fecha + 'T00:00:00');
            return fecha.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' });
        });
        const montosDiarias = ventasDiarias.map(v => parseFloat(v.monto));

        new Chart(document.getElementById('chartVentasDiarias'), {
            type: 'bar',
            data: {
                labels: labelsDiarias,
                datasets: [{
                    label: 'Ventas ($)',
                    data: montosDiarias,
                    backgroundColor: '#48bb78'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Ventas por Vendedor
        const ventasVendedor = <?php echo json_encode($ventasPorVendedor); ?>;
        const labelsVendedor = ventasVendedor.map(v => v.vendedor);
        const montosVendedor = ventasVendedor.map(v => parseFloat(v.monto));

        new Chart(document.getElementById('chartVendedor'), {
            type: 'bar',
            data: {
                labels: labelsVendedor,
                datasets: [{
                    label: 'Ventas ($)',
                    data: montosVendedor,
                    backgroundColor: '#4299e1'
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });

        // Ventas por Forma de Pago
        const ventasFormaPago = <?php echo json_encode($ventasPorFormaPago); ?>;
        const labelsFormaPago = ventasFormaPago.map(v => {
            const nombres = {
                'efectivo': 'Efectivo',
                'tarjeta_debito': 'T. Débito',
                'tarjeta_credito': 'T. Crédito',
                'transferencia': 'Transferencia',
                'otro': 'Otro'
            };
            return nombres[v.forma_pago] || v.forma_pago;
        });
        const montosFormaPago = ventasFormaPago.map(v => parseFloat(v.monto));

        new Chart(document.getElementById('chartFormaPago'), {
            type: 'pie',
            data: {
                labels: labelsFormaPago,
                datasets: [{
                    data: montosFormaPago,
                    backgroundColor: ['#48bb78', '#4299e1', '#ed8936', '#9f7aea', '#f56565']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.open('exportar_ventas.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>