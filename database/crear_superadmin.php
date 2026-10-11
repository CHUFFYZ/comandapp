<?php
// Uso: php database/crear_superadmin.php "Tu Nombre" correo@dominio.com "TuContraseñaSegura"
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por línea de comandos.'); }
require_once __DIR__ . '/../config/database.php';
[$_, $nombre, $email, $pass] = $argv + [null, null, null, null];
if (!$nombre || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string)$pass) < 8) {
    exit("Uso: php database/crear_superadmin.php \"Nombre\" correo@dominio.com \"contraseña (mín. 8)\"\n");
}
$st = db()->prepare("INSERT INTO usuarios (id_empresa, id_local, id_rol, nombre, email, password_hash)
                     VALUES (NULL, NULL, (SELECT id_rol FROM roles WHERE nombre_rol = 'SUPER_ADMIN'), ?, ?, ?)");
$st->execute([$nombre, $email, password_hash($pass, PASSWORD_DEFAULT)]);
echo "Superusuario creado: $email\n";
