<?php
require_once 'config/conexion.php';
validarSesion();

// Obtener estadísticas básicas
try {
    $db = getDB();
    
    // Total de ventas del mes actual
    $sqlVentasMes = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto
                     FROM Ventas 
                     WHERE MONTH(fecha) = MONTH(CURRENT_DATE()) 
                     AND YEAR(fecha) = YEAR(CURRENT_DATE())";
    $stmtVentas = $db->query($sqlVentasMes);
    $ventasMes = $stmtVentas->fetch();
    
    // Productos con stock bajo
    $sqlStockBajo = "SELECT COUNT(*) as total
                     FROM vista_productos_stock
                     WHERE estado_stock = 'BAJO' AND activo = 1";
    $stmtStock = $db->query($sqlStockBajo);
    $stockBajo = $stmtStock->fetch();
    
    // Total de clientes activos
    $sqlClientes = "SELECT COUNT(*) as total FROM Clientes WHERE activo = 1";
    $stmtClientes = $db->query($sqlClientes);
    $clientes = $stmtClientes->fetch();
    
    // Últimas 5 ventas
    $sqlUltimasVentas = "SELECT 
                            v.id_venta,
                            v.fecha,
                            v.numero_comprobante,
                            v.tipo_comprobante,
                            CONCAT(c.nombre, ' ', c.apellido) as cliente,
                            v.total
                         FROM Ventas v
                         INNER JOIN Clientes c ON v.id_cliente = c.id_cliente
                         ORDER BY v.fecha DESC
                         LIMIT 5";
    $stmtUltimas = $db->query($sqlUltimasVentas);
    $ultimasVentas = $stmtUltimas->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $ventasMes = ['total' => 0, 'monto' => 0];
    $stockBajo = ['total' => 0];
    $clientes = ['total' => 0];
    $ultimasVentas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Gestión Comercial</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
        }

        /* NAVEGACIÓN */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .nav-brand {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-menu {
            display: flex;
            gap: 5px;
            list-style: none;
        }

        .nav-item a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item a:hover {
            background: rgba(255,255,255,0.2);
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.9;
        }

        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }

        /* CONTENIDO */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .welcome-section h1 {
            font-size: 32px;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .welcome-section p {
            color: #718096;
            font-size: 16px;
        }

        /* TARJETAS DE ESTADÍSTICAS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 5px solid;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-card.ventas { border-color: #48bb78; }
        .stat-card.stock { border-color: #ed8936; }
        .stat-card.clientes { border-color: #4299e1; }
        .stat-card.productos { border-color: #9f7aea; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            font-size: 36px;
            opacity: 0.8;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-footer {
            font-size: 13px;
            color: #a0aec0;
        }

        /* MÓDULOS */
        .modules-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .module-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 2px solid transparent;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .module-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .module-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .module-description {
            font-size: 14px;
            color: #718096;
            line-height: 1.6;
        }

        /* TABLA DE ÚLTIMAS VENTAS */
        .recent-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: #f7fafc;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px;
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

        .badge-a { background: #c6f6d5; color: #22543d; }
        .badge-b { background: #bee3f8; color: #2c5282; }
        .badge-c { background: #feebc8; color: #744210; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .container {
                padding: 0 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .modules-grid {
                grid-template-columns: 1fr;
            }

            .user-info {
                display: none;
            }
        }

        /* MENÚ MÓVIL */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- NAVEGACIÓN -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                🏪 Gestión Comercial
            </div>

            <ul class="nav-menu">
                <?php if (tienePermiso('ventas_ver') || tienePermiso('ventas_crear')): ?>
                    <li class="nav-item">
                        <a href="modules/ventas/index.php">💰 Ventas</a>
                    </li>
                <?php endif; ?>

                <?php if (tienePermiso('compras_ver') || tienePermiso('compras_crear')): ?>
                    <li class="nav-item">
                        <a href="modules/compras/index.php">🛒 Compras</a>
                    </li>
                <?php endif; ?>

                <?php if (tienePermiso('stock_ver')): ?>
                    <li class="nav-item">
                        <a href="modules/stock/index.php">📦 Stock</a>
                    </li>
                <?php endif; ?>

                <?php if (tienePermiso('productos_ver')): ?>
                    <li class="nav-item">
                        <a href="modules/productos/index.php">📂 Productos</a>
                    </li>
                <?php endif; ?>

                <?php if (tienePermiso('clientes_ver')): ?>
                    <li class="nav-item">
                        <a href="modules/clientes/index.php">👥 Clientes</a>
                    </li>
                <?php endif; ?>

                <?php if (tienePermiso('reportes_ver')): ?>
                    <li class="nav-item">
                        <a href="modules/reportes/index.php">📊 Reportes</a>
                    </li>
                <?php endif; ?>

                <?php if (tienePermiso('usuarios_gestionar')): ?>
                    <li class="nav-item">
                        <a href="modules/usuarios/index.php">⚙️ Usuarios</a>
                    </li>
                <?php endif; ?>
            </ul>

            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>

            <div class="nav-user">
                <div class="user-info">
                    <div class="user-name">
                        <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?>
                    </div>
                    <div class="user-role"><?php echo $_SESSION['rol']; ?></div>
                </div>
                <a href="logout.php" class="btn-logout">🚪 Salir</a>
            </div>
        </div>
    </nav>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="container">
        <!-- BIENVENIDA -->
        <div class="welcome-section">
            <h1>¡Bienvenido, <?php echo $_SESSION['nombre']; ?>! 👋</h1>
            <p>Gestiona tu negocio desde el panel de control</p>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card ventas">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Ventas del Mes</div>
                        <div class="stat-value"><?php echo $ventasMes['total']; ?></div>
                        <div class="stat-footer">
                            Total: <?php echo formatearMoneda($ventasMes['monto']); ?>
                        </div>
                    </div>
                    <div class="stat-icon">💰</div>
                </div>
            </div>

            <div class="stat-card stock">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Productos con Stock Bajo</div>
                        <div class="stat-value"><?php echo $stockBajo['total']; ?></div>
                        <div class="stat-footer">Requieren atención</div>
                    </div>
                    <div class="stat-icon">⚠️</div>
                </div>
            </div>

            <div class="stat-card clientes">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Clientes Activos</div>
                        <div class="stat-value"><?php echo $clientes['total']; ?></div>
                        <div class="stat-footer">Base de datos</div>
                    </div>
                    <div class="stat-icon">👥</div>
                </div>
            </div>

            <div class="stat-card productos">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Promedio Venta</div>
                        <div class="stat-value">
                            <?php 
                                $promedio = $ventasMes['total'] > 0 ? $ventasMes['monto'] / $ventasMes['total'] : 0;
                                echo formatearMoneda($promedio); 
                            ?>
                        </div>
                        <div class="stat-footer">Por transacción</div>
                    </div>
                    <div class="stat-icon">📈</div>
                </div>
            </div>
        </div>

        <!-- MÓDULOS DISPONIBLES -->
        <div class="modules-section">
            <h2 class="section-title">📋 Módulos Disponibles</h2>
            <div class="modules-grid">
                <?php if (tienePermiso('ventas_crear') || tienePermiso('ventas_ver')): ?>
                    <a href="modules/ventas/index.php" class="module-card">
                        <div class="module-icon">💰</div>
                        <div class="module-title">Módulo de Ventas</div>
                        <div class="module-description">
                            Gestiona las ventas, emite comprobantes y lleva un control completo de las transacciones comerciales.
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (tienePermiso('compras_crear') || tienePermiso('compras_ver')): ?>
                    <a href="modules/compras/index.php" class="module-card">
                        <div class="module-icon">🛒</div>
                        <div class="module-title">Módulo de Compras</div>
                        <div class="module-description">
                            Registra compras a proveedores y actualiza el inventario automáticamente.
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (tienePermiso('stock_ver')): ?>
                    <a href="modules/stock/index.php" class="module-card">
                        <div class="module-icon">📦</div>
                        <div class="module-title">Módulo de Stock</div>
                        <div class="module-description">
                            Controla el inventario, realiza ajustes y mantén actualizado el stock de productos.
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (tienePermiso('clientes_ver')): ?>
                    <a href="modules/clientes/index.php" class="module-card">
                        <div class="module-icon">👥</div>
                        <div class="module-title">Módulo de Clientes</div>
                        <div class="module-description">
                            Administra la base de datos de clientes y consulta su historial de compras.
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (tienePermiso('productos_ver')): ?>
                    <a href="modules/productos/index.php" class="module-card">
                        <div class="module-icon">📦</div>
                        <div class="module-title">Módulo de Productos</div>
                        <div class="module-description">
                            Gestiona el catálogo de productos, precios, categorías y proveedores.
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (tienePermiso('reportes_ver')): ?>
                    <a href="modules/reportes/index.php" class="module-card">
                        <div class="module-icon">📊</div>
                        <div class="module-title">Módulo de Reportes</div>
                        <div class="module-description">
                            Analiza estadísticas, genera reportes y obtén insights de tu negocio.
                        </div>
                    </a>
                <?php endif; ?>

                <?php if (tienePermiso('usuarios_gestionar')): ?>
                    <a href="modules/usuarios/index.php" class="module-card">
                        <div class="module-icon">⚙️</div>
                        <div class="module-title">Gestión de Usuarios</div>
                        <div class="module-description">
                            Administra usuarios, roles y permisos del sistema.
                        </div>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ÚLTIMAS VENTAS -->
        <?php if (tienePermiso('ventas_ver') && !empty($ultimasVentas)): ?>
            <div class="recent-section">
                <h2 class="section-title">📋 Últimas Ventas</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasVentas as $venta): ?>
                            <tr>
                                <td><?php echo formatearFecha($venta['fecha']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($venta['tipo_comprobante']); ?>">
                                        <?php echo $venta['numero_comprobante']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($venta['cliente']); ?></td>
                                <td><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                                <td>
                                    <a href="modules/ventas/generar_pdf.php?id_venta=<?php echo $venta['id_venta']; ?>" 
                                       target="_blank" 
                                       style="color: #667eea; text-decoration: none;">
                                        📄 Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="modules/ventas/historial.php" style="color: #667eea; text-decoration: none; font-weight: 500;">
                        Ver todas las ventas →
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleMobileMenu() {
            const menu = document.querySelector('.nav-menu');
            if (menu.style.display === 'flex') {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'flex';
                menu.style.flexDirection = 'column';
                menu.style.position = 'absolute';
                menu.style.top = '70px';
                menu.style.left = '0';
                menu.style.right = '0';
                menu.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                menu.style.padding = '20px';
                menu.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
            }
        }
    </script>
</body>
</html>