<?php
// Verificar sesión activa
if (!isset($_SESSION['id_vendedor'])) {
    header('Location: /login.php');
    exit;
}

$nombre_usuario = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
$rol_usuario = $_SESSION['nombre_rol'] ?? 'Usuario';
?>

<style>
    .main-nav {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .nav-container {
        max-width: 1600px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .nav-brand {
        color: white;
        font-size: 24px;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .nav-menu {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .nav-link {
        color: white;
        text-decoration: none;
        padding: 8px 15px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        background: rgba(255,255,255,0.1);
    }
    
    .nav-link:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .nav-user {
        display: flex;
        align-items: center;
        gap: 15px;
        color: white;
    }
    
    .user-info {
        text-align: right;
        font-size: 13px;
    }
    
    .user-name {
        font-weight: 600;
    }
    
    .user-role {
        opacity: 0.8;
        font-size: 11px;
    }
    
    .btn-logout {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 8px 15px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-logout:hover {
        background: rgba(255,255,255,0.3);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .main-nav {
            padding: 10px 15px;
        }
        
        .nav-container {
            flex-direction: column;
            gap: 10px;
        }
        
        .nav-menu {
            width: 100%;
            justify-content: center;
        }
        
        .nav-link {
            flex: 1;
            text-align: center;
        }
    }
</style>

<nav class="main-nav">
    <div class="nav-container">
        <a href="/index.php" class="nav-brand">
            🏪 Sistema de Gestión
        </a>
        
        <div class="nav-menu">
            <a href="/index.php" class="nav-link">🏠 Inicio</a>
            
            <?php if (tienePermiso('ventas_ver')): ?>
                <a href="/modules/ventas/index.php" class="nav-link">💰 Ventas</a>
            <?php endif; ?>
            
            <?php if (tienePermiso('compras_ver')): ?>
                <a href="/modules/compras/index.php" class="nav-link">📦 Compras</a>
            <?php endif; ?>
            
            <?php if (tienePermiso('stock_ver')): ?>
                <a href="/modules/stock/index.php" class="nav-link">📊 Stock</a>
            <?php endif; ?>
            
            <?php if (tienePermiso('productos_ver')): ?>
                <a href="/modules/productos/index.php" class="nav-link">🏷️ Productos</a>
            <?php endif; ?>
            
            <?php if (tienePermiso('clientes_ver')): ?>
                <a href="/modules/clientes/index.php" class="nav-link">👥 Clientes</a>
            <?php endif; ?>
            
            <?php if (tienePermiso('reportes_ver')): ?>
                <a href="/modules/reportes/index.php" class="nav-link">📈 Reportes</a>
            <?php endif; ?>
            
            <?php if (tienePermiso('usuarios_gestionar')): ?>
                <a href="/modules/usuarios/index.php" class="nav-link">⚙️ Usuarios</a>
            <?php endif; ?>
        </div>
        
        <div class="nav-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></div>
            </div>
            <a href="/logout.php" class="btn-logout">🚪 Salir</a>
        </div>
    </div>
</nav>