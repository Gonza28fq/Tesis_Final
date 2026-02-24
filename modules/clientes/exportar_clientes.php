<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('clientes_ver')) {
    die('Sin permisos');
}

try {
    $db = getDB();
    
    // Obtener los mismos filtros que en index.php
    $filtroBusqueda = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
    $filtroTipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
    $filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : 'activos';
    
    // Construir consulta
    $sql = "SELECT 
                c.nombre as 'Nombre',
                c.apellido as 'Apellido',
                c.email as 'Email',
                c.telefono as 'Teléfono',
                c.dni_cuit as 'DNI/CUIT',
                c.direccion as 'Dirección',
                CASE c.tipo_cliente
                    WHEN 'consumidor_final' THEN 'Consumidor Final'
                    WHEN 'responsable_inscripto' THEN 'Responsable Inscripto'
                    WHEN 'monotributista' THEN 'Monotributista'
                    WHEN 'exento' THEN 'Exento'
                END as 'Tipo',
                COUNT(DISTINCT v.id_venta) as 'Total Compras',
                COALESCE(SUM(v.total), 0) as 'Total Gastado',
                MAX(v.fecha) as 'Última Compra',
                CASE WHEN c.activo = 1 THEN 'Activo' ELSE 'Inactivo' END as 'Estado',
                DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') as 'Fecha Registro'
            FROM Clientes c
            LEFT JOIN Ventas v ON c.id_cliente = v.id_cliente
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtroBusqueda)) {
        $sql .= " AND (c.nombre LIKE :busqueda1 
                  OR c.apellido LIKE :busqueda2 
                  OR c.email LIKE :busqueda3 
                  OR c.dni_cuit LIKE :busqueda4)";
        $searchTerm = "%{$filtroBusqueda}%";
        $params[':busqueda1'] = $searchTerm;
        $params[':busqueda2'] = $searchTerm;
        $params[':busqueda3'] = $searchTerm;
        $params[':busqueda4'] = $searchTerm;
    }
    
    if (!empty($filtroTipo)) {
        $sql .= " AND c.tipo_cliente = :tipo";
        $params[':tipo'] = $filtroTipo;
    }
    
    if ($filtroEstado === 'activos') {
        $sql .= " AND c.activo = 1";
    } elseif ($filtroEstado === 'inactivos') {
        $sql .= " AND c.activo = 0";
    }
    
    $sql .= " GROUP BY c.id_cliente
              ORDER BY c.fecha_registro DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar CSV (compatible con Excel)
    $filename = "clientes_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Agregar BOM UTF-8 para que Excel lo reconozca correctamente
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Escribir encabezado
    if (!empty($clientes)) {
        fputcsv($output, array_keys($clientes[0]), ';');
        
        // Escribir datos
        foreach ($clientes as $row) {
            fputcsv($output, $row, ';');
        }
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Error al exportar: " . $e->getMessage());
    die('Error al generar el archivo');
}
?>