<?php
require_once __DIR__ . '/../includes/inventario.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL');
$locales = locales_empresa($user); $local = local_actual($locales);

if ($local && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); inventario_guardar($local['id_local'], $user['id_empresa']);
    flash('Inventario guardado.'); redirect('gerente/inventario.php');
}
$titulo = 'Inventario por local';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'inventario.php','badge'=>rol_badge($user['rol']),'menu'=>panel_menu($user['rol'])]);
if (!$local) sin_local_y_salir();
component('local_selector', ['locales' => $locales, 'local' => $local]);
component('inventario_tabla', ['rows' => inventario_rows($local['id_local'], $user['id_empresa'])]);
component('admin_footer');
