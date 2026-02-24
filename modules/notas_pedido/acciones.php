<?php
// =============================================
// modules/notas_pedido/acciones.php
// Acciones de Notas de Pedido (AJAX)
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

header('Content-Type: application/json');

$db = getDB();
$pdo = $db->getConexion();

try {
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        
        // ============================================
        // APROBAR NOTA DE PEDIDO
        // ============================================
        case 'aprobar':
            requierePermiso('notas_pedido', 'aprobar');
            
            $id_nota = (int)$_POST['id'];
            
            if ($id_nota <= 0) {
                throw new Exception('ID de nota inválido');
            }
            
            // Verificar que la nota existe y está pendiente
            $sql = "SELECT * FROM notas_pedido WHERE id_nota_pedido = ? AND estado = 'pendiente'";
            $nota = $db->query($sql, [$id_nota])->fetch();
            
            if (!$nota) {
                throw new Exception('La nota no existe o ya fue procesada');
            }
            
            // Verificar que el usuario no esté aprobando su propia nota
            if ($nota['id_usuario_solicitante'] == $_SESSION['usuario_id']) {
                throw new Exception('No puede aprobar su propia nota de pedido');
            }
            
            $pdo->beginTransaction();
            
            // Actualizar estado
            $sql_update = "UPDATE notas_pedido 
                          SET estado = 'aprobada',
                              id_usuario_aprobador = ?,
                              fecha_aprobacion = NOW()
                          WHERE id_nota_pedido = ?";
            
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([$_SESSION['usuario_id'], $id_nota]);
            
            $pdo->commit();
            
            // Registrar auditoría
            registrarAuditoria(
                'notas_pedido',
                'aprobar',
                "Nota de pedido {$nota['numero_nota']} aprobada"
            );
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Nota de pedido aprobada correctamente. Ya puede convertirse en compra.'
            ]);
            break;
            
        // ============================================
        // RECHAZAR NOTA DE PEDIDO
        // ============================================
        case 'rechazar':
            requierePermiso('notas_pedido', 'rechazar');
            
            $id_nota = (int)$_POST['id'];
            $motivo = limpiarInput($_POST['motivo'] ?? '');
            
            if ($id_nota <= 0) {
                throw new Exception('ID de nota inválido');
            }
            
            if (empty($motivo)) {
                throw new Exception('Debe indicar el motivo del rechazo');
            }
            
            // Verificar que la nota existe y está pendiente
            $sql = "SELECT * FROM notas_pedido WHERE id_nota_pedido = ? AND estado = 'pendiente'";
            $nota = $db->query($sql, [$id_nota])->fetch();
            
            if (!$nota) {
                throw new Exception('La nota no existe o ya fue procesada');
            }
            
            // Verificar que el usuario no esté rechazando su propia nota
            if ($nota['id_usuario_solicitante'] == $_SESSION['usuario_id']) {
                throw new Exception('No puede rechazar su propia nota de pedido');
            }
            
            $pdo->beginTransaction();
            
            // Actualizar estado
            $sql_update = "UPDATE notas_pedido 
                          SET estado = 'rechazada',
                              id_usuario_aprobador = ?,
                              fecha_aprobacion = NOW(),
                              motivo_rechazo = ?
                          WHERE id_nota_pedido = ?";
            
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([$_SESSION['usuario_id'], $motivo, $id_nota]);
            
            $pdo->commit();
            
            // Registrar auditoría
            registrarAuditoria(
                'notas_pedido',
                'rechazar',
                "Nota de pedido {$nota['numero_nota']} rechazada - Motivo: $motivo"
            );
            
            echo json_encode([
                'success' => true,
                'mensaje' => 'Nota de pedido rechazada correctamente.'
            ]);
            break;
            
        // ============================================
        // ACCIÓN NO VÁLIDA
        // ============================================
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>