<?php
// =============================================
// modules/usuarios/trazabilidad.php
// Trazabilidad Completa de un Registro
// CU-AUD-003: Rastrear Trazabilidad de Registro
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('auditoria', 'ver');

$titulo_pagina = 'Trazabilidad de Registro';
$breadcrumb = [
    'Usuarios' => 'modules/usuarios/index.php',
    'Auditoría' => 'modules/usuarios/auditoria.php',
    'Trazabilidad' => ''
];

$db = getDB();

// Obtener parámetros
$modulo = isset($_GET['modulo']) ? limpiarInput($_GET['modulo']) : '';
$id_registro = isset($_GET['id_registro']) ? (int)$_GET['id_registro'] : 0;

if (empty($modulo) || $id_registro <= 0) {
    setAlerta('error', 'Parámetros inválidos para trazabilidad');
    redirigir('auditoria.php');
}

// Mapeo de módulos a tablas y campos
$config_modulos = [
    'productos' => [
        'tabla' => 'productos',
        'campo_id' => 'id_producto',
        'campo_nombre' => 'nombre',
        'url_ver' => '../productos/ver.php'
    ],
    'clientes' => [
        'tabla' => 'clientes',
        'campo_id' => 'id_cliente',
        'campo_nombre' => 'nombre',
        'url_ver' => '../clientes/ver.php'
    ],
    'ventas' => [
        'tabla' => 'ventas',
        'campo_id' => 'id_venta',
        'campo_nombre' => 'numero_venta',
        'url_ver' => '../ventas/ver.php'
    ],
    'compras' => [
        'tabla' => 'compras',
        'campo_id' => 'id_compra',
        'campo_nombre' => 'numero_compra',
        'url_ver' => '../compras/ver.php'
    ],
    'usuarios' => [
        'tabla' => 'usuarios',
        'campo_id' => 'id_usuario',
        'campo_nombre' => 'nombre_completo',
        'url_ver' => '../usuarios/ver.php'
    ]
];

// Validar módulo
if (!isset($config_modulos[$modulo])) {
    setAlerta('error', 'Módulo no soportado para trazabilidad');
    redirigir('auditoria.php');
}

$config = $config_modulos[$modulo];

// Obtener información del registro actual
$sql_registro = "SELECT {$config['campo_nombre']}, fecha_creacion, estado 
                 FROM {$config['tabla']} 
                 WHERE {$config['campo_id']} = ?";
$stmt = $db->query($sql_registro, [$id_registro]);
$registro_actual = $stmt->fetch();

if (!$registro_actual) {
    setAlerta('error', 'Registro no encontrado');
    redirigir('auditoria.php');
}

// Obtener TODO el historial de cambios
$sql_historial = "SELECT a.*, u.nombre_completo, u.usuario
                  FROM auditoria a
                  INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                  WHERE a.modulo = ? 
                  AND (a.descripcion LIKE ? OR a.descripcion LIKE ?)
                  ORDER BY a.fecha_hora ASC";

$historial = $db->query($sql_historial, [
    $modulo,
    "%ID: $id_registro%",
    "%ID: " . $id_registro . ")%"
])->fetchAll();

// Agrupar por fecha
$historial_agrupado = [];
foreach ($historial as $registro) {
    $fecha = date('Y-m-d', strtotime($registro['fecha_hora']));
    if (!isset($historial_agrupado[$fecha])) {
        $historial_agrupado[$fecha] = [];
    }
    $historial_agrupado[$fecha][] = $registro;
}

require_once '../../includes/header.php';
?>

