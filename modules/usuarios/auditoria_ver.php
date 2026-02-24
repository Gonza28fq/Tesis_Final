<?php
// =============================================
// modules/usuarios/auditoria_ver.php
// Ver Detalle Completo de Registro de Auditoría
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('auditoria', 'ver');

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setAlerta('error', 'ID de auditoría no válido');
    redirigir('auditoria.php');
}

$id_auditoria = (int)$_GET['id'];
$titulo_pagina = 'Detalle de Auditoría';

$db = getDB();

// Obtener el registro de auditoría con información del usuario
$sql = "SELECT a.*, 
        u.nombre_completo, 
        u.usuario, 
        u.email,
        r.nombre_rol
        FROM auditoria a
        INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE a.id_auditoria = ?";

$stmt = $db->query($sql, [$id_auditoria]);
$registro = $stmt->fetch();

if (!$registro) {
    setAlerta('error', 'Registro de auditoría no encontrado');
    redirigir('auditoria.php');
}

// Función auxiliar para extraer ID
if (!function_exists('extraerIdDeDescripcion')) {
    function extraerIdDeDescripcion($descripcion) {
        if (preg_match('/ID:\s*(\d+)\)/', $descripcion, $matches)) {
            return (int)$matches[1];
        }
        if (preg_match('/ID:\s*(\d+)/', $descripcion, $matches)) {
            return (int)$matches[1];
        }
        if (preg_match('/id_\w+:\s*(\d+)/', $descripcion, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
}

// Extraer ID del registro afectado (si existe)
$id_registro_afectado = extraerIdDeDescripcion($registro['descripcion']);

// Obtener operaciones relacionadas del mismo usuario en un rango de tiempo
$sql_relacionadas = "SELECT a.*, u.usuario
                     FROM auditoria a
                     INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                     WHERE a.id_usuario = ? 
                     AND a.id_auditoria != ?
                     AND a.fecha_hora BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)
                     ORDER BY a.fecha_hora DESC
                     LIMIT 10";

$relacionadas = $db->query($sql_relacionadas, [
    $registro['id_usuario'],
    $id_auditoria,
    $registro['fecha_hora'],
    $registro['fecha_hora']
])->fetchAll();

// Obtener todas las operaciones del mismo usuario en el mismo día
$sql_mismo_dia = "SELECT COUNT(*) as total
                  FROM auditoria
                  WHERE id_usuario = ?
                  AND DATE(fecha_hora) = DATE(?)";
$stmt = $db->query($sql_mismo_dia, [$registro['id_usuario'], $registro['fecha_hora']]);
$stats_mismo_dia = $stmt->fetch();

// Obtener información del navegador/sistema (si existe)
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'No disponible';

// Badge de color según acción
$badge_colors = [
    'crear' => 'success',
    'actualizar' => 'warning',
    'eliminar' => 'danger',
    'ver' => 'info',
    'login' => 'primary',
    'logout' => 'secondary',
    'exportar' => 'success'
];
$badge_color = $badge_colors[$registro['accion']] ?? 'secondary';

// Iconos según acción
$action_icons = [
    'crear' => 'bi-plus-circle',
    'actualizar' => 'bi-pencil-square',
    'eliminar' => 'bi-trash',
    'ver' => 'bi-eye',
    'login' => 'bi-box-arrow-in-right',
    'logout' => 'bi-box-arrow-right',
    'exportar' => 'bi-download'
];
$action_icon = $action_icons[$registro['accion']] ?? 'bi-circle';

$breadcrumb = [
    'Usuarios' => 'modules/usuarios/index.php',
    'Auditoría' => 'modules/usuarios/auditoria.php',
    'Detalle' => ''
];

include '../../includes/header.php';
?>

<!-- Header con información principal -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1>
                <i class="bi <?php echo $action_icon; ?>"></i> 
                Registro de Auditoría #<?php echo $id_auditoria; ?>
            </h1>
            <p class="lead">
                <span class="badge bg-<?php echo $badge_color; ?> fs-6">
                    <?php echo ucfirst($registro['accion']); ?>
                </span>
                en 
                <span class="badge bg-info fs-6">
                    <?php echo ucfirst($registro['modulo']); ?>
                </span>
            </p>
        </div>
        <div>
            <a href="auditoria.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Auditoría
            </a>
        </div>
    </div>
</div>

<!-- Información Principal -->
<div class="row mb-4">
    <!-- Fecha y Hora -->
    <div class="col-md-3">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-calendar-event fs-1 text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1 text-muted">Fecha y Hora</h6>
                        <p class="mb-0"><strong><?php echo formatearFechaHora($registro['fecha_hora']); ?></strong></p>
                        <small class="text-muted">
                            <?php
                            $fecha_registro = strtotime($registro['fecha_hora']);
                            $fecha_actual = time();
                            $diferencia = $fecha_actual - $fecha_registro;
                            
                            if ($diferencia < 60) {
                                echo "Hace " . $diferencia . " segundos";
                            } elseif ($diferencia < 3600) {
                                echo "Hace " . floor($diferencia / 60) . " minutos";
                            } elseif ($diferencia < 86400) {
                                echo "Hace " . floor($diferencia / 3600) . " horas";
                            } else {
                                echo "Hace " . floor($diferencia / 86400) . " días";
                            }
                            ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Usuario -->
    <div class="col-md-3">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-person-circle fs-1 text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1 text-muted">Usuario</h6>
                        <p class="mb-0"><strong><?php echo htmlspecialchars($registro['nombre_completo']); ?></strong></p>
                        <small class="text-muted">@<?php echo htmlspecialchars($registro['usuario']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- IP Address -->
    <div class="col-md-3">
        <div class="card border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-globe fs-1 text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1 text-muted">Dirección IP</h6>
                        <p class="mb-0"><code><?php echo htmlspecialchars($registro['ip_address']); ?></code></p>
                        <small class="text-muted">
                            <?php
                            if (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                                echo '<span class="text-success">IP Pública</span>';
                            } else {
                                echo '<span class="text-info">Red Local</span>';
                            }
                            ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Operaciones del Día -->
    <div class="col-md-3">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-activity fs-1 text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1 text-muted">Actividad del Día</h6>
                        <p class="mb-0"><strong><?php echo $stats_mismo_dia['total']; ?></strong> operaciones</p>
                        <small class="text-muted">Por este usuario</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenido Principal -->
<div class="row">
    <!-- Columna Izquierda -->
    <div class="col-md-8">
        <!-- Descripción Detallada -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-card-text"></i> Descripción de la Operación
            </div>
            <div class="card-body">
                <div class="alert alert-light border">
                    <p class="mb-0 fs-5"><?php echo nl2br(htmlspecialchars($registro['descripcion'])); ?></p>
                </div>
                
                <?php if ($id_registro_afectado > 0): ?>
                <div class="alert alert-info mt-3">
                    <strong><i class="bi bi-info-circle"></i> Registro Afectado:</strong>
                    <p class="mb-0">
                        ID del registro: <code>#<?php echo $id_registro_afectado; ?></code>
                        
                        <?php if (in_array($registro['modulo'], ['productos', 'clientes', 'ventas', 'compras', 'usuarios'])): ?>
                        <br>
                        <a href="trazabilidad.php?modulo=<?php echo $registro['modulo']; ?>&id_registro=<?php echo $id_registro_afectado; ?>" 
                           class="btn btn-sm btn-info mt-2" 
                           target="_blank">
                            <i class="bi bi-clock-history"></i> Ver Historial Completo del Registro
                        </a>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Información Detallada del Usuario -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person-badge"></i> Información del Usuario
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nombre de Usuario</label>
                        <p class="form-control-plaintext"><code><?php echo htmlspecialchars($registro['usuario']); ?></code></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nombre Completo</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($registro['nombre_completo']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <p class="form-control-plaintext">
                            <a href="mailto:<?php echo htmlspecialchars($registro['email']); ?>">
                                <?php echo htmlspecialchars($registro['email']); ?>
                            </a>
                        </p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Rol</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-primary">
                                <?php echo htmlspecialchars($registro['nombre_rol']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Operaciones Relacionadas -->
        <?php if (!empty($relacionadas)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-link-45deg"></i> Operaciones Relacionadas (±1 hora)
            </div>
            <div class="card-body">
                <p class="text-muted">Otras operaciones realizadas por el mismo usuario en un rango de 1 hora:</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Descripción</th>
                                <th>IP</th>
                                <th width="80"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relacionadas as $rel): ?>
                            <tr>
                                <td><small><?php echo formatearFechaHora($rel['fecha_hora']); ?></small></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($rel['modulo']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $color_rel = $badge_colors[$rel['accion']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color_rel; ?>">
                                        <?php echo ucfirst($rel['accion']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars(substr($rel['descripcion'], 0, 60)); ?>...</small></td>
                                <td><small><code><?php echo htmlspecialchars($rel['ip_address']); ?></code></small></td>
                                <td>
                                    <a href="auditoria_ver.php?id=<?php echo $rel['id_auditoria']; ?>" 
                                       class="btn btn-sm btn-info"
                                       title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Columna Derecha -->
    <div class="col-md-4">
        <!-- Información Técnica -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-gear"></i> Información Técnica
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">ID de Auditoría</label>
                    <p class="form-control-plaintext"><code>#<?php echo $id_auditoria; ?></code></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">ID de Usuario</label>
                    <p class="form-control-plaintext"><code>#<?php echo $registro['id_usuario']; ?></code></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Dirección IP</label>
                    <p class="form-control-plaintext">
                        <code><?php echo htmlspecialchars($registro['ip_address']); ?></code>
                        <br>
                        <?php
                        if (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            echo '<span class="badge bg-info">IPv4</span>';
                        } elseif (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                            echo '<span class="badge bg-info">IPv6</span>';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Tipo de Red</label>
                    <p class="form-control-plaintext">
                        <?php
                        if (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            echo '<span class="badge bg-success">IP Pública</span>';
                        } else {
                            echo '<span class="badge bg-warning">Red Local/Privada</span>';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Contexto Temporal -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-clock"></i> Contexto Temporal
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Fecha Completa</label>
                    <p class="form-control-plaintext">
                        <?php
                        $fecha_obj = strtotime($registro['fecha_hora']);
                        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        echo $dias[date('w', $fecha_obj)] . ', ';
                        echo date('d/m/Y', $fecha_obj);
                        ?>
                    </p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Hora Exacta</label>
                    <p class="form-control-plaintext">
                        <?php echo date('H:i:s', $fecha_obj); ?>
                        <?php
                        $hora = (int)date('H', $fecha_obj);
                        if ($hora >= 6 && $hora < 12) {
                            echo ' <span class="badge bg-warning">Mañana</span>';
                        } elseif ($hora >= 12 && $hora < 18) {
                            echo ' <span class="badge bg-info">Tarde</span>';
                        } elseif ($hora >= 18 && $hora < 24) {
                            echo ' <span class="badge bg-primary">Noche</span>';
                        } else {
                            echo ' <span class="badge bg-dark">Madrugada</span>';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Tiempo Transcurrido</label>
                    <p class="form-control-plaintext">
                        <?php
                        $diferencia = time() - $fecha_obj;
                        
                        if ($diferencia < 60) {
                            echo "Hace " . $diferencia . " segundos";
                        } elseif ($diferencia < 3600) {
                            echo "Hace " . floor($diferencia / 60) . " minutos";
                        } elseif ($diferencia < 86400) {
                            echo "Hace " . floor($diferencia / 3600) . " horas";
                        } else {
                            $dias = floor($diferencia / 86400);
                            echo "Hace " . $dias . " día" . ($dias > 1 ? 's' : '');
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning"></i> Acciones Rápidas
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="auditoria.php?usuario=<?php echo $registro['id_usuario']; ?>" 
                       class="btn btn-primary">
                        <i class="bi bi-person"></i> Ver Todas las Operaciones del Usuario
                    </a>
                    
                    <a href="auditoria.php?modulo=<?php echo $registro['modulo']; ?>" 
                       class="btn btn-info">
                        <i class="bi bi-folder"></i> Ver Operaciones del Módulo "<?php echo ucfirst($registro['modulo']); ?>"
                    </a>
                    
                    <a href="auditoria.php?accion=<?php echo $registro['accion']; ?>" 
                       class="btn btn-secondary">
                        <i class="bi bi-tag"></i> Ver Operaciones de "<?php echo ucfirst($registro['accion']); ?>"
                    </a>
                    
                    <?php if ($id_registro_afectado > 0 && in_array($registro['modulo'], ['productos', 'clientes', 'ventas', 'compras', 'usuarios'])): ?>
                    <hr>
                    <a href="trazabilidad.php?modulo=<?php echo $registro['modulo']; ?>&id_registro=<?php echo $id_registro_afectado; ?>" 
                       class="btn btn-warning" 
                       target="_blank">
                        <i class="bi bi-clock-history"></i> Ver Trazabilidad Completa
                    </a>
                    <?php endif; ?>
                    
                    <hr>
                    <a href="auditoria.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver a Auditoría
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>