<?php
// =============================================
// modules/clientes/nuevo.php y editar.php
// Formulario para Crear/Editar Cliente
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

$es_edicion = basename($_SERVER['PHP_SELF']) == 'editar.php';

if ($es_edicion) {
    requierePermiso('clientes', 'editar');
    $titulo_pagina = 'Editar Cliente';
    $id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id_cliente == 0) {
        setAlerta('error', 'Cliente no válido');
        redirigir('index.php');
    }
} else {
    requierePermiso('clientes', 'crear');
    $titulo_pagina = 'Nuevo Cliente';
    $id_cliente = 0;
}

$db = getDB();
$pdo = $db->getConexion();
$errores = [];
$cliente = [
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'documento' => '',
    'tipo_cliente' => '',
    'id_tipo_cliente' => 0,
    'id_lista_precio' => null,
    'direccion' => '',
    'ciudad' => '',
    'provincia' => '',
    'codigo_postal' => '',
    'cuit_cuil' => '',
    'razon_social' => '',
    'condicion_iva' => '',
    'limite_credito' => 0,
    'estado' => 'activo',
    'observaciones' => ''
];

// Si es edición, cargar datos del cliente
if ($es_edicion) {
    $sql = "SELECT * FROM clientes WHERE id_cliente = ?";
    $stmt = $db->query($sql, [$id_cliente]);
    $cliente_db = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_db) {
        setAlerta('error', 'Cliente no encontrado');
        redirigir('index.php');
    }
    
    $cliente = $cliente_db;
}

// Obtener tipos de cliente
$tipos_cliente = $db->query("SELECT * FROM tipos_cliente ORDER BY nombre_tipo")->fetchAll(PDO::FETCH_ASSOC);

