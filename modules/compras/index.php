<?php
require_once '../../config/conexion.php';
validarSesion();

// Verificar permiso para crear compras
if (!tienePermiso('compras_crear')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Compras - Sistema de Gestión</title>
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

        .header-info {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
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

        .btn-primary:hover {
            background: #e080ea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }

        .content {
            padding: 30px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .section {
            background: #f7fafc;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f093fb;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .search-box {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 10;
            display: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .search-results.active {
            display: block;
        }

        .search-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s;
        }

        .search-item:hover {
            background: #edf2f7;
        }

        .proveedor-seleccionado {
            background: #fef5e7;
            border: 2px solid #f5cba7;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .carrito {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .carrito-header {
            background: #f093fb;
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }

        .carrito-items {
            max-height: 400px;
            overflow-y: auto;
        }

        .carrito-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            gap: 15px;
        }

        .item-info {
            flex: 1;
        }

        .item-nombre {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .item-detalles {
            font-size: 13px;
            color: #718096;
        }

        .item-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .input-small {
            width: 90px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            text-align: center;
        }

        .precio-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
            font-size: 12px;
        }

        .precio-anterior {
            color: #a0aec0;
            text-decoration: line-through;
        }

        .precio-diferencia {
            font-weight: 600;
        }

        .precio-diferencia.aumento {
            color: #f56565;
        }

        .precio-diferencia.reduccion {
            color: #48bb78;
        }

        .carrito-footer {
            background: #f7fafc;
            padding: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #c6f6d5;
            border: 2px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: #fed7d7;
            border: 2px solid #fc8181;
            color: #742a2a;
        }

        .alert-warning {
            background: #feebc8;
            border: 2px solid #f5cba7;
            color: #744210;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-warning {
            background: #feebc8;
            color: #744210;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .content {
                padding: 15px;
            }

            .section {
                padding: 15px;
            }

            .item-inputs {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        @media (max-width: 768px) {
            .grid-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Módulo de Compras</h1>
            <div class="header-info">
                <div class="user-info">
                    <span>👤 <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?></span>
                </div>
                <a href="historial.php" class="btn btn-secondary">📋 Historial</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <!-- Alertas -->
            <div id="alert" class="alert"></div>

            <div class="grid-2">
                <!-- Sección Proveedor -->
                <div class="section">
                    <h2>🏭 Proveedor</h2>
                    <div class="form-group search-box">
                        <label for="buscar-proveedor">Buscar proveedor (Nombre o CUIT)</label>
                        <input type="text" id="buscar-proveedor" placeholder="Escriba para buscar...">
                        <div id="resultados-proveedores" class="search-results"></div>
                    </div>
                    <div id="proveedor-seleccionado"></div>
                    <button type="button" class="btn btn-primary" onclick="mostrarFormNuevoProveedor()" style="margin-top: 15px;">
                        ➕ Nuevo Proveedor
                    </button>
                </div>

                <!-- Sección Datos del Ingreso -->
                <div class="section">
                    <h2>📝 Datos del Ingreso</h2>
                    <div class="form-group">
                        <label for="numero-comprobante">N° Comprobante/Factura del Proveedor</label>
                        <input type="text" id="numero-comprobante" placeholder="Ej: A-0001-00001234">
                    </div>
                    <div class="form-group">
                        <label for="fecha-ingreso">Fecha del Ingreso</label>
                        <input type="date" id="fecha-ingreso" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="observaciones">Observaciones (Opcional)</label>
                        <textarea id="observaciones" placeholder="Notas sobre el ingreso..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Sección Agregar Productos -->
            <div class="section" style="margin-bottom: 30px;">
                <h2>📦 Agregar Productos</h2>
                <div class="grid-3">
                    <div class="form-group search-box">
                        <label for="buscar-producto">Buscar producto</label>
                        <input type="text" id="buscar-producto" placeholder="Nombre o código...">
                        <div id="resultados-productos" class="search-results"></div>
                    </div>
                    <div class="form-group">
                        <label for="cantidad-producto">Cantidad</label>
                        <input type="number" id="cantidad-producto" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label for="precio-compra">Precio de Compra Unitario</label>
                        <input type="number" id="precio-compra" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label for="ubicacion-stock">Ubicación del Stock</label>
                    <select id="ubicacion-stock">
                        <option value="">Seleccione ubicación...</option>
                    </select>
                </div>
                <button type="button" class="btn btn-success" onclick="agregarProducto()">
                    ➕ Agregar al Ingreso
                </button>
            </div>

            <!-- Carrito de Compra -->
            <div class="carrito">
                <div class="carrito-header">
                    🛒 Detalle del Ingreso
                </div>
                <div class="carrito-items" id="carrito-items">
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <p>No hay productos agregados</p>
                        <small>Busque y agregue productos para comenzar</small>
                    </div>
                </div>
                <div class="carrito-footer">
                    <div class="total-row">
                        <span>TOTAL:</span>
                        <span id="total-compra">$0.00</span>
                    </div>
                    <button type="button" class="btn btn-success" onclick="confirmarCompra()" style="width: 100%; padding: 15px; font-size: 16px;">
                        ✓ Confirmar Ingreso de Mercadería
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/compras.js"></script>
</body>
</html>