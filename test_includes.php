<?php
echo "Verificando archivos...<br><br>";

$archivos = [
    'config/constantes.php',
    'config/conexion.php',
    'includes/funciones.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/navbar.php'
];

foreach ($archivos as $archivo) {
    if (file_exists($archivo)) {
        echo "✅ $archivo - EXISTE<br>";
    } else {
        echo "❌ $archivo - NO EXISTE<br>";
    }
}