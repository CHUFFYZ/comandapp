<?php
require_once __DIR__ . '/../includes/notificaciones.php';
$user = require_role('SUPER_ADMIN', 'GERENTE', 'GERENTE_LOCAL', 'MESERO', 'COCINERO');
$pdo = db();

if (isset($_GET['ir'])) {   // abrir una notificación: se marca como leída y se va a su enlace
    $st = $pdo->prepare('SELECT id_notificacion, enlace FROM notificaciones WHERE id_notificacion = ? AND id_usuario = ?');
    $st->execute([(int)$_GET['ir'], $user['id']]); $n = $st->fetch();
    if ($n) { $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id_notificacion = ?')->execute([$n['id_notificacion']]); redirect($n['enlace'] ?: 'pages/notificaciones.php'); }
    redirect('pages/notificaciones.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = $_POST['accion'] ?? '';
    if ($a === 'leer_todas') $pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id_usuario = ?')->execute([$user['id']]);
    elseif ($a === 'borrar_leidas') $pdo->prepare('DELETE FROM notificaciones WHERE id_usuario = ? AND leida = 1')->execute([$user['id']]);
    redirect('pages/notificaciones.php');
}
$st = $pdo->prepare('SELECT * FROM notificaciones WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 100');
$st->execute([$user['id']]); $lista = $st->fetchAll();

$base = panel_base($user['rol']);   // el menú del panel con enlaces absolutos (esta página vive en /pages)
$menu = array_map(fn($m) => ['label' => $m['label'], 'href' => url($base . '/' . $m['href'])], panel_menu($user['rol']));
$titulo = 'Notificaciones';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user' => $user, 'titulo' => $titulo, 'activo' => '', 'badge' => rol_badge($user['rol']), 'menu' => $menu]);
?>
<div class="d-flex gap-2 mb-3 flex-wrap">
  <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-ca btn-ca-dark" name="accion" value="leer_todas">Marcar todas como leídas</button></form>
  <form method="post" onsubmit="return confirm('¿Borrar las notificaciones leídas?')"><?= csrf_field() ?><button class="btn btn-sm btn-ca btn-ca-ghost" name="accion" value="borrar_leidas">Borrar leídas</button></form>
</div>
<div class="list-group">
  <?php foreach ($lista as $x): ?>
    <a class="list-group-item list-group-item-action notif-item <?= $x['leida'] ? '' : 'notif-no-leida' ?>" href="?ir=<?= (int)$x['id_notificacion'] ?>">
      <div class="d-flex gap-3"><i class="bi <?= e(notif_icono($x['tipo'])) ?> fs-4"></i>
        <div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><strong><?= e($x['titulo']) ?></strong><small class="text-muted text-nowrap"><?= e(notif_tiempo($x['fecha'])) ?></small></div>
          <div class="text-muted"><?= e($x['mensaje']) ?></div></div></div>
    </a>
  <?php endforeach; if (!$lista): ?><div class="list-group-item text-center text-muted py-5">No tienes notificaciones.</div><?php endif; ?>
</div>
<?php component('admin_footer'); ?>
