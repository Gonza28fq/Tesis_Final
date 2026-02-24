<?php
// =============================================
// modules/clientes/actualizar_credito.php
// Actualizar Cuenta Corriente del Cliente
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requierePermiso('clientes', 'editar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = (int)$_POST['id_cliente'];
    $monto = (float)$_POST['monto'];
    $forma_pago = limpiarInput($_POST['forma_pago']);
    $numero_comprobante = limpiarInput($_POST['numero_comprobante'] ?? '');
    $observaciones = limpiarInput($_POST['observaciones'] ?? '');
    
    $db = getDB();
    $pdo = $db->getConexion();
    $errores = [];
    
    // Validaciones
    if ($id_cliente <= 0) {
        $errores[] = 'Cliente inválido';
    }
    
    if ($monto <= 0) {
        $errores[] = 'El monto debe ser mayor a cero';
    }
    
    if (empty($forma_pago)) {
        $errores[] = 'Debe seleccionar una forma de pago';
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // Obtener cliente
            $cliente = $db->query("SELECT * FROM clientes WHERE id_cliente = ?", [$id_cliente])->fetch();
            
            if (!$cliente) {
                throw new Exception('Cliente no encontrado');
            }
            
            // Validar que el monto no exceda la deuda
            if ($monto > $cliente['credito_utilizado']) {
                throw new Exception('El monto a pagar (' . formatearMoneda($monto) . ') no puede ser mayor a la deuda actual (' . formatearMoneda($cliente['credito_utilizado']) . ')');
            }
            
            // Actualizar crédito utilizado
            $sql = "UPDATE clientes 
                    SET credito_utilizado = credito_utilizado - ? 
                    WHERE id_cliente = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$monto, $id_cliente]);
            
            // Calcular nuevo saldo
            $nuevo_saldo = $cliente['credito_utilizado'] - $monto;
            
            // Registrar en auditoría
            $detalle_auditoria = "Pago de cuenta corriente registrado\n" .
                                "Cliente: {$cliente['nombre']}\n" .
                                "Monto pagado: " . formatearMoneda($monto) . "\n" .
                                "Forma de pago: $forma_pago\n" .
                                "Saldo anterior: " . formatearMoneda($cliente['credito_utilizado']) . "\n" .
                                "Saldo actual: " . formatearMoneda($nuevo_saldo);
            
            if ($numero_comprobante) {
                $detalle_auditoria .= "\nComprobante: $numero_comprobante";
            }
            
            if ($observaciones) {
                $detalle_auditoria .= "\nObservaciones: $observaciones";
            }
            
            registrarAuditoria('clientes', 'pago_credito', $detalle_auditoria);
            
            $pdo->commit();
            
            setAlerta('success', 'Pago registrado exitosamente. Nuevo saldo: ' . formatearMoneda($nuevo_saldo));
            redirigir('modules/clientes/ver.php?id=' . $id_cliente);
            
        } catch (Exception $e) {
            $pdo->rollback();
            setAlerta('danger', 'Error al registrar el pago: ' . $e->getMessage());
            redirigir('modules/clientes/ver.php?id=' . $id_cliente);
        }
    } else {
        setAlerta('danger', implode('<br>', $errores));
        redirigir('modules/clientes/ver.php?id=' . $id_cliente);
    }
} else {
    redirigir('modules/clientes/index.php');
}
?>