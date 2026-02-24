<?php
// =============================================
// modules/stock/movimientos.php
// Historial de Movimientos de Stock
// =============================================
require_once '../../config/constantes.php';      // 1️⃣ Primero
require_once '../../config/conexion.php';        // 2️⃣ Segundo
require_once '../../includes/funciones.php';     // 3️⃣ Tercero

iniciarSesion();  // 4️⃣ Cuarto
requierePermiso('stock', 'ver_movimientos');

$titulo_pagina = 'Movimientos de Stock';
$db = getDB();

// Parámetros de filtros
$producto = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;
$ubicacion = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;
$tipo = isset($_GET['tipo']) ? limpiarInput($_GET['tipo']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Construir filtros
$where = ['1=1'];
$params = [];

if ($producto > 0) {
    $where[] = 'ms.id_producto = ?';
    $params[] = $producto;
}

if ($ubicacion > 0) {
    $where[] = '(ms.id_ubicacion_origen = ? OR ms.id_ubicacion_destino = ?)';
    $params[] = $ubicacion;
    $params[] = $ubicacion;
}

if (!empty($tipo)) {
    $where[] = 'ms.tipo_movimiento = ?';
    $params[] = $tipo;
}

if (!empty($fecha_desde)) {
    $where[] = 'DATE(ms.fecha_movimiento) >= ?';
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $where[] = 'DATE(ms.fecha_movimiento) <= ?';
    $params[] = $fecha_hasta;
}

$where_clause = implode(' AND ', $where);

// Contar total
$sql_count = "SELECT COUNT(*) FROM movimientos_stock ms WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);

// Paginación
$paginacion = calcularPaginacion($total_registros, $pagina);

// Consulta de movimientos
$sql = "SELECT ms.*,
        p.codigo as producto_codigo, p.nombre as producto_nombre,
        uo.nombre_ubicacion as ubicacion_origen,
        ud.nombre_ubicacion as ubicacion_destino,
        u.usuario as usuario_nombre
        FROM movimientos_stock ms
        INNER JOIN productos p ON ms.id_producto = p.id_producto
        LEFT JOIN ubicaciones uo ON ms.id_ubicacion_origen = uo.id_ubicacion
        LEFT JOIN ubicaciones ud ON ms.id_ubicacion_destino = ud.id_ubicacion
        INNER JOIN usuarios u ON ms.id_usuario = u.id_usuario
        WHERE $where_clause
        ORDER BY ms.fecha_movimiento DESC
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$movimientos = $db->query($sql, $params)->fetchAll();

// Obtener productos para filtro
$productos = $db->query("SELECT id_producto, codigo, nombre FROM productos WHERE estado = 'activo' ORDER BY nombre LIMIT 100")->fetchAll();

// Obtener ubicaciones
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE estado = 'activo' ORDER BY nombre_ubicacion")->fetchAll();

// Producto seleccionado (si viene del filtro)
$producto_info = null;
if ($producto > 0) {
    $sql_prod = "SELECT codigo, nombre FROM productos WHERE id_producto = ?";
    $producto_info = $db->query($sql_prod, [$producto])->fetch();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="activity"></i> Movimientos de Stock</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Stock</a></li>
            <li class="breadcrumb-item active">Movimientos</li>
        </ol>
    </nav>
</div>

<?php if ($producto_info): ?>
<div class="alert alert-info">
    <strong>Filtrando por producto:</strong> <?php echo $producto_info['codigo']; ?> - <?php echo $producto_info['nombre']; ?>
    <a href="movimientos.php" class="btn btn-sm btn-outline-primary ms-2">Ver todos</a>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="filter"></i> Filtros
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Producto</label>
                    <select class="form-select" name="producto">
                        <option value="0">Todos</option>
                        <?php foreach ($productos as $prod): ?>
                        <option value="<?php echo $prod['id_producto']; ?>" <?php echo $producto == $prod['id_producto'] ? 'selected' : ''; ?>>
                            <?php echo $prod['codigo']; ?> - <?php echo substr($prod['nombre'], 0, 30); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Ubicación</label>
                    <select class="form-select" name="ubicacion">
                        <option value="0">Todas</option>
                        <?php foreach ($ubicaciones as $ubi): ?>
                        <option value="<?php echo $ubi['id_ubicacion']; ?>" <?php echo $ubicacion == $ubi['id_ubicacion'] ? 'selected' : ''; ?>>
                            <?php echo $ubi['nombre_ubicacion']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo">
                        <option value="">Todos</option>
                        <option value="entrada" <?php echo $tipo == 'entrada' ? 'selected' : ''; ?>>Entrada</option>
                        <option value="salida" <?php echo $tipo == 'salida' ? 'selected' : ''; ?>>Salida</option>
                        <option value="transferencia" <?php echo $tipo == 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                        <option value="ajuste" <?php echo $tipo == 'ajuste' ? 'selected' : ''; ?>>Ajuste</option>
                        <option value="devolucion" <?php echo $tipo == 'devolucion' ? 'selected' : ''; ?>>Devolución</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fecha_desde; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fecha_hasta; ?>">
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i data-feather="search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Movimientos -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Historial de Movimientos (<?php echo $total_registros; ?> registros)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Motivo</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movimientos)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No se encontraron movimientos</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $mov): ?>
                        <?php
                        $badges_tipo = [
                            'entrada' => 'success',
                            'salida' => 'danger',
                            'transferencia' => 'info',
                            'ajuste' => 'warning',
                            'devolucion' => 'secondary'
                        ];
                        $badge_class = $badges_tipo[$mov['tipo_movimiento']] ?? 'secondary';
                        
                        $iconos_tipo = [
                            'entrada' => 'arrow-down',
                            'salida' => 'arrow-up',
                            'transferencia' => 'shuffle',
                            'ajuste' => 'edit',
                            'devolucion' => 'corner-down-left'
                        ];
                        $icono = $iconos_tipo[$mov['tipo_movimiento']] ?? 'activity';
                        ?>
                        <tr>
                            <td>
                                <small><?php echo formatearFechaHora($mov['fecha_movimiento']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo $mov['producto_codigo']; ?></strong><br>
                                <small class="text-muted"><?php echo substr($mov['producto_nombre'], 0, 30); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <i data-feather="<?php echo $icono; ?>" width="14"></i>
                                    <?php echo ucfirst($mov['tipo_movimiento']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo $mov['cantidad']; ?></strong>
                            </td>
                            <td>
                                <small><?php echo $mov['ubicacion_origen'] ?? '-'; ?></small>
                            </td>
                            <td>
                                <small><?php echo $mov['ubicacion_destino'] ?? '-'; ?></small>
                            </td>
                            <td>
                                <small><?php echo $mov['motivo'] ?? '-'; ?></small>
                                <?php if ($mov['observaciones']): ?>
                                <br><small class="text-muted"><?php echo substr($mov['observaciones'], 0, 40); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo $mov['usuario_nombre']; ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($paginacion['total_paginas'] > 1): ?>
        <div class="mt-3">
            <?php
            $url_base = 'movimientos.php?producto=' . $producto . 
                        '&ubicacion=' . $ubicacion . 
                        '&tipo=' . urlencode($tipo) .
                        '&fecha_desde=' . $fecha_desde .
                        '&fecha_hasta=' . $fecha_hasta;
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$custom_js = <<<'JS'
<script>
feather.replace();
</script>
JS;

include '../../includes/footer.php';
?>