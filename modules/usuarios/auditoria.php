<?php
// =============================================
// modules/usuarios/auditoria.php
// Ver Auditoría del Sistema - COMPLETO
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('auditoria', 'ver');

$titulo_pagina = 'Auditoría del Sistema';
$breadcrumb = [
    'Usuarios' => 'modules/usuarios/index.php',
    'Auditoría' => 'modules/usuarios/auditoria.php'
];

$db = getDB();

// Filtros
$usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$modulo = isset($_GET['modulo']) ? limpiarInput($_GET['modulo']) : '';
$accion = isset($_GET['accion']) ? limpiarInput($_GET['accion']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$orden = isset($_GET['orden']) ? limpiarInput($_GET['orden']) : 'fecha_hora';
$direccion = isset($_GET['dir']) ? limpiarInput($_GET['dir']) : 'DESC';

// Validar orden
$columnas_permitidas = ['fecha_hora', 'usuario', 'modulo', 'accion', 'ip_address'];
if (!in_array($orden, $columnas_permitidas)) {
    $orden = 'fecha_hora';
}
if (!in_array($direccion, ['ASC', 'DESC'])) {
    $direccion = 'DESC';
}

// Construir filtros
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

// Contar total
$sql_count = "SELECT COUNT(*) FROM auditoria a WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);

// Paginación
$paginacion = calcularPaginacion($total_registros, $pagina);

// Consulta con ordenamiento
$sql = "SELECT a.*, u.nombre_completo, u.usuario
        FROM auditoria a
        INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
        WHERE $where_clause
        ORDER BY a.$orden $direccion
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$registros = $db->query($sql, $params)->fetchAll();

// Obtener usuarios para filtro
$usuarios = $db->query("SELECT id_usuario, usuario, nombre_completo FROM usuarios ORDER BY nombre_completo")->fetchAll();

// Obtener módulos
$modulos = $db->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo")->fetchAll();

// Obtener acciones
$acciones = $db->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll();

// Estadísticas
$stats_hoy = $db->count("SELECT COUNT(*) FROM auditoria WHERE DATE(fecha_hora) = CURDATE()");
$stats_semana = $db->count("SELECT COUNT(*) FROM auditoria WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats_mes = $db->count("SELECT COUNT(*) FROM auditoria WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

require_once '../../includes/header.php';
?>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Registros Hoy</h6>
                        <h3 class="mb-0"><?php echo formatearNumero($stats_hoy); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-calendar-day fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Última Semana</h6>
                        <h3 class="mb-0"><?php echo formatearNumero($stats_semana); ?></h3>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-calendar-week fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Último Mes</h6>
                        <h3 class="mb-0"><?php echo formatearNumero($stats_mes); ?></h3>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-calendar-month fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-funnel"></i> Filtros de Búsqueda
        </div>
        <div>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalExportar">
                <i class="bi bi-download"></i> Exportar
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="" id="form-filtros">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Usuario</label>
                    <select class="form-select" name="usuario">
                        <option value="0">Todos los usuarios</option>
                        <?php foreach ($usuarios as $u): ?>
                        <option value="<?php echo $u['id_usuario']; ?>" <?php echo $usuario == $u['id_usuario'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['nombre_completo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Módulo</label>
                    <select class="form-select" name="modulo">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $m): ?>
                        <option value="<?php echo $m['modulo']; ?>" <?php echo $modulo == $m['modulo'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($m['modulo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Acción</label>
                    <select class="form-select" name="accion">
                        <option value="">Todas</option>
                        <?php foreach ($acciones as $a): ?>
                        <option value="<?php echo $a['accion']; ?>" <?php echo $accion == $a['accion'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($a['accion']); ?>
                        </option>
                        <?php endforeach; ?>
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
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-9">
                    <input type="text" class="form-control" name="buscar" placeholder="Buscar en descripción o IP..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-3">
                    <a href="auditoria.php" class="btn btn-secondary w-100">
                        <i class="bi bi-x"></i> Limpiar Filtros
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list"></i> Registros de Auditoría (<?php echo formatearNumero($total_registros); ?>)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'fecha_hora', 'dir' => $orden == 'fecha_hora' && $direccion == 'DESC' ? 'ASC' : 'DESC'])); ?>" class="text-decoration-none">
                                Fecha/Hora 
                                <?php if ($orden == 'fecha_hora'): ?>
                                    <i class="bi bi-arrow-<?php echo $direccion == 'DESC' ? 'down' : 'up'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'usuario', 'dir' => $orden == 'usuario' && $direccion == 'ASC' ? 'DESC' : 'ASC'])); ?>" class="text-decoration-none">
                                Usuario
                                <?php if ($orden == 'usuario'): ?>
                                    <i class="bi bi-arrow-<?php echo $direccion == 'DESC' ? 'down' : 'up'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'modulo', 'dir' => $orden == 'modulo' && $direccion == 'ASC' ? 'DESC' : 'ASC'])); ?>" class="text-decoration-none">
                                Módulo
                                <?php if ($orden == 'modulo'): ?>
                                    <i class="bi bi-arrow-<?php echo $direccion == 'DESC' ? 'down' : 'up'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'accion', 'dir' => $orden == 'accion' && $direccion == 'ASC' ? 'DESC' : 'ASC'])); ?>" class="text-decoration-none">
                                Acción
                                <?php if ($orden == 'accion'): ?>
                                    <i class="bi bi-arrow-<?php echo $direccion == 'DESC' ? 'down' : 'up'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Descripción</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'ip_address', 'dir' => $orden == 'ip_address' && $direccion == 'ASC' ? 'DESC' : 'ASC'])); ?>" class="text-decoration-none">
                                IP
                                <?php if ($orden == 'ip_address'): ?>
                                    <i class="bi bi-arrow-<?php echo $direccion == 'DESC' ? 'down' : 'up'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th width="80">Detalle</th>

                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">No hay registros que coincidan con los filtros</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $reg): ?>
                        <tr>
                            <td>
                                <small><?php echo formatearFechaHora($reg['fecha_hora']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($reg['nombre_completo']); ?></strong>
                                <br><small class="text-muted">@<?php echo htmlspecialchars($reg['usuario']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo ucfirst($reg['modulo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $badge_color = [
                                    'crear' => 'success',
                                    'actualizar' => 'warning',
                                    'eliminar' => 'danger',
                                    'ver' => 'info',
                                    'login' => 'primary',
                                    'logout' => 'secondary'
                                ];
                                $color = $badge_color[$reg['accion']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($reg['accion']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($reg['descripcion']); ?></small>
                            </td>
                            <td>
                                <small><code><?php echo htmlspecialchars($reg['ip_address']); ?></code></small>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" onclick="verDetalle(<?php echo $reg['id_auditoria']; ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($paginacion['total_paginas'] > 1): ?>
        <div class="mt-3">
            <?php
            $url_params = $_GET;
            unset($url_params['pagina']);
            $url_base = 'auditoria.php?' . http_build_query($url_params);
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Exportar -->
<div class="modal fade" id="modalExportar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download"></i> Exportar Logs de Auditoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Seleccione el formato de exportación:</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success btn-lg" onclick="exportarAuditoria('excel')">
                        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="exportarAuditoria('csv')">
                        <i class="bi bi-filetype-csv"></i> Exportar a CSV
                    </button>
                    <button type="button" class="btn btn-danger btn-lg" onclick="exportarAuditoria('pdf')">
                        <i class="bi bi-file-earmark-pdf"></i> Exportar a PDF
                    </button>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <small>
                        <i class="bi bi-info-circle"></i> 
                        Se exportarán los registros según los filtros aplicados.
                        Total: <strong><?php echo formatearNumero($total_registros); ?></strong> registros.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-text"></i> Detalle del Registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetalle">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function verDetalle(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
    modal.show();
    
    fetch('auditoria_detalle.php?id=' + id)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoDetalle').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('contenidoDetalle').innerHTML = 
                '<div class="alert alert-danger">Error al cargar el detalle</div>';
        });
}

function exportarAuditoria(formato) {
    const form = document.getElementById('form-filtros');
    const params = new URLSearchParams(new FormData(form));
    params.append('formato', formato);
    
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalExportar'));
    if (modal) {
        modal.hide();
    }
    
    // Abrir en nueva ventana
    window.open('auditoria_exportar.php?' + params.toString(), '_blank');
}
</script>

<?php require_once '../../includes/footer.php'; ?>