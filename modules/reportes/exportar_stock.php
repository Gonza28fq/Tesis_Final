<?php
// =============================================
// modules/stock/exportar.php
// Exportar Stock a Excel
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('stock', 'ver');

$db = getDB();

// Parámetros de búsqueda (los mismos del index)
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$ubicacion = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;
$filtro_stock = isset($_GET['filtro_stock']) ? limpiarInput($_GET['filtro_stock']) : '';

// Construir filtros
$where = ['p.estado = "activo"'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($categoria > 0) {
    $where[] = 'p.id_categoria = ?';
    $params[] = $categoria;
}

$where_clause = implode(' AND ', $where);

// Consulta principal con stock agrupado
$sql = "SELECT p.codigo,
        p.nombre,
        c.nombre_categoria,
        p.unidad_medida,
        COALESCE(SUM(s.cantidad), 0) as stock_total,
        p.stock_minimo,
        p.stock_maximo,
        p.precio_costo,
        p.precio_base,
        GROUP_CONCAT(CONCAT(u.nombre_ubicacion, ':', s.cantidad) SEPARATOR ' | ') as detalle_stock
        FROM productos p
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        LEFT JOIN stock s ON p.id_producto = s.id_producto
        LEFT JOIN ubicaciones u ON s.id_ubicacion = u.id_ubicacion
        WHERE $where_clause";

if ($ubicacion > 0) {
    $sql .= " AND s.id_ubicacion = " . (int)$ubicacion;
}

$sql .= " GROUP BY p.id_producto";

// Aplicar filtro de stock
if ($filtro_stock === 'critico') {
    $sql .= " HAVING stock_total <= p.stock_minimo";
} elseif ($filtro_stock === 'bajo') {
    $sql .= " HAVING stock_total > p.stock_minimo AND stock_total <= (p.stock_minimo * 1.5)";
} elseif ($filtro_stock === 'sin_stock') {
    $sql .= " HAVING stock_total = 0";
}

$sql .= " ORDER BY p.nombre";

$productos = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_productos = count($productos);
$valor_total = 0;
foreach ($productos as $prod) {
    $valor_total += $prod['precio_costo'] * $prod['stock_total'];
}

// Nombre del archivo
$fecha_export = date('Y-m-d_H-i-s');
$nombre_archivo = "stock_export_{$fecha_export}.csv";

// Headers para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir salida
$output = fopen('php://output', 'w');

// BOM para UTF-8 (para que Excel reconozca acentos)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezado del reporte
fputcsv($output, ['REPORTE DE STOCK'], ';');
fputcsv($output, ['Fecha de Exportación:', date('d/m/Y H:i:s')], ';');
fputcsv($output, ['Usuario:', $_SESSION['usuario_nombre'] ?? 'Sistema'], ';');
fputcsv($output, ['Total de Productos:', $total_productos], ';');
fputcsv($output, ['Valorización Total:', '$' . number_format($valor_total, 2, ',', '.')], ';');
fputcsv($output, [], ';'); // Línea en blanco

// Encabezados de columnas
fputcsv($output, [
    'Código',
    'Producto',
    'Categoría',
    'Unidad',
    'Stock Total',
    'Stock Mínimo',
    'Stock Máximo',
    'Precio Costo',
    'Precio Base',
    'Valorización',
    'Estado Stock',
    'Detalle por Ubicación'
], ';');

// Datos de productos
foreach ($productos as $prod) {
    $stock = (int)$prod['stock_total'];
    $stock_min = (int)$prod['stock_minimo'];
    $valorizacion = $prod['precio_costo'] * $stock;
    
    // Determinar estado
    if ($stock == 0) {
        $estado = 'Sin Stock';
    } elseif ($stock <= $stock_min) {
        $estado = 'Crítico';
    } elseif ($stock <= $stock_min * 1.5) {
        $estado = 'Bajo';
    } else {
        $estado = 'Normal';
    }
    
    fputcsv($output, [
        $prod['codigo'],
        $prod['nombre'],
        $prod['nombre_categoria'] ?? '-',
        $prod['unidad_medida'],
        $stock,
        $prod['stock_minimo'],
        $prod['stock_maximo'],
        '$' . number_format($prod['precio_costo'], 2, ',', '.'),
        '$' . number_format($prod['precio_base'], 2, ',', '.'),
        '$' . number_format($valorizacion, 2, ',', '.'),
        $estado,
        $prod['detalle_stock'] ?? '-'
    ], ';');
}

// Línea de totales
fputcsv($output, [], ';');
fputcsv($output, [
    '',
    'TOTALES',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '$' . number_format($valor_total, 2, ',', '.'),
    '',
    ''
], ';');

fclose($output);

// Registrar auditoría
registrarAuditoria('stock', 'exportar', "Exportación de stock - Total productos: $total_productos");

exit;