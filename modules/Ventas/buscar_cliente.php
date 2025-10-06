<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // GET: Buscar clientes
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
        
        if (strlen($query) < 2) {
            jsonResponse([
                'success' => false,
                'message' => 'La búsqueda debe tener al menos 2 caracteres'
            ]);
        }
        
        $sql = "SELECT id_cliente, nombre, apellido, email, telefono, dni_cuit, direccion, tipo_cliente
                FROM Clientes 
                WHERE activo = 1 
                AND (
                    nombre LIKE :query1 
                    OR apellido LIKE :query2 
                    OR email LIKE :query3 
                    OR dni_cuit LIKE :query4
                )
                ORDER BY nombre, apellido
                LIMIT 10";
        
        $stmt = $db->prepare($sql);
        $searchTerm = "%{$query}%";
        $stmt->execute([
            ':query1' => $searchTerm,
            ':query2' => $searchTerm,
            ':query3' => $searchTerm,
            ':query4' => $searchTerm
        ]);
        
        $clientes = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'clientes' => $clientes
        ]);
    }
    
    // POST: Crear nuevo cliente
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
            jsonResponse([
                'success' => false,
                'message' => implode(', ', $errores)
            ], 400);
        }
        
        // Verificar si el email ya existe
        $sqlCheck = "SELECT id_cliente FROM Clientes WHERE email = :email";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([':email' => $input['email']]);
        
        if ($stmtCheck->fetch()) {
            jsonResponse([
                'success' => false,
                'message' => 'Ya existe un cliente con ese email'
            ], 400);
        }
        
        // Insertar nuevo cliente
        $sql = "INSERT INTO Clientes (nombre, apellido, email, telefono, dni_cuit, direccion, tipo_cliente) 
                VALUES (:nombre, :apellido, :email, :telefono, :dni_cuit, :direccion, :tipo_cliente)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nombre' => sanitize($input['nombre']),
            ':apellido' => sanitize($input['apellido']),
            ':email' => sanitize($input['email']),
            ':telefono' => sanitize($input['telefono'] ?? null),
            ':dni_cuit' => sanitize($input['dni_cuit'] ?? null),
            ':direccion' => sanitize($input['direccion'] ?? null),
            ':tipo_cliente' => $input['tipo_cliente'] ?? 'consumidor_final'
        ]);
        
        $idCliente = $db->lastInsertId();
        
        // Obtener datos del cliente creado
        $sqlCliente = "SELECT id_cliente, nombre, apellido, email, telefono, dni_cuit, direccion, tipo_cliente
                       FROM Clientes WHERE id_cliente = :id";
        $stmtCliente = $db->prepare($sqlCliente);
        $stmtCliente->execute([':id' => $idCliente]);
        $cliente = $stmtCliente->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'cliente' => $cliente
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error en buscar_cliente.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ], 500);
}
?>