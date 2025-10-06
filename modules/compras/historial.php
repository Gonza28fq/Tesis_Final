<?php
require_once '../../config/conexion.php';
validarSesion();

// Verificar permiso
if (!tienePermiso('compras_ver')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

// Obtener filtros
$filtroFechaDesde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$filtroProveedor = isset($_GET['proveedor']) ? sanitize($_GET['proveedor']) : '';
$filtroComprobante = isset($_GET['comprobante']) ? sanitize($_GET['comprobante']) : '';

try {
    $db = getDB();
    
    // Construir consulta con filtros
    $sql = "SELECT 
                i.id_ingreso,
                i.fecha,
                i.numero_comprobante,
                p.nombre as proveedor,
                p.cuit as proveedor_cuit,
                i.total_ingresado,
                i.observaciones,
                COUNT(di.id_detalle_ingreso) as cantidad_items
            FROM Ingresos i
            INNER JOIN Proveedores p ON i.id_proveedor = p.id_proveedor
            LEFT JOIN Detalle_Ingreso di ON i.id_ingreso = di.id_ingreso
            WHERE i.fecha BETWEEN :fecha_desde AND :fecha_hasta";
    
    $params = [
        ':fecha_desde' => $filtroFechaDesde,
        ':fecha_hasta' => $filtroFechaHasta
    ];
    
    if (!empty($filtroProveedor)) {
        $sql .= " AND p.nombre LIKE :proveedor";
        $params[':proveedor'] = "%{$filtroProveedor}%";
    }
    
    if (!empty($filtroComprobante)) {
        $sql .= " AND i.numero_comprobante LIKE :comprobante";
        $params[':comprobante'] = "%{$filtroComprobante}%";
    }
    
    $sql .= " GROUP BY i.id_ingreso ORDER BY i.fecha DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ingresos = $stmt->fetchAll();
    
    // Calcular totales
    $totalIngresos = count($ingresos);
    $sumaTotal = array_sum(array_column($ingresos, 'total_ingresado'));
    
    // Si se solicita detalle de un ingreso específico
    $detalleIngreso = null;
    if (isset($_GET['id_ingreso'])) {
        $idIngreso = intval($_GET['id_ingreso']);
        
        // Obtener datos del ingreso
        $sqlDetalle = "SELECT 
                          i.*,
                          p.nombre as proveedor_nombre,
                          p.cuit as proveedor_cuit,
                          p.contacto as proveedor_contacto,
                          p.telefono as proveedor_telefono
                       FROM Ingresos i
                       INNER JOIN Proveedores p ON i.id_proveedor = p.id_proveedor
                       WHERE i.id_ingreso = :id_ingreso";
        $stmtDetalle = $db->prepare($sqlDetalle);
        $stmtDetalle->execute([':id_ingreso' => $idIngreso]);
        $detalleIngreso = $stmtDetalle->fetch();
        
        // Obtener productos del ingreso
        if ($detalleIngreso) {
            $sqlProductos = "SELECT 
                               di.*,
                               prod.nombre as producto_nombre,
                               prod.codigo_producto,
                               cat.nombre_categoria
                            FROM Detalle_Ingreso di
                            INNER JOIN Productos prod ON di.id_producto = prod.id_producto
                            LEFT JOIN Categorias cat ON prod.id_categoria = cat.id_categoria
                            WHERE di.id_ingreso = :id_ingreso
                            ORDER BY di.id_detalle_ingreso";
            $stmtProductos = $db->prepare($sqlProductos);
            $stmtProductos->execute([':id_ingreso' => $idIngreso]);
            $detalleIngreso['productos'] = $stmtProductos->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    error_log("Error en historial.php: " . $e->getMessage());
    $ingresos = [];
    $totalIngresos = 0;
    $sumaTotal = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras - Sistema de Gestión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            background: #f093fb;
            color: white;
        }

        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .content {
            padding: 30px;
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
            font-size: 14px;
            color: #4a5568;
        }

        .form-group input {
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
        }

        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
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
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
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
            background: #e6f0ff;
            color: #2d3aa3;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 15px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-section h4 {
            color: #f093fb;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-section p {
            margin: 5px 0;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .content { padding: 15px; }
            .filters-grid { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Historial de Compras</h1>
            <div>
                <a href="index.php" class="btn btn-secondary">← Nueva Compra</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <!-- Filtros -->
            <form method="GET" class="filters">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filtros de Búsqueda</h3>
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo $filtroFechaDesde; ?>">
                    </div>
                    <div class="form-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo $filtroFechaHasta; ?>">
                    </div>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <input type="text" name="proveedor" value="<?php echo htmlspecialchars($filtroProveedor); ?>" placeholder="Nombre del proveedor...">
                    </div>
                    <div class="form-group">
                        <label>N° Comprobante</label>
                        <input type="text" name="comprobante" value="<?php echo htmlspecialchars($filtroComprobante); ?>" placeholder="Número...">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="historial.php" class="btn btn-secondary">Limpiar</a>
            </form>

            <!-- Estadísticas -->
            <div class="stats">
                <div class="stat-card">
                    <h3>Total de Ingresos</h3>
                    <div class="value"><?php echo $totalIngresos; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Monto Total</h3>
                    <div class="value"><?php echo formatearMoneda($sumaTotal); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Promedio</h3>
                    <div class="value"><?php echo formatearMoneda($totalIngresos > 0 ? $sumaTotal / $totalIngresos : 0); ?></div>
                </div>
            </div>

            <!-- Tabla de ingresos -->
            <div class="table-container">
                <?php if (empty($ingresos)): ?>
                    <div class="empty-state">
                        <h3>No se encontraron compras</h3>
                        <p>Ajuste los filtros o realice una nueva compra</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Proveedor</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingresos as $ingreso): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($ingreso['fecha'])); ?></td>
                                    <td>
                                        <span class="badge">
                                            <?php echo $ingreso['numero_comprobante'] ?: 'S/N'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ingreso['proveedor']); ?></strong><br>
                                        <small><?php echo $ingreso['proveedor_cuit'] ? 'CUIT: ' . $ingreso['proveedor_cuit'] : ''; ?></small>
                                    </td>
                                    <td><?php echo $ingreso['cantidad_items']; ?></td>
                                    <td><strong><?php echo formatearMoneda($ingreso['total_ingresado']); ?></strong></td>
                                    <td class="actions">
                                        <button class="btn btn-primary btn-small" onclick="verDetalle(<?php echo $ingreso['id_ingreso']; ?>)">
                                            👁 Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de detalle -->
    <div class="modal-overlay" id="modalDetalle">
        <div class="modal">
            <div class="modal-header">
                <h2>📦 Detalle del Ingreso</h2>
                <button class="modal-close" onclick="cerrarModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
    </div>

    <?php if ($detalleIngreso): ?>
    <script>
        // Auto-abrir modal si hay detalle
        window.onload = function() {
            verDetalle(<?php echo $detalleIngreso['id_ingreso']; ?>);
        };
    </script>
    <?php endif; ?>

    <script>
        async function verDetalle(idIngreso) {
            const modal = document.getElementById('modalDetalle');
            const body = document.getElementById('modalBody');
            
            body.innerHTML = '<div style="text-align: center; padding: 40px;">Cargando...</div>';
            modal.classList.add('active');
            
            try {
                const response = await fetch(`detalle_ingreso.php?id_ingreso=${idIngreso}`);
                const data = await response.json();
                
                if (data.success) {
                    body.innerHTML = data.html;
                } else {
                    body.innerHTML = '<div style="text-align: center; padding: 40px; color: #f56565;">Error al cargar el detalle</div>';
                }
            } catch (error) {
                console.error('Error:', error);
                body.innerHTML = '<div style="text-align: center; padding: 40px; color: #f56565;">Error de conexión</div>';
            }
        }
        
        function cerrarModal() {
            document.getElementById('modalDetalle').classList.remove('active');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalDetalle').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
    </script>
</body>
</html>