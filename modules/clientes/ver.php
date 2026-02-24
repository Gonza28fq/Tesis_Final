<?php
// =============================================
// modules/clientes/ver.php
// Ver Detalles del Cliente
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('clientes', 'ver');

$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_cliente == 0) {
    setAlerta('error', 'Cliente no válido');
    redirigir('index.php');
}

$db = getDB();

// Obtener datos del cliente con información relacionada
$sql = "SELECT c.*, 
        tc.nombre_tipo as tipo_cliente_nombre,
        lp.nombre_lista as lista_precio_nombre
        FROM clientes c
        LEFT JOIN tipos_cliente tc ON c.id_tipo_cliente = tc.id_tipo_cliente
        LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
        WHERE c.id_cliente = ?";

$stmt = $db->query($sql, [$id_cliente]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    setAlerta('error', 'Cliente no encontrado');
    redirigir('index.php');
}

// Obtener estadísticas del cliente (ventas)
$sql_stats = "SELECT 
              COUNT(v.id_venta) as total_ventas,
              COALESCE(SUM(v.total), 0) as monto_total_ventas,
              COALESCE(SUM(CASE WHEN v.estado = 'pendiente' THEN v.total ELSE 0 END), 0) as saldo_pendiente
              FROM ventas v
              WHERE v.id_cliente = ?";

$stmt_stats = $db->query($sql_stats, [$id_cliente]);
$estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Obtener últimas ventas del cliente
$sql_ventas = "SELECT v.*, u.nombre_completo as nombre_usuario 
               FROM ventas v
               LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
               WHERE v.id_cliente = ?
               ORDER BY v.fecha_venta DESC
               LIMIT 10";

