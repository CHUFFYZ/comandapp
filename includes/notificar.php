<?php
require_once __DIR__ . '/functions.php';

/** Crea o actualiza una notificación (única por usuario+clave: no se duplica). */
function notificar(int $idUsuario, string $clave, string $tipo, string $titulo, string $mensaje, ?string $enlace = null): void {
    db()->prepare('INSERT INTO notificaciones (id_usuario, clave, tipo, titulo, mensaje, enlace) VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), mensaje = VALUES(mensaje), enlace = VALUES(enlace)')
        ->execute([$idUsuario, mb_substr($clave, 0, 120), $tipo, mb_substr($titulo, 0, 120), mb_substr($mensaje, 0, 500), $enlace]);
}

/** Avisa a los gerentes generales activos de una empresa. */
function notificar_gerentes(int $idEmpresa, string $clave, string $tipo, string $titulo, string $mensaje, ?string $enlace = null): void {
    $st = db()->prepare("SELECT u.id_usuario FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
                         WHERE u.id_empresa = ? AND r.nombre_rol = 'GERENTE' AND u.activo = 1");
    $st->execute([$idEmpresa]);
    foreach ($st->fetchAll() as $r) notificar((int)$r['id_usuario'], $clave, $tipo, $titulo, $mensaje, $enlace);
}
