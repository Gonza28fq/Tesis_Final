<?php
require_once '../../config/conexion.php';
validarSesion();

// Verificar permiso para crear ventas
if (!tienePermiso('ventas_crear')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Ventas - Sistema de Gestión</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
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

        .search-item:last-child {
            border-bottom: none;
        }

        .cliente-seleccionado,
        .producto-agregado {
            background: #e6fffa;
            border: 2px solid #81e6d9;
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
            background: #667eea;
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
        }

        .carrito-item:last-child {
            border-bottom: none;
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

        .item-cantidad {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cantidad-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: #667eea;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .cantidad-input {
            width: 50px;
            text-align: center;
            padding: 5px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
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

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-warning {
            background: #feebc8;
            color: #744210;
        }

        .badge-danger {
            background: #fed7d7;
            color: #742a2a;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-info {
                width: 100%;
                justify-content: space-between;
            }

            .content {
                padding: 15px;
            }

            .section {
                padding: 15px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Módulo de Ventas</h1>
            <div class="header-info">
                <div class="user-info">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?></span>
                </div>
                <a href="historial.php" class="btn btn-secondary">📋 Historial</a>
                <a href="../../index.php" class="btn btn-secondary">🏠 Inicio</a>
            </div>
        </div>

        <div class="content">
            <!-- Alertas -->
            <div id="alert" class="alert"></div>

            <div class="grid-2">
                <!-- Sección Cliente -->
                <div class="section">
                    <h2>👤 Cliente</h2>
                    <div class="form-group search-box">
                        <label for="buscar-cliente">Buscar cliente (Nombre, Email o DNI)</label>
                        <input type="text" id="buscar-cliente" 
                               placeholder="Escriba para buscar..." 
                               onkeyup="buscarClientes()" 
                               autocomplete="off">
                        <div id="resultados-clientes" class="search-results"></div>
                    </div>
                    <div id="cliente-seleccionado"></div>
                    <button type="button" class="btn btn-primary" onclick="mostrarFormNuevoCliente()" style="margin-top: 15px;">
                        ➕ Nuevo Cliente
                    </button>
                </div>

                <!-- Sección Producto -->
                <div class="section">
                    <h2>📦 Productos</h2>
                    <div class="form-group search-box">
                        <label for="buscar-producto">Buscar producto (Nombre o Código)</label>
                        <input type="text" id="buscar-producto" 
                               placeholder="Escriba para buscar..." 
                               onkeyup="buscarProductos()" 
                               autocomplete="off">
                        <div id="resultados-productos" class="search-results"></div>
                    </div>
                    <div class="form-group">
                        <label for="cantidad-producto">Cantidad</label>
                        <input type="number" id="cantidad-producto" value="1" min="1">
                    </div>
                    <button type="button" class="btn btn-success" onclick="agregarProducto()">
                        ➕ Agregar al carrito
                    </button>
                </div>
            </div>

            <!-- Carrito de compras -->
            <div class="carrito">
                <div class="carrito-header">
                    🛒 Carrito de Venta
                </div>
                <div class="carrito-items" id="carrito-items">
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <p>El carrito está vacío</p>
                        <small>Busque y agregue productos para comenzar</small>
                    </div>
                </div>
                <div class="carrito-footer">
                    <div class="form-group">
                        <label for="tipo-comprobante">Tipo de Comprobante</label>
                        <select id="tipo-comprobante">
                            <option value="B">Factura B</option>
                            <option value="A">Factura A</option>
                            <option value="C">Factura C</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="forma-pago">Forma de Pago</label>
                        <select id="forma-pago">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta_debito">Tarjeta de Débito</option>
                            <option value="tarjeta_credito">Tarjeta de Crédito</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="total-row">
                        <span>TOTAL:</span>
                        <span id="total-venta">$0.00</span>
                    </div>
                    <button type="button" class="btn btn-success" onclick="confirmarVenta()" style="width: 100%; padding: 15px; font-size: 16px;">
                        ✓ Confirmar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ventas.js"></script>
</body>
</html>