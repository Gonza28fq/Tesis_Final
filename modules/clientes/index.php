<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('clientes_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Filtros
$filtroBusqueda = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$filtroTipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
$filtroEstado = isset($_GET['estado']) ? sanitize($_GET['estado']) : 'activos';

try {
    $db = getDB();
    
    // Construir consulta
    $sql = "SELECT 
                c.*,
                COUNT(DISTINCT v.id_venta) as total_compras,
                COALESCE(SUM(v.total), 0) as total_gastado,
                MAX(v.fecha) as ultima_compra
            FROM Clientes c
            LEFT JOIN Ventas v ON c.id_cliente = v.id_cliente
            WHERE 1=1";
    
    $params = [];
    
    // Filtro de búsqueda
    if (!empty($filtroBusqueda)) {
        $sql .= " AND (c.nombre LIKE :busqueda1 
                  OR c.apellido LIKE :busqueda2 
                  OR c.email LIKE :busqueda3 
                  OR c.dni_cuit LIKE :busqueda4)";
        $searchTerm = "%{$filtroBusqueda}%";
        $params[':busqueda1'] = $searchTerm;
        $params[':busqueda2'] = $searchTerm;
        $params[':busqueda3'] = $searchTerm;
        $params[':busqueda4'] = $searchTerm;
    }
    
    // Filtro por tipo
    if (!empty($filtroTipo)) {
        $sql .= " AND c.tipo_cliente = :tipo";
        $params[':tipo'] = $filtroTipo;
    }
    
    // Filtro por estado
    if ($filtroEstado === 'activos') {
        $sql .= " AND c.activo = 1";
    } elseif ($filtroEstado === 'inactivos') {
        $sql .= " AND c.activo = 0";
    }
    
    $sql .= " GROUP BY c.id_cliente
              ORDER BY c.fecha_registro DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
    
    // Estadísticas generales
    $sqlStats = "SELECT 
                    COUNT(DISTINCT c.id_cliente) as total_clientes,
                    COUNT(DISTINCT CASE WHEN c.activo = 1 THEN c.id_cliente END) as activos,
                    COUNT(DISTINCT CASE WHEN c.activo = 0 THEN c.id_cliente END) as inactivos,
                    COUNT(DISTINCT v.id_venta) as total_ventas
                 FROM Clientes c
                 LEFT JOIN Ventas v ON c.id_cliente = v.id_cliente";
    
    $stmtStats = $db->query($sqlStats);
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $clientes = [];
    $stats = ['total_clientes' => 0, 'activos' => 0, 'inactivos' => 0, 'total_ventas' => 0];
}

