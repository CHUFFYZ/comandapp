<?php
require_once __DIR__ . '/../includes/usuarios.php';
$user = require_role('SUPER_ADMIN');
$t = usuario_para_editar($user, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
if (!$t) { flash('Usuario no encontrado.', 'danger'); redirect('admin/usuarios.php'); }
$op = usuario_opciones($user, $t);
$volver = 'usuarios.php' . ($t['id_empresa'] ? '?empresa=' . (int)$t['id_empresa'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $err = usuario_guardar($user, $t, $op);
    if ($err) { flash($err, 'danger'); redirect('admin/usuario_editar.php?id=' . (int)$t['id_usuario']); }
    flash('Datos del usuario actualizados.'); redirect('admin/' . $volver);
}
$titulo = 'Editar usuario';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'usuarios.php']);
component('usuario_form', ['t' => $t, 'op' => $op, 'esYo' => false, 'volver' => $volver]);
component('admin_footer');