<!-- Información del Registro -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-file-text"></i> 
                <strong>Trazabilidad Completa:</strong> 
                <?php echo htmlspecialchars($registro_actual[$config['campo_nombre']]); ?>
            </div>
            <div>
                <a href="<?php echo $config['url_ver']; ?>?id=<?php echo $id_registro; ?>" class="btn btn-light btn-sm" target="_blank">
                    <i class="bi bi-box-arrow-up-right"></i> Ver Registro
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Módulo:</strong>
                <p class="text-muted"><span class="badge bg-info"><?php echo ucfirst($modulo); ?></span></p>
            </div>
            <div class="col-md-3">
                <strong>ID del Registro:</strong>
                <p class="text-muted"><code>#<?php echo $id_registro; ?></code></p>
            </div>
            <div class="col-md-3">
                <strong>Estado Actual:</strong>
                <p class="text-muted">
                    <span class="badge bg-<?php echo $registro_actual['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($registro_actual['estado']); ?>
                    </span>
                </p>
            </div>
            <div class="col-md-3">
                <strong>Fecha de Creación:</strong>
                <p class="text-muted"><?php echo formatearFechaHora($registro_actual['fecha_creacion']); ?></p>
            </div>
        </div>
        
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle"></i> 
            <strong>Total de Operaciones Registradas:</strong> <?php echo count($historial); ?>
        </div>
    </div>
</div>

<?php if (empty($historial)): ?>
<!-- Sin Historial -->
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-clock-history text-muted" style="font-size: 4rem;"></i>
        <h5 class="mt-3 text-muted">No hay historial de cambios registrado</h5>
        <p class="text-muted">Este registro no tiene operaciones de auditoría asociadas.</p>
    </div>
</div>

