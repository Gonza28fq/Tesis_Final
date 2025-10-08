<style>
    .main-footer {
        background: #2d3748;
        color: #a0aec0;
        padding: 20px;
        margin-top: 40px;
        text-align: center;
        font-size: 14px;
    }
    
    .footer-content {
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .footer-links {
        margin-top: 10px;
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .footer-link {
        color: #cbd5e0;
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .footer-link:hover {
        color: white;
    }
    
    @media (max-width: 768px) {
        .main-footer {
            padding: 15px;
            font-size: 12px;
        }
    }
</style>

<footer class="main-footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión Comercial. Todos los derechos reservados.</p>
        
        <div class="footer-links">
            <a href="/index.php" class="footer-link">Inicio</a>
            <span>|</span>
            <?php if (tienePermiso('reportes_ver')): ?>
                <a href="/modules/reportes/index.php" class="footer-link">Reportes</a>
                <span>|</span>
            <?php endif; ?>
            <?php if (tienePermiso('usuarios_gestionar')): ?>
                <a href="/modules/usuarios/index.php" class="footer-link">Usuarios</a>
                <span>|</span>
            <?php endif; ?>
            <a href="/logout.php" class="footer-link">Cerrar Sesión</a>
        </div>
        
        <p style="margin-top: 10px; font-size: 12px;">
            Usuario: <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Invitado'); ?> | 
            Versión: 1.0.0
        </p>
    </div>
</footer>

</body>
</html>