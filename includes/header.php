<?php
// Verificar sesión activa
if (!isset($_SESSION['id_vendedor'])) {
    header('Location: /login.php');
    exit;
}

// Calcular URL base relativa
$ruta_actual = $_SERVER['PHP_SELF'];
$nivel = substr_count($ruta_actual, '/') - 1;
$base_url = str_repeat('../', $nivel);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo ?? 'Sistema de Gestión Comercial'; ?></title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏪</text></svg>">
    
    <!-- Chart.js (si se necesita) -->
    <?php if (isset($incluir_charts) && $incluir_charts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    
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
            max-width: 1600px;
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
            color: white;
            text-decoration: none;
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

        @media (max-width: 768px) {
            .nav-menu { display: none; }
            .user-info { display: none; }
        }
    </style>
</head>
<body>

<!-- NAVEGACIÓN -->
<nav class="navbar">
    <div class="nav-container">
        <a href="<?php echo $base_url; ?>index.php" class="nav-brand">
            🏪 Gestión Comercial
        </a>

        <ul class="nav-menu">
            <?php if (tienePermiso('ventas_ver') || tienePermiso('ventas_crear')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/ventas/index.php">💰 Ventas</a>
                </li>
            <?php endif; ?>

            <?php if (tienePermiso('compras_ver') || tienePermiso('compras_crear')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/compras/index.php">🛒 Compras</a>
                </li>
            <?php endif; ?>

            <?php if (tienePermiso('stock_ver')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/stock/index.php">📦 Stock</a>
                </li>
            <?php endif; ?>

            <?php if (tienePermiso('productos_ver')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/productos/index.php">📂 Productos</a>
                </li>
            <?php endif; ?>

            <?php if (tienePermiso('clientes_ver')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/clientes/index.php">👥 Clientes</a>
                </li>
            <?php endif; ?>

            <?php if (tienePermiso('reportes_ver')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/reportes/index.php">📊 Reportes</a>
                </li>
            <?php endif; ?>

            <?php if (tienePermiso('usuarios_gestionar')): ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>modules/usuarios/index.php">⚙️ Usuarios</a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="nav-user">
            <div class="user-info">
                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>
                </div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['rol']); ?></div>
            </div>
            <a href="<?php echo $base_url; ?>logout.php" class="btn-logout">🚪 Salir</a>
        </div>
    </div>
</nav>