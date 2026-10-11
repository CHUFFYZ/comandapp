<?php
require_once __DIR__ . '/../includes/pedidos.php';
$user = require_role('COCINERO'); $pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); manejar_post_pago($user); volver_a('cocina/pedidos.php'); }

$vista = ($_GET['vista'] ?? '') === 'concluidos' ? 'concluidos' : 'activos';
$titulo = 'Pedidos del día';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'pedidos.php','badge'=>'Cocina','menu'=>panel_menu('COCINERO')]);
if (!$user['id_local']) sin_local_y_salir();
component('pills_vista', ['vista' => $vista]);
component('pedidos_tabla', ['pedidos' => listar_pedidos($pdo, (int)$user['id_local'], $vista, ['hoy' => true]), 'user' => $user,
    'opt' => ['mesero' => true, 'vacio' => 'No hay pedidos de hoy en esta vista.']]);
component('admin_footer');
