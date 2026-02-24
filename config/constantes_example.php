<?php
// =============================================
// config/constantes.php
// Constantes del Sistema
// =============================================

// Información del sistema
define('NOMBRE_SISTEMA', 'Sistema de Gestión Comercial 2.0');
define('VERSION_SISTEMA', '2.0.0');
define('EMPRESA', 'Tu Empresa');

define('DB_HOST', 'localhost');
define('DB_NAME', 'nombre_de_tu_base_datos');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
define('DB_CHARSET', 'utf8mb4');
// Configuración de sesión
define('SESSION_NAME', 'GC_SESSION');
define('SESSION_LIFETIME', 3600); // 1 hora en segundos
define('SESSION_PATH', '/');

// Configuración de seguridad
define('HASH_ALGORITHM', PASSWORD_BCRYPT);
define('HASH_COST', 10);

// Rutas del sistema
define('BASE_URL', 'http://localhost/proyectov2/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('MODULES_URL', BASE_URL . 'modules/');

// Configuración de paginación
define('REGISTROS_POR_PAGINA', 20);

// Configuración de email (para recuperación de contraseña)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_email@gmail.com');
define('SMTP_PASS', 'tu_password_email');
define('SMTP_FROM', 'noreply@sistema.com');
define('SMTP_FROM_NAME', NOMBRE_SISTEMA);

// Configuración de archivos
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Estados del sistema
define('ESTADO_ACTIVO', 'activo');
define('ESTADO_INACTIVO', 'inactivo');

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Mostrar errores (solo en desarrollo)
define('MODO_DEBUG', true);
if (MODO_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}