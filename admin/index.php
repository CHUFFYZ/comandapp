<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('SUPER_ADMIN');

$uso = db()->query(
  'SELECT e.nombre_comercial, l.nombre_sucursal, l.fecha_vencimiento, l.activo,
          ps.nombre AS plan, ps.limite_empleados, ps.precio_mensual, ps.precio_empleado_extra,
          COUNT(u.id_usuario) AS emp
   FROM locales l
   JOIN empresas e ON e.id_empresa = l.id_empresa
   JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo
   LEFT JOIN usuarios u ON u.id_local = l.id_local AND u.activo = TRUE
   GROUP BY l.id_local, e.id_empresa, ps.id_plan
   ORDER BY l.fecha_vencimiento ASC')->fetchAll();

$ingreso = 0; $extras = 0; $localesActivos = 0;
foreach ($uso as $r) {
    if (!$r['activo']) continue;
    $localesActivos++;
    $ex = max(0, $r['emp'] - $r['limite_empleados']);
    $extras += $ex;
    $ingreso += $r['precio_mensual'] + $ex * $r['precio_empleado_extra'];
}
$empresas = (int) db()->query('SELECT COUNT(*) FROM empresas WHERE activo = 1')->fetchColumn();
$stats = [
  ['Empresas activas', $empresas, 'bi-building'],
  ['Locales activos', $localesActivos, 'bi-shop'],
  ['Empleados extra', $extras, 'bi-person-plus'],
  ['Ingreso mensual estimado', money($ingreso), 'bi-cash-coin'],
];

$titulo = 'Panel del superusuario';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'index.php']);
?>
<div class="row g-3 mb-4">
  <?php foreach ($stats as [$label, $valor, $icono]): ?>
    <div class="col-6 col-lg-3"><div class="stat"><i class="bi <?= e($icono) ?>"></i><strong><?= e($valor) ?></strong><span><?= e($label) ?></span></div></div>
  <?php endforeach; ?>
</div>
<div class="panel">
  <h2>Uso de empleados y vencimientos por local</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Empresa</th><th>Local</th><th>Plan</th><th>Empleados</th><th>Extras</th><th>Vencimiento</th></tr></thead>
      <tbody>
      <?php foreach ($uso as $r): $ex = max(0, $r['emp'] - $r['limite_empleados']); [$txt, $cls] = estado_vencimiento($r['fecha_vencimiento']); ?>
        <tr class="<?= $r['activo'] ? '' : 'text-muted' ?>">
          <td><?= e($r['nombre_comercial']) ?></td>
          <td><?= e($r['nombre_sucursal']) ?><?= $r['activo'] ? '' : ' <span class="badge text-bg-secondary">inactivo</span>' ?></td>
          <td><?= e($r['plan']) ?></td>
          <td><?= (int)$r['emp'] ?> / <?= (int)$r['limite_empleados'] ?></td>
          <td><?= $ex ? '<span class="badge text-bg-warning">+' . $ex . ' (' . money($ex * $r['precio_empleado_extra']) . ')</span>' : '0' ?></td>
          <td><span class="badge text-bg-<?= $cls ?>"><?= e($txt) ?></span> <small class="text-muted"><?= e($r['fecha_vencimiento']) ?></small></td>
        </tr>
      <?php endforeach; if (!$uso): ?><tr><td colspan="6" class="text-center text-muted py-4">Aún no hay locales. Crea una empresa y agrégale su primer local.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php component('admin_footer'); ?>
