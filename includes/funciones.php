<?php
// =============================================
// includes/funciones.php
// Funciones Globales del Sistema - ACTUALIZADO
// =============================================

// Función para iniciar sesión segura
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS
        
        session_name(SESSION_NAME);
        session_start();
        
        if (!isset($_SESSION['iniciada'])) {
            $_SESSION['iniciada'] = true;
            $_SESSION['tiempo_inicio'] = time();
        }
        
        // Verificar tiempo de sesión
        if (isset($_SESSION['tiempo_inicio']) && 
            (time() - $_SESSION['tiempo_inicio'] > SESSION_LIFETIME)) {
            cerrarSesion();
            return false;
        }
        
        $_SESSION['tiempo_inicio'] = time();
    }
    return true;
}

// Función para cerrar sesión
function cerrarSesion() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        
        if (isset($_COOKIE[SESSION_NAME])) {
            setcookie(SESSION_NAME, '', time() - 3600, SESSION_PATH);
        }
        
        session_destroy();
    }
}

// Función para verificar si el usuario está logueado
function estaLogueado() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_nombre']);
}

// Función para verificar permisos
function tienePermiso($modulo, $accion) {
    if (!estaLogueado()) {
        return false;
    }
    
    // El administrador tiene todos los permisos
    if ($_SESSION['rol_id'] == 1) {
        return true;
    }
    
    // Verificar permisos específicos
    if (isset($_SESSION['permisos'])) {
        $permisoRequerido = $modulo . '.' . $accion;
        return in_array($permisoRequerido, $_SESSION['permisos']);
    }
    
    return false;
}

// Función para requerir permiso (redirige si no tiene permiso)
function requierePermiso($modulo, $accion) {
    if (!tienePermiso($modulo, $accion)) {
        $_SESSION['error'] = 'No tiene permisos para acceder a esta sección';
        redirigir('index.php');
        exit();
    }
}

// Función para verificar si es administrador
function esAdministrador() {
    return isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == 1;
}

