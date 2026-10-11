<?php
require_once __DIR__ . '/../includes/pagos.php';
$user = require_role('GERENTE'); $pdo = db(); $emp = (int)$user['id_empresa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = $_POST['accion'] ?? '';
    if ($a === 'crear_local') {
        $n = trim($_POST['nombre_sucursal'] ?? ''); $plan = (int)($_POST['id_plan'] ?? 0); $dur = (int)($_POST['id_duracion'] ?? 0);
        if ($n === '' || !plan_por_id($plan) || !duracion_valida($dur)) flash('Escribe el nombre de la sucursal y elige plan y duración.', 'danger');
        else { crear_local($pdo, $emp, $n, $plan, $dur); flash('Sucursal creada. Ya puede operar; tienes 7 días para subir su comprobante de pago.'); }
    } elseif (in_array($a, ['programar_cambio', 'cancelar_cambio'], true)) {
        suscripcion_post((int)$user['id'], array_map(fn($l) => (int)$l['id_local'], locales_con_cobro($emp)));
    } elseif ($a === 'comprobante') {
        $loc = null;
        foreach (locales_con_cobro($emp) as $l) if ((int)$l['id_local'] === (int)($_POST['id_local'] ?? 0)) $loc = $l;
        if (!$loc || !$loc['activo']) flash('Sucursal no válida.', 'danger');
        else { $err = guardar_comprobante($loc, $_FILES['comprobante'] ?? []); flash($err ?? 'Comprobante enviado: quedó en revisión.', $err ? 'danger' : 'success'); }
    }
    redirect('gerente/sucursales.php');
}
$ls = locales_con_cobro($emp); $planes = get_planes(); $duraciones = get_duraciones(true);

$titulo = 'Sucursales y pagos';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'sucursales.php','badge'=>'Gerente','menu'=>panel_menu('GERENTE')]);
?>
<p class="text-muted"><?= e(PAGO_INSTRUCCIONES) ?></p>
<div class="row g-4">
  <div class="col-lg-8">
  <?php foreach ($ls as $l): $c = $l['cobro']; $p = $c['pago']; $ciclo = $c['ciclo'];
        $borde = ['vencido' => 'danger', 'urgente' => 'danger', 'atencion' => 'warning', 'ok' => 'success'][$c['nivel']]; ?>
    <div class="panel mb-3 sucursal border-<?= $borde ?>">
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <div><h2 class="mb-0"><?= e($l['nombre_sucursal']) ?></h2>
          <small class="text-muted">Plan <?= e($ciclo['plan_nombre'] ?: $l['plan']) ?> · <?= e($ciclo['dur_nombre']) ?> (<?= (int)$ciclo['meses'] ?> m) · alta <?= e(fdate($l['fecha_alta'])) ?></small></div>
        <div class="text-end"><?= cobro_badge($c['estado']) ?>
          <?php if ($c['suspendido']): ?><span class="badge text-bg-danger">Servicio suspendido</span><?php endif; ?></div>
      </div>
      <p class="mt-3 mb-2">
        <?php if ($c['estado'] === 'aprobado'): ?><i class="bi bi-check-circle text-success"></i> Pago al día. Tu suscripción termina el <strong><?= e(fdate($c['prox_corte'])) ?></strong>; tendrás hasta el <strong><?= e(fdate($c['prox_limite'])) ?></strong> para enviar el siguiente comprobante.
        <?php elseif ($c['estado'] === 'pendiente'): ?><i class="bi bi-hourglass-split"></i> Comprobante enviado el <?= e(date('d/m H:i', strtotime($p['fecha_envio']))) ?>, en revisión. Debe aprobarse antes del <strong><?= e(fdate($c['limite'])) ?></strong>.
        <?php elseif ($c['estado'] === 'rechazado'): ?><i class="bi bi-x-circle text-danger"></i> <strong>Comprobante rechazado</strong><?= $p['nota_revision'] ? ': ' . e($p['nota_revision']) : '' ?>. Vuelve a enviarlo; el plazo <strong>no se aplaza</strong>: <?= e(fdate($c['limite'])) ?>.
        <?php else: ?><i class="bi bi-upload"></i> Paga <strong><?= money($ciclo['monto']) ?></strong> y sube tu comprobante antes del <strong><?= e(fdate($c['limite'])) ?></strong>.<?php endif; ?>
        <?php if ($c['estado'] !== 'aprobado'): ?><br><?= $c['suspendido'] ? '<strong class="text-danger">El servicio está suspendido. Sube tu comprobante para reactivarlo cuando sea aprobado.</strong>' : '<span class="' . ($c['dias'] <= 3 ? 'text-danger fw-bold' : 'text-muted') . '">Quedan ' . max(0, $c['dias']) . ' día(s) de plazo. Monto: ' . money($ciclo['monto']) . '.</span>'; ?><?php endif; ?></p>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if ($p): ?><a class="btn btn-sm btn-outline-dark" href="<?= url('pages/comprobante.php?id=' . (int)$p['id_pago']) ?>" target="_blank" rel="noopener">Ver comprobante enviado</a><?php endif; ?>
        <?php if ($l['activo'] && in_array($c['estado'], ['sin_envio', 'rechazado'], true)): ?>
          <form method="post" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="comprobante"><input type="hidden" name="id_local" value="<?= (int)$l['id_local'] ?>">
            <input class="form-control form-control-sm" type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required aria-label="Comprobante de <?= e($l['nombre_sucursal']) ?>">
            <button class="btn btn-sm btn-ca btn-ca-primary"><?= $c['estado'] === 'rechazado' ? 'Reenviar' : 'Enviar comprobante' ?></button>
          </form>
        <?php endif; ?>
        <?php if (!$l['activo']): ?><span class="badge text-bg-secondary">Desactivada por soporte</span><?php endif; ?>
      </div>
      <?php if ($l['activo']) component('suscripcion_cambio', ['local' => $l, 'planes' => $planes, 'duraciones' => $duraciones, 'cambio' => cambio_programado((int)$l['id_local'])]); ?>
    </div>
  <?php endforeach; if (!$ls): ?><div class="alert alert-info">Aún no tienes sucursales.</div><?php endif; ?>
  </div>
  <div class="col-lg-4"><div class="panel"><h2>Agregar sucursal</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="crear_local">
      <div class="mb-2"><label class="form-label">Nombre de la sucursal</label><input class="form-control" name="nombre_sucursal" maxlength="100" required></div>
      <div class="mb-2"><label class="form-label">Plan</label><select class="form-select" name="id_plan"><?php foreach ($planes as $p): ?><option value="<?= (int)$p['id_plan'] ?>"><?= e($p['nombre']) ?> — <?= money($p['precio_mensual']) ?>/mes</option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">Duración</label><select class="form-select" name="id_duracion"><?php foreach ($duraciones as $d): ?><option value="<?= (int)$d['id_duracion'] ?>"><?= e(duracion_texto($d)) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-ca btn-ca-primary w-100">Crear sucursal</button>
      <p class="small text-muted mt-2 mb-0">Empieza a operar de inmediato. Tienes 7 días para subir su comprobante de pago.</p>
    </form></div></div>
</div>
<?php component('admin_footer'); ?>
