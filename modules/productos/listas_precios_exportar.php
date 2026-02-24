<?php
// =============================================
// modules/productos/listas_precios_exportar.php
// Exportar Lista de Precios a Excel
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'exportar');

$db = getDB();
$id_lista = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_lista == 0) {
    die('Lista no válida');
}

// Obtener información de la lista
$sql_lista = "SELECT lp.*, tc.nombre_tipo
              FROM listas_precios lp
              LEFT JOIN tipos_cliente tc ON lp.id_tipo_cliente = tc.id_tipo_cliente
              WHERE lp.id_lista_precio = ?";
$stmt = $db->query($sql_lista, [$id_lista]);
$lista = $stmt->fetch();

if (!$lista) {
    die('Lista no encontrada');
}

// Obtener productos con precios
$sql = "SELECT p.codigo, p.nombre, p.precio_costo, p.precio_base,
        pp.precio as precio_lista,
        c.nombre_categoria,
        CASE 
            WHEN pp.precio IS NOT NULL THEN ((pp.precio - p.precio_base) / p.precio_base * 100)
            ELSE 0
        END as diferencia_porcentaje,
        CASE 
            WHEN pp.precio IS NOT NULL AND p.precio_costo > 0 
            THEN ((pp.precio - p.precio_costo) / p.precio_costo * 100)
            ELSE 0
        END as margen_porcentaje
        FROM productos p
        LEFT JOIN productos_precios pp ON p.id_producto = pp.id_producto AND pp.id_lista_precio = ?
        LEFT JOIN categorias_producto c ON p.id_categoria = c.id_categoria
        WHERE p.estado = 'activo'
        ORDER BY c.nombre_categoria, p.nombre";

$productos = $db->query($sql, [$id_lista])->fetchAll();

// Registrar auditoría
registrarAuditoria('listas_precios', 'exportar', "Lista: {$lista['nombre_lista']} (ID: $id_lista)");

// Configurar headers para descarga Excel
$filename = 'Lista_Precios_' . preg_replace('/[^a-zA-Z0-9]/', '_', $lista['nombre_lista']) . '_' . date('Y-m-d') . '.xls';

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
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        td {
            border: 1px solid #000;
            padding: 5px;
        }
        .header {
            background-color: #f8f9fa;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .text-right {
            text-align: right;
        }
        .positivo {
            color: green;
        }
        .negativo {
            color: red;
        }
    </style>
</head>
<body>
    <!-- Encabezado -->
    <table style="margin-bottom: 20px; border: none;">
        <tr>
            <td colspan="9" style="border: none; text-align: center;">
                <h2>LISTA DE PRECIOS: <?php echo strtoupper($lista['nombre_lista']); ?></h2>
            </td>
        </tr>
        <tr>
            <td colspan="9" style="border: none;">
                <strong>Descripción:</strong> <?php echo $lista['descripcion'] ?? 'N/A'; ?>
            </td>
        </tr>
        <tr>
            <td colspan="9" style="border: none;">
                <strong>Tipo de Cliente:</strong> <?php echo $lista['nombre_tipo'] ?? 'General'; ?>
            </td>
        </tr>
        <tr>
            <td colspan="9" style="border: none;">
                <strong>Ajuste:</strong> 
                <?php if ($lista['porcentaje_incremento'] > 0): ?>
                    +<?php echo $lista['porcentaje_incremento']; ?>% (Incremento)
                <?php elseif ($lista['porcentaje_incremento'] < 0): ?>
                    <?php echo $lista['porcentaje_incremento']; ?>% (Descuento)
                <?php else: ?>
                    Precio Base
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td colspan="9" style="border: none;">
                <strong>Fecha de Exportación:</strong> <?php echo date('d/m/Y H:i'); ?>
            </td>
        </tr>
        <tr>
            <td colspan="9" style="border: none; height: 10px;"></td>
        </tr>
    </table>

    <!-- Tabla de Productos -->
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Precio Costo</th>
                <th>Precio Base</th>
                <th>Precio Lista</th>
                <th>Diferencia %</th>
                <th>Margen %</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($productos)): ?>
            <tr>
                <td colspan="9" style="text-align: center;">No hay productos en esta lista</td>
            </tr>
            <?php else: ?>
                <?php 
                $categoria_actual = '';
                $total_productos = 0;
                $suma_base = 0;
                $suma_lista = 0;
                
                foreach ($productos as $prod): 
                    // Separador de categoría
                    if ($categoria_actual != $prod['nombre_categoria']):
                        if ($categoria_actual != ''):
                            echo '<tr><td colspan="9" style="height: 5px; background-color: #e9ecef;"></td></tr>';
                        endif;
                        $categoria_actual = $prod['nombre_categoria'];
                    endif;
                    
                    $total_productos++;
                    $suma_base += $prod['precio_base'];
                    if ($prod['precio_lista']) {
                        $suma_lista += $prod['precio_lista'];
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($prod['codigo']); ?></td>
                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($prod['nombre_categoria'] ?? 'Sin categoría'); ?></td>
                    <td class="text-right">$<?php echo number_format($prod['precio_costo'], 2, ',', '.'); ?></td>
                    <td class="text-right">$<?php echo number_format($prod['precio_base'], 2, ',', '.'); ?></td>
                    <td class="text-right">
                        <?php if ($prod['precio_lista']): ?>
                            $<?php echo number_format($prod['precio_lista'], 2, ',', '.'); ?>
                        <?php else: ?>
                            Sin precio
                        <?php endif; ?>
                    </td>
                    <td class="text-right <?php echo $prod['diferencia_porcentaje'] > 0 ? 'positivo' : ($prod['diferencia_porcentaje'] < 0 ? 'negativo' : ''); ?>">
                        <?php if ($prod['precio_lista']): ?>
                            <?php echo $prod['diferencia_porcentaje'] > 0 ? '+' : ''; ?><?php echo number_format($prod['diferencia_porcentaje'], 2); ?>%
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <?php if ($prod['precio_lista'] && $prod['precio_costo'] > 0): ?>
                            <?php echo number_format($prod['margen_porcentaje'], 2); ?>%
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo $prod['precio_lista'] ? 'Con precio' : 'Sin precio'; ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Totales -->
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="3">TOTALES</td>
                    <td colspan="2" class="text-right">Base: $<?php echo number_format($suma_base, 2, ',', '.'); ?></td>
                    <td class="text-right">Lista: $<?php echo number_format($suma_lista, 2, ',', '.'); ?></td>
                    <td colspan="3">Total Productos: <?php echo $total_productos; ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pie de página -->
    <table style="margin-top: 20px; border: none;">
        <tr>
            <td colspan="9" style="border: none; text-align: center; font-size: 10px; color: #666;">
                Generado por Sistema de Gestión Comercial - <?php echo date('d/m/Y H:i:s'); ?>
            </td>
        </tr>
    </table>
</body>
</html>