<?php else: ?>
<!-- Línea de Tiempo -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> Cronología de Cambios
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php foreach ($historial_agrupado as $fecha => $registros): ?>
            <!-- Separador de Fecha -->
            <div class="timeline-date">
                <h5>
                    <i class="bi bi-calendar3"></i> 
                    <?php
                    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
                    echo strftime('%d de %B de %Y', $fecha_obj->getTimestamp());
                    ?>
                </h5>
            </div>
            
            <!-- Registros del día -->
            <?php foreach ($registros as $reg): ?>
            <div class="timeline-item">
                <div class="timeline-marker 
                    <?php 
                    $marker_colors = [
                        'crear' => 'bg-success',
                        'actualizar' => 'bg-warning',
                        'eliminar' => 'bg-danger',
                        'ver' => 'bg-info'
                    ];
                    echo $marker_colors[$reg['accion']] ?? 'bg-secondary';
                    ?>">
                    <i class="bi 
                        <?php 
                        $icons = [
                            'crear' => 'bi-plus-circle',
                            'actualizar' => 'bi-pencil',
                            'eliminar' => 'bi-trash',
                            'ver' => 'bi-eye'
                        ];
                        echo $icons[$reg['accion']] ?? 'bi-circle';
                        ?>
                    "></i>
                </div>
                
                <div class="timeline-content">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-1">
                                        <span class="badge 
                                            <?php 
                                            $badge_colors = [
                                                'crear' => 'bg-success',
                                                'actualizar' => 'bg-warning text-dark',
                                                'eliminar' => 'bg-danger',
                                                'ver' => 'bg-info'
                                            ];
                                            echo $badge_colors[$reg['accion']] ?? 'bg-secondary';
                                            ?>">
                                            <?php echo ucfirst($reg['accion']); ?>
                                        </span>
                                        <?php 
                                        $titulos = [
                                            'crear' => 'Registro Creado',
                                            'actualizar' => 'Registro Modificado',
                                            'eliminar' => 'Registro Eliminado',
                                            'ver' => 'Registro Consultado'
                                        ];
                                        echo $titulos[$reg['accion']] ?? 'Operación Ejecutada';
                                        ?>
                                    </h6>
                                    <p class="card-text mb-2">
                                        <i class="bi bi-person"></i> 
                                        <strong><?php echo htmlspecialchars($reg['nombre_completo']); ?></strong>
                                        <small class="text-muted">(@<?php echo htmlspecialchars($reg['usuario']); ?>)</small>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> 
                                        <?php echo date('H:i:s', strtotime($reg['fecha_hora'])); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> 
                                        <?php echo htmlspecialchars($reg['ip_address']); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="mt-2">
                                <strong>Descripción:</strong>
                                <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($reg['descripcion'])); ?></p>
                            </div>
                            
                            <!-- Detalles adicionales si es actualización -->
                            <?php if ($reg['accion'] == 'actualizar' && strpos($reg['descripcion'], '→') !== false): ?>
                            <div class="mt-3 alert alert-light mb-0">
                                <strong><i class="bi bi-arrow-left-right"></i> Cambios Detectados:</strong>
                                <div class="mt-2">
                                    <?php
                                    // Intentar extraer cambios específicos de la descripción
                                    $lineas = explode("\n", $reg['descripcion']);
                                    foreach ($lineas as $linea) {
                                        if (strpos($linea, '→') !== false) {
                                            echo '<div class="change-line">' . htmlspecialchars($linea) . '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Estadísticas del Historial -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-bar-chart"></i> Estadísticas de Operaciones
    </div>
    <div class="card-body">
        <div class="row text-center">
            <?php
            $stats = [
                'crear' => 0,
                'actualizar' => 0,
                'eliminar' => 0,
                'ver' => 0
            ];
            
            foreach ($historial as $reg) {
                if (isset($stats[$reg['accion']])) {
                    $stats[$reg['accion']]++;
                }
            }
            
            $usuarios_unicos = array_unique(array_column($historial, 'id_usuario'));
            ?>
            
            <div class="col-md-2">
                <div class="stat-box">
                    <h3 class="text-success"><?php echo $stats['crear']; ?></h3>
                    <p class="text-muted mb-0">Creaciones</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <h3 class="text-warning"><?php echo $stats['actualizar']; ?></h3>
                    <p class="text-muted mb-0">Actualizaciones</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <h3 class="text-danger"><?php echo $stats['eliminar']; ?></h3>
                    <p class="text-muted mb-0">Eliminaciones</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <h3 class="text-info"><?php echo $stats['ver']; ?></h3>
                    <p class="text-muted mb-0">Consultas</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <h3 class="text-primary"><?php echo count($usuarios_unicos); ?></h3>
                    <p class="text-muted mb-0">Usuarios</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <h3 class="text-secondary"><?php echo count($historial); ?></h3>
                    <p class="text-muted mb-0">Total</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Acciones -->
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <a href="auditoria.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Auditoría
            </a>
            <div>
                <a href="<?php echo $config['url_ver']; ?>?id=<?php echo $id_registro; ?>" class="btn btn-info" target="_blank">
                    <i class="bi bi-eye"></i> Ver Registro
                </a>
                <button type="button" class="btn btn-success" onclick="exportarTrazabilidad()">
                    <i class="bi bi-download"></i> Exportar Historial
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para la línea de tiempo */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-date {
    margin: 30px 0 20px 0;
    padding-left: 60px;
}

.timeline-date h5 {
    color: #666;
    font-weight: 600;
}

.timeline-item {
    position: relative;
    padding-left: 60px;
    padding-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 30px;
    bottom: -20px;
    width: 2px;
    background: #dee2e6;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-marker {
    position: absolute;
    left: 8px;
    top: 8px;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    border: 3px solid #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    z-index: 1;
}

.timeline-content {
    flex: 1;
}

.stat-box {
    padding: 15px;
}

.stat-box h3 {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.change-line {
    padding: 5px 10px;
    margin: 5px 0;
    background: #f8f9fa;
    border-left: 3px solid #007bff;
    font-family: monospace;
    font-size: 0.9em;
}
</style>

<script>
function exportarTrazabilidad() {
    const params = new URLSearchParams({
        modulo: '<?php echo $modulo; ?>',
        id_registro: '<?php echo $id_registro; ?>',
        formato: 'excel'
    });
    
    window.open('trazabilidad_exportar.php?' + params.toString(), '_blank');
}
</script>

<?php require_once '../../includes/footer.php'; ?>