// Función para redirigir
function redirigir($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

// Función para mostrar alertas
function mostrarAlerta($tipo, $mensaje) {
    $iconos = [
        'success' => 'check-circle',
        'error' => 'x-circle',
        'warning' => 'alert-triangle',
        'info' => 'info'
    ];
    
    $colores = [
        'success' => 'success',
        'error' => 'danger',
        'warning' => 'warning',
        'info' => 'info'
    ];
    
    $icono = $iconos[$tipo] ?? 'info';
    $color = $colores[$tipo] ?? 'info';
    
    return "
    <div class='alert alert-{$color} alert-dismissible fade show' role='alert'>
        <i class='bi bi-{$icono}-fill me-2'></i>
        {$mensaje}
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}

// Función para establecer alertas en sesión
function setAlerta($tipo, $mensaje) {
    $_SESSION['alerta'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

// Función para obtener y limpiar alerta
function getAlerta() {
    if (isset($_SESSION['alerta'])) {
        $alerta = $_SESSION['alerta'];
        unset($_SESSION['alerta']);
        return $alerta;
    }
    return null;
}

// Función para mostrar alerta guardada
function mostrarAlertaGuardada() {
    $alerta = getAlerta();
    if ($alerta) {
        return mostrarAlerta($alerta['tipo'], $alerta['mensaje']);
    }
    return '';
}

// Función para validar y limpiar datos - CORREGIDA
function limpiarDatos($dato) {
    // Manejar valores null o vacíos
    if ($dato === null || $dato === '') {
        return '';
    }
    
    // Convertir a string si no lo es
    $dato = (string)$dato;
    
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');
    return $dato;
}

// Función para validar y limpiar datos (ALIAS) - CORREGIDA
function limpiarInput($dato) {
    // Manejar valores null o vacíos
    if ($dato === null || $dato === '') {
        return '';
    }
    
    // Convertir a string si no lo es
    $dato = (string)$dato;
    
    return limpiarDatos($dato);
}

// Función para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para hashear contraseñas
function hashPassword($password) {
    return password_hash($password, HASH_ALGORITHM, ['cost' => HASH_COST]);
}

// Función para verificar contraseñas
function verificarPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Función para formatear fecha
function formatearFecha($fecha, $formato = 'd/m/Y') {
    if (empty($fecha)) return '-';
    $dt = new DateTime($fecha);
    return $dt->format($formato);
}

// Función para formatear fecha con hora
function formatearFechaHora($fecha, $formato = 'd/m/Y H:i') {
    if (empty($fecha)) return '-';
    $dt = new DateTime($fecha);
    return $dt->format($formato);
}

// Función para formatear moneda
function formatearMoneda($monto) {
    // Manejar valores null, vacíos o no numéricos
    if ($monto === null || $monto === '' || !is_numeric($monto)) {
        return '$ 0,00';
    }
    
    return '$ ' . number_format((float)$monto, 2, ',', '.');
}

// Función para formatear número
function formatearNumero($numero, $decimales = 0) {
    // Manejar valores null, vacíos o no numéricos
    if ($numero === null || $numero === '' || !is_numeric($numero)) {
        return '0';
    }
    
    return number_format((float)$numero, $decimales, ',', '.');
}

// Función para generar token único
function generarToken($longitud = 32) {
    return bin2hex(random_bytes($longitud));
}

// Función para registrar en auditoría
function registrarAuditoria($modulo, $accion, $descripcion) {
    if (!estaLogueado()) return false;
    
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        
        $sql = "INSERT INTO auditoria (id_usuario, modulo, accion, descripcion, ip_address) 
                VALUES (?, ?, ?, ?, ?)";
        
        $db->insert($sql, [
            $_SESSION['usuario_id'],
            $modulo,
            $accion,
            $descripcion,
            $ip
        ]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Función para generar breadcrumb
function generarBreadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">';
    
    $total = count($items);
    $contador = 1;
    
    foreach ($items as $nombre => $url) {
        if ($contador == $total) {
            $html .= '<li class="breadcrumb-item active">' . $nombre . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . BASE_URL . $url . '">' . $nombre . '</a></li>';
        }
        $contador++;
    }
    
    $html .= '</ol></nav>';
    return $html;
}

// Función para calcular paginación
function calcularPaginacion($total_registros, $pagina_actual = 1, $registros_por_pagina = null) {
    if ($registros_por_pagina === null) {
        $registros_por_pagina = defined('REGISTROS_POR_PAGINA') ? REGISTROS_POR_PAGINA : 20;
    }
    
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    $pagina_actual = max(1, min($pagina_actual, $total_paginas));
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    
    return [
        'total_registros' => $total_registros,
        'total_paginas' => $total_paginas,
        'pagina_actual' => $pagina_actual,
        'registros_por_pagina' => $registros_por_pagina,
        'offset' => $offset
    ];
}

// Función para generar paginación HTML
function generarPaginacion($total_paginas, $pagina_actual, $url_base) {
    if ($total_paginas <= 1) return '';
    
    $html = '<nav><ul class="pagination justify-content-center">';
    
    // Botón anterior
    if ($pagina_actual > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $url_base . '&pagina=' . ($pagina_actual - 1) . '">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>';
    } else {
        $html .= '<li class="page-item disabled">
                    <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                  </li>';
    }
    
    // Números de página
    $rango = 2;
    $inicio = max(1, $pagina_actual - $rango);
    $fin = min($total_paginas, $pagina_actual + $rango);
    
    if ($inicio > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url_base . '&pagina=1">1</a></li>';
        if ($inicio > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $inicio; $i <= $fin; $i++) {
        $active = ($i == $pagina_actual) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">
                    <a class="page-link" href="' . $url_base . '&pagina=' . $i . '">' . $i . '</a>
                  </li>';
    }
    
    if ($fin < $total_paginas) {
        if ($fin < $total_paginas - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $url_base . '&pagina=' . $total_paginas . '">' . $total_paginas . '</a></li>';
    }
    
    // Botón siguiente
    if ($pagina_actual < $total_paginas) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $url_base . '&pagina=' . ($pagina_actual + 1) . '">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                  </li>';
    } else {
        $html .= '<li class="page-item disabled">
                    <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                  </li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// Función para obtener IP del cliente
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    }
}

// Función para validar DNI argentino
function validarDNI($dni) {
    $dni = preg_replace('/[^0-9]/', '', $dni);
    return strlen($dni) >= 7 && strlen($dni) <= 8;
}

// Función para validar CUIT/CUIL
function validarCuit1($cuit) {
    $cuit = preg_replace('/[^0-9]/', '', $cuit);
    
    if (strlen($cuit) != 11) {
        return false;
    }
    
    if (!ctype_digit($cuit)) {
        return false;
    }
    
    $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    
    for ($i = 0; $i < 10; $i++) {
        $suma += (int)$cuit[$i] * $multiplicadores[$i];
    }
    
    $resto = $suma % 11;
    $digitoVerificador = 11 - $resto;
    
    if ($digitoVerificador == 11) {
        $digitoVerificador = 0;
    } elseif ($digitoVerificador == 10) {
        $digitoVerificador = 9;
    }
    
    return (int)$cuit[10] === $digitoVerificador;
}

// Función para validar CUIT (alias)
function validarCUIT($cuit) {
    return validarCuit1($cuit);
}

// Función para formatear CUIT
function formatearCuit($cuit) {
    $cuit = preg_replace('/[^0-9]/', '', $cuit);
    if (strlen($cuit) != 11) return $cuit;
    return substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10, 1);
}

// Función para subir archivo
function subirArchivo($archivo, $carpeta = 'general') {
    if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        return ['success' => false, 'mensaje' => 'No se recibió ningún archivo'];
    }
    
    if ($archivo['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'mensaje' => 'El archivo es demasiado grande'];
    }
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'mensaje' => 'Tipo de archivo no permitido'];
    }
    
    $rutaDestino = UPLOAD_PATH . $carpeta . '/';
    if (!file_exists($rutaDestino)) {
        mkdir($rutaDestino, 0755, true);
    }
    
    $nombreArchivo = time() . '_' . uniqid() . '.' . $extension;
    $rutaCompleta = $rutaDestino . $nombreArchivo;
    
    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return [
            'success' => true, 
            'archivo' => $nombreArchivo, 
            'ruta' => $rutaCompleta,
            'url' => BASE_URL . 'uploads/' . $carpeta . '/' . $nombreArchivo
        ];
    }
    
    return ['success' => false, 'mensaje' => 'Error al subir el archivo'];
}

// Función para debug
function debug($variable, $salir = false) {
    if (MODO_DEBUG) {
        echo '<pre>';
        print_r($variable);
        echo '</pre>';
        
        if ($salir) {
            exit();
        }
    }
}
?>
