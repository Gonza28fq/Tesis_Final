<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    if (!isset($_GET['id_ingreso'])) {
        jsonResponse(['success' => false, 'message' => 'ID no especificado'], 400);
    }
    
    $idIngreso = intval($_GET['id_ingreso']);
    
    // Obtener datos del ingreso
    $sqlIngreso = "SELECT 
                      i.*,
                      p.nombre as proveedor_nombre,
                      p.cuit as proveedor_cuit,
                      p.contacto as proveedor_contacto,
                      p.email as proveedor_email,
                      p.telefono as proveedor_telefono
                   FROM Ingresos i
                   INNER JOIN Proveedores p ON i.id_proveedor = p.id_proveedor
                   WHERE i.id_ingreso = :id_ingreso";
    
    $stmtIngreso = $db->prepare($sqlIngreso);
    $stmtIngreso->execute([':id_ingreso' => $idIngreso]);
    $ingreso = $stmtIngreso->fetch();
    
    if (!$ingreso) {
        jsonResponse(['success' => false, 'message' => 'Ingreso no encontrado'], 404);
    }
    
    // Obtener productos del ingreso
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
    $productos = $stmtProductos->fetchAll();
    
    // Generar HTML
    ob_start();
    ?>
    
    <div class="info-grid">
        <div class="info-section">
            <h4>📅 Información del Ingreso</h4>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($ingreso['fecha'])); ?></p>
            <p><strong>N° Comprobante:</strong> <?php echo $ingreso['numero_comprobante'] ?: 'Sin número'; ?></p>
            <p><strong>ID Ingreso:</strong> #<?php echo $ingreso['id_ingreso']; ?></p>
            <?php if ($ingreso['observaciones']): ?>
                <p><strong>Observaciones:</strong><br><?php echo nl2br(htmlspecialchars($ingreso['observaciones'])); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="info-section">
            <h4>🏭 Proveedor</h4>
            <p><strong><?php echo htmlspecialchars($ingreso['proveedor_nombre']); ?></strong></p>
            <?php if ($ingreso['proveedor_cuit']): ?>
                <p><strong>CUIT:</strong> <?php echo $ingreso['proveedor_cuit']; ?></p>
            <?php endif; ?>
            <?php if ($ingreso['proveedor_contacto']): ?>
                <p><strong>Contacto:</strong> <?php echo $ingreso['proveedor_contacto']; ?></p>
            <?php endif; ?>
            <?php if ($ingreso['proveedor_email']): ?>
                <p><strong>Email:</strong> <?php echo $ingreso['proveedor_email']; ?></p>
            <?php endif; ?>
            <?php if ($ingreso['proveedor_telefono']): ?>
                <p><strong>Teléfono:</strong> <?php echo $ingreso['proveedor_telefono']; ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <h4 style="color: #f093fb; margin: 20px 0 10px 0;">📦 Productos Ingresados</h4>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Precio Unit.</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td><?php echo $producto['codigo_producto'] ?: '-'; ?></td>
                        <td><strong><?php echo htmlspecialchars($producto['producto_nombre']); ?></strong></td>
                        <td><?php echo $producto['nombre_categoria']; ?></td>
                        <td style="text-align: right;"><?php echo $producto['cantidad']; ?></td>
                        <td style="text-align: right;"><?php echo formatearMoneda($producto['precio_unitario']); ?></td>
                        <td style="text-align: right;"><strong><?php echo formatearMoneda($producto['subtotal']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f7fafc;">
                    <td colspan="5" style="text-align: right; font-weight: 600; font-size: 18px; padding: 20px;">
                        TOTAL:
                    </td>
                    <td style="text-align: right; font-weight: 700; font-size: 20px; color: #f093fb; padding: 20px;">
                        <?php echo formatearMoneda($ingreso['total_ingresado']); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    jsonResponse([
        'success' => true,
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    error_log("Error en detalle_ingreso.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error al obtener el detalle'
    ], 500);
}
?>