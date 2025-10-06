<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('reportes_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Obtener fecha actual y rangos
$hoy = date('Y-m-d');
$inicioMes = date('Y-m-01');
$finMes = date('Y-m-t');
$inicioAnio = date('Y-01-01');

try {
    $db = getDB();
    
    // === ESTADÍSTICAS DE VENTAS ===
    // Ventas del mes actual
    $sqlVentasMes = "SELECT 
                        COUNT(*) as total_ventas,
                        COALESCE(SUM(total), 0) as monto_total,
                        COALESCE(AVG(total), 0) as ticket_promedio
                     FROM Ventas 
                     WHERE DATE(fecha) BETWEEN :inicio AND :fin";
    $stmtVentasMes = $db->prepare($sqlVentasMes);
    $stmtVentasMes->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
    $ventasMes = $stmtVentasMes->fetch();
    
    // Ventas de hoy
    $sqlVentasHoy = "SELECT 
                        COUNT(*) as total_ventas,
                        COALESCE(SUM(total), 0) as monto_total
                     FROM Ventas 
                     WHERE DATE(fecha) = :hoy";
    $stmtVentasHoy = $db->prepare($sqlVentasHoy);
    $stmtVentasHoy->execute([':hoy' => $hoy]);
    $ventasHoy = $stmtVentasHoy->fetch();
    
    // Ventas del año
    $sqlVentasAnio = "SELECT COALESCE(SUM(total), 0) as monto_total
                      FROM Ventas 
                      WHERE YEAR(fecha) = YEAR(CURDATE())";
    $stmtVentasAnio = $db->query($sqlVentasAnio);
    $ventasAnio = $stmtVentasAnio->fetch();
    
    // === PRODUCTOS ===
    $sqlProductos = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos
                     FROM Productos";
    $stmtProductos = $db->query($sqlProductos);
    $productos = $stmtProductos->fetch();
    
    // Productos con stock bajo
    $sqlStockBajo = "SELECT COUNT(DISTINCT p.id_producto) as total
                     FROM Productos p
                     LEFT JOIN Stock s ON p.id_producto = s.id_producto
                     GROUP BY p.id_producto
                     HAVING COALESCE(SUM(s.cantidad), 0) <= p.stock_minimo";
    $stmtStockBajo = $db->query($sqlStockBajo);
    $stockBajo = $stmtStockBajo->rowCount();
    
    // === CLIENTES ===
    $sqlClientes = "SELECT COUNT(*) as total FROM Clientes WHERE activo = 1";
    $stmtClientes = $db->query($sqlClientes);
    $clientes = $stmtClientes->fetch();
    
    // === TOP 5 PRODUCTOS MÁS VENDIDOS (MES ACTUAL) ===
    $sqlTopProductos = "SELECT 
                            p.nombre,
                            SUM(dv.cantidad) as cantidad_vendida,
                            SUM(dv.subtotal) as monto_total
                        FROM Detalle_Venta dv
                        INNER JOIN Productos p ON dv.id_producto = p.id_producto
                        INNER JOIN Ventas v ON dv.id_venta = v.id_venta
                        WHERE DATE(v.fecha) BETWEEN :inicio AND :fin
                        GROUP BY p.id_producto
                        ORDER BY cantidad_vendida DESC
                        LIMIT 5";
    $stmtTopProductos = $db->prepare($sqlTopProductos);
    $stmtTopProductos->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
    $topProductos = $stmtTopProductos->fetchAll();
    
    // === ÚLTIMAS 5 VENTAS ===
    $sqlUltimasVentas = "SELECT 
                            v.id_venta,
                            v.fecha,
                            v.numero_comprobante,
                            v.total,
                            CONCAT(c.nombre, ' ', c.apellido) as cliente
                         FROM Ventas v
                         INNER JOIN Clientes c ON v.id_cliente = c.id_cliente
                         ORDER BY v.fecha DESC
                         LIMIT 5";
    $stmtUltimasVentas = $db->query($sqlUltimasVentas);
    $ultimasVentas = $stmtUltimasVentas->fetchAll();
    
    // === VENTAS POR DÍA (ÚLTIMOS 7 DÍAS) ===
    $sqlVentasDiarias = "SELECT 
                            DATE(fecha) as fecha,
                            COUNT(*) as cantidad,
                            SUM(total) as monto
                         FROM Ventas
                         WHERE DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                         GROUP BY DATE(fecha)
                         ORDER BY fecha ASC";
    $stmtVentasDiarias = $db->query($sqlVentasDiarias);
    $ventasDiarias = $stmtVentasDiarias->fetchAll();
    
    // === VENTAS POR FORMA DE PAGO (MES ACTUAL) ===
    $sqlFormasPago = "SELECT 
                        forma_pago,
                        COUNT(*) as cantidad,
                        SUM(total) as monto
                      FROM Ventas
                      WHERE DATE(fecha) BETWEEN :inicio AND :fin
                      AND forma_pago IS NOT NULL
                      GROUP BY forma_pago
                      ORDER BY monto DESC";
    $stmtFormasPago = $db->prepare($sqlFormasPago);
    $stmtFormasPago->execute([':inicio' => $inicioMes, ':fin' => $finMes]);
    $formasPago = $stmtFormasPago->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error en reportes: " . $e->getMessage());
    $ventasMes = ['total_ventas' => 0, 'monto_total' => 0, 'ticket_promedio' => 0];
    $ventasHoy = ['total_ventas' => 0, 'monto_total' => 0];
    $ventasAnio = ['monto_total' => 0];
    $productos = ['total' => 0, 'activos' => 0];
    $stockBajo = 0;
    $clientes = ['total' => 0];
    $topProductos = [];
    $ultimasVentas = [];
    $ventasDiarias = [];
    $formasPago = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Gestión</title>
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
            max-width: 1800px;
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

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .stat-card.ventas { border-color: #48bb78; }
        .stat-card.productos { border-color: #ed8936; }
        .stat-card.clientes { border-color: #4299e1; }
        .stat-card.alertas { border-color: #f56565; }
        .stat-card.anio { border-color: #9f7aea; }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-subtitle {
            font-size: 12px;
            color: #a0aec0;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .report-card {
            background: #f7fafc;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .report-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .report-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-description {
            font-size: 14px;
            color: #718096;
            margin-bottom: 15px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Centro de Reportes</h1>
            <div class="header-actions">
                <a href="ventas.php" class="btn btn-primary">📈 Ventas</a>
                <a href="productos.php" class="btn btn-primary">📦 Productos</a>
                <a href="stock.php" class="btn btn-primary">📊 Stock</a>
                <a href="clientes.php" class="btn btn-primary">👥 Clientes</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <!-- Estadísticas Principales -->
            <div class="stats-grid">
                <div class="stat-card ventas">
                    <div class="stat-icon">💰</div>
                    <div class="stat-label">Ventas de Hoy</div>
                    <div class="stat-value"><?php echo formatearMoneda($ventasHoy['monto_total']); ?></div>
                    <div class="stat-subtitle"><?php echo $ventasHoy['total_ventas']; ?> transacciones</div>
                </div>

                <div class="stat-card ventas">
                    <div class="stat-icon">📅</div>
                    <div class="stat-label">Ventas del Mes</div>
                    <div class="stat-value"><?php echo formatearMoneda($ventasMes['monto_total']); ?></div>
                    <div class="stat-subtitle"><?php echo $ventasMes['total_ventas']; ?> ventas | Ticket: <?php echo formatearMoneda($ventasMes['ticket_promedio']); ?></div>
                </div>

                <div class="stat-card anio">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Ventas del Año</div>
                    <div class="stat-value"><?php echo formatearMoneda($ventasAnio['monto_total']); ?></div>
                    <div class="stat-subtitle"><?php echo date('Y'); ?></div>
                </div>

                <div class="stat-card productos">
                    <div class="stat-icon">📦</div>
                    <div class="stat-label">Productos Activos</div>
                    <div class="stat-value"><?php echo $productos['activos']; ?></div>
                    <div class="stat-subtitle">de <?php echo $productos['total']; ?> totales</div>
                </div>

                <div class="stat-card clientes">
                    <div class="stat-icon">👥</div>
                    <div class="stat-label">Clientes Activos</div>
                    <div class="stat-value"><?php echo $clientes['total']; ?></div>
                    <div class="stat-subtitle">en el sistema</div>
                </div>

                <div class="stat-card alertas">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-label">Stock Bajo</div>
                    <div class="stat-value"><?php echo $stockBajo; ?></div>
                    <div class="stat-subtitle">productos con alerta</div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="charts-grid">
                <!-- Ventas por Día -->
                <div class="chart-card">
                    <div class="chart-title">📈 Ventas Últimos 7 Días</div>
                    <canvas id="chartVentasDiarias"></canvas>
                </div>

                <!-- Formas de Pago -->
                <div class="chart-card">
                    <div class="chart-title">💳 Ventas por Forma de Pago (Este Mes)</div>
                    <canvas id="chartFormasPago"></canvas>
                </div>
            </div>

            <!-- Top Productos -->
            <div class="table-container">
                <div class="table-header">
                    <h3>🏆 Top 5 Productos Más Vendidos (Este Mes)</h3>
                </div>
                <?php if (empty($topProductos)): ?>
                    <div class="empty-state">
                        <p>No hay datos de productos vendidos este mes</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th style="text-align: right;">Cantidad Vendida</th>
                                <th style="text-align: right;">Monto Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProductos as $index => $prod): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                    <td style="text-align: right;"><strong><?php echo $prod['cantidad_vendida']; ?></strong></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($prod['monto_total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Últimas Ventas -->
            <div class="table-container">
                <div class="table-header">
                    <h3>🕒 Últimas 5 Ventas</h3>
                </div>
                <?php if (empty($ultimasVentas)): ?>
                    <div class="empty-state">
                        <p>No hay ventas registradas</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Cliente</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasVentas as $venta): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                    <td><?php echo $venta['numero_comprobante'] ?: '-'; ?></td>
                                    <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Accesos Rápidos a Reportes -->
            <h2 style="margin-bottom: 20px; color: #2d3748;">📑 Reportes Detallados</h2>
            <div class="reports-grid">
                <div class="report-card">
                    <div class="report-title">📈 Reporte de Ventas</div>
                    <div class="report-description">Análisis completo de ventas por período, vendedor, forma de pago y más.</div>
                    <a href="ventas.php" class="btn btn-primary">Ver Reporte</a>
                </div>

                <div class="report-card">
                    <div class="report-title">📦 Reporte de Productos</div>
                    <div class="report-description">Productos más vendidos, menos vendidos y análisis de categorías.</div>
                    <a href="productos.php" class="btn btn-primary">Ver Reporte</a>
                </div>

                <div class="report-card">
                    <div class="report-title">📊 Reporte de Stock</div>
                    <div class="report-description">Estado del inventario, productos con stock bajo y valorización.</div>
                    <a href="stock.php" class="btn btn-primary">Ver Reporte</a>
                </div>

                <div class="report-card">
                    <div class="report-title">👥 Reporte de Clientes</div>
                    <div class="report-description">Clientes más frecuentes, histórico de compras y análisis.</div>
                    <a href="clientes.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para gráfico de ventas diarias
        const ventasDiariasData = <?php echo json_encode($ventasDiarias); ?>;
        const labels = ventasDiariasData.map(v => {
            const fecha = new Date(v.fecha + 'T00:00:00');
            return fecha.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' });
        });
        const montos = ventasDiariasData.map(v => parseFloat(v.monto));

        // Gráfico de Ventas Diarias
        const ctxDiarias = document.getElementById('chartVentasDiarias').getContext('2d');
        new Chart(ctxDiarias, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ventas ($)',
                    data: montos,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Datos para gráfico de formas de pago
        const formasPagoData = <?php echo json_encode($formasPago); ?>;
        const formasLabels = formasPagoData.map(f => {
            const nombres = {
                'efectivo': 'Efectivo',
                'tarjeta_debito': 'T. Débito',
                'tarjeta_credito': 'T. Crédito',
                'transferencia': 'Transferencia',
                'otro': 'Otro'
            };
            return nombres[f.forma_pago] || f.forma_pago;
        });
        const formasMontos = formasPagoData.map(f => parseFloat(f.monto));

        // Gráfico de Formas de Pago
        const ctxFormas = document.getElementById('chartFormasPago').getContext('2d');
        new Chart(ctxFormas, {
            type: 'doughnut',
            data: {
                labels: formasLabels,
                datasets: [{
                    data: formasMontos,
                    backgroundColor: [
                        '#48bb78',
                        '#4299e1',
                        '#ed8936',
                        '#9f7aea',
                        '#f56565'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>