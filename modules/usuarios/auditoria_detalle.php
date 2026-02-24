<?php
// =============================================
// modules/usuarios/auditoria_detalle.php
// Vista Rápida de Auditoría (MODAL)
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('auditoria', 'ver');

$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ID no válido</div>';
    exit;
}

$id = (int)$_GET['id'];

// Obtener el registro
$sql = "SELECT a.*, u.nombre_completo, u.usuario, u.email, r.nombre_rol
        FROM auditoria a
        INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
        INNER JOIN roles r ON u.id_rol = r.id_rol
        WHERE a.id_auditoria = ?";

$stmt = $db->query($sql, [$id]);
$registro = $stmt->fetch();

if (!$registro) {
    echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Registro no encontrado</div>';
    exit;
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

// Extraer ID del registro afectado
$id_registro_afectado = extraerIdDeDescripcion($registro['descripcion']);

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
    'crear' => 'bi-plus-circle-fill',
    'actualizar' => 'bi-pencil-square',
    'eliminar' => 'bi-trash-fill',
    'ver' => 'bi-eye-fill',
    'login' => 'bi-box-arrow-in-right',
    'logout' => 'bi-box-arrow-right',
    'exportar' => 'bi-download'
];
$action_icon = $action_icons[$registro['accion']] ?? 'bi-circle-fill';
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="mb-3"><i class="bi bi-person-circle"></i> Información del Usuario</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>Usuario:</strong></td>
                <td><code><?php echo htmlspecialchars($registro['usuario']); ?></code></td>
            </tr>
            <tr>
                <td><strong>Nombre Completo:</strong></td>
                <td><?php echo htmlspecialchars($registro['nombre_completo']); ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo htmlspecialchars($registro['email']); ?></td>
            </tr>
            <tr>
                <td><strong>Rol:</strong></td>
                <td><span class="badge bg-primary"><?php echo htmlspecialchars($registro['nombre_rol']); ?></span></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="mb-3"><i class="bi bi-file-text"></i> Información de la Operación</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>ID Registro:</strong></td>
                <td><code>#<?php echo $registro['id_auditoria']; ?></code></td>
            </tr>
            <tr>
                <td><strong>Fecha y Hora:</strong></td>
                <td><?php echo formatearFechaHora($registro['fecha_hora']); ?></td>
            </tr>
            <tr>
                <td><strong>Módulo:</strong></td>
                <td><span class="badge bg-info"><?php echo ucfirst($registro['modulo']); ?></span></td>
            </tr>
            <tr>
                <td><strong>Acción:</strong></td>
                <td><span class="badge bg-<?php echo $badge_color; ?>"><?php echo ucfirst($registro['accion']); ?></span></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<div class="row">
    <div class="col-12">
        <h6 class="mb-3"><i class="bi bi-card-text"></i> Descripción Detallada</h6>
        <div class="alert alert-light">
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($registro['descripcion'])); ?></p>
        </div>
    </div>
</div>

<hr>

<div class="row">
    <div class="col-md-6">
        <h6 class="mb-3"><i class="bi bi-globe"></i> Información Técnica</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>Dirección IP:</strong></td>
                <td><code><?php echo htmlspecialchars($registro['ip_address']); ?></code></td>
            </tr>
            <tr>
                <td><strong>Tipo IP:</strong></td>
                <td>
                    <?php
                    if (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        echo '<span class="badge bg-info">IPv4</span>';
                    } elseif (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        echo '<span class="badge bg-info">IPv6</span>';
                    } else {
                        echo '<span class="badge bg-secondary">Desconocido</span>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Ubicación Estimada:</strong></td>
                <td>
                    <?php
                    // Detectar si es IP local o pública
                    if (filter_var($registro['ip_address'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        echo '<span class="text-success">IP Pública</span>';
                    } else {
                        echo '<span class="text-warning">Red Local</span>';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="mb-3"><i class="bi bi-diagram-3"></i> Contexto Adicional</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>Tiempo Transcurrido:</strong></td>
                <td>
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
                </td>
            </tr>
            <tr>
                <td><strong>Día de la Semana:</strong></td>
                <td>
                    <?php
                    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    echo $dias[date('w', $fecha_registro)];
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Horario:</strong></td>
                <td>
                    <?php
                    $hora = (int)date('H', $fecha_registro);
                    if ($hora >= 6 && $hora < 12) {
                        echo '<span class="badge bg-warning">Mañana</span>';
                    } elseif ($hora >= 12 && $hora < 18) {
                        echo '<span class="badge bg-info">Tarde</span>';
                    } elseif ($hora >= 18 && $hora < 24) {
                        echo '<span class="badge bg-primary">Noche</span>';
                    } else {
                        echo '<span class="badge bg-dark">Madrugada</span>';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<hr>

<!-- Operaciones Relacionadas -->
<div class="row">
    <div class="col-12">
        <h6 class="mb-3"><i class="bi bi-clock-history"></i> Operaciones Recientes del Usuario</h6>
        <?php
        // Obtener últimas operaciones del mismo usuario
        $sql_relacionadas = "SELECT a.*, u.usuario
                             FROM auditoria a
                             INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                             WHERE a.id_usuario = ? AND a.id_auditoria != ?
                             ORDER BY a.fecha_hora DESC
                             LIMIT 5";
        
        $relacionadas = $db->query($sql_relacionadas, [$registro['id_usuario'], $id])->fetchAll();
        
        if (!empty($relacionadas)):
        ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Módulo</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relacionadas as $rel): ?>
                    <tr>
                        <td><small><?php echo formatearFechaHora($rel['fecha_hora']); ?></small></td>
                        <td><span class="badge bg-info"><?php echo $rel['modulo']; ?></span></td>
                        <td>
                            <?php
                            $color_rel = $badge_colors[$rel['accion']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color_rel; ?>"><?php echo $rel['accion']; ?></span>
                        </td>
                        <td><small><?php echo htmlspecialchars(substr($rel['descripcion'], 0, 80)); ?>...</small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">No hay operaciones recientes adicionales de este usuario.</p>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3 text-end">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        Cerrar
    </button>
</div>