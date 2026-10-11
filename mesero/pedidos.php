<?php
require_once __DIR__ . '/../includes/pedidos.php';
$user = require_role('MESERO'); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (manejar_post_pago($user)) volver_a('mesero/pedidos.php');
    if (($_POST['accion'] ?? '') === 'cancelar') {   // solo se puede cancelar un pedido propio
        $st = $pdo->prepare("UPDATE pedidos SET estado='cancelado' WHERE id_pedido=? AND id_mesero=? AND estado='en proceso'");
        $st->execute([(int)$_POST['id'], $user['id']]);
        flash($st->rowCount() ? 'Pedido cancelado y stock devuelto.' : 'Solo puedes cancelar tus propios pedidos en proceso.', $st->rowCount() ? 'success' : 'danger');
    }
    volver_a('mesero/pedidos.php');
}

$vista = ($_GET['vista'] ?? '') === 'concluidos' ? 'concluidos' : 'activos';
$ver = in_array($_GET['ver'] ?? '', ['otros', 'todos'], true) ? $_GET['ver'] : 'mios';
$q = trim($_GET['q'] ?? '');
$f = ['q' => $q];
if ($ver === 'mios') $f['mesero'] = $user['id']; elseif ($ver === 'otros') $f['otros'] = $user['id'];
$pedidos = $user['id_local'] ? listar_pedidos($pdo, (int)$user['id_local'], $vista, $f) : [];

$titulo = 'Pedidos';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'pedidos.php','badge'=>'Mesero','menu'=>panel_menu('MESERO')]);
if (!$user['id_local']) sin_local_y_salir();
component('pills_vista', ['vista' => $vista]);
$tabs = ['mios' => 'Mis pedidos', 'otros' => 'Pedidos de otros', 'todos' => 'Todos'];
?>
<ul class="nav nav-tabs mb-3">
  <?php foreach ($tabs as $k => $label): ?><li class="nav-item"><a class="nav-link <?= $ver === $k ? 'active' : '' ?>" href="<?= e(url_con(['ver' => $k])) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
</ul>
<form method="get" class="row g-2 mb-3">
  <input type="hidden" name="vista" value="<?= e($vista) ?>"><input type="hidden" name="ver" value="<?= e($ver) ?>">
  <div class="col-12 col-md-6"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o teléfono del cliente, o # de pedido"></div>
  <div class="col-auto"><button class="btn btn-ca btn-ca-dark">Buscar</button> <?php if ($q !== ''): ?><a class="btn btn-ca btn-ca-ghost" href="<?= e(url_con(['q' => ''])) ?>">Limpiar</a><?php endif; ?></div>
</form>
<?php component('pedidos_tabla', ['pedidos' => $pedidos, 'user' => $user,
    'opt' => ['mesero' => true, 'modificar' => true, 'cancelar_propio' => true, 'vacio' => $q !== '' ? 'No se encontraron pedidos con esa búsqueda.' : 'No hay pedidos para mostrar.']]);
component('admin_footer');
