<?php
require_once __DIR__ . '/../includes/pagos.php';
$user = require_role('SUPER_ADMIN'); $pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id_local'] ?? 0);
$st = $pdo->prepare('SELECT l.*, e.nombre_comercial, ps.nombre AS plan, ps.precio_mensual FROM locales l
    JOIN empresas e ON e.id_empresa = l.id_empresa JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo WHERE l.id_local = ?');
$st->execute([$id]); $l = $st->fetch();
if (!$l) { flash('Sucursal no encontrada.', 'danger'); redirect('admin/locales.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); suscripcion_post((int)$user['id'], [$id], true);
    redirect('admin/suscripcion.php?id=' . $id);
}
$l = cobro_de_locales([$l])[0];
$titulo = 'Suscripción de ' . $l['nombre_sucursal'];
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'locales.php']);
?>
<div class="panel" style="max-width:820px">
  <p class="mb-1"><strong><?= e($l['nombre_comercial']) ?></strong> · <?= e($l['nombre_sucursal']) ?></p>
  <p class="small text-muted mb-0">Alta <?= e(fdate($l['fecha_alta'])) ?> · servicio vigente hasta <?= e(fdate($l['fecha_vencimiento'])) ?> · pago: <?= cobro_badge($l['cobro']['estado']) ?></p>
  <?php component('suscripcion_cambio', ['local' => $l, 'planes' => get_planes(), 'duraciones' => get_duraciones(true), 'cambio' => cambio_programado($id)]); ?>
  <a class="btn btn-ca btn-ca-ghost mt-3" href="locales.php">Volver a locales</a>
</div>
<?php component('admin_footer'); ?>
