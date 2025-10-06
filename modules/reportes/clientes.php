<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('reportes_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Filtros
$fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : date('Y-m-d');
$tipo_cliente = isset($_GET['tipo_cliente']) ? sanitize($_GET['tipo_cliente']) : '';

try {
    $db = getDB();
    
    // === TOP CLIENTES POR COMPRAS ===
    $sqlTopClientes = "SELECT 
                            c.id_cliente,
                            CONCAT(c.nombre, ' ', c.apellido) as cliente,
                            c.email,
                            c.tipo_cliente,
                            COUNT(v.id_venta) as total_compras,
                            SUM(v.total) as monto_total,
                            AVG(v.total) as ticket_promedio,
                            MAX(v.fecha) as ultima_compra
                       FROM Clientes c
                       INNER JOIN Ventas v ON c.id_cliente = v.id_cliente
                       WHERE DATE(v.fecha) BETWEEN :desde AND :hasta";
    
    $params = [':desde' => $fecha_desde, ':hasta' => $fecha_hasta];
    
    if (!empty($tipo_cliente)) {
        $sqlTopClientes .= " AND c.tipo_cliente = :tipo";
        $params[':tipo'] = $tipo_cliente;
    }
    
    $sqlTopClientes .= " GROUP BY c.id_cliente
                         ORDER BY monto_total DESC
                         LIMIT 20";
    
    $stmtTop = $db->prepare($sqlTopClientes);
    $stmtTop->execute($params);
    $topClientes = $stmtTop->fetchAll();
    
    // === CLIENTES FRECUENTES (MÁS DE 5 COMPRAS) ===
    $sqlFrecuentes = "SELECT 
                            c.id_cliente,
                            CONCAT(c.nombre, ' ', c.apellido) as cliente,
                            COUNT(v.id_venta) as total_compras,
                            SUM(v.total) as monto_total,
                            MAX(v.fecha) as ultima_compra
                       FROM Clientes c
                       INNER JOIN Ventas v ON c.id_cliente = v.id_cliente
                       WHERE DATE(v.fecha) BETWEEN :desde AND :hasta";
    
    if (!empty($tipo_cliente)) {
        $sqlFrecuentes .= " AND c.tipo_cliente = :tipo";
    }
    
    $sqlFrecuentes .= " GROUP BY c.id_cliente
                        HAVING total_compras >= 5
                        ORDER BY total_compras DESC";
    
    $stmtFrec = $db->prepare($sqlFrecuentes);
    $stmtFrec->execute($params);
    $clientesFrecuentes = $stmtFrec->fetchAll();
    
    // === CLIENTES INACTIVOS (SIN COMPRAS EN EL PERÍODO) ===
    $sqlInactivos = "SELECT 
                        c.id_cliente,
                        CONCAT(c.nombre, ' ', c.apellido) as cliente,
                        c.email,
                        c.telefono,
                        MAX(v.fecha) as ultima_compra,
                        DATEDIFF(CURDATE(), MAX(v.fecha)) as dias_inactivo
                     FROM Clientes c
                     LEFT JOIN Ventas v ON c.id_cliente = v.id_cliente
                     WHERE c.activo = 1
                     GROUP BY c.id_cliente
                     HAVING ultima_compra < :desde OR ultima_compra IS NULL
                     ORDER BY dias_inactivo DESC
                     LIMIT 20";
    
    $stmtInact = $db->prepare($sqlInactivos);
    $stmtInact->execute([':desde' => $fecha_desde]);
    $clientesInactivos = $stmtInact->fetchAll();
    
    // === VENTAS POR TIPO DE CLIENTE ===
    $sqlTipoCliente = "SELECT 
                            c.tipo_cliente,
                            COUNT(DISTINCT c.id_cliente) as cantidad_clientes,
                            COUNT(v.id_venta) as total_ventas,
                            SUM(v.total) as monto_total
                       FROM Clientes c
                       INNER JOIN Ventas v ON c.id_cliente = v.id_cliente
                       WHERE DATE(v.fecha) BETWEEN :desde AND :hasta
                       GROUP BY c.tipo_cliente
                       ORDER BY monto_total DESC";
    
    $stmtTipo = $db->prepare($sqlTipoCliente);
    $stmtTipo->execute($params);
    $ventasTipoCliente = $stmtTipo->fetchAll();
    
    // === ESTADÍSTICAS GENERALES ===
    $sqlStats = "SELECT 
                    COUNT(DISTINCT c.id_cliente) as clientes_activos,
                    COUNT(v.id_venta) as total_ventas,
                    SUM(v.total) as monto_total,
                    AVG(v.total) as ticket_promedio
                 FROM Clientes c
                 INNER JOIN Ventas v ON c.id_cliente = v.id_cliente
                 WHERE DATE(v.fecha) BETWEEN :desde AND :hasta";
    
    if (!empty($tipo_cliente)) {
        $sqlStats .= " AND c.tipo_cliente = :tipo";
    }
    
    $stmtStats = $db->prepare($sqlStats);
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error en reporte de clientes: " . $e->getMessage());
    $topClientes = [];
    $clientesFrecuentes = [];
    $clientesInactivos = [];
    $ventasTipoCliente = [];
    $stats = ['clientes_activos' => 0, 'total_ventas' => 0, 'monto_total' => 0, 'ticket_promedio' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Clientes - Sistema de Gestión</title>
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

        .stat-card.clientes { border-color: #4299e1; }
        .stat-card.ventas { border-color: #48bb78; }
        .stat-card.monto { border-color: #9f7aea; }
        .stat-card.ticket { border-color: #ed8936; }

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

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            margin-bottom: 20px;
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
        }

        .table-header h3 {
            font-size: 16px;
            color: #2d3748;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-cf { background: #c6f6d5; color: #22543d; }
        .badge-ri { background: #bee3f8; color: #2c5282; }
        .badge-mono { background: #feebc8; color: #7c2d12; }
        .badge-exento { background: #e2e8f0; color: #4a5568; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Reporte de Clientes</h1>
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
                        <label>Tipo de Cliente</label>
                        <select name="tipo_cliente">
                            <option value="">Todos</option>
                            <option value="consumidor_final" <?php echo $tipo_cliente === 'consumidor_final' ? 'selected' : ''; ?>>Consumidor Final</option>
                            <option value="responsable_inscripto" <?php echo $tipo_cliente === 'responsable_inscripto' ? 'selected' : ''; ?>>Responsable Inscripto</option>
                            <option value="monotributista" <?php echo $tipo_cliente === 'monotributista' ? 'selected' : ''; ?>>Monotributista</option>
                            <option value="exento" <?php echo $tipo_cliente === 'exento' ? 'selected' : ''; ?>>Exento</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="clientes.php" class="btn btn-secondary">Limpiar</a>
                    <button type="button" class="btn btn-success" onclick="window.print()">🖨️ Imprimir</button>
                </div>
            </form>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card clientes">
                    <div class="stat-label">Clientes con Compras</div>
                    <div class="stat-value"><?php echo $stats['clientes_activos']; ?></div>
                </div>

                <div class="stat-card ventas">
                    <div class="stat-label">Total Ventas</div>
                    <div class="stat-value"><?php echo $stats['total_ventas']; ?></div>
                </div>

                <div class="stat-card monto">
                    <div class="stat-label">Monto Total</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['monto_total']); ?></div>
                </div>

                <div class="stat-card ticket">
                    <div class="stat-label">Ticket Promedio</div>
                    <div class="stat-value"><?php echo formatearMoneda($stats['ticket_promedio']); ?></div>
                </div>
            </div>

            <!-- Gráfico de Ventas por Tipo de Cliente -->
            <?php if (!empty($ventasTipoCliente)): ?>
            <div class="chart-card">
                <div class="chart-title">📊 Ventas por Tipo de Cliente</div>
                <canvas id="chartTipoCliente"></canvas>
            </div>
            <?php endif; ?>

            <!-- Top 20 Clientes -->
            <div class="table-container">
                <div class="table-header">
                    <h3>🏆 Top 20 Clientes por Monto</h3>
                </div>
                <?php if (empty($topClientes)): ?>
                    <div class="empty-state">
                        <h3>No hay datos</h3>
                        <p>No se encontraron clientes con compras en este período</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th style="text-align: right;">Compras</th>
                                <th style="text-align: right;">Monto Total</th>
                                <th style="text-align: right;">Ticket Prom.</th>
                                <th>Última Compra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topClientes as $index => $cliente): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($cliente['cliente']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'consumidor_final' => '<span class="badge badge-cf">CF</span>',
                                            'responsable_inscripto' => '<span class="badge badge-ri">RI</span>',
                                            'monotributista' => '<span class="badge badge-mono">Mono</span>',
                                            'exento' => '<span class="badge badge-exento">Exento</span>'
                                        ];
                                        echo $badges[$cliente['tipo_cliente']] ?? '';
                                        ?>
                                    </td>
                                    <td style="text-align: right;"><?php echo $cliente['total_compras']; ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($cliente['monto_total']); ?></strong></td>
                                    <td style="text-align: right;"><?php echo formatearMoneda($cliente['ticket_promedio']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($cliente['ultima_compra'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Clientes Frecuentes -->
            <?php if (!empty($clientesFrecuentes)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3>⭐ Clientes Frecuentes (5+ compras)</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th style="text-align: right;">Total Compras</th>
                            <th style="text-align: right;">Monto Total</th>
                            <th>Última Compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientesFrecuentes as $cliente): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cliente['cliente']); ?></strong></td>
                                <td style="text-align: right;"><strong><?php echo $cliente['total_compras']; ?></strong></td>
                                <td style="text-align: right;"><?php echo formatearMoneda($cliente['monto_total']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cliente['ultima_compra'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Clientes Inactivos -->
            <?php if (!empty($clientesInactivos)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3>⚠️ Clientes Inactivos</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Última Compra</th>
                            <th style="text-align: right;">Días Inactivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientesInactivos as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente['cliente']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono'] ?: '-'); ?></td>
                                <td><?php echo $cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'Nunca'; ?></td>
                                <td style="text-align: right;"><strong><?php echo $cliente['dias_inactivo']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Gráfico de Ventas por Tipo de Cliente
        <?php if (!empty($ventasTipoCliente)): ?>
        const ventasTipo = <?php echo json_encode($ventasTipoCliente); ?>;
        const labelsTipo = ventasTipo.map(t => {
            const nombres = {
                'consumidor_final': 'Consumidor Final',
                'responsable_inscripto': 'Resp. Inscripto',
                'monotributista': 'Monotributista',
                'exento': 'Exento'
            };
            return nombres[t.tipo_cliente] || t.tipo_cliente;
        });
        const montosTipo = ventasTipo.map(t => parseFloat(t.monto_total));

        new Chart(document.getElementById('chartTipoCliente'), {
            type: 'bar',
            data: {
                labels: labelsTipo,
                datasets: [{
                    label: 'Monto Total ($)',
                    data: montosTipo,
                    backgroundColor: ['#4299e1', '#48bb78', '#ed8936', '#9f7aea']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>