// Obtener listas de precios activas
$listas_precios = $db->query("SELECT * FROM listas_precios WHERE estado = 'activa' ORDER BY nombre_lista")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente['nombre'] = limpiarInput($_POST['nombre'] ?? '');
    $cliente['email'] = limpiarInput($_POST['email'] ?? '');
    $cliente['telefono'] = limpiarInput($_POST['telefono'] ?? '');
    $cliente['documento'] = limpiarInput($_POST['documento'] ?? '');
    $cliente['tipo_cliente'] = limpiarInput($_POST['tipo_cliente'] ?? '');
    $cliente['id_tipo_cliente'] = (int)($_POST['id_tipo_cliente'] ?? 0);
    $cliente['id_lista_precio'] = !empty($_POST['id_lista_precio']) ? (int)$_POST['id_lista_precio'] : null;
    $cliente['direccion'] = limpiarInput($_POST['direccion'] ?? '');
    // Cambiar estas líneas:
    $cliente['ciudad'] = limpiarInput($_POST['ciudad'] ?? '');
    $cliente['provincia'] = limpiarInput($_POST['provincia'] ?? '');
    $cliente['codigo_postal'] = limpiarInput($_POST['codigo_postal'] ?? '');
    $cliente['cuit_cuil'] = limpiarInput($_POST['cuit_cuil'] ?? '');
    $cliente['razon_social'] = limpiarInput($_POST['razon_social'] ?? '');
    $cliente['condicion_iva'] = limpiarInput($_POST['condicion_iva'] ?? '');
    $cliente['limite_credito'] = (float)($_POST['limite_credito'] ?? 0);
    $cliente['estado'] = $_POST['estado'] ?? 'activo';
    $cliente['observaciones'] = limpiarInput($_POST['observaciones'] ?? '');
    
    // Validaciones
    if (empty($cliente['nombre'])) {
        $errores[] = 'El nombre es obligatorio';
    }
    
    if (empty($cliente['documento'])) {
        $errores[] = 'El documento es obligatorio';
    }
    
    if ($cliente['id_tipo_cliente'] == 0) {
        $errores[] = 'Debe seleccionar un tipo de cliente';
    }
    
    if (!empty($cliente['email']) && !filter_var($cliente['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido';
    }
    
    // Verificar documento único
    if ($es_edicion) {
        $sql_check = "SELECT COUNT(*) as total FROM clientes WHERE documento = ? AND id_cliente != ?";
        $stmt = $db->query($sql_check, [$cliente['documento'], $id_cliente]);
    } else {
        $sql_check = "SELECT COUNT(*) as total FROM clientes WHERE documento = ?";
        $stmt = $db->query($sql_check, [$cliente['documento']]);
    }
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado['total'] > 0) {
        $errores[] = 'Ya existe un cliente con ese documento';
    }
    
    // Si no hay errores, guardar
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // Si no se especificó lista de precios, asignar automáticamente según el tipo de cliente
            if ($cliente['id_lista_precio'] === null && $cliente['id_tipo_cliente'] > 0) {
                $sql_lista = "SELECT id_lista_precio FROM listas_precios 
                             WHERE id_tipo_cliente = ? AND estado = 'activa' LIMIT 1";
                $stmt = $db->query($sql_lista, [$cliente['id_tipo_cliente']]);
                $lista_auto = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($lista_auto) {
                    $cliente['id_lista_precio'] = $lista_auto['id_lista_precio'];
                }
            }
            
            if ($es_edicion) {
                // Actualizar cliente
                $sql = "UPDATE clientes SET 
                        nombre = ?, email = ?, telefono = ?, documento = ?, tipo_cliente = ?,
                        id_tipo_cliente = ?, id_lista_precio = ?, direccion = ?,
                        ciudad = ?, provincia = ?, codigo_postal = ?, cuit_cuil = ?,
                        razon_social = ?, condicion_iva = ?, limite_credito = ?,
                        estado = ?, observaciones = ?
                        WHERE id_cliente = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $cliente['nombre'], $cliente['email'], $cliente['telefono'], $cliente['documento'], $cliente['tipo_cliente'],
                    $cliente['id_tipo_cliente'], $cliente['id_lista_precio'], $cliente['direccion'],
                    $cliente['ciudad'], $cliente['provincia'], $cliente['codigo_postal'], $cliente['cuit_cuil'],
                    $cliente['razon_social'], $cliente['condicion_iva'], $cliente['limite_credito'],
                    $cliente['estado'], $cliente['observaciones'],
                    $id_cliente
                ]);
                
                $cliente_id = $id_cliente;
                $mensaje = 'Cliente actualizado correctamente';
                $accion_auditoria = 'actualizar';
                
            } else {
                // Insertar nuevo cliente
                $sql = "INSERT INTO clientes 
                        (nombre, email, telefono, documento, tipo_cliente, id_tipo_cliente, id_lista_precio,
                         direccion, ciudad, provincia, codigo_postal, cuit_cuil, razon_social,
                         condicion_iva, limite_credito, estado, observaciones)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $cliente['nombre'], $cliente['email'], $cliente['telefono'], $cliente['documento'], $cliente['tipo_cliente'],
                    $cliente['id_tipo_cliente'], $cliente['id_lista_precio'], $cliente['direccion'],
                    $cliente['ciudad'], $cliente['provincia'], $cliente['codigo_postal'], $cliente['cuit_cuil'],
                    $cliente['razon_social'], $cliente['condicion_iva'], $cliente['limite_credito'],
                    $cliente['estado'], $cliente['observaciones']
                ]);
                
                $cliente_id = $pdo->lastInsertId();
                $mensaje = 'Cliente creado correctamente';
                $accion_auditoria = 'crear';
            }
            
            $pdo->commit();
            
            // Registrar en auditoría
            registrarAuditoria('clientes', $accion_auditoria, 
                "Cliente: {$cliente['nombre']} (ID: $cliente_id)");
            
            setAlerta('success', $mensaje);
            redirigir('index.php');
            
        } catch (Exception $e) {
            $pdo->rollback();
            $errores[] = 'Error al guardar el cliente: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1>
        <i data-feather="user"></i> 
        <?php echo $es_edicion ? 'Editar Cliente' : 'Nuevo Cliente'; ?>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Clientes</a></li>
            <li class="breadcrumb-item active"><?php echo $es_edicion ? 'Editar' : 'Nuevo'; ?></li>
        </ol>
    </nav>
</div>

<?php if (!empty($errores)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i data-feather="alert-circle"></i> Errores encontrados:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errores as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="" id="form-cliente">
    <div class="row">
        <!-- Columna Principal -->
        <div class="col-md-8">
            <!-- Información Básica -->
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="user"></i> Información Básica
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nombre Completo / Razón Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">DNI/Documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="documento" value="<?php echo htmlspecialchars($cliente['documento']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo de Cliente <span class="text-danger">*</span></label>
                            <select name="tipo_cliente" class="form-control" required>
                                <option value="">Seleccione...</option>
                                <option value="fisico" <?= ($cliente['tipo_cliente'] == 'fisico' ? 'selected' : '') ?>>Físico</option>
                                <option value="juridico" <?= ($cliente['tipo_cliente'] == 'juridico' ? 'selected' : '') ?>>Jurídico</option>
                            </select>
                        </div>

                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Información Fiscal -->
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="file-text"></i> Información Fiscal
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">clasificación <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_tipo_cliente" id="id_tipo_cliente" required>
                                <option value="0">Seleccione...</option>
                                <?php foreach ($tipos_cliente as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo_cliente']; ?>" 
                                        <?php echo $cliente['id_tipo_cliente'] == $tipo['id_tipo_cliente'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre_tipo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Define el comprobante fiscal y lista de precios</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lista de Precios <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_lista_precio" id="id_lista_precio" required>
                                <option value="">Asignar automáticamente</option>
                                <?php foreach ($listas_precios as $lista): ?>
                                <option value="<?php echo $lista['id_lista_precio']; ?>" 
                                        <?php echo $cliente['id_lista_precio'] == $lista['id_lista_precio'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lista['nombre_lista']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Si no se especifica, se asigna según el tipo</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CUIT/CUIL <span class="text-danger">*</span> </label>
                            <input type="text" class="form-control" name="cuit_cuil" placeholder="XX-XXXXXXXX-X" value="<?php echo htmlspecialchars($cliente['cuit_cuil']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Condición IVA </label>
                            <select class="form-select" name="condicion_iva">
                                <option value="">Sin especificar</option>
                                <option value="Responsable Inscripto" <?php echo $cliente['condicion_iva'] == 'Responsable Inscripto' ? 'selected' : ''; ?>>Responsable Inscripto</option>
                                <option value="Monotributista" <?php echo $cliente['condicion_iva'] == 'Monotributista' ? 'selected' : ''; ?>>Monotributista</option>
                                <option value="Exento" <?php echo $cliente['condicion_iva'] == 'Exento' ? 'selected' : ''; ?>>Exento</option>
                                <option value="Consumidor Final" <?php echo $cliente['condicion_iva'] == 'Consumidor Final' ? 'selected' : ''; ?>>Consumidor Final</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Razón Social</label>
                        <input type="text" class="form-control" name="razon_social" value="<?php echo htmlspecialchars($cliente['razon_social']); ?>">
                        <small class="text-muted">Para facturación (si es diferente al nombre)</small>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="map-pin"></i> Dirección
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Dirección Completa <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="direccion" id="direccion" 
                            value="<?php echo htmlspecialchars($cliente['direccion']); ?>" 
                            placeholder="Calle, Número, Piso, Depto" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Provincia <span class="text-danger">*</span></label>
                            <select class="form-select select2-provincia" name="provincia_select" id="select_provincia" required>
                                <option value="">Cargando provincias...</option>
                            </select>
                            <input type="hidden" name="provincia" id="provincia_texto" value="<?php echo htmlspecialchars($cliente['provincia']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <select class="form-select select2-ciudad" name="ciudad_select" id="select_ciudad" required disabled>
                                <option value="">Primero seleccione provincia</option>
                            </select>
                            <input type="hidden" name="ciudad" id="ciudad_texto" value="<?php echo htmlspecialchars($cliente['ciudad']); ?>">
                            <small class="text-muted d-none" id="loading_ciudades">
                                <span class="spinner-border spinner-border-sm"></span> Cargando ciudades...
                            </small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Código Postal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo_postal" id="codigo_postal" 
                                value="<?php echo htmlspecialchars($cliente['codigo_postal']); ?>" 
                                placeholder="Ej: 4000" required>
                            <small class="text-muted">Se completa automáticamente</small>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Panel Lateral -->
        <div class="col-md-4">
            <!-- Configuración -->
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="settings"></i> Configuración
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Límite de Crédito</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="limite_credito" value="<?php echo $cliente['limite_credito']; ?>" step="0.01" min="0">
                        </div>
                        <small class="text-muted">Crédito máximo disponible</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="activo" <?php echo $cliente['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $cliente['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    
                    <?php if ($es_edicion && isset($cliente['fecha_creacion'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Fecha de Registro</label>
                        <input type="text" class="form-control-plaintext" value="<?php echo formatearFechaHora($cliente['fecha_creacion']); ?>" readonly>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="card mb-4">
                <div class="card-header">
                    <i data-feather="message-square"></i> Observaciones
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="observaciones" rows="4" placeholder="Notas adicionales sobre el cliente..."><?php echo htmlspecialchars($cliente['observaciones']); ?></textarea>
                </div>
            </div>
            
            <!-- Acciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="save"></i> <?php echo $es_edicion ? 'Actualizar' : 'Guardar'; ?> Cliente
                        </button>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i data-feather="x"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
.select2-container--bootstrap-5 .select2-selection {
    min-height: 38px;
}
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    line-height: 38px;
}
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 36px;
}
.select2-container {
    width: 100% !important;
}
</style>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
jQuery(document).ready(function($) {
    console.log('Script iniciado con jQuery');
    
    // Validación de CUIT
    const inputCuit = document.querySelector('[name="cuit_cuil"]');
    if (inputCuit) {
        inputCuit.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '-' + value.substring(2);
            }
            if (value.length > 11) {
                value = value.substring(0, 11) + '-' + value.substring(11);
            }
            if (value.length > 14) {
                value = value.substring(0, 14);
            }
            e.target.value = value;
        });
    }
    
    // Validación de DNI
    const inputDocumento = document.querySelector('[name="documento"]');
    if (inputDocumento) {
        inputDocumento.addEventListener('blur', function() {
            let dni = this.value.replace(/[^0-9]/g, '');
            if (dni.length > 0 && (dni.length < 7 || dni.length > 8)) {
                alert('El DNI debe tener entre 7 y 8 dígitos');
                this.value = '';
            }
        });
    }
    
    // Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // ========== SISTEMA DE UBICACIONES ==========
    
    const provinciaActual = '<?php echo addslashes($cliente["provincia"]); ?>';
    const ciudadActual = '<?php echo addslashes($cliente["ciudad"]); ?>';
    
    console.log('Provincia actual:', provinciaActual);
    console.log('Ciudad actual:', ciudadActual);
    
    // Inicializar Select2 para provincia
    $('#select_provincia').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar provincia...',
        allowClear: false,
        width: '100%'
    });
    
    // Inicializar Select2 para ciudad
    $('#select_ciudad').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar ciudad...',
        allowClear: false,
        width: '100%'
    });
    
    // Cargar provincias
    cargarProvincias();
    
    // Evento cambio de provincia
    $('#select_provincia').on('change', function() {
        const idProvincia = $(this).val();
        const nombreProvincia = $(this).find('option:selected').text();
        
        console.log('Provincia seleccionada:', idProvincia, nombreProvincia);
        
        $('#provincia_texto').val(nombreProvincia);
        $('#select_ciudad').html('<option value="">Seleccione ciudad...</option>').prop('disabled', true);
        $('#codigo_postal').val('');
        $('#ciudad_texto').val('');
        
        if (idProvincia) {
            cargarCiudades(idProvincia);
        }
    });
    
    // Evento cambio de ciudad
    $('#select_ciudad').on('change', function() {
        const nombreCiudad = $(this).find('option:selected').text();
        const codigoPostal = $(this).find('option:selected').data('codigo-postal') || '';
        
        console.log('Ciudad seleccionada:', nombreCiudad, 'CP:', codigoPostal);
        
        $('#ciudad_texto').val(nombreCiudad);
        $('#codigo_postal').val(codigoPostal);
    });
    
    // Función cargar provincias
    function cargarProvincias() {
        console.log('Cargando provincias...');
        
        $.ajax({
            url: 'ajax_ubicaciones.php?accion=provincias',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Provincias recibidas:', response);
                
                if (response.success && response.data) {
                    let options = '<option value="">Seleccione provincia...</option>';
                    
                    $.each(response.data, function(index, provincia) {
                        const selected = provincia.nombre === provinciaActual ? 'selected' : '';
                        options += '<option value="' + provincia.id_provincia + '" ' + selected + '>' + provincia.nombre + '</option>';
                    });
                    
                    $('#select_provincia').html(options).trigger('change.select2');
                    
                    // Si hay provincia actual, cargar ciudades
                    if (provinciaActual && $('#select_provincia').val()) {
                        console.log('Cargando ciudades para:', $('#select_provincia').val());
                        cargarCiudades($('#select_provincia').val());
                    }
                } else {
                    console.error('Error en respuesta:', response);
                    alert('Error al cargar provincias');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                console.error('Response:', xhr.responseText);
                alert('Error al cargar provincias. Revise la consola.');
            }
        });
    }
    
    // Función cargar ciudades
    function cargarCiudades(idProvincia) {
        console.log('Cargando ciudades para provincia:', idProvincia);
        
        $('#loading_ciudades').removeClass('d-none');
        $('#select_ciudad').prop('disabled', true);
        
        $.ajax({
            url: 'ajax_ubicaciones.php?accion=ciudades&id_provincia=' + idProvincia,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Ciudades recibidas:', response);
                
                $('#loading_ciudades').addClass('d-none');
                
                if (response.success && response.data) {
                    let options = '<option value="">Seleccione ciudad...</option>';
                    
                    if (response.data.length === 0) {
                        options = '<option value="">No hay ciudades disponibles</option>';
                        $('#select_ciudad').html(options).prop('disabled', true).trigger('change.select2');
                        return;
                    }
                    
                    $.each(response.data, function(index, ciudad) {
                        const selected = ciudad.nombre === ciudadActual ? 'selected' : '';
                        options += '<option value="' + ciudad.id_ciudad + '" data-codigo-postal="' + (ciudad.codigo_postal || '') + '" ' + selected + '>' + ciudad.nombre + '</option>';
                    });
                    
                    $('#select_ciudad').html(options).prop('disabled', false).trigger('change.select2');
                    
                    // Si había ciudad seleccionada
                    if (ciudadActual && $('#select_ciudad').val()) {
                        const cp = $('#select_ciudad').find('option:selected').data('codigo-postal') || '';
                        $('#codigo_postal').val(cp);
                        $('#ciudad_texto').val(ciudadActual);
                    }
                } else {
                    console.error('Error en respuesta:', response);
                    alert('Error al cargar ciudades');
                }
            },
            error: function(xhr, status, error) {
                $('#loading_ciudades').addClass('d-none');
                $('#select_ciudad').prop('disabled', false);
                console.error('Error AJAX:', error);
                console.error('Response:', xhr.responseText);
                alert('Error al cargar ciudades');
            }
        });
    }
});


</script>

<?php include '../../includes/footer.php'; ?>
