<?php
// =============================================
// modules/reportes/programar.php
// Programación de Envío Automático de Reportes
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('reportes', 'financieros'); // O crear permiso específico

$titulo_pagina = 'Programar Reportes';
$db = getDB();
$pdo = $db->getConexion();

// Crear tabla si no existe
$sql_tabla = "CREATE TABLE IF NOT EXISTS reportes_programados (
    id_reporte_programado INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_reporte ENUM('financiero', 'stock', 'ventas', 'compras') NOT NULL,
    frecuencia ENUM('diario', 'semanal', 'mensual') NOT NULL,
    dia_envio INT DEFAULT NULL COMMENT 'Día del mes (mensual) o día de semana (semanal: 1=Lunes)',
    hora_envio TIME NOT NULL,
    destinatarios TEXT NOT NULL COMMENT 'Emails separados por coma',
    formato ENUM('pdf', 'excel') NOT NULL DEFAULT 'pdf',
    activo BOOLEAN DEFAULT TRUE,
    ultimo_envio DATETIME NULL,
    proximo_envio DATETIME NULL,
    id_usuario_creador INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB";
$pdo->exec($sql_tabla);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = limpiarInput($_POST['nombre']);
        $tipo_reporte = $_POST['tipo_reporte'];
        $frecuencia = $_POST['frecuencia'];
        $dia_envio = isset($_POST['dia_envio']) ? (int)$_POST['dia_envio'] : null;
        $hora_envio = $_POST['hora_envio'];
        $destinatarios = limpiarInput($_POST['destinatarios']);
        $formato = $_POST['formato'];
        
        // Calcular próximo envío
        $proximo_envio = calcularProximoEnvio($frecuencia, $dia_envio, $hora_envio);
        
        try {
            $sql = "INSERT INTO reportes_programados 
                    (nombre, tipo_reporte, frecuencia, dia_envio, hora_envio, destinatarios, formato, proximo_envio, id_usuario_creador) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $tipo_reporte, $frecuencia, $dia_envio, $hora_envio, $destinatarios, $formato, $proximo_envio, $_SESSION['usuario_id']]);
            
            registrarAuditoria('reportes', 'crear_programacion', "Reporte: $nombre");
            setAlerta('success', 'Programación de reporte creada correctamente');
        } catch (Exception $e) {
            setAlerta('error', 'Error al crear programación: ' . $e->getMessage());
        }
        redirigir('programar.php');
    }
    
    if ($accion === 'toggle') {
        $id = (int)$_POST['id'];
        $activo = (int)$_POST['activo'];
        
        $sql = "UPDATE reportes_programados SET activo = ? WHERE id_reporte_programado = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$activo, $id]);
        
        registrarAuditoria('reportes', 'toggle_programacion', "ID: $id, Activo: $activo");
        setAlerta('success', $activo ? 'Reporte activado' : 'Reporte desactivado');
        redirigir('programar.php');
    }
    
    if ($accion === 'eliminar') {
        $id = (int)$_POST['id'];
        
        $sql = "DELETE FROM reportes_programados WHERE id_reporte_programado = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        registrarAuditoria('reportes', 'eliminar_programacion', "ID: $id");
        setAlerta('success', 'Programación eliminada');
        redirigir('programar.php');
    }
}

// Obtener reportes programados
$sql = "SELECT rp.*, u.nombre_completo as creador
        FROM reportes_programados rp
        INNER JOIN usuarios u ON rp.id_usuario_creador = u.id_usuario
        ORDER BY rp.activo DESC, rp.proximo_envio ASC";
