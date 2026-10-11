<?php
require_once __DIR__ . '/../includes/inventario.php';
$user = require_role('COCINERO');

if ($user['id_local'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); inventario_guardar((int)$user['id_local'], (int)$user['id_empresa']);
    flash('Inventario actualizado.'); redirect('cocina/inventario.php');
}
$titulo = 'Inventario del local';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'inventario.php','badge'=>'Cocina','menu'=>panel_menu('COCINERO')]);
if (!$user['id_local']) sin_local_y_salir();
component('inventario_tabla', ['rows' => inventario_rows((int)$user['id_local'], (int)$user['id_empresa'])]);
component('admin_footer');
