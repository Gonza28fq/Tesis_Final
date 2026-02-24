<?php
// =============================================
// modules/ventas/exportar.php
// Exportar Ventas a Excel o PDF
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('ventas', 'ver');

$db = getDB();

// Obtener parámetros de filtros
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : '';
$cliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;
$vendedor = isset($_GET['vendedor']) ? (int)$_GET['vendedor'] : 0;
$formato = isset($_GET['formato']) ? $_GET['formato'] : '';

// Si hay formato seleccionado, procesar exportación
if (!empty($formato) && in_array($formato, ['excel', 'pdf'])) {
    // Construir filtros
    $where = ['1=1'];
    $params = [];
    
    if (!empty($buscar)) {
        $where[] = '(v.numero_venta LIKE ? OR c.nombre LIKE ? OR v.numero_comprobante LIKE ?)';
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }
    
    if (!empty($fecha_desde)) {
        $where[] = 'v.fecha_venta >= ?';
        $params[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $where[] = 'v.fecha_venta <= ?';
        $params[] = $fecha_hasta;
    }
    
    if (!empty($estado)) {
        $where[] = 'v.estado = ?';
        $params[] = $estado;
    }
    
    if ($cliente > 0) {
        $where[] = 'v.id_cliente = ?';
        $params[] = $cliente;
    }
    
    if ($vendedor > 0) {
        $where[] = 'v.id_usuario = ?';
        $params[] = $vendedor;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Consulta de ventas
    $sql = "SELECT v.numero_venta, v.fecha_venta, c.nombre as cliente, c.documento,
            tc.codigo as tipo_comprobante, v.numero_comprobante, v.forma_pago,
            v.subtotal, v.descuento, v.impuestos, v.total, v.estado,
            u.nombre_completo as vendedor
            FROM ventas v
            INNER JOIN clientes c ON v.id_cliente = c.id_cliente
            INNER JOIN tipos_comprobante tc ON v.id_tipo_comprobante = tc.id_tipo_comprobante
            INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
            WHERE $where_clause
            ORDER BY v.fecha_venta DESC, v.id_venta DESC";
    
    $ventas = $db->query($sql, $params)->fetchAll();
    
    // ============================================
    // EXPORTAR A EXCEL (CSV)
    // ============================================
    if ($formato == 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ventas_' . date('Y-m-d_His') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, [
            'N° Venta',
            'Fecha',
            'Cliente',
            'Documento',
            'Tipo Comprobante',
            'N° Comprobante',
            'Forma Pago',
            'Subtotal',
            'Descuento',
            'IVA',
            'Total',
            'Estado',
            'Vendedor'
        ]);
        
        // Datos
        foreach ($ventas as $venta) {
            fputcsv($output, [
                $venta['numero_venta'],
                formatearFecha($venta['fecha_venta']),
                $venta['cliente'],
                $venta['documento'],
                $venta['tipo_comprobante'],
                $venta['numero_comprobante'] ?: '-',
                $venta['forma_pago'] ?: '-',
                number_format($venta['subtotal'], 2, ',', '.'),
                number_format($venta['descuento'], 2, ',', '.'),
                number_format($venta['impuestos'], 2, ',', '.'),
                number_format($venta['total'], 2, ',', '.'),
                ucfirst($venta['estado']),
                $venta['vendedor']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    // ============================================
    // EXPORTAR A PDF
    // ============================================
    if ($formato == 'pdf') {
        require __DIR__ . '/../../vendor/autoload.php';

        
        // Crear HTML para PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Reporte de Ventas</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10pt;
                }
                h1 {
                    text-align: center;
                    color: #333;
                    font-size: 18pt;
                }
                .info {
                    text-align: center;
                    margin-bottom: 20px;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th {
                    background-color: #4CAF50;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-size: 9pt;
                }
                td {
                    border: 1px solid #ddd;
                    padding: 6px;
                    font-size: 8pt;
                }
                tr:nth-child(even) {
                    background-color: #f2f2f2;
                }
                .total-row {
                    background-color: #e8f5e9 !important;
                    font-weight: bold;
                }
                .text-right {
                    text-align: right;
                }
                .badge {
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 7pt;
                }
                .badge-success { background-color: #4CAF50; color: white; }
                .badge-warning { background-color: #ff9800; color: white; }
                .badge-danger { background-color: #f44336; color: white; }
                .badge-secondary { background-color: #9e9e9e; color: white; }
            </style>
        </head>
        <body>
            <h1>Reporte de Ventas</h1>
            <div class="info">
                <strong>Período:</strong> ' . formatearFecha($fecha_desde) . ' al ' . formatearFecha($fecha_hasta) . '<br>
                <strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>N° Venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Comprobante</th>
                        <th class="text-right">Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>';
        
        $total_general = 0;
        foreach ($ventas as $venta) {
            $badge_class = [
                'completada' => 'success',
                'pendiente' => 'warning',
                'cancelada' => 'danger',
                'devuelta' => 'secondary'
            ];
            
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($venta['numero_venta']) . '</td>
                        <td>' . formatearFecha($venta['fecha_venta']) . '</td>
                        <td>' . htmlspecialchars($venta['cliente']) . '</td>
                        <td>' . htmlspecialchars($venta['tipo_comprobante'] . ' ' . $venta['numero_comprobante']) . '</td>
                        <td class="text-right">$ ' . number_format($venta['total'], 2, ',', '.') . '</td>
                        <td><span class="badge badge-' . $badge_class[$venta['estado']] . '">' . ucfirst($venta['estado']) . '</span></td>
                    </tr>';
            
            if ($venta['estado'] == 'completada') {
                $total_general += $venta['total'];
            }
        }
        
        $html .= '
                    <tr class="total-row">
                        <td colspan="4" class="text-right">TOTAL GENERAL:</td>
                        <td class="text-right">$ ' . number_format($total_general, 2, ',', '.') . '</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';
        
        // Generar PDF con mPDF o similar
        // Aquí uso una implementación simple con DomPDF
        try {
            // Si tienes DomPDF instalado vía Composer
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream('ventas_' . date('Y-m-d_His') . '.pdf', ['Attachment' => true]);
        } catch (Exception $e) {
            // Si no está instalado DomPDF, generamos un HTML simple descargable
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename=ventas_' . date('Y-m-d_His') . '.html');
            echo $html;
        }
        
        exit;
    }
}

$titulo_pagina = 'Exportar Ventas';

// Obtener clientes para filtro
$clientes = $db->query("SELECT c.id_cliente, c.nombre 
                        FROM clientes c
                        WHERE c.estado = 'activo'
                        ORDER BY c.nombre
                        LIMIT 50")->fetchAll();

// Vendedores activos
$vendedores = $db->query("SELECT id_usuario, nombre_completo 
                         FROM usuarios 
                         WHERE estado = 'activo' 
                         ORDER BY nombre_completo")->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="download"></i> Exportar Ventas</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Ventas</a></li>
            <li class="breadcrumb-item active">Exportar</li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <!-- Información -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <i data-feather="file-text" width="64" class="text-primary mb-3"></i>
                <h3>Exportar Reporte de Ventas</h3>
                <p class="text-muted">
                    Seleccione los filtros y el formato deseado para exportar el reporte de ventas
                </p>
            </div>
        </div>
        
        <!-- Formulario de Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i data-feather="filter"></i> Filtros del Reporte</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="form-exportar">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" name="buscar" 
                                   placeholder="N° venta, cliente..." 
                                   value="<?php echo htmlspecialchars($buscar); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" name="fecha_desde" 
                                   value="<?php echo $fecha_desde; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" name="fecha_hasta" 
                                   value="<?php echo $fecha_hasta; ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="">Todos</option>
                                <option value="pendiente" <?php echo $estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="completada" <?php echo $estado == 'completada' ? 'selected' : ''; ?>>Completada</option>
                                <option value="cancelada" <?php echo $estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                <option value="devuelta" <?php echo $estado == 'devuelta' ? 'selected' : ''; ?>>Devuelta</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Cliente</label>
                            <select class="form-select" name="cliente">
                                <option value="0">Todos</option>
                                <?php foreach ($clientes as $cli): ?>
                                <option value="<?php echo $cli['id_cliente']; ?>" 
                                        <?php echo $cliente == $cli['id_cliente'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cli['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Vendedor</label>
                            <select class="form-select" name="vendedor">
                                <option value="0">Todos</option>
                                <?php foreach ($vendedores as $vend): ?>
                                <option value="<?php echo $vend['id_usuario']; ?>" 
                                        <?php echo $vendedor == $vend['id_usuario'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vend['nombre_completo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Preview de registros -->
                    <div class="alert alert-info mt-4" id="preview-info" style="display: none;">
                        <i data-feather="info"></i>
                        <strong>Vista previa:</strong> <span id="preview-count">0</span> registros serán exportados
                    </div>
                    
                    <button type="button" class="btn btn-secondary mt-3" onclick="previewData()">
                        <i data-feather="eye"></i> Vista Previa
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Selección de Formato -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i data-feather="file"></i> Seleccionar Formato de Exportación</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100 border-success" style="cursor: pointer;" onclick="exportar('excel')">
                            <div class="card-body text-center">
                                <i data-feather="file-text" width="64" class="text-success mb-3"></i>
                                <h4>Excel (CSV)</h4>
                                <p class="text-muted">
                                    Formato compatible con Microsoft Excel, Google Sheets y LibreOffice Calc
                                </p>
                                <ul class="text-start">
                                    <li>Fácil de editar</li>
                                    <li>Compatible con fórmulas</li>
                                    <li>Importable a otros sistemas</li>
                                </ul>
                                <button type="button" class="btn btn-success mt-3">
                                    <i data-feather="download"></i> Exportar a Excel
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100 border-danger" style="cursor: pointer;" onclick="exportar('pdf')">
                            <div class="card-body text-center">
                                <i data-feather="file" width="64" class="text-danger mb-3"></i>
                                <h4>PDF</h4>
                                <p class="text-muted">
                                    Formato de documento portable ideal para impresión y archivo
                                </p>
                                <ul class="text-start">
                                    <li>Listo para imprimir</li>
                                    <li>No editable (seguro)</li>
                                    <li>Formato profesional</li>
                                </ul>
                                <button type="button" class="btn btn-danger mt-3">
                                    <i data-feather="download"></i> Exportar a PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i data-feather="arrow-left"></i> Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportar(formato) {
    const form = document.getElementById('form-exportar');
    const formData = new FormData(form);
    
    // Agregar formato
    formData.append('formato', formato);
    
    // Construir URL
    const params = new URLSearchParams(formData);
    const url = 'exportar.php?' + params.toString();
    
    // Abrir en nueva ventana para descargar
    window.open(url, '_blank');
}

function previewData() {
    const form = document.getElementById('form-exportar');
    const formData = new FormData(form);
    
    // Hacer petición AJAX para contar registros
    fetch('exportar_preview.php?' + new URLSearchParams(formData))
        .then(response => response.json())
        .then(data => {
            document.getElementById('preview-count').textContent = data.count;
            document.getElementById('preview-info').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php
$custom_js = '<script>feather.replace();</script>';
include '../../includes/footer.php';
?>