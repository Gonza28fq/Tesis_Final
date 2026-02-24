<?php
require_once 'config/constantes.php';
require_once 'config/conexion.php';
require_once 'includes/funciones.php';

iniciarSesion();

if (!estaLogueado()) {
    redirigir('login.php');
}

$titulo_pagina = 'Dashboard';
$db = getDB();

// CORRECCIÓN: Obtener el usuario correctamente
// La función estaLogueado() ya verifica la sesión, ahora obtenemos los datos

$id_usuario = $_SESSION['usuario_id'] ?? 0;
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$rol_id = $_SESSION['rol_id'] ?? 0;
$nombre_rol = $_SESSION['rol_nombre'] ?? 'Sin rol';


// Crear array $usuario para compatibilidad con el resto del código
$usuario = [
    'id_usuario' => $id_usuario,
    'nombre_completo' => $nombre_usuario,
    'id_rol' => $rol_id
];

$rol_info = $db->query("SELECT nombre_rol FROM roles WHERE id_rol = ?", [$rol_id])->fetch();
$nombre_rol = $rol_info['nombre_rol'] ?? 'Usuario';

$es_admin = in_array($rol_id, [1, 2]);
$es_jefe = in_array($rol_id, [3, 5, 7, 8]);
$es_vendedor = $rol_id == 4;
$es_almacenero = $rol_id == 6;

