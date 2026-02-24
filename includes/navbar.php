<?php
// =============================================
// includes/navbar.php
// Barra de navegación adicional (opcional)
// =============================================
?>

<!-- Navbar Secundaria (Opcional) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-4">
    <div class="container-fluid">
        <!-- Breadcrumb dinámico -->
        <?php if (isset($breadcrumb_items) && is_array($breadcrumb_items)): ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>index.php">
                        <i class="bi bi-house-door"></i> Inicio
                    </a>
                </li>
                <?php 
                $total = count($breadcrumb_items);
                $contador = 1;
                foreach ($breadcrumb_items as $item => $url): 
                ?>
                    <?php if ($contador == $total): ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($item); ?>
                        </li>
                    <?php else: ?>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL . $url; ?>">
                                <?php echo htmlspecialchars($item); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php 
                $contador++;
                endforeach; 
                ?>
            </ol>
        </nav>
        <?php endif; ?>
        
        <!-- Acciones rápidas del módulo -->
        <?php if (isset($acciones_rapidas) && is_array($acciones_rapidas)): ?>
        <div class="ms-auto">
            <?php foreach ($acciones_rapidas as $accion): ?>
                <a href="<?php echo $accion['url']; ?>" 
                   class="btn btn-<?php echo $accion['tipo'] ?? 'primary'; ?> btn-sm">
                    <i class="bi bi-<?php echo $accion['icono']; ?>"></i>
                    <?php echo $accion['texto']; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</nav>