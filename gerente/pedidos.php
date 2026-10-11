<?php
require_once __DIR__ . '/../includes/pedidos.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL'); $pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); if (manejar_post_pago($user)) volver_a('gerente/pedidos.php'); redirect('gerente/pedidos.php'); }

$locales = locales_empresa($user); $local = local_actual($locales);
$vista = ($_GET['vista'] ?? '') === 'concluidos' ? 'concluidos' : 'activos';
$est = in_array($_GET['estado'] ?? '', ['en proceso', 'terminado', 'cancelado'], true) ? $_GET['estado'] : '';
$q = trim($_GET['q'] ?? '');

$titulo = 'Pedidos y cobros';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'pedidos.php','badge'=>rol_badge($user['rol']),'menu'=>panel_menu($user['rol'])]);
if (!$local) sin_local_y_salir();
component('local_selector', ['locales' => $locales, 'local' => $local]);
component('pills_vista', ['vista' => $vista]);
$pedidos = listar_pedidos($pdo, (int)$local['id_local'], $vista, ['estado' => $est, 'q' => $q]);
?>
<form method="get" class="row g-2 mb-3">
  <input type="hidden" name="vista" value="<?= e($vista) ?>"><input type="hidden" name="local" value="<?= (int)$local['id_local'] ?>">
  <div class="col-6 col-md-3"><select class="form-select" name="estado" onchange="this.form.submit()">
    <option value="">Todos los estados</option>
    <?php foreach (['en proceso', 'terminado', 'cancelado'] as $e2): ?><option <?= $est === $e2 ? 'selected' : '' ?>><?= e($e2) ?></option><?php endforeach; ?></select></div>
  <div class="col-12 col-md-5"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Cliente, teléfono o # de pedido"></div>
  <div class="col-auto"><button class="btn btn-ca btn-ca-dark">Buscar</button></div>
</form>
<?php component('pedidos_tabla', ['pedidos' => $pedidos, 'user' => $user, 'opt' => ['mesero' => true, 'vacio' => 'No hay pedidos con esos filtros.']]);
component('admin_footer');
