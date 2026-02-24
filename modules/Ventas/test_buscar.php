<?php
/**
 * ARCHIVO DE PRUEBA - Colocar en modules/ventas/
 * Abrir en navegador: localhost/proyecto-gestion-comercial/modules/ventas/test_buscar.php
 */

require_once '../../config/conexion.php';
validarSesion();

echo "<h2>🔍 TEST DE BÚSQUEDA DE PRODUCTOS</h2>";
echo "<hr>";

// Test 1: Verificar conexión
echo "<h3>1. Verificando conexión a BD...</h3>";
try {
    $db = getDB();
    echo "✅ Conexión exitosa<br><br>";
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br><br>";
    die();
}

// Test 2: Verificar productos en BD
echo "<h3>2. Verificando productos en BD...</h3>";
try {
    $sql = "SELECT COUNT(*) as total FROM Productos WHERE activo = 1";
    $stmt = $db->query($sql);
    $result = $stmt->fetch();
    echo "✅ Total de productos activos: " . $result['total'] . "<br><br>";
    
    if ($result['total'] == 0) {
        echo "⚠️ <strong>ADVERTENCIA:</strong> No hay productos activos en la base de datos<br><br>";
    }
} catch (PDOException $e) {
    echo "❌ Error al consultar productos: " . $e->getMessage() . "<br><br>";
}

// Test 3: Probar búsqueda
echo "<h3>3. Probando búsqueda de productos...</h3>";
$busqueda = "a"; // Buscar productos que contengan 'a'

try {
    $sql = "SELECT 
                p.id_producto,
                p.nombre,
                p.codigo_producto,
                p.precio_unitario,
                c.nombre_categoria,
                COALESCE(SUM(s.cantidad), 0) as stock_disponible
            FROM Productos p
            LEFT JOIN Categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN Stock s ON p.id_producto = s.id_producto
            WHERE p.activo = 1
            AND (p.nombre LIKE :busqueda 
                 OR p.codigo_producto LIKE :busqueda2)
            GROUP BY p.id_producto
            HAVING stock_disponible > 0
            ORDER BY p.nombre
            LIMIT 10";
    
    $searchTerm = "%{$busqueda}%";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':busqueda' => $searchTerm,
        ':busqueda2' => $searchTerm
    ]);
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Búsqueda exitosa. Productos encontrados: " . count($productos) . "<br><br>";
    
    if (count($productos) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Categoría</th></tr>";
        foreach ($productos as $prod) {
            echo "<tr>";
            echo "<td>" . $prod['id_producto'] . "</td>";
            echo "<td>" . $prod['nombre'] . "</td>";
            echo "<td>$" . $prod['precio_unitario'] . "</td>";
            echo "<td>" . $prod['stock_disponible'] . "</td>";
            echo "<td>" . $prod['nombre_categoria'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "⚠️ No se encontraron productos con stock disponible<br><br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error en la búsqueda: " . $e->getMessage() . "<br><br>";
}

// Test 4: Verificar archivo buscar_producto.php
echo "<h3>4. Verificando archivo buscar_producto.php...</h3>";
if (file_exists('buscar_producto.php')) {
    echo "✅ El archivo buscar_producto.php existe<br><br>";
} else {
    echo "❌ <strong>ERROR:</strong> El archivo buscar_producto.php NO existe en esta carpeta<br>";
    echo "📁 Ubicación esperada: modules/ventas/buscar_producto.php<br><br>";
}

// Test 5: Probar llamada AJAX simulada
echo "<h3>5. Probando llamada AJAX...</h3>";
echo "<button onclick='probarAjax()' style='padding:10px 20px;cursor:pointer;'>🧪 Probar Búsqueda AJAX</button>";
echo "<div id='resultado-ajax' style='margin-top:10px;padding:10px;background:#f7f7f7;min-height:50px;'></div>";

?>

<script>
function probarAjax() {
    const resultado = document.getElementById('resultado-ajax');
    resultado.innerHTML = '⏳ Buscando...';
    
    fetch('buscar_producto.php?q=a')
        .then(response => {
            console.log('Status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP Error ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Datos recibidos:', data);
            resultado.innerHTML = '<strong style="color:green;">✅ AJAX EXITOSO</strong><br><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            console.error('Error:', error);
            resultado.innerHTML = '<strong style="color:red;">❌ ERROR EN AJAX</strong><br>' + error.message;
        });
}
</script>

<hr>
<h3>📝 INSTRUCCIONES:</h3>
<ol>
    <li>Si ves errores arriba, corrígelos primero</li>
    <li>Verifica que tengas productos con stock en la BD</li>
    <li>Asegúrate de que <code>buscar_producto.php</code> está en la misma carpeta que este archivo</li>
    <li>Haz click en "Probar Búsqueda AJAX" para verificar la conexión JavaScript</li>
</ol>