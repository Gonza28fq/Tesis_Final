<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // GET: Buscar clientes
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        
        if (strlen($query) < 2) {
            echo json_encode([
                'success' => false,
                'message' => 'La búsqueda debe tener al menos 2 caracteres',
                'clientes' => []
            ]);
            exit;
        }
        
        $sql = "SELECT 
                    id_cliente, 
                    nombre, 
                    apellido, 
                    email, 
                    telefono, 
                    dni_cuit, 
                    direccion, 
                    tipo_cliente
                FROM Clientes 
                WHERE activo = 1 
                AND (
                    nombre LIKE :query1 
                    OR apellido LIKE :query2 
                    OR email LIKE :query3 
                    OR dni_cuit LIKE :query4
                    OR CONCAT(nombre, ' ', apellido) LIKE :query5
                )
                ORDER BY nombre, apellido
                LIMIT 15";
        
        $stmt = $db->prepare($sql);
        $searchTerm = "%{$query}%";
        $stmt->execute([
            ':query1' => $searchTerm,
            ':query2' => $searchTerm,
            ':query3' => $searchTerm,
            ':query4' => $searchTerm,
            ':query5' => $searchTerm
        ]);
        
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'clientes' => $clientes,
            'total' => count($clientes)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // POST: Crear nuevo cliente rápido
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validaciones
        $errores = [];
        
        if (empty($input['nombre'])) {
            $errores[] = 'El nombre es obligatorio';
        }
        
        if (empty($input['apellido'])) {
            $errores[] = 'El apellido es obligatorio';
        }
        
        if (empty($input['email'])) {
            $errores[] = 'El email es obligatorio';
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no es válido';
        }
        
        if (!empty($errores)) {
            echo json_encode([
                'success' => false,
                'message' => implode(', ', $errores)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Verificar si el email ya existe
        $sqlCheck = "SELECT id_cliente FROM Clientes WHERE email = :email";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([':email' => $input['email']]);
        
        if ($stmtCheck->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Ya existe un cliente con ese email'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Insertar nuevo cliente
        $sql = "INSERT INTO Clientes (
                    nombre, 
                    apellido, 
                    email, 
                    telefono, 
                    dni_cuit, 
                    direccion, 
                    tipo_cliente,
                    activo
                ) VALUES (
                    :nombre, 
                    :apellido, 
                    :email, 
                    :telefono, 
                    :dni_cuit, 
                    :direccion, 
                    :tipo_cliente,
                    1
                )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nombre' => trim($input['nombre']),
            ':apellido' => trim($input['apellido']),
            ':email' => trim($input['email']),
            ':telefono' => isset($input['telefono']) ? trim($input['telefono']) : null,
            ':dni_cuit' => isset($input['dni_cuit']) ? trim($input['dni_cuit']) : null,
            ':direccion' => isset($input['direccion']) ? trim($input['direccion']) : null,
            ':tipo_cliente' => isset($input['tipo_cliente']) ? $input['tipo_cliente'] : 'consumidor_final'
        ]);
        
        $idCliente = $db->lastInsertId();
        
        // Obtener datos del cliente creado
        $sqlCliente = "SELECT 
                        id_cliente, 
                        nombre, 
                        apellido, 
                        email, 
                        telefono, 
                        dni_cuit, 
                        direccion, 
                        tipo_cliente
                       FROM Clientes 
                       WHERE id_cliente = :id";
        $stmtCliente = $db->prepare($sqlCliente);
        $stmtCliente->execute([':id' => $idCliente]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'cliente' => $cliente
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Método no permitido
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log("Error en buscar_cliente.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Error general en buscar_cliente.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error inesperado',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>