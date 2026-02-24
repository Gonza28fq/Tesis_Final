<?php
// =============================================
// modules/clientes/index.php
// Listado de Clientes
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('clientes', 'ver');

$titulo_pagina = 'Gestión de Clientes';
$db = getDB();

// Parámetros de búsqueda y filtrado
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$tipo_cliente = isset($_GET['tipo_cliente']) ? (int)$_GET['tipo_cliente'] : 0;
$lista_precio = isset($_GET['lista_precio']) ? (int)$_GET['lista_precio'] : 0;
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : 'activo';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Construir filtros
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(c.nombre LIKE ? OR c.documento LIKE ? OR c.email LIKE ? OR c.cuit_cuil LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($tipo_cliente > 0) {
    $where[] = 'c.id_tipo_cliente = ?';
    $params[] = $tipo_cliente;
}

if ($lista_precio > 0) {
    $where[] = 'c.id_lista_precio = ?';
    $params[] = $lista_precio;
}

if (!empty($estado)) {
    $where[] = 'c.estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);

// Contar total de registros
$sql_count = "SELECT COUNT(*) FROM clientes c WHERE $where_clause";
$total_registros = $db->count($sql_count, $params);

// Calcular paginación
$paginacion = calcularPaginacion($total_registros, $pagina);

// Consulta principal
$sql = $sql = "SELECT c.*, 
        tc.nombre_tipo,
        lp.nombre_lista,
        (SELECT COUNT(*) FROM ventas WHERE id_cliente = c.id_cliente) as total_ventas,
        (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE id_cliente = c.id_cliente AND estado = 'completada') as total_gastado,
        (c.limite_credito - COALESCE(c.credito_utilizado, 0)) as credito_disponible,
        CASE 
            WHEN c.limite_credito > 0 AND (c.limite_credito - COALESCE(c.credito_utilizado, 0)) <= 0 THEN 'sin_credito'
            WHEN c.limite_credito > 0 AND (c.limite_credito - COALESCE(c.credito_utilizado, 0)) < (c.limite_credito * 0.20) THEN 'credito_bajo'
            ELSE 'credito_ok'
        END as estado_credito
        FROM clientes c
        INNER JOIN tipos_cliente tc ON c.id_tipo_cliente = tc.id_tipo_cliente
        LEFT JOIN listas_precios lp ON c.id_lista_precio = lp.id_lista_precio
        WHERE $where_clause
        ORDER BY c.fecha_creacion DESC
        LIMIT {$paginacion['registros_por_pagina']} OFFSET {$paginacion['offset']}";

$clientes = $db->query($sql, $params)->fetchAll();

// Obtener tipos de cliente para filtros
$tipos_cliente = $db->query("SELECT * FROM tipos_cliente ORDER BY nombre_tipo")->fetchAll();

// Obtener listas de precios para filtros
$listas_precios = $db->query("SELECT * FROM listas_precios WHERE estado = 'activa' ORDER BY nombre_lista")->fetchAll();

// Estadísticas
$total_clientes = $db->count("SELECT COUNT(*) FROM clientes WHERE estado = 'activo'");
$clientes_inactivos = $db->count("SELECT COUNT(*) FROM clientes WHERE estado = 'inactivo'");

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="users"></i> Gestión de Clientes</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item active">Clientes</li>
        </ol>
    </nav>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Clientes</h6>
                        <h3 class="mb-0"><?php echo $total_clientes; ?></h3>
                    </div>
                    <div class="text-primary">
                        <i data-feather="users" width="40" height="40"></i>
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
                        <h6 class="text-muted mb-1">Clientes Activos</h6>
                        <h3 class="mb-0 text-success"><?php echo $total_clientes; ?></h3>
                    </div>
                    <div class="text-success">
                        <i data-feather="check-circle" width="40" height="40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Clientes Inactivos</h6>
                        <h3 class="mb-0 text-warning"><?php echo $clientes_inactivos; ?></h3>
                    </div>
                    <div class="text-warning">
                        <i data-feather="user-x" width="40" height="40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Botones de Acción -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="btn-group" role="group">
                <?php if (tienePermiso('clientes', 'crear')): ?>
                <a href="nuevo.php" class="btn btn-primary">
                    <i data-feather="user-plus"></i> Nuevo Cliente
                </a>
                <?php endif; ?>
                 <?php if (tienePermiso('clientes', 'editar')): ?>
                <a href="../productos/listas_precios.php" class="btn btn-warning">
                    <i data-feather="dollar-sign"></i> Listas de Precios
                </a>
                <a href="estadisticas.php" class="btn btn-success">
                    <i data-feather="bar-chart-2"></i> Estadísticas
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (tienePermiso('clientes', 'exportar')): ?>
            <a href="exportar.php" class="btn btn-primary">
                <i data-feather="download"></i> Exportar
            </a>   
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="search"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="" id="form-filtros">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="buscar" placeholder="Nombre, email, DNI..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Tipo de Cliente</label>
                    <select class="form-select" name="tipo_cliente">
                        <option value="0">Todos</option>
                        <?php foreach ($tipos_cliente as $tipo): ?>
                        <option value="<?php echo $tipo['id_tipo_cliente']; ?>" <?php echo $tipo_cliente == $tipo['id_tipo_cliente'] ? 'selected' : ''; ?>>
                            <?php echo $tipo['nombre_tipo']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Lista de Precios</label>
                    <select class="form-select" name="lista_precio">
                        <option value="0">Todas</option>
                        <?php foreach ($listas_precios as $lista): ?>
                        <option value="<?php echo $lista['id_lista_precio']; ?>" <?php echo $lista_precio == $lista['id_lista_precio'] ? 'selected' : ''; ?>>
                            <?php echo $lista['nombre_lista']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo $estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo $estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="search"></i> Buscar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i data-feather="x"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Clientes -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Listado de Clientes (<?php echo $total_registros; ?> registros)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
               <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Tipo / Lista</th>
                        <th>Compras</th>
                        <th>Total Gastado</th>
                        <th>Crédito</th>
                        <th>Estado</th>
                        <th width="120">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No se encontraron clientes</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td>
                                <strong><?php echo $cliente['nombre']; ?></strong>
                                <br><small class="text-muted">
                                    <?php if ($cliente['cuit_cuil']): ?>
                                        CUIT: <?php echo $cliente['cuit_cuil']; ?>
                                    <?php else: ?>
                                        DNI: <?php echo $cliente['documento']; ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($cliente['email']): ?>
                                <i data-feather="mail" width="14"></i> <?php echo $cliente['email']; ?><br>
                                <?php endif; ?>
                                <?php if ($cliente['telefono']): ?>
                                <i data-feather="phone" width="14"></i> <?php echo $cliente['telefono']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $cliente['nombre_tipo']; ?></span>
                                <?php if ($cliente['nombre_lista']): ?>
                                <br><small class="text-muted"><?php echo $cliente['nombre_lista']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $cliente['total_ventas']; ?></span>
                            </td>
                            <td>
                                <strong><?php echo formatearMoneda($cliente['total_gastado']); ?></strong>
                            </td>
                            <td>
                                <?php if ($cliente['limite_credito'] > 0): ?>
                                    <div class="small">
                                        <strong>Límite:</strong> <?php echo formatearMoneda($cliente['limite_credito']); ?><br>
                                        <strong>Usado:</strong> <?php echo formatearMoneda($cliente['credito_utilizado']); ?><br>
                                        <strong>Disponible:</strong> 
                                        <span class="fw-bold text-<?php 
                                            echo $cliente['estado_credito'] == 'sin_credito' ? 'danger' : 
                                                ($cliente['estado_credito'] == 'credito_bajo' ? 'warning' : 'success'); 
                                        ?>">
                                            <?php echo formatearMoneda($cliente['credito_disponible']); ?>
                                        </span>
                                        
                                        <?php if ($cliente['estado_credito'] == 'sin_credito'): ?>
                                            <br><span class="badge bg-danger mt-1">
                                                <i data-feather="alert-circle" width="12"></i> Sin crédito
                                            </span>
                                        <?php elseif ($cliente['estado_credito'] == 'credito_bajo'): ?>
                                            <br><span class="badge bg-warning mt-1">
                                                <i data-feather="alert-triangle" width="12"></i> Crédito bajo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Sin límite</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $cliente['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($cliente['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if (tienePermiso('clientes', 'editar')): ?>
                                    <a href="editar.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (tienePermiso('clientes', 'eliminar')): ?>
                                    <button type="button" class="btn btn-danger" title="Eliminar" onclick="eliminarCliente(<?php echo $cliente['id_cliente']; ?>, '<?php echo htmlspecialchars($cliente['nombre']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
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
            $url_base = 'index.php?buscar=' . urlencode($buscar) . 
                        '&tipo_cliente=' . $tipo_cliente . 
                        '&lista_precio=' . $lista_precio . 
                        '&estado=' . urlencode($estado);
            echo generarPaginacion($paginacion['total_paginas'], $paginacion['pagina_actual'], $url_base);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$custom_js = <<<'JS'
<script>
function eliminarCliente(id, nombre) {
    if (confirm('¿Está seguro de eliminar el cliente "' + nombre + '"?\n\nEsta acción cambiará su estado a inactivo.')) {
        fetch('acciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=eliminar&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.mensaje);
                location.reload();
            } else {
                alert('Error: ' + data.mensaje);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}


feather.replace();
</script>
JS;

include '../../includes/footer.php';
?>