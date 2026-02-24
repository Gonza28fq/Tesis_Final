<?php
// =============================================
// modules/clientes/exportar.php
// Exportar Clientes a Excel/CSV
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('clientes', 'exportar');

$db = getDB();

// Parámetros de filtrado (igual que index.php)
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$tipo_cliente = isset($_GET['tipo_cliente']) ? (int)$_GET['tipo_cliente'] : 0;
$lista_precio = isset($_GET['lista_precio']) ? (int)$_GET['lista_precio'] : 0;
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : 'activo';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'csv'; // csv o excel

// Construir filtros (igual que index.php)
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(c.nombre LIKE ? OR c.documento LIKE ? OR c.email LIKE ? OR c.cuit_cuil LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($tipo_cliente > 0) {
    $where[] = 'c.id_tipo_cliente = ?';
    $params[] = $tipo_cliente;
}

if ($lista_precio > 0) {
    $where[] = 'c.id_lista_precio = ?';
    $params[] = $lista_precio;
}

if (!empty($estado)) {
    $where[] = 'c.estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);

// Consulta de clientes
$sql = "SELECT c.*, 
        tc.nombre_tipo,
        tc.codigo_afip,
        lp.nombre_lista,
        (SELECT COUNT(*) FROM ventas WHERE id_cliente = c.id_cliente) as total_ventas,
        (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE id_cliente = c.id_cliente AND estado = 'completada') as total_gastado
        FROM clientes c
        INNER JOIN tipos_cliente tc ON c.id_tipo_cliente = tc.id_tipo_cliente
        LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
        WHERE $where_clause
        ORDER BY c.nombre";

$clientes = $db->query($sql, $params)->fetchAll();

if (empty($clientes)) {
    setAlerta('warning', 'No hay clientes para exportar con los filtros seleccionados');
    redirigir('index.php');
}

// Registrar auditoría
registrarAuditoria('clientes', 'exportar', "Formato: $formato - Total: " . count($clientes));

// ==========================================
// EXPORTAR SEGÚN FORMATO
// ==========================================

if ($formato === 'excel') {
    exportarExcel($clientes);
} else {
    exportarCSV($clientes);
}

// ==========================================
// FUNCIÓN EXPORTAR CSV
// ==========================================
function exportarCSV($clientes) {
    $filename = 'clientes_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM para UTF-8 (para Excel)
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, [
        'ID',
        'Nombre Completo',
        'Razón Social',
        'Documento',
        'CUIT/CUIL',
        'Tipo de Cliente',
        'Código AFIP',
        'Lista de Precios',
        'Email',
        'Teléfono',
        'Dirección',
        'Ciudad',
        'Provincia',
        'Código Postal',
        'Condición IVA',
        'Límite de Crédito',
        'Total Ventas',
        'Total Gastado',
        'Estado',
        'Fecha de Registro',
        'Observaciones'
    ], ';'); // Separador punto y coma para mejor compatibilidad con Excel
    
    // Datos
    foreach ($clientes as $cliente) {
        fputcsv($output, [
            $cliente['id_cliente'],
            $cliente['nombre'],
            $cliente['razon_social'] ?? '',
            $cliente['documento'],
            $cliente['cuit_cuil'] ?? '',
            $cliente['nombre_tipo'],
            $cliente['codigo_afip'] ?? '',
            $cliente['nombre_lista'] ?? 'Sin lista',
            $cliente['email'] ?? '',
            $cliente['telefono'] ?? '',
            $cliente['direccion'] ?? '',
            $cliente['ciudad'] ?? '',
            $cliente['provincia'] ?? '',
            $cliente['codigo_postal'] ?? '',
            $cliente['condicion_iva'] ?? '',
            number_format($cliente['limite_credito'], 2, ',', '.'),
            $cliente['total_ventas'],
            number_format($cliente['total_gastado'], 2, ',', '.'),
            ucfirst($cliente['estado']),
            date('d/m/Y H:i', strtotime($cliente['fecha_creacion'])),
            $cliente['observaciones'] ?? ''
        ], ';');
    }
    
    fclose($output);
    exit();
}

// ==========================================
// FUNCIÓN EXPORTAR EXCEL (HTML TABLE)
// ==========================================
function exportarExcel($clientes) {
    $filename = 'clientes_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { 
                background-color: #2563eb; 
                color: white; 
                font-weight: bold; 
                padding: 10px;
                text-align: left;
                border: 1px solid #ddd;
            }
            td { 
                padding: 8px; 
                border: 1px solid #ddd;
            }
            tr:nth-child(even) { background-color: #f9fafb; }
            .currency { text-align: right; }
            .center { text-align: center; }
            .header-info {
                margin-bottom: 20px;
                padding: 15px;
                background-color: #f3f4f6;
                border: 1px solid #d1d5db;
            }
        </style>
    </head>
    <body>
        <div class="header-info">
            <h2>📊 Reporte de Clientes</h2>
            <p><strong>Generado:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><strong>Total de registros:</strong> <?php echo count($clientes); ?></p>
            <p><strong>Sistema:</strong> Gestión Comercial 2.0</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre Completo</th>
                    <th>Razón Social</th>
                    <th>Documento</th>
                    <th>CUIT/CUIL</th>
                    <th>Tipo Cliente</th>
                    <th>Lista Precios</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Ciudad</th>
                    <th>Provincia</th>
                    <th>CP</th>
                    <th>Cond. IVA</th>
                    <th>Límite Crédito</th>
                    <th>Total Ventas</th>
                    <th>Total Gastado</th>
                    <th>Estado</th>
                    <th>Fecha Registro</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td class="center"><?php echo $cliente['id_cliente']; ?></td>
                    <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($cliente['razon_social'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['documento']); ?></td>
                    <td><?php echo htmlspecialchars($cliente['cuit_cuil'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['nombre_tipo']); ?></td>
                    <td><?php echo htmlspecialchars($cliente['nombre_lista'] ?? 'Sin lista'); ?></td>
                    <td><?php echo htmlspecialchars($cliente['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['ciudad'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['provincia'] ?? ''); ?></td>
                    <td class="center"><?php echo htmlspecialchars($cliente['codigo_postal'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($cliente['condicion_iva'] ?? ''); ?></td>
                    <td class="currency">$ <?php echo number_format($cliente['limite_credito'], 2, ',', '.'); ?></td>
                    <td class="center"><?php echo $cliente['total_ventas']; ?></td>
                    <td class="currency">$ <?php echo number_format($cliente['total_gastado'], 2, ',', '.'); ?></td>
                    <td class="center">
                        <span style="background-color: <?php echo $cliente['estado'] == 'activo' ? '#d1fae5' : '#fee2e2'; ?>; padding: 3px 8px; border-radius: 3px;">
                            <?php echo ucfirst($cliente['estado']); ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($cliente['fecha_creacion'])); ?></td>
                    <td><?php echo htmlspecialchars($cliente['observaciones'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #e5e7eb; font-weight: bold;">
                    <td colspan="15" style="text-align: right; padding-right: 10px;">TOTAL:</td>
                    <td class="center"><?php echo array_sum(array_column($clientes, 'total_ventas')); ?></td>
                    <td class="currency">$ <?php echo number_format(array_sum(array_column($clientes, 'total_gastado')), 2, ',', '.'); ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>
    <?php
    exit();
}