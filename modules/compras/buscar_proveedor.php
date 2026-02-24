<?php
require_once '../../config/conexion.php';
validarSesion();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // GET: Buscar proveedores
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
        
        if (strlen($query) < 2) {
            jsonResponse([
                'success' => false,
                'message' => 'La búsqueda debe tener al menos 2 caracteres'
            ]);
        }
        
        $sql = "SELECT id_proveedor, nombre, contacto, email, telefono, cuit, direccion
                FROM Proveedores 
                WHERE activo = 1 
                AND (
                    nombre LIKE :query1 
                    OR cuit LIKE :query2 
                    OR contacto LIKE :query3
                )
                ORDER BY nombre
                LIMIT 10";
        
        $stmt = $db->prepare($sql);
        $searchTerm = "%{$query}%";
        $stmt->execute([
            ':query1' => $searchTerm,
            ':query2' => $searchTerm,
            ':query3' => $searchTerm
        ]);
        
        $proveedores = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'proveedores' => $proveedores
        ]);
    }
    
    // POST: Crear nuevo proveedor
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validaciones
        if (empty($input['nombre'])) {
            jsonResponse([
                'success' => false,
                'message' => 'El nombre es obligatorio'
            ], 400);
        }
        
        // Verificar si el CUIT ya existe (si se proporcionó)
        if (!empty($input['cuit'])) {
            $sqlCheck = "SELECT id_proveedor FROM Proveedores WHERE cuit = :cuit";
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute([':cuit' => $input['cuit']]);
            
            if ($stmtCheck->fetch()) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Ya existe un proveedor con ese CUIT'
                ], 400);
            }
        }
        
        // Insertar nuevo proveedor
        $sql = "INSERT INTO Proveedores (nombre, cuit, contacto, email, telefono, direccion) 
                VALUES (:nombre, :cuit, :contacto, :email, :telefono, :direccion)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nombre' => sanitize($input['nombre']),
            ':cuit' => sanitize($input['cuit'] ?? null),
            ':contacto' => sanitize($input['contacto'] ?? null),
            ':email' => sanitize($input['email'] ?? null),
            ':telefono' => sanitize($input['telefono'] ?? null),
            ':direccion' => sanitize($input['direccion'] ?? null)
        ]);
        
        $idProveedor = $db->lastInsertId();
        
        // Obtener datos del proveedor creado
        $sqlProveedor = "SELECT id_proveedor, nombre, contacto, email, telefono, cuit, direccion
                        FROM Proveedores WHERE id_proveedor = :id";
        $stmtProveedor = $db->prepare($sqlProveedor);
        $stmtProveedor->execute([':id' => $idProveedor]);
        $proveedor = $stmtProveedor->fetch();
        
        jsonResponse([
            'success' => true,
            'message' => 'Proveedor creado exitosamente',
            'proveedor' => $proveedor
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error en buscar_proveedor.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ], 500);
}
?>