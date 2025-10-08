<?php
require_once '../../config/conexion.php';
validarSesion();

if (!tienePermiso('usuarios_gestionar')) {
    header('Location: ../../index.php?error=sin_permiso');
    exit;
}

$id_vendedor = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    $db = getDB();
    
    // Verificar que el usuario existe
    $sql = "SELECT usuario FROM Vendedores WHERE id_vendedor = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id_vendedor]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: index.php?error=usuario_no_encontrado');
        exit;
    }
    
    // No permitir eliminar al admin
    if ($usuario['usuario'] === 'admin') {
        header('Location: index.php?error=no_se_puede_eliminar_admin');
        exit;
    }
    
    // Eliminar usuario
    $sqlDelete = "DELETE FROM Vendedores WHERE id_vendedor = :id";
    $stmtDelete = $db->prepare($sqlDelete);
    $stmtDelete->execute([':id' => $id_vendedor]);
    
    header('Location: index.php?success=deleted');
    exit;
    
} catch (PDOException $e) {
    error_log("Error al eliminar usuario: " . $e->getMessage());
    header('Location: index.php?error=db_error');
    exit;
}
?>