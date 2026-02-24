<?php
// =============================================
// modules/productos/listas_precios.php
// Gestión de Listas de Precios
// =============================================
require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('productos', 'gestionar_listas');

$titulo_pagina = 'Listas de Precios';
$db = getDB();

// Obtener tipos de cliente
$tipos_cliente = $db->query("SELECT * FROM tipos_cliente ORDER BY nombre_tipo")->fetchAll();

// Obtener listas de precios
$sql = "SELECT lp.*, tc.nombre_tipo,
        (SELECT COUNT(*) FROM productos_precios WHERE id_lista_precio = lp.id_lista_precio) as total_productos
        FROM listas_precios lp
        LEFT JOIN tipos_cliente tc ON lp.id_tipo_cliente = tc.id_tipo_cliente
        ORDER BY lp.nombre_lista";
$listas = $db->query($sql)->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i data-feather="dollar-sign"></i> Gestión de Listas de Precios</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Inicio</a></li>
            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
            <li class="breadcrumb-item active">Listas de Precios</li>
        </ol>
    </nav>
</div>

<!-- Información -->
<div class="alert alert-info">
    <h5 class="alert-heading"><i data-feather="info"></i> ¿Cómo funcionan las Listas de Precios?</h5>
    <p class="mb-0">Las listas de precios permiten establecer diferentes precios para un mismo producto según el tipo de cliente. Al crear o editar un producto, se generan automáticamente los precios para todas las listas activas aplicando el porcentaje de incremento configurado.</p>
</div>

