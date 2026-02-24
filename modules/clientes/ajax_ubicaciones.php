<?php
// =============================================
// modules/clientes/ajax_ubicaciones.php
// API para cargar ciudades según provincia
// =============================================

require_once '../../config/constantes.php';
require_once '../../config/conexion.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

// Permitir acceso (comentar validación AJAX estricta)
/*
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}
*/

try {
    $db = getDB();
    $accion = $_GET['accion'] ?? '';
    
    switch ($accion) {
        case 'provincias':
            // Obtener todas las provincias
            $provincias = $db->query("
                SELECT id_provincia, nombre, codigo 
                FROM provincias 
                ORDER BY nombre ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $provincias
            ]);
            break;
            
        case 'ciudades':
            // Obtener ciudades de una provincia
            $id_provincia = (int)($_GET['id_provincia'] ?? 0);
            
            if ($id_provincia === 0) {
                echo json_encode([
                    'success' => false,
                    'error' => 'ID de provincia inválido'
                ]);
                exit;
            }
            
            $ciudades = $db->query("
                SELECT id_ciudad, nombre, codigo_postal 
                FROM ciudades 
                WHERE id_provincia = ? 
                ORDER BY nombre ASC
            ", [$id_provincia])->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $ciudades
            ]);
            break;
            
        case 'buscar_ciudad':
            // Buscar ciudad por nombre (para autocompletado)
            $termino = $_GET['q'] ?? '';
            
            if (strlen($termino) < 2) {
                echo json_encode([
                    'success' => true,
                    'data' => []
                ]);
                exit;
            }
            
            $ciudades = $db->query("
                SELECT c.id_ciudad, c.nombre, c.codigo_postal, p.nombre as provincia, c.id_provincia
                FROM ciudades c
                INNER JOIN provincias p ON c.id_provincia = p.id_provincia
                WHERE c.nombre LIKE ?
                ORDER BY c.nombre ASC
                LIMIT 20
            ", ["%$termino%"])->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $ciudades
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Acción no válida. Acción recibida: ' . $accion
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>
