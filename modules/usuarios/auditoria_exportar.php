<?php
// =============================================
// modules/usuarios/auditoria_exportar.php
// Exportar Logs de Auditoría
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('auditoria', 'ver');

$db = getDB();

// Obtener filtros
$usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$modulo = isset($_GET['modulo']) ? limpiarInput($_GET['modulo']) : '';
$accion = isset($_GET['accion']) ? limpiarInput($_GET['accion']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$formato = isset($_GET['formato']) ? limpiarInput($_GET['formato']) : 'excel';

// Construir filtros (igual que en index)
$where = ['1=1'];
$params = [];

if ($usuario > 0) {
    $where[] = 'a.id_usuario = ?';
    $params[] = $usuario;
}

if (!empty($modulo)) {
    $where[] = 'a.modulo = ?';
    $params[] = $modulo;
}

if (!empty($accion)) {
    $where[] = 'a.accion = ?';
    $params[] = $accion;
}

if (!empty($fecha_desde)) {
    $where[] = 'DATE(a.fecha_hora) >= ?';
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where[] = 'DATE(a.fecha_hora) <= ?';
    $params[] = $fecha_hasta;
}

if (!empty($buscar)) {
    $where[] = '(a.descripcion LIKE ? OR a.ip_address LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$where_clause = implode(' AND ', $where);

// Consulta completa
$sql = "SELECT a.*, u.nombre_completo, u.usuario
        FROM auditoria a
        INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
        WHERE $where_clause
        ORDER BY a.fecha_hora DESC";

$registros = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

if (empty($registros)) {
    setAlerta('warning', 'No hay registros para exportar');
    redirigir('auditoria.php');
}

// Registrar la exportación en auditoría (meta-auditoría)
registrarAuditoria('auditoria', 'exportar', 
    "Exportación de " . count($registros) . " registros en formato $formato");

$fecha = date('Y-m-d_H-i-s');
$nombre_archivo = "auditoria_$fecha";

// Exportar según formato
if ($formato === 'csv') {
    // ==================== CSV ====================
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    // Encabezados
    fputcsv($output, [
        'ID',
        'Fecha/Hora',
        'Usuario',
        'Nombre Completo',
        'Módulo',
        'Acción',
        'Descripción',
        'Dirección IP'
    ], ';');
    
    // Datos
    foreach ($registros as $reg) {
        fputcsv($output, [
            $reg['id_auditoria'],
            date('d/m/Y H:i:s', strtotime($reg['fecha_hora'])),
            $reg['usuario'],
            $reg['nombre_completo'],
            $reg['modulo'],
            $reg['accion'],
            $reg['descripcion'],
            $reg['ip_address']
        ], ';');
    }
    
    fclose($output);
    exit;

} elseif ($formato === 'pdf') {
    // ==================== PDF ====================
    // Nota: Requiere librería TCPDF o similar
    // Por simplicidad, aquí generamos HTML con estilos para imprimir
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Auditoría del Sistema</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            h1 { text-align: center; color: #333; }
            .info { background: #f0f0f0; padding: 10px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #4472C4; color: white; padding: 8px; text-align: left; }
            td { padding: 6px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background: #f9f9f9; }
            .badge { padding: 2px 6px; border-radius: 3px; font-size: 9pt; }
            .badge-success { background: #28a745; color: white; }
            .badge-warning { background: #ffc107; }
            .badge-danger { background: #dc3545; color: white; }
            .badge-info { background: #17a2b8; color: white; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>';
    
    echo '<div class="no-print" style="text-align: center; margin: 20px;">
        <button onclick="window.print()" class="btn">Imprimir/Guardar como PDF</button>
    </div>';
    
    echo '<h1>Logs de Auditoría del Sistema</h1>';
    echo '<div class="info">';
    echo '<strong>Fecha de Generación:</strong> ' . date('d/m/Y H:i:s') . '<br>';
    echo '<strong>Usuario:</strong> ' . obtenerUsuarioActual()['nombre_completo'] . '<br>';
    echo '<strong>Período:</strong> ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)) . '<br>';
    echo '<strong>Total de Registros:</strong> ' . count($registros);
    echo '</div>';
    
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>Fecha/Hora</th>';
    echo '<th>Usuario</th>';
    echo '<th>Módulo</th>';
    echo '<th>Acción</th>';
    echo '<th>Descripción</th>';
    echo '<th>IP</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($registros as $reg) {
        $badge_color = [
            'crear' => 'success',
            'actualizar' => 'warning',
            'eliminar' => 'danger',
            'ver' => 'info'
        ];
        $color = $badge_color[$reg['accion']] ?? 'info';
        
        echo '<tr>';
        echo '<td>' . date('d/m/Y H:i', strtotime($reg['fecha_hora'])) . '</td>';
        echo '<td>' . htmlspecialchars($reg['nombre_completo']) . '</td>';
        echo '<td>' . ucfirst($reg['modulo']) . '</td>';
        echo '<td><span class="badge badge-' . $color . '">' . ucfirst($reg['accion']) . '</span></td>';
        echo '<td>' . htmlspecialchars($reg['descripcion']) . '</td>';
        echo '<td>' . htmlspecialchars($reg['ip_address']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    exit;

} else {
    // ==================== EXCEL (HTML) ====================
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.xls"');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; font-family: Arial; font-size: 10pt; }';
    echo 'th { background-color: #4472C4; color: white; font-weight: bold; padding: 8px; border: 1px solid #ccc; }';
    echo 'td { padding: 6px; border: 1px solid #ccc; }';
    echo '.crear { background-color: #C6EFCE; color: #006100; }';
    echo '.actualizar { background-color: #FFEB9C; color: #9C6500; }';
    echo '.eliminar { background-color: #FFC7CE; color: #9C0006; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>Logs de Auditoría del Sistema</h2>';
    echo '<p><strong>Fecha de Exportación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p><strong>Usuario:</strong> ' . obtenerUsuarioActual()['nombre_completo'] . '</p>';
    echo '<p><strong>Período:</strong> ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)) . '</p>';
    echo '<p><strong>Total de Registros:</strong> ' . count($registros) . '</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Fecha/Hora</th>';
    echo '<th>Usuario</th>';
    echo '<th>Nombre Completo</th>';
    echo '<th>Módulo</th>';
    echo '<th>Acción</th>';
    echo '<th>Descripción</th>';
    echo '<th>Dirección IP</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($registros as $reg) {
        $clase = $reg['accion'];
        echo '<tr>';
        echo '<td>' . $reg['id_auditoria'] . '</td>';
        echo '<td>' . date('d/m/Y H:i:s', strtotime($reg['fecha_hora'])) . '</td>';
        echo '<td>' . htmlspecialchars($reg['usuario']) . '</td>';
        echo '<td>' . htmlspecialchars($reg['nombre_completo']) . '</td>';
        echo '<td>' . htmlspecialchars($reg['modulo']) . '</td>';
        echo '<td class="' . $clase . '">' . ucfirst($reg['accion']) . '</td>';
        echo '<td>' . htmlspecialchars($reg['descripcion']) . '</td>';
        echo '<td>' . htmlspecialchars($reg['ip_address']) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}