<!-- Botones -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <?php if (tienePermiso('productos', 'crear')): ?>
            <a href="listas_precios_nueva.php" class="btn btn-primary">
                <i data-feather="plus-circle"></i> Nueva Lista de Precios
            </a>
            <?php endif; ?>
            
            <div>
                <button type="button" class="btn btn-success" id="btnRegenerar">
                    <i data-feather="refresh-cw"></i> Regenerar Precios
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i data-feather="arrow-left"></i> Volver a Productos
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Listas de Precios -->
<div class="row">
    <?php if (empty($listas)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="inbox" width="64" height="64" class="text-muted mb-3"></i>
                <h5 class="text-muted">No hay listas de precios configuradas</h5>
                <p class="text-muted">Cree su primera lista para comenzar</p>
            </div>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($listas as $lista): ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100 <?php echo $lista['estado'] == 'activa' ? 'border-primary' : ''; ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($lista['nombre_lista']); ?></strong>
                        <?php if ($lista['estado'] == 'activa'): ?>
                        <span class="badge bg-success ms-2">Activa</span>
                        <?php else: ?>
                        <span class="badge bg-secondary ms-2">Inactiva</span>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?php if (tienePermiso('productos', 'editar')): ?>
                            <a href="lista_precios_editar.php?id=<?= $lista['id_lista_precio']; ?>" class="btn btn-warning">
                                <i class="bi bi-pencil"></i>
                            </a>
                        <?php endif; ?>

                        <a href="lista_precios_detalle.php?id=<?php echo $lista['id_lista_precio']; ?>" class="btn btn-info">
                            <i class="bi bi-eye"></i>
                        </a>
                        
                        <?php if (tienePermiso('productos', 'eliminar') && $lista['total_productos'] == 0): ?>
                        <button type="button" class="btn btn-danger btn-eliminar" 
                                data-id="<?php echo $lista['id_lista_precio']; ?>"
                                data-nombre="<?php echo htmlspecialchars($lista['nombre_lista']); ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($lista['descripcion']): ?>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($lista['descripcion']); ?></p>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Tipo de Cliente</small>
                            <p class="mb-0"><strong><?php echo htmlspecialchars($lista['nombre_tipo'] ?? 'General'); ?></strong></p>
                        </div>
                        <!-- Visualización en las tarjetas -->
                    <div class="col-6">
                        <small class="text-muted">Ajuste de Precio</small>
                        <p class="mb-0">
                            <?php if ($lista['porcentaje_incremento'] > 0): ?>
                                <span class="badge bg-success fs-6">
                                    <i data-feather="trending-up" width="14"></i> +<?php echo number_format($lista['porcentaje_incremento'], 2); ?>%
                                </span>
                            <?php elseif ($lista['porcentaje_incremento'] < 0): ?>
                                <span class="badge bg-danger fs-6">
                                    <i data-feather="trending-down" width="14"></i> <?php echo number_format($lista['porcentaje_incremento'], 2); ?>%
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Precio Base</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i data-feather="package" width="14"></i> <?php echo $lista['total_productos']; ?> productos con precio
                        </small>
                        <small class="text-muted">
                            Creada: <?php echo formatearFecha($lista['fecha_creacion']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Nueva/Editar Lista -->
<div class="modal fade" id="modalLista" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nueva Lista de Precios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formLista">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id_lista_precio" id="id_lista_precio">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Lista <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_lista" id="nombre_lista" required>
                        <small class="text-muted">Ej: Lista Minorista, Lista Mayorista, etc.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" id="descripcion" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Cliente Asociado</label>
                        <select class="form-select" name="id_tipo_cliente" id="id_tipo_cliente">
                            <option value="">General (Sin asociar)</option>
                            <?php foreach ($tipos_cliente as $tipo): ?>
                            <option value="<?php echo $tipo['id_tipo_cliente']; ?>">
                                <?php echo htmlspecialchars($tipo['nombre_tipo']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Al crear un cliente de este tipo, se le asignará automáticamente esta lista</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Porcentaje de Incremento/Descuento (%)</label>
                        <input type="number" class="form-control" name="porcentaje_incremento" id="porcentaje_incremento" 
                            value="0" step="0.01" min="-100" max="500">
                        <div class="form-text">
                            <i data-feather="info" width="14"></i>
                            <strong>Incremento:</strong> Valores positivos (Ej: 21 para IVA +21%)<br>
                            <i data-feather="info" width="14"></i>
                            <strong>Descuento:</strong> Valores negativos (Ej: -10 para descuento -10%)
                        </div>
                    </div>
                                        
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado" id="estado">
                            <option value="activa">Activa</option>
                            <option value="inactiva">Inactiva</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning" id="alertGenerarPrecios" style="display: none;">
                        <small><i data-feather="alert-triangle" width="16"></i> Al crear esta lista, se generarán automáticamente los precios para todos los productos activos.</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarLista">
                        <i data-feather="save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Regenerar Precios -->
<div class="modal fade" id="modalRegenerar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Regenerar Todos los Precios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong><i data-feather="alert-triangle"></i> Atención</strong>
                    <p class="mb-0 mt-2">Esta acción recalculará TODOS los precios de TODOS los productos para TODAS las listas activas basándose en el precio base actual y el porcentaje de incremento de cada lista.</p>
                </div>
                
                <p><strong>¿Está seguro de continuar?</strong></p>
                <p class="text-muted">Esta operación puede tardar unos segundos si tiene muchos productos.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarRegenerar">
                    <i data-feather="refresh-cw"></i> Sí, Regenerar Todo
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modales
    const modalLista = new bootstrap.Modal(document.getElementById('modalLista'));
    const modalRegenerar = new bootstrap.Modal(document.getElementById('modalRegenerar'));
    
    // Botón Nueva Lista
    const btnNuevaLista = document.getElementById('btnNuevaLista');
    if (btnNuevaLista) {
        btnNuevaLista.addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Nueva Lista de Precios';
            document.getElementById('accion').value = 'crear';
            document.getElementById('formLista').reset();
            document.getElementById('id_lista_precio').value = '';
            document.getElementById('estado').value = 'activa';
            document.getElementById('porcentaje_incremento').value = '0';
            document.getElementById('alertGenerarPrecios').style.display = 'block';
            modalLista.show();
        });
    }
    
    // Botones Editar
    document.querySelectorAll('.btn-editar').forEach(btn => {
        btn.addEventListener('click', function() {
            const lista = JSON.parse(this.dataset.lista);
            document.getElementById('modalTitle').textContent = 'Editar Lista de Precios';
            document.getElementById('accion').value = 'editar';
            document.getElementById('id_lista_precio').value = lista.id_lista_precio;
            document.getElementById('nombre_lista').value = lista.nombre_lista || '';
            document.getElementById('descripcion').value = lista.descripcion || '';
            document.getElementById('id_tipo_cliente').value = lista.id_tipo_cliente || '';
            document.getElementById('porcentaje_incremento').value = lista.porcentaje_incremento || 0;
            document.getElementById('estado').value = lista.estado || 'activa';
            document.getElementById('alertGenerarPrecios').style.display = 'none';
            modalLista.show();
        });
    });
    
    // Botones Eliminar
    document.querySelectorAll('.btn-eliminar').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            
            if (confirm('¿Está seguro de eliminar la lista "' + nombre + '"?\n\nSe eliminarán todos los precios asociados a esta lista.')) {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id_lista_precio', id);
                
                fetch('listas_precios_acciones.php', {
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
                    alert('Error al eliminar la lista');
                });
            }
        });
    });
    
    // Formulario Guardar Lista
    document.getElementById('formLista').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnGuardar = document.getElementById('btnGuardarLista');
        const textoOriginal = btnGuardar.innerHTML;
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        
        const formData = new FormData(this);
        
        fetch('listas_precios_acciones.php', {
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
    
    // Botón Regenerar
    const btnRegenerar = document.getElementById('btnRegenerar');
    if (btnRegenerar) {
        btnRegenerar.addEventListener('click', function() {
            modalRegenerar.show();
        });
    }
    
    // Confirmar Regenerar
    document.getElementById('btnConfirmarRegenerar').addEventListener('click', function() {
        modalRegenerar.hide();
        
        // Mostrar loading overlay
        const loading = document.createElement('div');
        loading.id = 'loadingOverlay';
        loading.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center';
        loading.style.backgroundColor = 'rgba(0,0,0,0.7)';
        loading.style.zIndex = '9999';
        loading.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-light mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="text-light h5">Regenerando precios...</p>
                <p class="text-light">Por favor espere...</p>
            </div>
        `;
        document.body.appendChild(loading);
        
        const formData = new FormData();
        formData.append('accion', 'regenerar_todos');
        
        fetch('listas_precios_acciones.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.remove();
            
            if (data.success) {
                alert(data.mensaje);
                location.reload();
            } else {
                alert('Error: ' + data.mensaje);
            }
        })
        .catch(error => {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.remove();
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    });
    
    // Reemplazar iconos de Feather
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>