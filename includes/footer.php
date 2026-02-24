</div>
            <!-- Footer -->
            <footer class="mt-auto py-3 bg-light border-top">
                <div class="container-fluid px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            © <?php echo date('Y'); ?> <?php echo EMPRESA; ?> - <?php echo NOMBRE_SISTEMA; ?>
                        </small>
                        <small class="text-muted">
                            Versión <?php echo VERSION_SISTEMA; ?>
                        </small>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.js"></script>
    
    <!-- Custom Scripts -->
    <script src="<?php echo ASSETS_URL; ?>js/scripts.js"></script>
    
    <script>
        // Toggle Sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        // Configuración global de DataTables en español
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                "sProcessing": "Procesando...",
                "sLengthMenu": "Mostrar _MENU_ registros",
                "sZeroRecords": "No se encontraron resultados",
                "sEmptyTable": "Ningún dato disponible en esta tabla",
                "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sSearch": "Buscar:",
                "sLoadingRecords": "Cargando...",
                "oPaginate": {
                    "sFirst": "Primero",
                    "sLast": "Último",
                    "sNext": "Siguiente",
                    "sPrevious": "Anterior"
                },
                "oAria": {
                    "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                    "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                }
            }
        });
        
        // Configuración global de Select2
        $.fn.select2.defaults.set("theme", "bootstrap-5");
        $.fn.select2.defaults.set("language", "es");
        
        // Función para mostrar alertas con SweetAlert2
        function mostrarAlerta(tipo, titulo, mensaje) {
            Swal.fire({
                icon: tipo,
                title: titulo,
                text: mensaje,
                confirmButtonText: 'Aceptar'
            });
        }
        
        // Función para confirmar eliminación
        function confirmarEliminacion(callback) {
            Swal.fire({
                title: '¿Está seguro?',
                text: "Esta acción no se puede revertir",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    callback();
                }
            });
        }
        
        // Función para formatear moneda
        function formatearMoneda(valor) {
            return new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: 'ARS'
            }).format(valor);
        }
        
        // Función para formatear número
        function formatearNumero(valor, decimales = 0) {
            return new Intl.NumberFormat('es-AR', {
                minimumFractionDigits: decimales,
                maximumFractionDigits: decimales
            }).format(valor);
        }
        
        // Prevenir doble submit en formularios
        $('form').on('submit', function() {
            $(this).find('button[type="submit"]').prop('disabled', true);
        });
        
        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
    
    <!-- Scripts personalizados de la página -->
    <?php if (isset($scripts_adicionales)): ?>
        <?php echo $scripts_adicionales; ?>
    <?php endif; ?>
</body>
</html>
