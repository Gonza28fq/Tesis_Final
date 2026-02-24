<?php
// Solo para depuración - ELIMINAR después
$password = 'admin123';
$hash_bd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

echo "Contraseña: " . $password . "<br>";
echo "Hash BD: " . $hash_bd . "<br>";
echo "Verificación: " . (password_verify($password, $hash_bd) ? 'CORRECTO ✓' : 'INCORRECTO ✗') . "<br><br>";

echo "Hash nuevo generado:<br>";
echo password_hash($password, PASSWORD_DEFAULT);
?>