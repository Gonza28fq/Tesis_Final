<?php
session_start();

require_once 'config/conexion.php';

// Registrar logout en auditoría si hay sesión activa
if (isset($_SESSION['id_vendedor'])) {
    registrarAuditoria($_SESSION['id_vendedor'], 'logout');
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
?>