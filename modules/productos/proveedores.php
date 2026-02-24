<?php
// =============================================
// modules/productos/proveedores.php
// Gestión de Proveedores
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'ver');

$titulo_pagina = 'Gestión de Proveedores';
$db = getDB();

// Parámetros de búsqueda
$buscar = isset($_GET['buscar']) ? limpiarInput($_GET['buscar']) : '';
$estado = isset($_GET['estado']) ? limpiarInput($_GET['estado']) : 'activo';

// Construir consulta con filtros
$where = ['1=1'];
$params = [];

if (!empty($buscar)) {
    $where[] = '(nombre_proveedor LIKE ? OR razon_social LIKE ? OR cuit LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if (!empty($estado)) {
    $where[] = 'estado = ?';
    $params[] = $estado;
}

$where_clause = implode(' AND ', $where);

// Obtener proveedores
$sql = "SELECT p.*,
        (SELECT COUNT(*) FROM productos WHERE id_proveedor = p.id_proveedor AND estado = 'activo') as total_productos
        FROM proveedores p
        WHERE $where_clause
        ORDER BY p.nombre_proveedor";
$proveedores = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="truck"></i> Gestión de Proveedores</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item active">Proveedores</li>
        </ol>
    </nav>
</div>

<!-- Botones de acción -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <?php if (tienePermiso('productos', 'crear')): ?>
            <button type="button" class="btn btn-primary" id="btnNuevoProveedor">
                <i data-feather="plus-circle"></i> Nuevo Proveedor
            </button>
            <?php endif; ?>
            
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i data-feather="arrow-left"></i> Volver a Productos
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <i data-feather="search"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" name="buscar" placeholder="Nombre, razón social o CUIT..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="activo" <?php echo $estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo $estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i data-feather="search"></i> Buscar
                    </button>
                    <a href="proveedores.php" class="btn btn-secondary">
                        <i data-feather="x"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Proveedores -->
