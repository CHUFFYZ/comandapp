<?php
require_once __DIR__ . '/../includes/tenant.php';
$user = require_role('COCINERO');
$titulo = 'Comandas pendientes';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'index.php','badge'=>'Cocina','menu'=>panel_menu('COCINERO')]);
if (!$user['id_local']) sin_local_y_salir();
?>
<p class="text-muted"><i class="bi bi-arrow-repeat"></i> Se actualiza automáticamente cada 10 segundos. Las comandas con 15 min o más se marcan en rojo.</p>
<div id="comandas" data-url="comandas.php" data-csrf="<?= e(csrf_token()) ?>">Cargando…</div>
<?php component('admin_footer'); ?>
<script src="<?= asset('js/cocina.js') ?>"></script>
