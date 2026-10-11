<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/suscripciones.php';
$user = require_role('SUPER_ADMIN');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['accion'] ?? '') === 'duracion') {
        $nom = trim($_POST['nombre'] ?? ''); $meses = (int)($_POST['meses'] ?? 0); $desc = (float)($_POST['descuento_pct'] ?? -1); $gr = (int)($_POST['meses_gratis'] ?? -1);
        $otras = db()->prepare('SELECT COUNT(*) FROM duraciones_suscripcion WHERE activa = 1 AND id_duracion <> ?'); $otras->execute([(int)$_POST['id']]);
        if ($nom === '' || $meses < 1 || $meses > 36 || $desc < 0 || $desc > 90 || $gr < 0 || $gr > 12) flash('Revisa los valores de la duración.', 'danger');
        elseif (!isset($_POST['activa']) && !(int)$otras->fetchColumn()) flash('Debe quedar al menos una duración activa.', 'danger');
        else {
            db()->prepare('UPDATE duraciones_suscripcion SET nombre=?, meses=?, descuento_pct=?, meses_gratis=?, activa=? WHERE id_duracion=?')
                ->execute([$nom, $meses, $desc, $gr, isset($_POST['activa']) ? 1 : 0, (int)$_POST['id']]);
            flash('Duración actualizada. Aplica a las próximas suscripciones; las ya contratadas no cambian.');
        }
        redirect('admin/planes.php');
    }
    $lim = (int)($_POST['limite_empleados'] ?? 0);
    $pm = (float)($_POST['precio_mensual'] ?? -1); $pe = (float)($_POST['precio_empleado_extra'] ?? -1);
    $nom = trim($_POST['nombre'] ?? '');
    if ($nom === '' || $lim < 1 || $pm < 0 || $pe < 0) flash('Revisa los valores del plan.', 'danger');
    else {
        db()->prepare('UPDATE planes_suscripcion SET nombre=?, limite_empleados=?, precio_mensual=?, precio_empleado_extra=? WHERE id_plan=?')
            ->execute([$nom, $lim, $pm, $pe, (int)$_POST['id']]);
        flash('Plan actualizado. Los cambios ya se ven en la página principal.');
    }
    redirect('admin/planes.php');
}

$planes = get_planes();
$titulo = 'Planes de suscripción';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'planes.php']);
?>
<div class="panel">
  <h2>Precios y límites</h2>
  <p class="text-muted">Los precios son por local. Se muestran en la sección de precios de la página principal.</p>
  <?php foreach ($planes as $p): ?>
    <form method="post" class="row g-2 align-items-end plan-row">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id_plan'] ?>">
      <div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= e($p['nombre']) ?>" required></div>
      <div class="col-6 col-md-2"><label class="form-label">Empleados incl.</label><input class="form-control" type="number" min="1" name="limite_empleados" value="<?= (int)$p['limite_empleados'] ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label">Precio mensual</label><input class="form-control" type="number" step="0.01" min="0" name="precio_mensual" value="<?= e($p['precio_mensual']) ?>" required></div>
      <div class="col-6 col-md-2"><label class="form-label">Empleado extra</label><input class="form-control" type="number" step="0.01" min="0" name="precio_empleado_extra" value="<?= e($p['precio_empleado_extra']) ?>" required></div>
      <div class="col-6 col-md-2"><button class="btn btn-ca btn-ca-dark w-100">Guardar</button></div>
    </form>
  <?php endforeach; ?>
</div>
<?php $duraciones = get_duraciones(); ?>
<div class="panel mt-4">
  <h2>Duraciones de suscripción</h2>
  <p class="text-muted">Define cuántos meses se pagan, el descuento y los meses gratis que se regalan. Ejemplo: Anual = 12 meses, 20% de descuento y 1 mes gratis → se cobran 12 meses con descuento y la suscripción cubre 13.</p>
  <?php foreach ($duraciones as $d): ?>
    <form method="post" class="row g-2 align-items-end plan-row"><?= csrf_field() ?><input type="hidden" name="accion" value="duracion"><input type="hidden" name="id" value="<?= (int)$d['id_duracion'] ?>">
      <div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= e($d['nombre']) ?>" maxlength="30" required></div>
      <div class="col-6 col-md-2"><label class="form-label">Meses pagados</label><input class="form-control" type="number" min="1" max="36" name="meses" value="<?= (int)$d['meses'] ?>" required></div>
      <div class="col-6 col-md-2"><label class="form-label">Descuento %</label><input class="form-control" type="number" step="0.01" min="0" max="90" name="descuento_pct" value="<?= e($d['descuento_pct']) ?>" required></div>
      <div class="col-6 col-md-2"><label class="form-label">Meses gratis</label><input class="form-control" type="number" min="0" max="12" name="meses_gratis" value="<?= (int)$d['meses_gratis'] ?>" required></div>
      <div class="col-3 col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="activa" id="d<?= (int)$d['id_duracion'] ?>" <?= $d['activa'] ? 'checked' : '' ?>><label class="form-check-label" for="d<?= (int)$d['id_duracion'] ?>">Activa</label></div></div>
      <div class="col-3 col-md-2"><button class="btn btn-ca btn-ca-dark w-100">Guardar</button></div>
    </form>
  <?php endforeach; ?>
</div>
<?php component('admin_footer'); ?>