$reportes_programados = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Función auxiliar para calcular próximo envío
function calcularProximoEnvio($frecuencia, $dia_envio, $hora_envio) {
    $ahora = new DateTime();
    $fecha_envio = new DateTime();
    
    list($hora, $minuto) = explode(':', $hora_envio);
    $fecha_envio->setTime((int)$hora, (int)$minuto, 0);
    
    switch ($frecuencia) {
        case 'diario':
            // Si ya pasó la hora hoy, programar para mañana
            if ($fecha_envio <= $ahora) {
                $fecha_envio->modify('+1 day');
            }
            break;
            
        case 'semanal':
            // $dia_envio: 1=Lunes, 7=Domingo
            $dia_actual = (int)$ahora->format('N');
            $dias_hasta = ($dia_envio - $dia_actual + 7) % 7;
            
            if ($dias_hasta == 0 && $fecha_envio <= $ahora) {
                $dias_hasta = 7;
            }
            
            $fecha_envio->modify("+{$dias_hasta} days");
            break;
            
        case 'mensual':
            // $dia_envio: día del mes (1-31)
            $fecha_envio->setDate((int)$ahora->format('Y'), (int)$ahora->format('m'), $dia_envio);
            
            if ($fecha_envio <= $ahora) {
                $fecha_envio->modify('+1 month');
            }
            break;
    }
    
    return $fecha_envio->format('Y-m-d H:i:s');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="clock"></i> Programar Envío de Reportes</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Reportes</a></li>
            <li class="breadcrumb-item active">Programar</li>
        </ol>
    </nav>
</div>

<!-- Botón Nueva Programación -->
<div class="card mb-4">
    <div class="card-body">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo">
            <i data-feather="plus-circle"></i> Nueva Programación
        </button>
    </div>
</div>

<!-- Listado de Reportes Programados -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i data-feather="list"></i> Reportes Programados (<?php echo count($reportes_programados); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($reportes_programados)): ?>
        <div class="text-center py-5 text-muted">
            <i data-feather="inbox" width="48" height="48"></i>
            <p class="mt-2">No hay reportes programados</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Frecuencia</th>
                        <th>Próximo Envío</th>
                        <th>Destinatarios</th>
                        <th>Formato</th>
                        <th>Estado</th>
                        <th width="150">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes_programados as $reporte): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($reporte['nombre']); ?></strong><br>
                            <small class="text-muted">Por: <?php echo htmlspecialchars($reporte['creador']); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo ucfirst($reporte['tipo_reporte']); ?></span>
                        </td>
                        <td>
                            <?php
                            $frecuencia_texto = ucfirst($reporte['frecuencia']);
                            if ($reporte['frecuencia'] == 'semanal') {
                                $dias = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                $frecuencia_texto .= ' (' . $dias[$reporte['dia_envio']] . ')';
                            } elseif ($reporte['frecuencia'] == 'mensual') {
                                $frecuencia_texto .= ' (Día ' . $reporte['dia_envio'] . ')';
                            }
                            $frecuencia_texto .= ' a las ' . substr($reporte['hora_envio'], 0, 5);
                            ?>
                            <?php echo $frecuencia_texto; ?>
                        </td>
                        <td>
                            <?php if ($reporte['proximo_envio']): ?>
                            <span class="text-primary">
                                <i data-feather="calendar"></i>
                                <?php echo date('d/m/Y H:i', strtotime($reporte['proximo_envio'])); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars(substr($reporte['destinatarios'], 0, 40)); ?>
                            <?php if (strlen($reporte['destinatarios']) > 40): ?>...</<?php endif; ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo strtoupper($reporte['formato']); ?></span>
                        </td>
                        <td>
                            <?php if ($reporte['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $reporte['id_reporte_programado']; ?>">
                                    <input type="hidden" name="activo" value="<?php echo $reporte['activo'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-<?php echo $reporte['activo'] ? 'warning' : 'success'; ?>" 
                                            title="<?php echo $reporte['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                        <i data-feather="<?php echo $reporte['activo'] ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-danger btn-eliminar" 
                                        data-id="<?php echo $reporte['id_reporte_programado']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($reporte['nombre']); ?>">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Información de Configuración -->
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i data-feather="info"></i> Información Importante</h6>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li><strong>Configuración de Email:</strong> Asegúrese de tener configurado el servidor SMTP en el sistema.</li>
            <li><strong>Ejecución:</strong> Los reportes se envían automáticamente según la programación mediante un cron job.</li>
            <li><strong>Cron Job Sugerido:</strong> <code>*/15 * * * * php /ruta/al/proyecto/cron/enviar_reportes.php</code></li>
            <li><strong>Formatos:</strong> PDF (para impresión) o Excel (para análisis de datos).</li>
        </ul>
    </div>
</div>

<!-- Modal Nueva Programación -->
<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Programación de Reporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de la Programación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" required placeholder="Ej: Reporte Mensual Gerencia">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Reporte <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_reporte" required>
                                <option value="financiero">Financiero</option>
                                <option value="stock">Stock e Inventario</option>
                                <option value="ventas">Ventas</option>
                                <option value="compras">Compras</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Frecuencia <span class="text-danger">*</span></label>
                            <select class="form-select" name="frecuencia" id="frecuencia" required onchange="toggleDiaEnvio()">
                                <option value="diario">Diario</option>
                                <option value="semanal">Semanal</option>
                                <option value="mensual">Mensual</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3" id="div_dia_envio" style="display: none;">
                            <label class="form-label">Día de Envío</label>
                            <select class="form-select" name="dia_envio" id="dia_envio">
                                <!-- Se llenará dinámicamente -->
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora de Envío <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="hora_envio" required value="09:00">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Destinatarios (Emails) <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="destinatarios" rows="2" required 
                                  placeholder="email1@empresa.com, email2@empresa.com"></textarea>
                        <small class="text-muted">Separar múltiples emails con comas</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Formato <span class="text-danger">*</span></label>
                        <select class="form-select" name="formato" required>
                            <option value="pdf">PDF (para impresión)</option>
                            <option value="excel">Excel (para análisis)</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save"></i> Crear Programación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDiaEnvio() {
    const frecuencia = document.getElementById('frecuencia').value;
    const divDia = document.getElementById('div_dia_envio');
    const selectDia = document.getElementById('dia_envio');
    
    if (frecuencia === 'diario') {
        divDia.style.display = 'none';
        selectDia.required = false;
    } else {
        divDia.style.display = 'block';
        selectDia.required = true;
        selectDia.innerHTML = '';
        
        if (frecuencia === 'semanal') {
            const dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
            dias.forEach((dia, index) => {
                selectDia.innerHTML += `<option value="${index + 1}">${dia}</option>`;
            });
        } else if (frecuencia === 'mensual') {
            for (let i = 1; i <= 31; i++) {
                selectDia.innerHTML += `<option value="${i}">Día ${i}</option>`;
            }
        }
    }
}

// Botones Eliminar
document.querySelectorAll('.btn-eliminar').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const nombre = this.dataset.nombre;
        
        if (confirm(`¿Está seguro de eliminar la programación "${nombre}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Inicializar
toggleDiaEnvio();
feather.replace();
</script>

<?php include '../../includes/footer.php'; ?>