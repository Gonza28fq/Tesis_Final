<?php
// =============================================
// modules/productos/exportar.php
// Exportar Productos a Excel/CSV
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'exportar');

$db = getDB();

// Obtener filtros de la URL
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : '';
$formato = isset($_GET['formato']) ? limpiarInput($_GET['formato']) : 'excel';

// Construir filtros (igual que en index.php)
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(p.codigo LIKE ? OR p.nombre LIKE ? OR p.descripcion LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($categoria > 0) {
    $where[] = 'p.id_categoria = ?';
    $params[] = $categoria;
}

if ($proveedor > 0) {
    $where[] = 'p.id_proveedor = ?';
    $params[] = $proveedor;
}

if (!empty($estado)) {
    $where[] = 'p.estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);

// Consulta completa para exportar
$sql = "SELECT 
        p.codigo,
        p.nombre,
        p.descripcion,
        c.nombre_categoria,
        prov.nombre_proveedor,
        p.precio_costo,
        p.precio_base,
        p.unidad_medida,
        COALESCE(SUM(s.cantidad), 0) as stock_total,
        p.stock_minimo,
        p.stock_maximo,
        p.estado,
        p.fecha_creacion
        FROM productos p
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        LEFT JOIN proveedores prov ON p.id_proveedor = prov.id_proveedor
        LEFT JOIN stock s ON p.id_producto = s.id_producto
        WHERE $where_clause
        GROUP BY p.id_producto
        ORDER BY p.codigo";

$productos = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

if (empty($productos)) {
    setAlerta('warning', 'No hay productos para exportar con los filtros aplicados');
    redirigir('index.php');
}

// Nombre del archivo
$fecha = date('Y-m-d_H-i-s');
$nombre_archivo = "productos_$fecha";

// Exportar según formato
if ($formato === 'csv') {
    // =============================================
    // EXPORTAR A CSV
    // =============================================
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (para Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'Código',
        'Nombre',
        'Descripción',
        'Categoría',
        'Proveedor',
        'Precio Costo',
        'Precio Base',
        'Unidad',
        'Stock',
        'Stock Mínimo',
        'Stock Máximo',
        'Estado',
        'Fecha Creación'
    ], ';');
    
    // Datos
    foreach ($productos as $producto) {
        fputcsv($output, [
            $producto['codigo'],
            $producto['nombre'],
            $producto['descripcion'] ?? '',
            $producto['nombre_categoria'] ?? 'Sin categoría',
            $producto['nombre_proveedor'] ?? 'Sin proveedor',
            number_format($producto['precio_costo'], 2, ',', '.'),
            number_format($producto['precio_base'], 2, ',', '.'),
            $producto['unidad_medida'],
            $producto['stock_total'],
            $producto['stock_minimo'],
            $producto['stock_maximo'],
            ucfirst($producto['estado']),
            date('d/m/Y H:i', strtotime($producto['fecha_creacion']))
        ], ';');
    }
    
    fclose($output);
    exit;
    
} else {
    // =============================================
    // EXPORTAR A EXCEL (HTML)
    // =============================================
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.xls"');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; font-family: Arial; font-size: 11pt; }';
    echo 'th { background-color: #4472C4; color: white; font-weight: bold; padding: 8px; border: 1px solid #ccc; }';
    echo 'td { padding: 6px; border: 1px solid #ccc; }';
    echo '.numero { text-align: right; }';
    echo '.activo { background-color: #C6EFCE; color: #006100; }';
    echo '.inactivo { background-color: #FFC7CE; color: #9C0006; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>Listado de Productos</h2>';
    echo '<p><strong>Fecha de Exportación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    echo '<p><strong>Total de Registros:</strong> ' . count($productos) . '</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Nombre</th>';
    echo '<th>Descripción</th>';
    echo '<th>Categoría</th>';
    echo '<th>Proveedor</th>';
    echo '<th>Precio Costo</th>';
    echo '<th>Precio Base</th>';
    echo '<th>Unidad</th>';
    echo '<th>Stock</th>';
    echo '<th>Stock Mínimo</th>';
    echo '<th>Stock Máximo</th>';
    echo '<th>Estado</th>';
    echo '<th>Fecha Creación</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($productos as $producto) {
        $estado_class = $producto['estado'] == 'activo' ? 'activo' : 'inactivo';
        echo '<tr>';
        echo '<td>' . htmlspecialchars($producto['codigo']) . '</td>';
        echo '<td>' . htmlspecialchars($producto['nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($producto['descripcion'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($producto['nombre_categoria'] ?? 'Sin categoría') . '</td>';
        echo '<td>' . htmlspecialchars($producto['nombre_proveedor'] ?? 'Sin proveedor') . '</td>';
        echo '<td class="numero">$ ' . number_format($producto['precio_costo'], 2, ',', '.') . '</td>';
        echo '<td class="numero">$ ' . number_format($producto['precio_base'], 2, ',', '.') . '</td>';
        echo '<td>' . htmlspecialchars($producto['unidad_medida']) . '</td>';
        echo '<td class="numero">' . number_format($producto['stock_total'], 0, ',', '.') . '</td>';
        echo '<td class="numero">' . number_format($producto['stock_minimo'], 0, ',', '.') . '</td>';
        echo '<td class="numero">' . number_format($producto['stock_maximo'], 0, ',', '.') . '</td>';
        echo '<td class="' . $estado_class . '">' . ucfirst($producto['estado']) . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($producto['fecha_creacion'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}