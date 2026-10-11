<?php
require_once __DIR__ . '/../includes/usuarios.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL');
$t = usuario_para_editar($user, (int)($_GET['id'] ?? $_POST['id'] ?? $user['id']));   // sin id = mi perfil
if (!$t) { flash('No tienes permiso para editar ese usuario.', 'danger'); redirect('gerente/empleados.php'); }
$yo = usuario_es_yo($user, $t); $op = usuario_opciones($user, $t);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $err = usuario_guardar($user, $t, $op);
    if ($err) { flash($err, 'danger'); redirect('gerente/usuario_editar.php?id=' . (int)$t['id_usuario']); }
    flash($yo ? 'Tus datos fueron actualizados.' : 'Datos del empleado actualizados.');
    redirect($yo ? 'gerente/usuario_editar.php' : 'gerente/empleados.php');
}
$titulo = $yo ? 'Mi perfil' : 'Editar empleado';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>$yo ? 'usuario_editar.php' : 'empleados.php','badge'=>rol_badge($user['rol']),'menu'=>panel_menu($user['rol'])]);
component('usuario_form', ['t' => $t, 'op' => $op, 'esYo' => $yo, 'volver' => $yo ? 'index.php' : 'empleados.php']);
component('admin_footer');
