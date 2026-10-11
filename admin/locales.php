<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/suscripciones.php';
$user = require_role('SUPER_ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($accion === 'toggle') {
        $pdo->prepare('UPDATE locales SET activo = NOT activo WHERE id_local = ?')->execute([$id]);
        flash('Estado del local actualizado.');
    } elseif ($accion === 'renovar') {
        $pdo->prepare('UPDATE locales SET fecha_vencimiento = DATE_ADD(GREATEST(CURDATE(), fecha_vencimiento), INTERVAL 1 MONTH) WHERE id_local = ?')->execute([$id]);
        flash('Suscripción renovada por 1 mes.');
    } elseif ($accion === 'crear') {
        $nom = trim($_POST['nombre_sucursal'] ?? ''); $venc = $_POST['fecha_vencimiento'] ?? '';
        $ok = $nom !== '' && (int)$_POST['id_empresa'] > 0 && (int)$_POST['id_plan'] > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc);
        if ($ok) {
            $nuevo = crear_local($pdo, (int)$_POST['id_empresa'], $nom, (int)$_POST['id_plan'], duracion_base()['id_duracion']);
            $pdo->prepare('UPDATE locales SET fecha_vencimiento = ? WHERE id_local = ?')->execute([$venc, $nuevo]);
            flash('Local creado.');
        } else flash('Completa todos los campos del local.', 'danger');
    }
    redirect('admin/locales.php');
}

$empresas = $pdo->query('SELECT id_empresa, nombre_comercial FROM empresas WHERE activo = 1 ORDER BY nombre_comercial')->fetchAll();
$planes = get_planes();
$locales = $pdo->query(
  'SELECT l.*, e.nombre_comercial, ps.nombre AS plan FROM locales l
   JOIN empresas e ON e.id_empresa = l.id_empresa
   JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo
   ORDER BY e.nombre_comercial, l.nombre_sucursal')->fetchAll();

$titulo = 'Locales';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'locales.php']);
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="panel">
      <h2>Nuevo local</h2>
      <?php if (!$empresas): ?><p class="text-muted">Primero crea una empresa.</p><?php else: ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
        <div class="mb-2"><label class="form-label">Empresa</label>
          <select class="form-select" name="id_empresa" required><?php foreach ($empresas as $e): ?><option value="<?= (int)$e['id_empresa'] ?>"><?= e($e['nombre_comercial']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Nombre de la sucursal</label><input class="form-control" name="nombre_sucursal" required maxlength="100"></div>
        <div class="mb-2"><label class="form-label">Plan</label>
          <select class="form-select" name="id_plan" required><?php foreach ($planes as $p): ?><option value="<?= (int)$p['id_plan'] ?>"><?= e($p['nombre']) ?> — <?= money($p['precio_mensual']) ?>/mes</option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Vence el</label><input class="form-control" type="date" name="fecha_vencimiento" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required></div>
        <button class="btn btn-ca btn-ca-primary w-100">Crear local</button>
      </form><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="panel">
      <h2>Locales</h2>
      <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Local</th><th>Plan</th><th>Vencimiento</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($locales as $l): [$txt, $cls] = estado_vencimiento($l['fecha_vencimiento']); ?>
          <tr class="<?= $l['activo'] ? '' : 'text-muted' ?>">
            <td><strong><?= e($l['nombre_sucursal']) ?></strong><br><small class="text-muted"><?= e($l['nombre_comercial']) ?></small></td>
            <td><?= e($l['plan']) ?></td>
            <td><span class="badge text-bg-<?= $cls ?>"><?= e($txt) ?></span><br><small class="text-muted"><?= e($l['fecha_vencimiento']) ?></small></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="suscripcion.php?id=<?= (int)$l['id_local'] ?>">Suscripción</a> <?php component('form_accion', ['accion' => 'renovar', 'id' => $l['id_local'], 'texto' => 'Cortesía +1 mes', 'clase' => 'btn-outline-success']); ?>
              <?php component('form_accion', ['accion' => 'toggle', 'id' => $l['id_local'], 'texto' => $l['activo'] ? 'Desactivar' : 'Activar']); ?>
            </td>
          </tr>
        <?php endforeach; if (!$locales): ?><tr><td colspan="4" class="text-center text-muted py-4">Aún no hay locales.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php component('admin_footer'); ?>
