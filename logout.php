<?php
require_once 'config/constantes.php';
require_once 'config/conexion.php';
require_once 'includes/funciones.php';

iniciarSesion();

// Registrar el cierre de sesión en auditoría antes de destruir la sesión
if (estaLogueado()) {
    registrarAuditoria('usuarios', 'logout', 'Cierre de sesión');
}

// Cerrar sesión
cerrarSesion();

// Redirigir al login
header('Location: ' . BASE_URL . 'login.php');
exit();