try {
    if ($es_admin || $es_jefe) {
        $kpis = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM clientes WHERE estado = 'activo') as total_clientes,
                (SELECT COUNT(*) FROM productos WHERE estado = 'activo') as total_productos,
                (SELECT COUNT(*) FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())) as ventas_mes,
                (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE()) AND estado = 'completada') as ingresos_mes,
                (SELECT COUNT(*) FROM ventas WHERE DATE(fecha_venta) = CURDATE()) as ventas_hoy,
                (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE(fecha_venta) = CURDATE() AND estado = 'completada') as ingresos_hoy
        ")->fetch();
        
        $alertas = [];
        
        $stock_bajo = $db->count("
            SELECT COUNT(DISTINCT p.id_producto) 
            FROM productos p
            INNER JOIN stock s ON p.id_producto = s.id_producto
            WHERE s.cantidad <= p.stock_minimo AND s.cantidad > 0 AND p.estado = 'activo'
        ");
        if ($stock_bajo > 0) $alertas[] = ['tipo' => 'warning', 'icono' => 'exclamation-triangle', 'mensaje' => "$stock_bajo productos con stock bajo"];
        
        $sin_stock = $db->count("
            SELECT COUNT(DISTINCT p.id_producto) 
            FROM productos p
            LEFT JOIN stock s ON p.id_producto = s.id_producto
            WHERE (s.cantidad IS NULL OR s.cantidad = 0) AND p.estado = 'activo'
        ");
        if ($sin_stock > 0) $alertas[] = ['tipo' => 'danger', 'icono' => 'x-circle', 'mensaje' => "$sin_stock productos sin stock"];
        
        $ventas_pendientes = $db->count("SELECT COUNT(*) FROM ventas WHERE estado = 'pendiente'");
        if ($ventas_pendientes > 0) $alertas[] = ['tipo' => 'info', 'icono' => 'clock', 'mensaje' => "$ventas_pendientes ventas pendientes"];
        
        $mes_actual = date('Y-m');
        $mes_anterior = date('Y-m', strtotime('-1 month'));
        $comparacion = $db->query("
            SELECT 
                (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND estado = 'completada') as mes_actual,
                (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = ? AND estado = 'completada') as mes_anterior
        ", [$mes_actual, $mes_anterior])->fetch();
        
        $tendencia_ventas = 0;
        if ($comparacion['mes_anterior'] > 0) {
            $tendencia_ventas = (($comparacion['mes_actual'] - $comparacion['mes_anterior']) / $comparacion['mes_anterior']) * 100;
        }
        
        $actividad_reciente = $db->query("
            SELECT 'venta' as tipo, v.numero_venta as referencia, c.nombre as detalle, v.total as monto, v.fecha_creacion as fecha
            FROM ventas v
            INNER JOIN clientes c ON v.id_cliente = c.id_cliente
            WHERE v.fecha_venta >= CURDATE() - INTERVAL 7 DAY
            UNION ALL
            SELECT 'compra' as tipo, co.numero_compra as referencia, p.nombre_proveedor as detalle, co.total as monto, co.fecha_creacion as fecha
            FROM compras co
            INNER JOIN proveedores p ON co.id_proveedor = p.id_proveedor
            WHERE co.fecha_compra >= CURDATE() - INTERVAL 7 DAY
            ORDER BY fecha DESC
            LIMIT 10
        ")->fetchAll();
        
        $top_clientes = $db->query("
            SELECT c.nombre, COUNT(v.id_venta) as compras, SUM(v.total) as total
            FROM ventas v
            INNER JOIN clientes c ON v.id_cliente = c.id_cliente
            WHERE MONTH(v.fecha_venta) = MONTH(CURRENT_DATE()) 
            AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
            AND v.estado = 'completada'
            GROUP BY c.id_cliente, c.nombre
            ORDER BY total DESC
            LIMIT 5
        ")->fetchAll();
        
        $top_productos = $db->query("
            SELECT p.nombre, p.codigo, SUM(vd.cantidad) as cantidad, SUM(vd.subtotal) as total
            FROM ventas_detalle vd
            INNER JOIN ventas v ON vd.id_venta = v.id_venta
            INNER JOIN productos p ON vd.id_producto = p.id_producto
            WHERE MONTH(v.fecha_venta) = MONTH(CURRENT_DATE())
            AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
            AND v.estado = 'completada'
            GROUP BY p.id_producto, p.nombre, p.codigo
            ORDER BY cantidad DESC
            LIMIT 5
        ")->fetchAll();
        
    } elseif ($es_vendedor) {
        $id_usuario = $usuario['id_usuario'];
        
        $kpis = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM ventas WHERE id_usuario = ? AND MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())) as ventas_mes,
                (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE id_usuario = ? AND MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE()) AND estado = 'completada') as ingresos_mes,
                (SELECT COUNT(*) FROM ventas WHERE id_usuario = ? AND DATE(fecha_venta) = CURDATE()) as ventas_hoy,
                (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE id_usuario = ? AND DATE(fecha_venta) = CURDATE() AND estado = 'completada') as ingresos_hoy,
                (SELECT COUNT(DISTINCT id_cliente) FROM ventas WHERE id_usuario = ? AND MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())) as clientes_atendidos,
                (SELECT AVG(total) FROM ventas WHERE id_usuario = ? AND estado = 'completada' AND MONTH(fecha_venta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())) as ticket_promedio
        ", [$id_usuario, $id_usuario, $id_usuario, $id_usuario, $id_usuario, $id_usuario])->fetch();
        
        $alertas = [];
        $pendientes = $db->count("SELECT COUNT(*) FROM ventas WHERE id_usuario = ? AND estado = 'pendiente'", [$id_usuario]);
        if ($pendientes > 0) $alertas[] = ['tipo' => 'warning', 'icono' => 'clock', 'mensaje' => "Tienes $pendientes ventas pendientes"];
        
        $actividad_reciente = $db->query("
            SELECT v.numero_venta as referencia, c.nombre as detalle, v.total as monto, v.fecha_creacion as fecha, v.estado
            FROM ventas v
            INNER JOIN clientes c ON v.id_cliente = c.id_cliente
            WHERE v.id_usuario = ?
            ORDER BY v.fecha_creacion DESC
            LIMIT 10
        ", [$id_usuario])->fetchAll();
        
        $top_clientes = $db->query("
            SELECT c.nombre, COUNT(v.id_venta) as compras, SUM(v.total) as total
            FROM ventas v
            INNER JOIN clientes c ON v.id_cliente = c.id_cliente
            WHERE v.id_usuario = ?
            AND MONTH(v.fecha_venta) = MONTH(CURRENT_DATE())
            AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
            AND v.estado = 'completada'
            GROUP BY c.id_cliente, c.nombre
            ORDER BY total DESC
            LIMIT 5
        ", [$id_usuario])->fetchAll();
        
        $top_productos = $db->query("
            SELECT p.nombre, p.codigo, SUM(vd.cantidad) as cantidad, SUM(vd.subtotal) as total
            FROM ventas_detalle vd
            INNER JOIN ventas v ON vd.id_venta = v.id_venta
            INNER JOIN productos p ON vd.id_producto = p.id_producto
            WHERE v.id_usuario = ?
            AND MONTH(v.fecha_venta) = MONTH(CURRENT_DATE())
            AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
            AND v.estado = 'completada'
            GROUP BY p.id_producto, p.nombre, p.codigo
            ORDER BY cantidad DESC
            LIMIT 5
        ", [$id_usuario])->fetchAll();
        
    } elseif ($es_almacenero) {
        $kpis = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM productos WHERE estado = 'activo') as total_productos,
                (SELECT COUNT(DISTINCT p.id_producto) FROM productos p INNER JOIN stock s ON p.id_producto = s.id_producto WHERE s.cantidad <= p.stock_minimo AND s.cantidad > 0 AND p.estado = 'activo') as stock_bajo,
                (SELECT COUNT(DISTINCT p.id_producto) FROM productos p LEFT JOIN stock s ON p.id_producto = s.id_producto WHERE (s.cantidad IS NULL OR s.cantidad = 0) AND p.estado = 'activo') as sin_stock,
                (SELECT COUNT(*) FROM movimientos_stock WHERE MONTH(fecha_movimiento) = MONTH(CURRENT_DATE()) AND YEAR(fecha_movimiento) = YEAR(CURRENT_DATE())) as movimientos_mes,
                (SELECT COUNT(*) FROM notas_pedido WHERE estado IN ('pendiente', 'aprobada')) as notas_pendientes,
                (SELECT COUNT(*) FROM compras WHERE estado = 'pendiente') as compras_pendientes
        ")->fetch();
        
        $alertas = [];
        if ($kpis['stock_bajo'] > 0) $alertas[] = ['tipo' => 'warning', 'icono' => 'exclamation-triangle', 'mensaje' => $kpis['stock_bajo'] . " productos con stock bajo"];
        if ($kpis['sin_stock'] > 0) $alertas[] = ['tipo' => 'danger', 'icono' => 'x-circle', 'mensaje' => $kpis['sin_stock'] . " productos sin stock"];
        if ($kpis['notas_pendientes'] > 0) $alertas[] = ['tipo' => 'info', 'icono' => 'file-text', 'mensaje' => $kpis['notas_pendientes'] . " notas de pedido pendientes"];
        
        $actividad_reciente = $db->query("
            SELECT ms.tipo_movimiento as tipo, p.nombre as detalle, ms.cantidad as cantidad, ms.fecha_movimiento as fecha, u.nombre_completo as usuario
            FROM movimientos_stock ms
            INNER JOIN productos p ON ms.id_producto = p.id_producto
            INNER JOIN usuarios u ON ms.id_usuario = u.id_usuario
            ORDER BY ms.fecha_movimiento DESC
            LIMIT 10
        ")->fetchAll();
        
        $productos_criticos = $db->query("
            SELECT p.codigo, p.nombre, COALESCE(SUM(s.cantidad), 0) as stock, p.stock_minimo
            FROM productos p
            LEFT JOIN stock s ON p.id_producto = s.id_producto
            WHERE p.estado = 'activo'
            AND (s.cantidad IS NULL OR s.cantidad <= p.stock_minimo)
            GROUP BY p.id_producto, p.codigo, p.nombre, p.stock_minimo
            ORDER BY stock ASC
            LIMIT 10
        ")->fetchAll();
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

include 'includes/header.php';
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.metric-card {
    border-radius: 12px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    margin: 0.5rem 0 0.25rem 0;
}

.metric-label {
    color: #6c757d;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.alert-card {
    border-left: 4px solid;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}

.activity-item {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.activity-item:hover {
    background: #f8f9fa;
}

.activity-item:last-child {
    border-bottom: none;
}

.quick-action {
    text-align: center;
    padding: 1.5rem;
    border-radius: 12px;
    background: white;
    border: 2px dashed #dee2e6;
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
    color: inherit;
}

.quick-action:hover {
    border-color: #667eea;
    background: #f8f9ff;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
}

.quick-action i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
}

.trend-badge {
    font-size: 0.875rem;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    font-weight: 600;
}

.section-card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 1.25rem 1.5rem;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain');
$fecha_formateada = strftime('%A, %d de %B de %Y');
// Si strftime no está disponible en PHP 8.1+, usar alternativa:
if (empty($fecha_formateada)) {
    $formatter = new IntlDateFormatter(
        'es_ES',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        'America/Argentina/Tucuman',
        IntlDateFormatter::GREGORIAN,
        "EEEE, dd 'de' MMMM 'de' yyyy"
    );
    $fecha_formateada = $formatter->format(new DateTime());
}
echo $fecha_formateada; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2 class="mb-2"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
            <p class="mb-0 opacity-75">
        <i class="bi bi-calendar3 me-2"></i>
            <?php
            $meses = [
                "January" => "enero", "February" => "febrero", "March" => "marzo",
                "April" => "abril", "May" => "mayo", "June" => "junio",
                "July" => "julio", "August" => "agosto", "September" => "septiembre",
                "October" => "octubre", "November" => "noviembre", "December" => "diciembre"
            ];

            $dias = [
                "Monday" => "Lunes", "Tuesday" => "Martes", "Wednesday" => "Miércoles",
                "Thursday" => "Jueves", "Friday" => "Viernes", "Saturday" => "Sábado",
                "Sunday" => "Domingo"
            ];

            $fecha = new DateTime();
            $dia = $dias[$fecha->format('l')];
            $mes = $meses[$fecha->format('F')];

            echo "$dia, " . $fecha->format('d') . " de $mes de " . $fecha->format('Y');
            ?>


            </p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0"> 
            <h4 class="mb-1">
                ¡Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!
            </h4>
            <p class="mb-0 opacity-75">
                <i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($nombre_rol); ?>
            </p>
        </div>
    </div>
</div>

<?php if (!empty($alertas)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card section-card">
            <div class="section-header">
                <i class="bi bi-bell"></i> Notificaciones Importantes
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($alertas as $alerta): ?>
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="alert-card border-<?php echo $alerta['tipo']; ?>">
                            <i class="bi bi-<?php echo $alerta['icono']; ?> text-<?php echo $alerta['tipo']; ?> me-2"></i>
                            <span><?php echo $alerta['mensaje']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-4">
    <?php if ($es_admin || $es_jefe): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Ventas Hoy</div>
                        <div class="metric-value text-primary"><?php echo number_format($kpis['ventas_hoy']); ?></div>
                        <small class="text-muted"><?php echo formatearMoneda($kpis['ingresos_hoy']); ?></small>
                    </div>
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-cart-check"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Ventas del Mes</div>
                        <div class="metric-value text-success"><?php echo number_format($kpis['ventas_mes']); ?></div>
                        <small class="text-muted"><?php echo formatearMoneda($kpis['ingresos_mes']); ?></small>
                        <?php if (isset($tendencia_ventas)): ?>
                        <div class="mt-1">
                            <span class="trend-badge bg-<?php echo $tendencia_ventas >= 0 ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $tendencia_ventas >= 0 ? 'success' : 'danger'; ?>">
                                <i class="bi bi-arrow-<?php echo $tendencia_ventas >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo number_format(abs($tendencia_ventas), 1); ?>%
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="metric-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Clientes</div>
                        <div class="metric-value text-info"><?php echo number_format($kpis['total_clientes']); ?></div>
                        <small class="text-muted">Activos</small>
                    </div>
                    <div class="metric-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Productos</div>
                        <div class="metric-value text-warning"><?php echo number_format($kpis['total_productos']); ?></div>
                        <small class="text-muted">En catálogo</small>
                    </div>
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($es_vendedor): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Mis Ventas Hoy</div>
                        <div class="metric-value text-primary"><?php echo number_format($kpis['ventas_hoy']); ?></div>
                        <small class="text-muted"><?php echo formatearMoneda($kpis['ingresos_hoy']); ?></small>
                    </div>
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-cart-check"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Ventas del Mes</div>
                        <div class="metric-value text-success"><?php echo number_format($kpis['ventas_mes']); ?></div>
                        <small class="text-muted"><?php echo formatearMoneda($kpis['ingresos_mes']); ?></small>
                    </div>
                    <div class="metric-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Clientes Atendidos</div>
                        <div class="metric-value text-info"><?php echo number_format($kpis['clientes_atendidos']); ?></div>
                        <small class="text-muted">Este mes</small>
                    </div>
                    <div class="metric-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Ticket Promedio</div>
                        <div class="metric-value text-warning"><?php echo formatearMoneda($kpis['ticket_promedio'] ?? 0); ?></div>
                        <small class="text-muted">Por venta</small>
                    </div>
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($es_almacenero): ?>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Total Productos</div>
                        <div class="metric-value text-primary"><?php echo number_format($kpis['total_productos']); ?></div>
                        <small class="text-muted">Activos</small>
                    </div>
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Stock Bajo</div>
                        <div class="metric-value text-warning"><?php echo number_format($kpis['stock_bajo']); ?></div>
                        <small class="text-muted">Requieren reposición</small>
                    </div>
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Sin Stock</div>
                        <div class="metric-value text-danger"><?php echo number_format($kpis['sin_stock']); ?></div>
                        <small class="text-muted">Agotados</small>
                    </div>
                    <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="metric-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="metric-label">Movimientos</div>
                        <div class="metric-value text-info"><?php echo number_format($kpis['movimientos_mes']); ?></div>
                        <small class="text-muted">Este mes</small>
                    </div>
                    <div class="metric-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card section-card mb-4">
            <div class="section-header">
                <i class="bi bi-clock-history"></i>
                <?php 
                if ($es_vendedor) {
                    echo 'Mis Últimas Operaciones';
                } elseif ($es_almacenero) {
                    echo 'Últimos Movimientos de Stock';
                } else {
                    echo 'Actividad Reciente';
                }
                ?>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($actividad_reciente)): ?>
                    <?php foreach ($actividad_reciente as $actividad): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <?php if ($es_almacenero): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <?php
                                        $tipo_icons = [
                                            'entrada' => ['icon' => 'arrow-down-circle', 'color' => 'success'],
                                            'salida' => ['icon' => 'arrow-up-circle', 'color' => 'danger'],
                                            'transferencia' => ['icon' => 'arrow-left-right', 'color' => 'info'],
                                            'ajuste' => ['icon' => 'tools', 'color' => 'warning'],
                                            'devolucion' => ['icon' => 'arrow-counterclockwise', 'color' => 'secondary']
                                        ];
                                        $tipo_info = $tipo_icons[$actividad['tipo']] ?? ['icon' => 'circle', 'color' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?php echo $tipo_info['color']; ?>">
                                            <i class="bi bi-<?php echo $tipo_info['icon']; ?>"></i>
                                            <?php echo ucfirst($actividad['tipo']); ?>
                                        </span>
                                        <strong><?php echo $actividad['cantidad']; ?> unidades</strong>
                                    </div>
                                    <div><?php echo htmlspecialchars($actividad['detalle']); ?></div>
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($actividad['usuario']); ?>
                                    </small>
                                <?php else: ?>
                                    <?php if (isset($actividad['tipo'])): ?>
                                        <span class="badge bg-<?php echo $actividad['tipo'] == 'venta' ? 'success' : 'primary'; ?> mb-1">
                                            <i class="bi bi-<?php echo $actividad['tipo'] == 'venta' ? 'cart-check' : 'shopping-bag'; ?>"></i>
                                            <?php echo ucfirst($actividad['tipo']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($actividad['referencia']); ?></strong>
                                        - <?php echo htmlspecialchars($actividad['detalle']); ?>
                                    </div>
                                    <?php if (isset($actividad['estado'])): ?>
                                        <span class="badge bg-<?php echo $actividad['estado'] == 'completada' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($actividad['estado']); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if (isset($actividad['monto'])): ?>
                                    <div class="fw-bold text-success"><?php echo formatearMoneda($actividad['monto']); ?></div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i>
                                    <?php echo date('d/m H:i', strtotime($actividad['fecha'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3 mb-0">No hay actividad reciente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card section-card">
            <div class="section-header">
                <i class="bi bi-lightning-charge"></i> Accesos Rápidos
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if (tienePermiso('ventas', 'crear')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>ventas/nueva.php" class="quick-action">
                            <i class="bi bi-cart-plus text-primary"></i>
                            <div class="fw-semibold">Nueva Venta</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('clientes', 'crear')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>clientes/nuevo.php" class="quick-action">
                            <i class="bi bi-person-plus text-success"></i>
                            <div class="fw-semibold">Nuevo Cliente</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('productos', 'crear')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>productos/nuevo.php" class="quick-action">
                            <i class="bi bi-box-seam text-info"></i>
                            <div class="fw-semibold">Nuevo Producto</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('compras', 'crear')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>compras/nueva.php" class="quick-action">
                            <i class="bi bi-bag"></i>
                            <div class="fw-semibold">Nueva Compra</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('ventas', 'ver')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>ventas/index.php" class="quick-action">
                            <i class="bi bi-receipt text-primary"></i>
                            <div class="fw-semibold">Ver Ventas</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('clientes', 'ver')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>clientes/index.php" class="quick-action">
                            <i class="bi bi-people text-success"></i>
                            <div class="fw-semibold">Ver Clientes</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('productos', 'ver')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>productos/index.php" class="quick-action">
                            <i class="bi bi-box text-info"></i>
                            <div class="fw-semibold">Ver Productos</div>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (tienePermiso('stock', 'ver')): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?php echo MODULES_URL; ?>stock/index.php" class="quick-action">
                            <i class="bi bi-boxes text-warning"></i>
                            <div class="fw-semibold">Ver Stock</div>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <?php if ($es_almacenero && !empty($productos_criticos)): ?>
            <div class="card section-card mb-4">
                <div class="section-header">
                    <i class="bi bi-exclamation-diamond"></i> Productos Críticos
                </div>
                <div class="card-body p-0">
                    <?php foreach ($productos_criticos as $producto): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <small class="text-muted d-block"><?php echo htmlspecialchars($producto['codigo']); ?></small>
                                <strong><?php echo htmlspecialchars(substr($producto['nombre'], 0, 35)); ?></strong>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-<?php echo $producto['stock'] == 0 ? 'danger' : 'warning'; ?>">
                                    <?php echo $producto['stock']; ?> / <?php echo $producto['stock_minimo']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?php echo MODULES_URL; ?>productos/index.php?estado=stock_bajo" class="btn btn-sm btn-outline-primary w-100">
                        Ver Todos <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($top_clientes)): ?>
            <div class="card section-card mb-4">
                <div class="section-header">
                    <i class="bi bi-trophy"></i> 
                    <?php echo $es_vendedor ? 'Mis Mejores Clientes' : 'Top 5 Clientes'; ?>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($top_clientes as $index => $cliente): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3 flex-grow-1">
                                <div class="fs-4 fw-bold text-muted" style="min-width: 30px;">
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-<?php echo ['warning', 'secondary', 'info'][$index]; ?> rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars(substr($cliente['nombre'], 0, 30)); ?></strong>
                                    <br><small class="text-muted"><?php echo $cliente['compras']; ?> compras</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?php echo formatearMoneda($cliente['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?php echo MODULES_URL; ?>clientes/estadisticas.php" class="btn btn-sm btn-outline-success w-100">
                        Ver Estadísticas <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($top_productos)): ?>
            <div class="card section-card">
                <div class="section-header">
                    <i class="bi bi-star"></i> 
                    <?php echo $es_vendedor ? 'Mis Productos Más Vendidos' : 'Top 5 Productos'; ?>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($top_productos as $index => $producto): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-start gap-3 flex-grow-1">
                                <div class="fs-4 fw-bold text-muted" style="min-width: 30px;">
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-<?php echo ['warning', 'secondary', 'info'][$index]; ?> rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?php echo $index + 1; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($producto['codigo']); ?></small>
                                    <strong><?php echo htmlspecialchars(substr($producto['nombre'], 0, 30)); ?></strong>
                                    <br><small class="text-muted"><?php echo number_format($producto['cantidad']); ?> unidades</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-primary"><?php echo formatearMoneda($producto['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?php echo MODULES_URL; ?>productos/estadisticas.php" class="btn btn-sm btn-outline-primary w-100">
                        Ver Estadísticas <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card section-card bg-light border-0">
            <div class="card-body text-center py-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Panel actualizado automáticamente. Última actualización: <?php echo date('d/m/Y H:i'); ?>
                </small>
            </div>
        </div>
    </div>
</div>

<script>
setTimeout(function() {
    location.reload();
}, 300000);

document.addEventListener('DOMContentLoaded', function() {
    const metrics = document.querySelectorAll('.metric-card');
    metrics.forEach((metric, index) => {
        setTimeout(() => {
            metric.style.opacity = '0';
            metric.style.transform = 'translateY(20px)';
            metric.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                metric.style.opacity = '1';
                metric.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
});
</script>

<?php include 'includes/footer.php'; ?>