<?php
// =============================================
// modules/clientes/exportar_estadisticas.php
// Exportar Estadísticas de Clientes a Excel
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('clientes', 'ver');

$db = getDB();

// Filtros de fecha
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

// ===== OBTENER TODOS LOS DATOS =====

// Estadísticas Generales
$stats = $db->query("
    SELECT 
        COUNT(CASE WHEN estado = 'activo' THEN 1 END) as total_activos,
        COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as total_inactivos,
        COUNT(*) as total_clientes,
        COUNT(CASE WHEN DATE(fecha_creacion) >= ? THEN 1 END) as nuevos_periodo,
        AVG(limite_credito) as promedio_limite_credito
    FROM clientes
", [$fecha_desde])->fetch();

// Distribución por Tipo de Cliente
$tipos_distribucion = $db->query("
    SELECT 
        tc.nombre_tipo,
        tc.codigo_afip,
        COUNT(c.id_cliente) as cantidad,
        ROUND(COUNT(c.id_cliente) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM tipos_cliente tc
    LEFT JOIN clientes c ON tc.id_tipo_cliente = c.id_tipo_cliente
    GROUP BY tc.id_tipo_cliente, tc.nombre_tipo, tc.codigo_afip
    ORDER BY cantidad DESC
")->fetchAll();

// Distribución por Lista de Precios
$listas_distribucion = $db->query("
    SELECT 
        COALESCE(lp.nombre_lista, 'Sin lista asignada') as nombre_lista,
        COUNT(c.id_cliente) as cantidad,
        ROUND(COUNT(c.id_cliente) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM clientes c
    LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
    GROUP BY c.id_lista_precio, lp.nombre_lista
    ORDER BY cantidad DESC
")->fetchAll();

// Top Clientes por Ventas
$top_clientes = $db->query("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.documento,
        COUNT(v.id_venta) as total_compras,
        COALESCE(SUM(CASE WHEN v.estado = 'completada' THEN v.total ELSE 0 END), 0) as total_gastado,
        MAX(v.fecha_venta) as ultima_compra
    FROM clientes c
    LEFT JOIN ventas v ON c.id_cliente = v.id_cliente 
        AND v.fecha_venta BETWEEN ? AND ?
    GROUP BY c.id_cliente, c.nombre, c.documento
    HAVING total_compras > 0
    ORDER BY total_gastado DESC
    LIMIT 50
", [$fecha_desde, $fecha_hasta])->fetchAll();

// Distribución Geográfica
$distribucion_geografica = $db->query("
    SELECT 
        COALESCE(provincia, 'Sin especificar') as provincia,
        COUNT(*) as cantidad,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM clientes), 2) as porcentaje
    FROM clientes
    WHERE estado = 'activo'
    GROUP BY provincia
    ORDER BY cantidad DESC
")->fetchAll();

// Evolución Mensual
$evolucion_mensual = $db->query("
    SELECT 
        DATE_FORMAT(fecha_creacion, '%Y-%m') AS mes,
        DATE_FORMAT(fecha_creacion, '%b %Y') AS mes_nombre,
        COUNT(*) AS cantidad
    FROM clientes
    WHERE fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m'), DATE_FORMAT(fecha_creacion, '%b %Y')
    ORDER BY mes ASC
")->fetchAll();

// Clientes sin Compras
$clientes_sin_compras = $db->query("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.documento,
        c.email,
        c.telefono,
        c.fecha_creacion,
        DATEDIFF(CURDATE(), c.fecha_creacion) as dias_desde_registro
    FROM clientes c
    LEFT JOIN ventas v ON c.id_cliente = v.id_cliente
    WHERE c.estado = 'activo'
        AND v.id_venta IS NULL
    ORDER BY c.fecha_creacion DESC
")->fetchAll();

// Clientes Inactivos
$clientes_inactivos = $db->query("
    SELECT 
        c.id_cliente,
        c.nombre,
        c.documento,
        c.email,
        c.telefono,
        MAX(v.fecha_venta) as ultima_compra,
        DATEDIFF(CURDATE(), MAX(v.fecha_venta)) as dias_inactivo,
        COUNT(v.id_venta) as total_compras_historicas
    FROM clientes c
    INNER JOIN ventas v ON c.id_cliente = v.id_cliente
    WHERE c.estado = 'activo'
    GROUP BY c.id_cliente, c.nombre, c.documento, c.email, c.telefono
    HAVING dias_inactivo > 90
    ORDER BY dias_inactivo DESC
")->fetchAll();

// Límite de Crédito
$limite_credito_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN limite_credito > 0 THEN 1 END) as con_limite,
        COUNT(CASE WHEN limite_credito = 0 THEN 1 END) as sin_limite,
        MIN(CASE WHEN limite_credito > 0 THEN limite_credito END) as limite_minimo,
        MAX(limite_credito) as limite_maximo,
        AVG(CASE WHEN limite_credito > 0 THEN limite_credito END) as limite_promedio
    FROM clientes
    WHERE estado = 'activo'
")->fetch();

// ===== GENERAR EXCEL =====

// Nombre del archivo
$nombre_archivo = 'Estadisticas_Clientes_' . date('Y-m-d_H-i-s') . '.xls';

// Headers para descarga
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header("Pragma: no-cache");
header("Expires: 0");

// Comenzar salida HTML para Excel
echo "\xEF\xBB\xBF"; // BOM para UTF-8
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #4472C4; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; text-align: center; }
        td { padding: 6px; border: 1px solid #ccc; }
        .titulo { font-size: 18pt; font-weight: bold; margin-bottom: 10px; color: #2E75B6; }
        .subtitulo { font-size: 14pt; font-weight: bold; margin-top: 20px; margin-bottom: 10px; background-color: #D9E1F2; padding: 8px; }
        .resumen { background-color: #E7E6E6; font-weight: bold; }
        .numero { text-align: right; }
        .fecha { text-align: center; }
        .separador { margin-top: 30px; }
    </style>
</head>
<body>

<!-- ENCABEZADO -->
<div class="titulo">📊 ESTADÍSTICAS DE CLIENTES</div>
<p><strong>Período analizado:</strong> <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> - <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?></p>
<p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>

<!-- ESTADÍSTICAS GENERALES -->
<div class="subtitulo">RESUMEN GENERAL</div>
<table>
    <tr>
        <th>Métrica</th>
        <th>Valor</th>
    </tr>
    <tr>
        <td>Total de Clientes</td>
        <td class="numero"><?php echo number_format($stats['total_clientes']); ?></td>
    </tr>
    <tr>
        <td>Clientes Activos</td>
        <td class="numero"><?php echo number_format($stats['total_activos']); ?> (<?php echo round(($stats['total_activos'] / max($stats['total_clientes'], 1)) * 100, 1); ?>%)</td>
    </tr>
    <tr>
        <td>Clientes Inactivos</td>
        <td class="numero"><?php echo number_format($stats['total_inactivos']); ?> (<?php echo round(($stats['total_inactivos'] / max($stats['total_clientes'], 1)) * 100, 1); ?>%)</td>
    </tr>
    <tr>
        <td>Nuevos en el Período</td>
        <td class="numero"><?php echo number_format($stats['nuevos_periodo']); ?></td>
    </tr>
    <tr>
        <td>Límite de Crédito Promedio</td>
        <td class="numero">$<?php echo number_format($stats['promedio_limite_credito'], 2); ?></td>
    </tr>
</table>

<!-- DISTRIBUCIÓN POR TIPO DE CLIENTE -->
<div class="subtitulo separador">DISTRIBUCIÓN POR TIPO DE CLIENTE</div>
<table>
    <tr>
        <th>Tipo de Cliente</th>
        <th>Código AFIP</th>
        <th>Cantidad</th>
        <th>Porcentaje</th>
    </tr>
    <?php foreach ($tipos_distribucion as $tipo): ?>
    <tr>
        <td><?php echo $tipo['nombre_tipo']; ?></td>
        <td class="fecha"><?php echo $tipo['codigo_afip']; ?></td>
        <td class="numero"><?php echo number_format($tipo['cantidad']); ?></td>
        <td class="numero"><?php echo $tipo['porcentaje']; ?>%</td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- DISTRIBUCIÓN POR LISTA DE PRECIOS -->
<div class="subtitulo separador">DISTRIBUCIÓN POR LISTA DE PRECIOS</div>
<table>
    <tr>
        <th>Lista de Precios</th>
        <th>Cantidad</th>
        <th>Porcentaje</th>
    </tr>
    <?php foreach ($listas_distribucion as $lista): ?>
    <tr>
        <td><?php echo $lista['nombre_lista']; ?></td>
        <td class="numero"><?php echo number_format($lista['cantidad']); ?></td>
        <td class="numero"><?php echo $lista['porcentaje']; ?>%</td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- EVOLUCIÓN MENSUAL -->
<div class="subtitulo separador">EVOLUCIÓN DE NUEVOS CLIENTES (ÚLTIMOS 12 MESES)</div>
<table>
    <tr>
        <th>Mes</th>
        <th>Cantidad de Nuevos Clientes</th>
    </tr>
    <?php 
    $total_evolucion = 0;
    foreach ($evolucion_mensual as $mes): 
        $total_evolucion += $mes['cantidad'];
    ?>
    <tr>
        <td><?php echo $mes['mes_nombre']; ?></td>
        <td class="numero"><?php echo number_format($mes['cantidad']); ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="resumen">
        <td>TOTAL</td>
        <td class="numero"><?php echo number_format($total_evolucion); ?></td>
    </tr>
</table>

<!-- TOP CLIENTES POR VENTAS -->
<div class="subtitulo separador">TOP CLIENTES POR VENTAS (PERÍODO SELECCIONADO)</div>
<table>
    <tr>
        <th>#</th>
        <th>Cliente</th>
        <th>Documento</th>
        <th>Total Compras</th>
        <th>Total Gastado</th>
        <th>Última Compra</th>
    </tr>
    <?php 
    if (empty($top_clientes)): 
    ?>
    <tr>
        <td colspan="6" style="text-align: center;">No hay ventas en el período seleccionado</td>
    </tr>
    <?php 
    else:
        $total_ventas = 0;
        foreach ($top_clientes as $index => $cliente): 
            $total_ventas += $cliente['total_gastado'];
    ?>
    <tr>
        <td class="numero"><?php echo $index + 1; ?></td>
        <td><?php echo $cliente['nombre']; ?></td>
        <td><?php echo $cliente['documento']; ?></td>
        <td class="numero"><?php echo number_format($cliente['total_compras']); ?></td>
        <td class="numero">$<?php echo number_format($cliente['total_gastado'], 2); ?></td>
        <td class="fecha"><?php echo date('d/m/Y', strtotime($cliente['ultima_compra'])); ?></td>
    </tr>
    <?php 
        endforeach; 
    ?>
    <tr class="resumen">
        <td colspan="4">TOTAL VENDIDO A TOP CLIENTES</td>
        <td class="numero">$<?php echo number_format($total_ventas, 2); ?></td>
        <td></td>
    </tr>
    <?php endif; ?>
</table>

<!-- DISTRIBUCIÓN GEOGRÁFICA -->
<div class="subtitulo separador">DISTRIBUCIÓN GEOGRÁFICA</div>
<table>
    <tr>
        <th>Provincia</th>
        <th>Cantidad</th>
        <th>Porcentaje</th>
    </tr>
    <?php foreach ($distribucion_geografica as $geo): ?>
    <tr>
        <td><?php echo $geo['provincia']; ?></td>
        <td class="numero"><?php echo number_format($geo['cantidad']); ?></td>
        <td class="numero"><?php echo $geo['porcentaje']; ?>%</td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- CLIENTES SIN COMPRAS -->
<div class="subtitulo separador">⚠️ CLIENTES ACTIVOS SIN COMPRAS</div>
<table>
    <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Documento</th>
        <th>Email</th>
        <th>Teléfono</th>
        <th>Fecha de Registro</th>
        <th>Días desde Registro</th>
    </tr>
    <?php 
    if (empty($clientes_sin_compras)): 
    ?>
    <tr>
        <td colspan="7" style="text-align: center; color: green;">¡Todos los clientes activos tienen compras!</td>
    </tr>
    <?php 
    else:
        foreach ($clientes_sin_compras as $cliente): 
    ?>
    <tr>
        <td class="numero"><?php echo $cliente['id_cliente']; ?></td>
        <td><?php echo $cliente['nombre']; ?></td>
        <td><?php echo $cliente['documento']; ?></td>
        <td><?php echo $cliente['email']; ?></td>
        <td><?php echo $cliente['telefono']; ?></td>
        <td class="fecha"><?php echo date('d/m/Y', strtotime($cliente['fecha_creacion'])); ?></td>
        <td class="numero"><?php echo $cliente['dias_desde_registro']; ?></td>
    </tr>
    <?php 
        endforeach; 
    endif; 
    ?>
</table>

<!-- CLIENTES INACTIVOS -->
<div class="subtitulo separador">🚨 CLIENTES INACTIVOS (MÁS DE 90 DÍAS SIN COMPRAR)</div>
<table>
    <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Documento</th>
        <th>Email</th>
        <th>Teléfono</th>
        <th>Última Compra</th>
        <th>Días Inactivo</th>
        <th>Compras Históricas</th>
    </tr>
    <?php 
    if (empty($clientes_inactivos)): 
    ?>
    <tr>
        <td colspan="8" style="text-align: center; color: green;">¡Todos los clientes están activos!</td>
    </tr>
    <?php 
    else:
        foreach ($clientes_inactivos as $cliente): 
    ?>
    <tr>
        <td class="numero"><?php echo $cliente['id_cliente']; ?></td>
        <td><?php echo $cliente['nombre']; ?></td>
        <td><?php echo $cliente['documento']; ?></td>
        <td><?php echo $cliente['email']; ?></td>
        <td><?php echo $cliente['telefono']; ?></td>
        <td class="fecha"><?php echo date('d/m/Y', strtotime($cliente['ultima_compra'])); ?></td>
        <td class="numero"><?php echo $cliente['dias_inactivo']; ?></td>
        <td class="numero"><?php echo $cliente['total_compras_historicas']; ?></td>
    </tr>
    <?php 
        endforeach; 
    endif; 
    ?>
</table>

<!-- ANÁLISIS DE LÍMITES DE CRÉDITO -->
<div class="subtitulo separador">ANÁLISIS DE LÍMITES DE CRÉDITO</div>
<table>
    <tr>
        <th>Métrica</th>
        <th>Valor</th>
    </tr>
    <tr>
        <td>Clientes con Límite Asignado</td>
        <td class="numero"><?php echo number_format($limite_credito_stats['con_limite']); ?></td>
    </tr>
    <tr>
        <td>Clientes sin Límite Asignado</td>
        <td class="numero"><?php echo number_format($limite_credito_stats['sin_limite']); ?></td>
    </tr>
    <tr>
        <td>Límite Mínimo</td>
        <td class="numero">$<?php echo number_format($limite_credito_stats['limite_minimo'], 2); ?></td>
    </tr>
    <tr>
        <td>Límite Promedio</td>
        <td class="numero">$<?php echo number_format($limite_credito_stats['limite_promedio'], 2); ?></td>
    </tr>
    <tr>
        <td>Límite Máximo</td>
        <td class="numero">$<?php echo number_format($limite_credito_stats['limite_maximo'], 2); ?></td>
    </tr>
</table>

<div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #000; text-align: center; color: #666;">
    <p><strong>Documento generado por el Sistema de Gestión</strong></p>
    <p>Fecha: <?php echo date('d/m/Y H:i:s'); ?> | Usuario: <?php echo $_SESSION['usuario_nombre'] ?? 'Sistema'; ?></p>
</div>

</body>
</html>