$stmt_ventas = $db->query($sql_ventas, [$id_cliente]);
$ventas_recientes = $stmt_ventas->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <i data-feather="user"></i> 
            <?php echo htmlspecialchars($cliente['nombre']); ?>
            <?php if ($cliente['estado'] == 'activo'): ?>
            <span class="badge bg-success">Activo</span>
            <?php else: ?>
            <span class="badge bg-secondary">Inactivo</span>
            <?php endif; ?>
        </h1>
        
        <div>
            <?php if (tienePermiso('clientes', 'editar')): ?>
            <a href="editar.php?id=<?php echo $id_cliente; ?>" class="btn btn-warning">
                <i data-feather="edit"></i> Editar
            </a>
            <?php endif; ?>
            
            <?php if (tienePermiso('ventas', 'crear')): ?>
            <a href="../ventas/nueva.php?cliente=<?php echo $id_cliente; ?>" class="btn btn-success">
                <i data-feather="shopping-cart"></i> Nueva Venta
            </a>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary">
                <i data-feather="arrow-left"></i> Volver
            </a>
        </div>
    </div>
    
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
            <li class="breadcrumb-item active">Ver Cliente</li>
        </ol>
    </nav>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Total Ventas</h6>
                        <h3 class="mb-0"><?php echo $estadisticas['total_ventas']; ?></h3>
                    </div>
                    <div>
                        <i data-feather="shopping-bag" width="48" height="48"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Monto Total</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($estadisticas['monto_total_ventas']); ?></h3>
                    </div>
                    <div>
                        <i data-feather="dollar-sign" width="48" height="48"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card <?php echo $estadisticas['saldo_pendiente'] > 0 ? 'bg-warning' : 'bg-info'; ?> text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Saldo Pendiente</h6>
                        <h3 class="mb-0"><?php echo formatearMoneda($estadisticas['saldo_pendiente']); ?></h3>
                    </div>
                    <div>
                        <i data-feather="credit-card" width="48" height="48"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Información del Cliente -->
    <div class="col-md-6">
        <!-- Datos Básicos -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="user"></i> Información Básica
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <th width="40%">Nombre:</th>
                            <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                        </tr>
                        <tr>
                            <th>Documento:</th>
                            <td><?php echo htmlspecialchars($cliente['documento']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php if (!empty($cliente['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($cliente['email']); ?>">
                                        <?php echo htmlspecialchars($cliente['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No especificado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Teléfono:</th>
                            <td>
                                <?php if (!empty($cliente['telefono'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($cliente['telefono']); ?>">
                                        <?php echo htmlspecialchars($cliente['telefono']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No especificado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Información Fiscal -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="file-text"></i> Información Fiscal
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tbody>
                        <tr>
                            <th width="40%">Tipo de Cliente:</th>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($cliente['tipo_cliente_nombre'] ?? 'Sin especificar'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>CUIT/CUIL:</th>
                            <td><?php echo !empty($cliente['cuit_cuil']) ? htmlspecialchars($cliente['cuit_cuil']) : '<span class="text-muted">No especificado</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Razón Social:</th>
                            <td><?php echo !empty($cliente['razon_social']) ? htmlspecialchars($cliente['razon_social']) : '<span class="text-muted">No especificado</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Condición IVA:</th>
                            <td><?php echo !empty($cliente['condicion_iva']) ? htmlspecialchars($cliente['condicion_iva']) : '<span class="text-muted">No especificado</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>Lista de Precios:</th>
                            <td>
                                <?php if (!empty($cliente['lista_precio_nombre'])): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($cliente['lista_precio_nombre']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Precio base</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Dirección -->
        <div class="card mb-4">
            <div class="card-header">
                <i data-feather="map-pin"></i> Dirección
            </div>
            <div class="card-body">
                <?php if (!empty($cliente['direccion'])): ?>
                <p class="mb-2"><strong>Dirección:</strong> <?php echo htmlspecialchars($cliente['direccion']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($cliente['ciudad']) || !empty($cliente['provincia']) || !empty($cliente['codigo_postal'])): ?>
                <p class="mb-0">
                    <?php if (!empty($cliente['ciudad'])): ?>
                        <strong>Ciudad:</strong> <?php echo htmlspecialchars($cliente['ciudad']); ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($cliente['provincia'])): ?>
                        | <strong>Provincia:</strong> <?php echo htmlspecialchars($cliente['provincia']); ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($cliente['codigo_postal'])): ?>
                        | <strong>CP:</strong> <?php echo htmlspecialchars($cliente['codigo_postal']); ?>
                    <?php endif; ?>
                </p>
                <?php else: ?>
                <p class="text-muted mb-0">No se especificó dirección completa</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Columna Derecha -->
    <div class="col-md-6">
        <!-- Configuración Comercial -->
                <?php if ($cliente['limite_credito'] > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i data-feather="credit-card"></i> Estado de Cuenta Corriente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted d-block mb-1">Límite de Crédito</small>
                                    <h4 class="mb-0 text-primary"><?php echo formatearMoneda($cliente['limite_credito']); ?></h4>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted d-block mb-1">Crédito Utilizado</small>
                                    <h4 class="mb-0 text-danger"><?php echo formatearMoneda($cliente['credito_utilizado'] ?? 0); ?></h4>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="border rounded p-3 text-center">
                                    <small class="text-muted d-block mb-1">Crédito Disponible</small>
                                    <?php 
                                    $credito_disponible = $cliente['limite_credito'] - ($cliente['credito_utilizado'] ?? 0);
                                    $porcentaje_usado = (($cliente['credito_utilizado'] ?? 0) / $cliente['limite_credito']) * 100;
                                    ?>
                                    <h4 class="mb-0 text-<?php echo $credito_disponible <= 0 ? 'danger' : ($porcentaje_usado > 80 ? 'warning' : 'success'); ?>">
                                        <?php echo formatearMoneda($credito_disponible); ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Barra de progreso -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Uso del crédito: <?php echo number_format($porcentaje_usado, 1); ?>%</small>
                                <?php if ($cliente['credito_utilizado'] > 0): ?>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalActualizarCredito">
                                    <i data-feather="dollar-sign" width="14"></i> Actualizar Cuenta Corriente
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?php echo $porcentaje_usado > 80 ? 'danger' : ($porcentaje_usado > 50 ? 'warning' : 'success'); ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo min($porcentaje_usado, 100); ?>%">
                                    <?php echo number_format($porcentaje_usado, 1); ?>%
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($credito_disponible <= 0): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i data-feather="alert-circle"></i> <strong>Sin crédito disponible.</strong> El cliente debe realizar un pago antes de continuar comprando.
                        </div>
                        <?php elseif ($porcentaje_usado > 80): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i data-feather="alert-triangle"></i> <strong>Crédito bajo.</strong> El cliente está utilizando más del 80% de su límite.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Configuración Comercial -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i data-feather="settings"></i> Configuración Comercial
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <th width="40%">Estado:</th>
                                    <td>
                                        <?php if ($cliente['estado'] == 'activo'): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Fecha de Registro:</th>
                                    <td><?php echo formatearFechaHora($cliente['fecha_creacion']); ?></td>
                                </tr>
                                <?php if (!empty($cliente['fecha_modificacion'])): ?>
                                <tr>
                                    <th>Última Modificación:</th>
                                    <td><?php echo formatearFechaHora($cliente['fecha_modificacion']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

<!-- Ventas Recientes -->
<?php if (!empty($ventas_recientes)): ?>
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i data-feather="shopping-bag"></i> Ventas Recientes
            </div>
            <a href="../ventas/index.php?cliente=<?php echo $id_cliente; ?>" class="btn btn-sm btn-primary">
                Ver Todas
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>N° Venta</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Vendedor</th>
                        <th width="100">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas_recientes as $venta): ?>
                    <tr>
                        <td><?php echo formatearFecha($venta['fecha_venta']); ?></td>
                        <td><strong>#<?php echo str_pad($venta['id_venta'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                        <td><strong><?php echo formatearMoneda($venta['total']); ?></strong></td>
                        <td>
                            <?php
                            $badge_class = [
                                'completada' => 'success',
                                'pendiente' => 'warning',
                                'cancelada' => 'danger'
                            ];
                            $class = $badge_class[$venta['estado']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $class; ?>">
                                <?php echo ucfirst($venta['estado']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($venta['id_usuario'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="../ventas/ver.php?id=<?php echo $venta['id_venta']; ?>" class="btn btn-sm btn-info" title="Ver venta">
                                <i data-feather="eye" width="16"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i data-feather="shopping-bag" width="64" height="64" class="text-muted mb-3"></i>
        <h5 class="text-muted">No hay ventas registradas</h5>
        <p class="text-muted">Este cliente aún no tiene ventas en el sistema</p>
        <?php if (tienePermiso('ventas', 'crear')): ?>
        <a href="../ventas/nueva.php?cliente=<?php echo $id_cliente; ?>" class="btn btn-primary">
            <i data-feather="plus-circle"></i> Crear Primera Venta
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<!-- Modal Actualizar Cuenta Corriente -->
<div class="modal fade" id="modalActualizarCredito" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="actualizar_credito.php" id="formActualizarCredito">
                <input type="hidden" name="id_cliente" value="<?php echo $cliente['id_cliente']; ?>">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i data-feather="dollar-sign"></i> Registrar Pago de Cuenta Corriente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Cliente:</strong> <?php echo htmlspecialchars($cliente['nombre']); ?><br>
                        <strong>Deuda actual:</strong> <?php echo formatearMoneda($cliente['credito_utilizado'] ?? 0); ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto del Pago <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-lg" name="monto" id="monto_pago"
                               step="0.01" min="0.01" max="<?php echo $cliente['credito_utilizado'] ?? 0; ?>" 
                               placeholder="0.00" required>
                        <small class="text-muted">Máximo a pagar: <?php echo formatearMoneda($cliente['credito_utilizado'] ?? 0); ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Forma de Pago <span class="text-danger">*</span></label>
                        <select class="form-select" name="forma_pago" required>
                            <option value="">Seleccione...</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Tarjeta Débito">Tarjeta Débito</option>
                            <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Número de Comprobante</label>
                        <input type="text" class="form-control" name="numero_comprobante" 
                               placeholder="Opcional - Nro. de recibo, transferencia, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3" 
                                  placeholder="Notas adicionales sobre el pago..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i data-feather="x"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i data-feather="check-circle"></i> Confirmar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reemplazar iconos
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>