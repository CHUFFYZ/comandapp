<?php
require_once __DIR__ . '/../includes/pagos.php';
$user = require_role('SUPER_ADMIN'); $pdo = db();
$f = in_array($_GET['f'] ?? '', ['revisar', 'riesgo', 'suspendidos'], true) ? $_GET['f'] : 'todos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ok = revisar_pago((int)($_POST['id'] ?? 0), ($_POST['accion'] ?? '') === 'aprobar' ? 'aprobar' : 'rechazar', (int)$user['id'], trim($_POST['nota'] ?? ''));
    flash($ok ? 'Comprobante actualizado.' : 'No se pudo actualizar ese comprobante.', $ok ? 'success' : 'danger');
    redirect('admin/pagos.php?f=' . $f);
}

$locales = $pdo->query('SELECT l.*, e.nombre_comercial, ps.nombre AS plan, ps.precio_mensual FROM locales l
    JOIN empresas e ON e.id_empresa = l.id_empresa JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo
    ORDER BY e.nombre_comercial, l.nombre_sucursal')->fetchAll();
$locales = cobro_de_locales($locales);   // sincroniza ciclos y calcula el cobro de cada local
$rank = ['vencido' => 0, 'urgente' => 1, 'atencion' => 2, 'ok' => 3];
$pasa = fn($c) => $f === 'todos' || ($f === 'revisar' && $c['estado'] === 'pendiente') || ($f === 'riesgo' && $c['nivel'] !== 'ok') || ($f === 'suspendidos' && $c['suspendido']);
$porEmpresa = []; $nRevisar = 0;
foreach ($locales as $l) {
    if ($l['cobro']['estado'] === 'pendiente') $nRevisar++;
    if (!$pasa($l['cobro'])) continue;
    $porEmpresa[$l['id_empresa']]['nombre'] = $l['nombre_comercial'];
    $porEmpresa[$l['id_empresa']]['locales'][] = $l;
}
$orden = fn($a, $b) => [$rank[$a['cobro']['nivel']], $a['cobro']['dias']] <=> [$rank[$b['cobro']['nivel']], $b['cobro']['dias']];
foreach ($porEmpresa as &$e) usort($e['locales'], $orden);
unset($e);
uasort($porEmpresa, fn($a, $b) => $orden($a['locales'][0], $b['locales'][0]));

$titulo = 'Pagos por sucursal';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'pagos.php']);
$tabs = ['todos' => 'Todas', 'revisar' => 'Por revisar (' . $nRevisar . ')', 'riesgo' => 'Sin aprobar', 'suspendidos' => 'Suspendidas'];
?>
<ul class="nav nav-pills mb-3">
  <?php foreach ($tabs as $k => $t): ?><li class="nav-item"><a class="nav-link <?= $f === $k ? 'active' : '' ?>" href="?f=<?= e($k) ?>"><?= e($t) ?></a></li><?php endforeach; ?>
</ul>
<?php foreach ($porEmpresa as $emp): ?>
  <div class="panel mb-3"><h2><?= e($emp['nombre']) ?></h2>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Local</th><th>Plan</th><th>Periodo</th><th>Comprobante</th><th>Plazo</th><th style="min-width:260px">Revisión</th></tr></thead><tbody>
      <?php foreach ($emp['locales'] as $l): $c = $l['cobro']; $p = $c['pago'];
        $cls = ['vencido' => 'table-danger', 'urgente' => 'table-danger', 'atencion' => 'table-warning', 'ok' => ''][$c['nivel']]; ?>
        <tr class="<?= $cls ?> <?= $l['activo'] ? '' : 'text-muted' ?>">
          <td><strong><?= e($l['nombre_sucursal']) ?></strong><br><small class="text-muted">Alta <?= e(fdate($l['fecha_alta'])) ?></small></td>
          <td><?= e($c['ciclo']['plan_nombre'] ?: $l['plan']) ?><br><small><?= e($c['ciclo']['dur_nombre']) ?> · <?= (int)$c['ciclo']['meses'] ?> mes(es)<br>Monto <strong><?= money($c['ciclo']['monto']) ?></strong></small><br><a class="small" href="suscripcion.php?id=<?= (int)$l['id_local'] ?>">Suscripción</a></td>
          <td>#<?= (int)$c['periodo'] ?><br><small>Corte <?= e(fdate($c['corte'])) ?></small></td>
          <td><?= cobro_badge($c['estado']) ?>
            <?php if ($p): ?><br><a href="<?= url('pages/comprobante.php?id=' . (int)$p['id_pago']) ?>" target="_blank" rel="noopener">Ver comprobante</a><br><small class="text-muted">Enviado <?= e(date('d/m H:i', strtotime($p['fecha_envio']))) ?></small><?php endif; ?></td>
          <td><?php if ($c['nivel'] === 'ok'): ?>Al día<br><small>Próx. corte <?= e(fdate($c['prox_corte'])) ?></small>
            <?php elseif ($c['suspendido']): ?><strong class="text-danger">SUSPENDIDA</strong><br><small>venció <?= e(fdate($l['fecha_vencimiento'])) ?></small>
            <?php else: ?><strong><?= max(0, $c['dias']) ?> día(s)</strong><br><small>límite <?= e(fdate($c['limite'])) ?></small><?php endif; ?></td>
          <td><?php if ($p && $p['estado'] !== 'aprobado'): ?>
            <?php if ($p['estado'] === 'rechazado' && $p['nota_revision']): ?><div class="small text-danger mb-1">Rechazado: <?= e($p['nota_revision']) ?></div><?php endif; ?>
            <form method="post" class="d-flex gap-1 flex-wrap"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id_pago'] ?>">
              <?php if ($p['estado'] === 'pendiente'): ?><input class="form-control form-control-sm" style="max-width:150px" name="nota" placeholder="Motivo (si rechazas)" maxlength="255"><?php endif; ?>
              <button class="btn btn-sm btn-success" name="accion" value="aprobar">Aprobar</button>
              <?php if ($p['estado'] === 'pendiente'): ?><button class="btn btn-sm btn-outline-danger" name="accion" value="rechazar">Rechazar</button><?php endif; ?>
            </form>
          <?php elseif (!$p): ?><span class="text-muted small">Esperando comprobante</span><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?></tbody></table></div></div>
<?php endforeach; if (!$porEmpresa): ?><div class="alert alert-light border text-center">No hay sucursales en esta vista.</div><?php endif; ?>
<?php component('admin_footer'); ?>
