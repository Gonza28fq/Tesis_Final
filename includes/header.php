<?php
// Verificar que haya sesión iniciada
if (!estaLogueado()) {
    redirigir('login.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina . ' - ' : ''; ?><?php echo NOMBRE_SISTEMA; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>img/favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/styles.css">
    
    <!-- jQuery (necesario para Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fa;
        }
        
        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1050;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h4 {
            margin: 10px 0 5px 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .sidebar-header small {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
        }
        
        .sidebar.collapsed .sidebar-header h4,
        .sidebar.collapsed .sidebar-header small {
            display: none;
        }
        
        .sidebar-menu {
            padding: 15px 0;
            list-style: none;
            margin: 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
        }
        
        .sidebar-menu li a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu li a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 4px solid #fff;
        }
        
        .sidebar-menu li a i {
            margin-right: 12px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .sidebar.collapsed .sidebar-menu li a span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-menu li a {
            justify-content: center;
            padding: 12px;
        }
        
        /* =============================================
           MAIN CONTENT
        ============================================= */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* =============================================
           TOP NAVBAR
        ============================================= */
        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .page-content {
            padding: 30px;
            flex: 1;
        }
        
        /* =============================================
           OVERLAY PARA MÓVIL
        ============================================= */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        /* =============================================
           CARDS Y COMPONENTES
        ============================================= */
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 8px;
            margin-bottom: 24px;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 6px;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .badge {
            padding: 6px 12px;
            font-weight: 500;
            border-radius: 4px;
        }
        
        /* =============================================
           RESPONSIVE DESIGN
        ============================================= */
        
        /* Tablets y móviles (hasta 992px) */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .top-navbar {
                padding: 10px 15px;
            }
            
            .page-content {
                padding: 20px 15px;
            }
        }
        
        /* Móviles (hasta 768px) */
        @media (max-width: 768px) {
            .page-content {
                padding: 15px 10px;
            }
            
            .card-header {
                padding: 15px;
                font-size: 0.95rem;
            }
            
            .card-body {
                padding: 15px;
            }
            
            /* Tablas responsive */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table thead {
                display: none;
            }
            
            .table,
            .table tbody,
            .table tr,
            .table td {
                display: block;
                width: 100%;
            }
            
            .table tr {
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                padding: 0.5rem;
                background: white;
            }
            
            .table td {
                text-align: right;
                padding: 0.5rem;
                position: relative;
                padding-left: 50%;
                border: none;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .table td:last-child {
                border-bottom: none;
            }
            
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 0.5rem;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: #6c757d;
            }
            
            /* Botones en móvil */
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                border-radius: 0.375rem !important;
                margin-bottom: 0.5rem;
            }
        }
        
        /* Móviles pequeños (hasta 576px) */
        @media (max-width: 576px) {
            .top-navbar {
                padding: 8px 10px;
            }
            
            .sidebar-header h4 {
                font-size: 1rem;
            }
            
            .user-name-desktop {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-shop" style="font-size: 2rem;"></i>
                <h4><?php echo NOMBRE_SISTEMA; ?></h4>
                <small>v<?php echo VERSION_SISTEMA; ?></small>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="<?php echo BASE_URL; ?>index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && !strpos($_SERVER['REQUEST_URI'], 'modules') ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (tienePermiso('clientes', 'ver')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>clientes/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'clientes') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-people"></i>
                        <span>Clientes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (tienePermiso('productos', 'ver')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>productos/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'productos') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam"></i>
                        <span>Productos</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (tienePermiso('stock', 'ver')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>stock/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'stock') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-boxes"></i>
                        <span>Stock</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (tienePermiso('ventas', 'ver')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>ventas/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'ventas') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-cart-check"></i>
                        <span>Ventas</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (tienePermiso('compras', 'ver')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>compras/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'compras') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-bag"></i>
                        <span>Compras</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (tienePermiso('reportes', 'ventas')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>reportes/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'reportes') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-graph-up"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (tienePermiso('usuarios', 'ver')): ?>
                <li>
                    <a href="<?php echo MODULES_URL; ?>usuarios/index.php" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'usuarios') !== false ? 'active' : ''; ?>">
                        <i class="bi bi-person-badge"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </aside>
        
        <!-- Overlay para cerrar sidebar en móvil -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        
        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="d-flex align-items-center">
                    <!-- Botón hamburguesa para móvil -->
                    <button class="btn btn-link text-dark p-0 me-3 d-lg-none" id="mobile-menu-toggle">
                        <i class="bi bi-list" style="font-size: 1.5rem;"></i>
                    </button>
                    
                    <!-- Botón toggle para desktop -->
                    <button class="btn btn-link text-dark p-0 me-3 d-none d-lg-block" id="sidebarToggle">
                        <i class="bi bi-list" style="font-size: 1.5rem;"></i>
                    </button>
                    
                    <?php if (isset($breadcrumb)): ?>
                        <?php echo generarBreadcrumb($breadcrumb); ?>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted d-none d-md-inline user-name-desktop">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo $_SESSION['usuario_nombre']; ?>
                    </span>
                    
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="d-md-none">
                                <span class="dropdown-item-text">
                                    <i class="bi bi-person-circle me-1"></i>
                                    <strong><?php echo $_SESSION['usuario_nombre']; ?></strong>
                                </span>
                            </li>
                            <li class="d-md-none"><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>perfil.php">
                                <i class="bi bi-person"></i> Mi Perfil
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>configuracion.php">
                                <i class="bi bi-sliders"></i> Configuración
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Page Content -->
            <div class="page-content">
                <?php
                // Mostrar alertas si existen
                if (isset($_SESSION['alerta'])) {
                    $alerta = $_SESSION['alerta'];
                    echo '<div class="alert alert-' . $alerta['tipo'] . ' alert-dismissible fade show" role="alert">';
                    echo '<i class="bi bi-' . ($alerta['tipo'] == 'success' ? 'check-circle' : ($alerta['tipo'] == 'danger' ? 'x-circle' : 'info-circle')) . ' me-2"></i>';
                    echo $alerta['mensaje'];
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    echo '</div>';
                    unset($_SESSION['alerta']);
                }
                ?>
