<?php
// =============================================
// cron/enviar_reportes.php
// Script CRON para Envío Automático de Reportes
// Ejecutar cada 15 minutos: */15 * * * * php /ruta/cron/enviar_reportes.php
// =============================================

// Permitir ejecución solo desde CLI o localhost
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die('Acceso denegado');
}


require_once __DIR__ . '/../../config/constantes.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../includes/funciones.php';

$db = getDB();
$pdo = $db->getConexion();

echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificación de reportes programados...\n";

// Obtener reportes que deben enviarse
$sql = "SELECT * FROM reportes_programados 
        WHERE activo = 1 
        AND (proximo_envio IS NULL OR proximo_envio <= NOW())
        ORDER BY proximo_envio ASC";

$reportes = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($reportes)) {
    echo "No hay reportes pendientes de envío.\n";
    exit(0);
}

echo "Encontrados " . count($reportes) . " reportes para enviar.\n\n";

foreach ($reportes as $reporte) {
    echo "Procesando: {$reporte['nombre']}...\n";
    
    try {
        // Calcular período del reporte
        $periodo = calcularPeriodoReporte($reporte['frecuencia']);
        
        // Generar el reporte según tipo
        $contenido_reporte = generarReporte($reporte['tipo_reporte'], $periodo['fecha_desde'], $periodo['fecha_hasta'], $reporte['formato'], $db);
        
        if ($contenido_reporte === false) {
            throw new Exception("Error al generar el reporte");
        }
        
        // Preparar destinatarios
        $destinatarios = array_map('trim', explode(',', $reporte['destinatarios']));
        
        // Enviar email
        $asunto = $reporte['nombre'] . ' - ' . date('d/m/Y');
        $mensaje = construirMensajeEmail($reporte, $periodo);
        
        $envio_exitoso = enviarEmailConAdjunto(
            $destinatarios,
            $asunto,
            $mensaje,
            $contenido_reporte['archivo'],
            $contenido_reporte['nombre_archivo']
        );
        
        if ($envio_exitoso) {
            // Actualizar registro
            $proximo_envio = calcularProximoEnvioReporte($reporte);
            
            $sql_update = "UPDATE reportes_programados 
                          SET ultimo_envio = NOW(), proximo_envio = ? 
                          WHERE id_reporte_programado = ?";
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([$proximo_envio, $reporte['id_reporte_programado']]);
            
            echo "✓ Reporte enviado exitosamente a " . count($destinatarios) . " destinatarios.\n";
            echo "  Próximo envío: $proximo_envio\n";
            
            // Limpiar archivo temporal
            if (file_exists($contenido_reporte['archivo'])) {
                unlink($contenido_reporte['archivo']);
            }
        } else {
            echo "✗ Error al enviar el email.\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Proceso completado.\n";

// ============================================
// FUNCIONES AUXILIARES
// ============================================

function calcularPeriodoReporte($frecuencia) {
    $fecha_hasta = date('Y-m-d');
    
    switch ($frecuencia) {
        case 'diario':
            $fecha_desde = date('Y-m-d', strtotime('-1 day'));
            $periodo_texto = 'Diario del ' . date('d/m/Y', strtotime($fecha_desde));
            break;
        case 'semanal':
            $fecha_desde = date('Y-m-d', strtotime('-7 days'));
            $periodo_texto = 'Semanal del ' . date('d/m/Y', strtotime($fecha_desde)) . ' al ' . date('d/m/Y');
            break;
        case 'mensual':
            $fecha_desde = date('Y-m-01', strtotime('-1 month'));
            $fecha_hasta = date('Y-m-t', strtotime('-1 month'));
            $periodo_texto = 'Mensual de ' . date('F Y', strtotime($fecha_desde));
            break;
        default:
            $fecha_desde = date('Y-m-01');
            $periodo_texto = 'Período actual';
    }
    
    return [
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
        'texto' => $periodo_texto
    ];
}

function generarReporte($tipo, $fecha_desde, $fecha_hasta, $formato, $db) {
    $nombre_archivo = "reporte_{$tipo}_" . date('Y-m-d_His') . "." . $formato;
    $ruta_archivo = __DIR__ . '/../temp/' . $nombre_archivo;
    
    // Crear directorio temp si no existe
    if (!is_dir(__DIR__ . '/../temp/')) {
        mkdir(__DIR__ . '/../temp/', 0755, true);
    }
    
    // Obtener datos según tipo de reporte
    if ($tipo == 'financiero') {
        $datos = obtenerDatosFinancieros($db, $fecha_desde, $fecha_hasta);
    } elseif ($tipo == 'stock') {
        $datos = obtenerDatosStock($db);
    } elseif ($tipo == 'ventas') {
        $datos = obtenerDatosVentas($db, $fecha_desde, $fecha_hasta);
    } else {
        return false;
    }
    
    // Generar archivo según formato
    if ($formato == 'excel') {
        generarExcel($datos, $tipo, $ruta_archivo);
    } else {
        generarPDF($datos, $tipo, $ruta_archivo, $fecha_desde, $fecha_hasta);
    }
    
    return [
        'archivo' => $ruta_archivo,
        'nombre_archivo' => $nombre_archivo
    ];
}

function generarExcel($datos, $tipo, $ruta) {
    // Generar Excel simple con HTML
    $html = '
    <html xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    </head>
    <body>
        <h1>Reporte ' . ucfirst($tipo) . '</h1>
        <table border="1">
            <tr><th>Concepto</th><th>Valor</th></tr>';
    
    foreach ($datos as $key => $value) {
        $html .= "<tr><td>$key</td><td>$value</td></tr>";
    }
    
    $html .= '</table></body></html>';
    
    file_put_contents($ruta, "\xEF\xBB\xBF" . $html);
    return true;
}

function generarPDF($datos, $tipo, $ruta, $fecha_desde, $fecha_hasta) {
    // Generar HTML para PDF (se puede usar con wkhtmltopdf o similar)
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; }
            th { background-color: #4CAF50; color: white; }
        </style>
    </head>
    <body>
        <h1>Reporte " . ucfirst($tipo) . "</h1>
        <p><strong>Período:</strong> " . formatearFecha($fecha_desde) . " al " . formatearFecha($fecha_hasta) . "</p>
        <table>
            <tr><th>Concepto</th><th>Valor</th></tr>";
    
    foreach ($datos as $key => $value) {
        $html .= "<tr><td>$key</td><td>$value</td></tr>";
    }
    
    $html .= "</table></body></html>";
    
    file_put_contents($ruta, $html);
    return true;
}

function obtenerDatosFinancieros($db, $fecha_desde, $fecha_hasta) {
    $sql = "SELECT 
        COUNT(*) as ventas,
        COALESCE(SUM(total), 0) as total_ventas
        FROM ventas 
        WHERE fecha_venta BETWEEN ? AND ? AND estado = 'completada'";
    $ventas = $db->query($sql, [$fecha_desde, $fecha_hasta])->fetch(PDO::FETCH_ASSOC);
    
    return [
        'Total Ventas' => $ventas['ventas'],
        'Monto Vendido' => formatearMoneda($ventas['total_ventas'])
    ];
}

function obtenerDatosStock($db) {
    $sql = "SELECT COUNT(*) as total FROM productos WHERE estado = 'activo'";
    $productos = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    
    return [
        'Total Productos' => $productos['total']
    ];
}

function obtenerDatosVentas($db, $fecha_desde, $fecha_hasta) {
    return obtenerDatosFinancieros($db, $fecha_desde, $fecha_hasta);
}

function construirMensajeEmail($reporte, $periodo) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Reporte Automático: {$reporte['nombre']}</h2>
        <p><strong>Tipo:</strong> " . ucfirst($reporte['tipo_reporte']) . "</p>
        <p><strong>Período:</strong> {$periodo['texto']}</p>
        <p><strong>Generado:</strong> " . date('d/m/Y H:i') . "</p>
        <hr>
        <p>Este es un reporte automático generado por el sistema.</p>
        <p>Encuentre el archivo adjunto en formato " . strtoupper($reporte['formato']) . ".</p>
    </body>
    </html>
    ";
}

function enviarEmailConAdjunto($destinatarios, $asunto, $mensaje, $archivo_adjunto, $nombre_archivo) {
    // Configurar PHPMailer o función mail() de PHP
    // Este es un ejemplo simplificado
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Sistema de Reportes <reportes@tuempresa.com>\r\n";
    
    // Por simplicidad, retornamos true
    // En producción, implementar envío real con PHPMailer
    
    foreach ($destinatarios as $email) {
        echo "  → Enviando a: $email\n";
        // mail($email, $asunto, $mensaje, $headers);
    }
    
    return true;
}

function calcularProximoEnvioReporte($reporte) {
    $ahora = new DateTime();
    $fecha_envio = new DateTime();
    
    list($hora, $minuto) = explode(':', $reporte['hora_envio']);
    $fecha_envio->setTime((int)$hora, (int)$minuto, 0);
    
    switch ($reporte['frecuencia']) {
        case 'diario':
            $fecha_envio->modify('+1 day');
            break;
        case 'semanal':
            $fecha_envio->modify('+7 days');
            break;
        case 'mensual':
            $fecha_envio->modify('+1 month');
            break;
    }
    
    return $fecha_envio->format('Y-m-d H:i:s');
}
?>