// Determinar segmento del cliente
function determinarSegmento($totalCompras, $totalGastado) {
    if ($totalCompras == 0) return 'Nuevo';
    if ($totalCompras >= 10 && $totalGastado >= 50000) return 'VIP';
    if ($totalCompras >= 5) return 'Regular';
    return 'Ocasional';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Sistema de Gestión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #4299e1 0%, #667eea 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4299e1 0%, #667eea 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #4299e1;
            color: white;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .content {
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total { border-color: #4299e1; }
        .stat-card.activos { border-color: #48bb78; }
        .stat-card.inactivos { border-color: #a0aec0; }
        .stat-card.ventas { border-color: #ed8936; }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
        }

        .filters {
            background: #f7fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid #e2e8f0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 13px;
            color: #4a5568;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .table-container {
            overflow-x: auto;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: #f7fafc;
            position: sticky;
            top: 0;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-vip {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .badge-regular {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge-ocasional {
            background: #feebc8;
            color: #744210;
        }

        .badge-nuevo {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-activo {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-inactivo {
            background: #e2e8f0;
            color: #718096;
        }

        .badge-consumidor {
            background: #e6f0ff;
            color: #2d3aa3;
        }

        .badge-responsable {
            background: #fef5e7;
            color: #f5a623;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #c6f6d5;
            border: 2px solid #9ae6b4;
            color: #22543d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Gestión de Clientes</h1>
            <div class="header-actions">
                <?php if (tienePermiso('clientes_crear')): ?>
                    <a href="nuevo.php" class="btn btn-success">➕ Nuevo Cliente</a>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="exportarExcel()">📊 Exportar</button>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✓ <?php 
                        if ($_GET['success'] === 'created') echo 'Cliente creado exitosamente';
                        elseif ($_GET['success'] === 'updated') echo 'Cliente actualizado exitosamente';
                        elseif ($_GET['success'] === 'deleted') echo 'Cliente desactivado exitosamente';
                    ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">👥</div>
                    <div class="stat-label">Total Clientes</div>
                    <div class="stat-value"><?php echo $stats['total_clientes']; ?></div>
                </div>

                <div class="stat-card activos">
                    <div class="stat-icon">✓</div>
                    <div class="stat-label">Activos</div>
                    <div class="stat-value"><?php echo $stats['activos']; ?></div>
                </div>

                <div class="stat-card inactivos">
                    <div class="stat-icon">⊗</div>
                    <div class="stat-label">Inactivos</div>
                    <div class="stat-value"><?php echo $stats['inactivos']; ?></div>
                </div>

                <div class="stat-card ventas">
                    <div class="stat-icon">💰</div>
                    <div class="stat-label">Total Ventas</div>
                    <div class="stat-value"><?php echo $stats['total_ventas']; ?></div>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros de Búsqueda</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Buscar</label>
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($filtroBusqueda); ?>" placeholder="Nombre, email, DNI...">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Cliente</label>
                        <select name="tipo">
                            <option value="">Todos</option>
                            <option value="consumidor_final" <?php echo $filtroTipo === 'consumidor_final' ? 'selected' : ''; ?>>Consumidor Final</option>
                            <option value="responsable_inscripto" <?php echo $filtroTipo === 'responsable_inscripto' ? 'selected' : ''; ?>>Responsable Inscripto</option>
                            <option value="monotributista" <?php echo $filtroTipo === 'monotributista' ? 'selected' : ''; ?>>Monotributista</option>
                            <option value="exento" <?php echo $filtroTipo === 'exento' ? 'selected' : ''; ?>>Exento</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="todos" <?php echo $filtroEstado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="activos" <?php echo $filtroEstado === 'activos' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivos" <?php echo $filtroEstado === 'inactivos' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="index.php" class="btn btn-secondary">Limpiar</a>
            </form>

            <!-- Tabla de Clientes -->
            <div class="table-container">
                <?php if (empty($clientes)): ?>
                    <div class="empty-state">
                        <h3>No se encontraron clientes</h3>
                        <p>Ajuste los filtros o cree un nuevo cliente</p>
                    </div>
                <?php else: ?>
                    <table id="tablaClientes">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th>Tipo</th>
                                <th>Segmento</th>
                                <th style="text-align: right;">Compras</th>
                                <th style="text-align: right;">Total Gastado</th>
                                <th>Última Compra</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): 
                                $segmento = determinarSegmento($cliente['total_compras'], $cliente['total_gastado']);
                                $badgeSegmento = '';
                                switch($segmento) {
                                    case 'VIP':
                                        $badgeSegmento = 'badge-vip';
                                        break;
                                    case 'Regular':
                                        $badgeSegmento = 'badge-regular';
                                        break;
                                    case 'Ocasional':
                                        $badgeSegmento = 'badge-ocasional';
                                        break;
                                    case 'Nuevo':
                                        $badgeSegmento = 'badge-nuevo';
                                        break;
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></strong>
                                        <?php if ($cliente['dni_cuit']): ?>
                                            <br><small style="color: #718096;">DNI/CUIT: <?php echo $cliente['dni_cuit']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $cliente['email']; ?>
                                        <?php if ($cliente['telefono']): ?>
                                            <br><small style="color: #718096;">Tel: <?php echo $cliente['telefono']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $cliente['tipo_cliente'] === 'responsable_inscripto' ? 'badge-responsable' : 'badge-consumidor'; ?>">
                                            <?php 
                                                $tipos = [
                                                    'consumidor_final' => 'Cons. Final',
                                                    'responsable_inscripto' => 'Resp. Insc.',
                                                    'monotributista' => 'Monotrib.',
                                                    'exento' => 'Exento'
                                                ];
                                                echo $tipos[$cliente['tipo_cliente']] ?? $cliente['tipo_cliente'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeSegmento; ?>">
                                            <?php echo $segmento; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;"><strong><?php echo $cliente['total_compras']; ?></strong></td>
                                    <td style="text-align: right;"><strong><?php echo formatearMoneda($cliente['total_gastado']); ?></strong></td>
                                    <td>
                                        <?php 
                                            if ($cliente['ultima_compra']) {
                                                echo date('d/m/Y', strtotime($cliente['ultima_compra']));
                                            } else {
                                                echo '<span style="color: #a0aec0;">Sin compras</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $cliente['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                            <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-primary btn-small" onclick="verDetalle(<?php echo $cliente['id_cliente']; ?>)">👁 Ver</button>
                                        <?php if (tienePermiso('clientes_editar')): ?>
                                            <a href="editar.php?id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-secondary btn-small">✏️ Editar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/clientes.js"></script>
</body>
</html>