<div class="card">
    <div class="card-header">
        <i data-feather="list"></i> Listado de Proveedores (<?php echo count($proveedores); ?>)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>CUIT</th>
                        <th>Contacto</th>
                        <th>Productos</th>
                        <th>Estado</th>
                        <th width="120">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proveedores)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i data-feather="inbox" width="48" height="48" class="text-muted"></i>
                            <p class="text-muted mt-2">No se encontraron proveedores</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($proveedores as $prov): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($prov['nombre_proveedor']); ?></strong>
                                <?php if (!empty($prov['razon_social'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($prov['razon_social']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($prov['cuit'] ?? '-'); ?></td>
                            <td>
                                <?php if ($prov['telefono']): ?>
                                <i data-feather="phone" width="14"></i> <?php echo htmlspecialchars($prov['telefono']); ?><br>
                                <?php endif; ?>
                                <?php if ($prov['email']): ?>
                                <i data-feather="mail" width="14"></i> <?php echo htmlspecialchars($prov['email']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $prov['total_productos']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $prov['estado'] == 'activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($prov['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-info btn-ver" 
                                            data-proveedor='<?php echo htmlspecialchars(json_encode($prov)); ?>'>
                                        <i data-feather="eye"></i>
                                    </button>
                                    
                                    <?php if (tienePermiso('productos', 'editar')): ?>
                                    <button type="button" class="btn btn-warning btn-editar" 
                                            data-proveedor='<?php echo htmlspecialchars(json_encode($prov)); ?>'>
                                        <i data-feather="edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (tienePermiso('productos', 'eliminar') && $prov['total_productos'] == 0): ?>
                                    <button type="button" class="btn btn-danger btn-eliminar" 
                                            data-id="<?php echo $prov['id_proveedor']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($prov['nombre_proveedor']); ?>">
                                        <i data-feather="trash-2"></i>
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
    </div>
</div>

<!-- Modal Nuevo/Editar Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formProveedor">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id_proveedor" id="id_proveedor">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre del Proveedor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_proveedor" id="nombre_proveedor" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Razón Social<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="razon_social" id="razon_social" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">CUIT<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cuit" id="cuit" placeholder="XX-XXXXXXXX-X" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Teléfono<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="telefono" id="telefono" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email<span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                    </div>
                    
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="direccion" id="direccion" 
                            placeholder="Calle, Número, Piso, Depto" required>
                    </div>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Provincia <span class="text-danger">*</span></label>
                            <select class="form-select select2-provincia-modal" name="provincia_select" id="select_provincia_modal" required>
                                <option value="">Cargando provincias...</option>
                            </select>
                            <input type="hidden" name="provincia" id="provincia_texto_modal">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <select class="form-select select2-ciudad-modal" name="ciudad_select" id="select_ciudad_modal" required disabled>
                                <option value="">Primero seleccione provincia</option>
                            </select>
                            <input type="hidden" name="ciudad" id="ciudad_texto_modal">
                            <small class="text-muted d-none" id="loading_ciudades_modal">
                                <span class="spinner-border spinner-border-sm"></span> Cargando...
                            </small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Código Postal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="codigo_postal" id="codigo_postal" 
                                placeholder="Ej: 4000" required>
                            <small class="text-muted">Se completa automático</small>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Datos de Contacto</h6>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nombre del Contacto<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contacto_nombre" id="contacto_nombre" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Teléfono del Contacto<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contacto_telefono" id="contacto_telefono" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email del Contacto<span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="contacto_email" id="contacto_email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Condición de Pago <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="condicion_pago" id="condicion_pago" placeholder="Ej: 30 días, Contado, etc." required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado<span class="text-danger">*</span></label>
                            <select class="form-select" name="estado" id="estado" required>
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" id="observaciones" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarProveedor">
                        <i data-feather="save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Proveedor -->
<div class="modal fade" id="modalVer" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoVer">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalProveedor = new bootstrap.Modal(document.getElementById('modalProveedor'));
    const modalVer = new bootstrap.Modal(document.getElementById('modalVer'));
    
    // ========== VARIABLES GLOBALES PARA UBICACIONES ==========
    let provinciaActualModal = '';
    let ciudadActualModal = '';
    
    // ========== FUNCIONES DE UBICACIÓN CON SELECT2 ==========
    
    // Inicializar Select2 para modal
    function inicializarSelect2Modal() {
        $('#select_provincia_modal').select2({
            theme: 'bootstrap-5',
            placeholder: 'Buscar provincia...',
            allowClear: false,
            width: '100%',
            dropdownParent: $('#modalProveedor')
        });
        
        $('#select_ciudad_modal').select2({
            theme: 'bootstrap-5',
            placeholder: 'Buscar ciudad...',
            allowClear: false,
            width: '100%',
            dropdownParent: $('#modalProveedor')
        });
    }
    
    // Cargar provincias
    function cargarProvinciasModal() {
        $.ajax({
            url: '../clientes/ajax_ubicaciones.php?accion=provincias',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    let options = '<option value="">Seleccione provincia...</option>';
                    
                    $.each(response.data, function(index, provincia) {
                        const selected = provincia.nombre === provinciaActualModal ? 'selected' : '';
                        options += '<option value="' + provincia.id_provincia + '" ' + selected + '>' + provincia.nombre + '</option>';
                    });
                    
                    $('#select_provincia_modal').html(options);
                    
                    if (provinciaActualModal && $('#select_provincia_modal').val()) {
                        cargarCiudadesModal($('#select_provincia_modal').val());
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar provincias:', error);
            }
        });
    }
    
    // Cargar ciudades
    function cargarCiudadesModal(idProvincia) {
        $('#loading_ciudades_modal').removeClass('d-none');
        $('#select_ciudad_modal').prop('disabled', true);
        
        $.ajax({
            url: '../clientes/ajax_ubicaciones.php?accion=ciudades&id_provincia=' + idProvincia,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#loading_ciudades_modal').addClass('d-none');
                
                if (response.success && response.data) {
                    let options = '<option value="">Seleccione ciudad...</option>';
                    
                    if (response.data.length === 0) {
                        options = '<option value="">No hay ciudades disponibles</option>';
                        $('#select_ciudad_modal').html(options).prop('disabled', true);
                        return;
                    }
                    
                    $.each(response.data, function(index, ciudad) {
                        const selected = ciudad.nombre === ciudadActualModal ? 'selected' : '';
                        options += '<option value="' + ciudad.id_ciudad + '" data-codigo-postal="' + (ciudad.codigo_postal || '') + '" ' + selected + '>' + ciudad.nombre + '</option>';
                    });
                    
                    $('#select_ciudad_modal').html(options).prop('disabled', false);
                    
                    if (ciudadActualModal && $('#select_ciudad_modal').val()) {
                        const cp = $('#select_ciudad_modal').find('option:selected').data('codigo-postal') || '';
                        $('#codigo_postal').val(cp);
                        $('#ciudad_texto_modal').val(ciudadActualModal);
                    }
                }
            },
            error: function(xhr, status, error) {
                $('#loading_ciudades_modal').addClass('d-none');
                $('#select_ciudad_modal').prop('disabled', false);
                console.error('Error al cargar ciudades:', error);
            }
        });
    }
    
    // Evento cambio de provincia
    $(document).on('change', '#select_provincia_modal', function() {
        const idProvincia = $(this).val();
        const nombreProvincia = $(this).find('option:selected').text();
        
        $('#provincia_texto_modal').val(nombreProvincia);
        $('#select_ciudad_modal').html('<option value="">Seleccione ciudad...</option>').prop('disabled', true);
        $('#codigo_postal').val('');
        $('#ciudad_texto_modal').val('');
        
        if (idProvincia) {
            cargarCiudadesModal(idProvincia);
        }
    });
    
    // Evento cambio de ciudad
    $(document).on('change', '#select_ciudad_modal', function() {
        const nombreCiudad = $(this).find('option:selected').text();
        const codigoPostal = $(this).find('option:selected').data('codigo-postal') || '';
        
        $('#ciudad_texto_modal').val(nombreCiudad);
        $('#codigo_postal').val(codigoPostal);
    });
    
    // ========== BOTÓN NUEVO PROVEEDOR ==========
    const btnNuevo = document.getElementById('btnNuevoProveedor');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Nuevo Proveedor';
            document.getElementById('accion').value = 'crear';
            document.getElementById('formProveedor').reset();
            document.getElementById('id_proveedor').value = '';
            document.getElementById('estado').value = 'activo';
            
            // Resetear valores de ubicación
            provinciaActualModal = '';
            ciudadActualModal = '';
            
            // Inicializar Select2 y cargar provincias después de que se muestre el modal
            setTimeout(function() {
                inicializarSelect2Modal();
                cargarProvinciasModal();
            }, 300);
            
            modalProveedor.show();
        });
    }
    
    // ========== BOTONES EDITAR ==========
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function() {
            const proveedor = JSON.parse(this.dataset.proveedor);
            document.getElementById('modalTitle').textContent = 'Editar Proveedor';
            document.getElementById('accion').value = 'editar';
            document.getElementById('id_proveedor').value = proveedor.id_proveedor;
            
            // Guardar valores actuales de ubicación
            provinciaActualModal = proveedor.provincia || '';
            ciudadActualModal = proveedor.ciudad || '';
            
            // Llenar campos
            document.getElementById('nombre_proveedor').value = proveedor.nombre_proveedor || '';
            document.getElementById('razon_social').value = proveedor.razon_social || '';
            document.getElementById('cuit').value = proveedor.cuit || '';
            document.getElementById('telefono').value = proveedor.telefono || '';
            document.getElementById('email').value = proveedor.email || '';
            document.getElementById('direccion').value = proveedor.direccion || '';
            document.getElementById('codigo_postal').value = proveedor.codigo_postal || '';
            document.getElementById('contacto_nombre').value = proveedor.contacto_nombre || '';
            document.getElementById('contacto_telefono').value = proveedor.contacto_telefono || '';
            document.getElementById('contacto_email').value = proveedor.contacto_email || '';
            document.getElementById('condicion_pago').value = proveedor.condicion_pago || '';
            document.getElementById('estado').value = proveedor.estado || 'activo';
            document.getElementById('observaciones').value = proveedor.observaciones || '';
            
            // Inicializar Select2 y cargar provincias después de que se muestre el modal
            setTimeout(function() {
                inicializarSelect2Modal();
                cargarProvinciasModal();
            }, 300);
            
            modalProveedor.show();
        });
    });
    
    // ========== BOTONES VER ==========
    document.querySelectorAll('.btn-ver').forEach(btn => {
        btn.addEventListener('click', function() {
            const proveedor = JSON.parse(this.dataset.proveedor);
            
            const html = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Nombre</label>
                        <p class="mb-0"><strong>${proveedor.nombre_proveedor}</strong></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Razón Social</label>
                        <p class="mb-0">${proveedor.razon_social || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">CUIT</label>
                        <p class="mb-0">${proveedor.cuit || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Teléfono</label>
                        <p class="mb-0">${proveedor.telefono || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Email</label>
                        <p class="mb-0">${proveedor.email || '-'}</p>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="text-muted">Dirección</label>
                        <p class="mb-0">${proveedor.direccion || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Ciudad</label>
                        <p class="mb-0">${proveedor.ciudad || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Provincia</label>
                        <p class="mb-0">${proveedor.provincia || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Código Postal</label>
                        <p class="mb-0">${proveedor.codigo_postal || '-'}</p>
                    </div>
                    <div class="col-12"><hr></div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Contacto</label>
                        <p class="mb-0">${proveedor.contacto_nombre || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Tel. Contacto</label>
                        <p class="mb-0">${proveedor.contacto_telefono || '-'}</p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted">Email Contacto</label>
                        <p class="mb-0">${proveedor.contacto_email || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Condición de Pago</label>
                        <p class="mb-0">${proveedor.condicion_pago || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted">Estado</label>
                        <p class="mb-0"><span class="badge bg-${proveedor.estado == 'activo' ? 'success' : 'secondary'}">${proveedor.estado}</span></p>
                    </div>
                    ${proveedor.observaciones ? `
                    <div class="col-12 mb-3">
                        <label class="text-muted">Observaciones</label>
                        <p class="mb-0">${proveedor.observaciones}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('contenidoVer').innerHTML = html;
            modalVer.show();
        });
    });
    
    // ========== BOTONES ELIMINAR ==========
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            if (confirm(`¿Está seguro de eliminar el proveedor "${nombre}"?\n\nEsta acción cambiará su estado a inactivo.`)) {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id_proveedor', id);
                
                fetch('proveedores_acciones.php', {
                    method: 'POST',
                    body: formData
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
        });
    });
    
    // ========== FORMULARIO GUARDAR ==========
    document.getElementById('formProveedor').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnGuardar = document.getElementById('btnGuardarProveedor');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        
        const formData = new FormData(this);
        
        fetch('proveedores_acciones.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.mensaje);
                location.reload();
            } else {
                alert('Error: ' + data.mensaje);
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoOriginal;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
        });
    });
    
    // ========== VALIDACIÓN DE CUIT ==========
    const inputCuit = document.getElementById('cuit');
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
    
    // ========== FEATHER ICONS ==========
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>