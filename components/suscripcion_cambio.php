<?php /* Vars: $local (fila con 'cobro'), $planes, $duraciones, $cambio (cambio programado o null) */
$c = $local['cobro']; $ciclo = $c['ciclo']; ?>
<div class="mt-3 pt-3 border-top">
  <h3 class="h6">Suscripción</h3>
  <p class="small mb-2">Actual: <strong><?= e($ciclo['plan_nombre']) ?></strong> · <?= e($ciclo['dur_nombre']) ?> (<?= (int)$ciclo['meses'] ?> <?= (int)$ciclo['meses'] === 1 ? 'mes' : 'meses' ?>) · <?= money($ciclo['monto']) ?>.
    Termina el <strong><?= e(fdate($c['prox_corte'])) ?></strong>.</p>
  <?php if ($cambio): ?>
    <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <span><i class="bi bi-calendar-check"></i> Programado: <strong><?= e($cambio['plan_nombre']) ?></strong> · <?= e($cambio['dur_nombre']) ?>. Se activará el <strong><?= e(fdate($c['prox_corte'])) ?></strong>, al terminar la suscripción actual.</span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="cancelar_cambio"><input type="hidden" name="id_local" value="<?= (int)$local['id_local'] ?>">
        <button class="btn btn-sm btn-outline-danger">Cancelar cambio</button></form>
    </div>
  <?php endif; ?>
  <details><summary class="small fw-bold" style="cursor:pointer"><?= $cambio ? 'Reemplazar el cambio programado' : 'Cambiar plan o duración' ?></summary>
    <form method="post" class="row g-2 align-items-end mt-1"><?= csrf_field() ?><input type="hidden" name="accion" value="programar_cambio"><input type="hidden" name="id_local" value="<?= (int)$local['id_local'] ?>">
      <div class="col-md-4"><label class="form-label small mb-0">Plan</label><select class="form-select form-select-sm" name="id_plan">
        <?php foreach ($planes as $p): ?><option value="<?= (int)$p['id_plan'] ?>" <?= (int)$p['id_plan'] === (int)$ciclo['id_plan'] ? 'selected' : '' ?>><?= e($p['nombre']) ?> — <?= money($p['precio_mensual']) ?>/mes</option><?php endforeach; ?></select></div>
      <div class="col-md-5"><label class="form-label small mb-0">Duración</label><select class="form-select form-select-sm" name="id_duracion">
        <?php foreach ($duraciones as $d): ?><option value="<?= (int)$d['id_duracion'] ?>" <?= (int)$d['id_duracion'] === (int)$ciclo['id_duracion'] ? 'selected' : '' ?>><?= e(duracion_texto($d)) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><button class="btn btn-sm btn-ca btn-ca-dark w-100">Programar cambio</button></div>
    </form>
    <p class="small text-muted mt-2 mb-1">El cambio se aplica cuando termina la suscripción en curso; mientras tanto puedes cancelarlo y todo sigue igual. Precio total por periodo:</p>
    <div class="table-responsive"><table class="table table-sm small mb-0">
      <thead><tr><th>Plan</th><?php foreach ($duraciones as $d): ?><th><?= e($d['nombre']) ?><?= (int)$d['meses_gratis'] ? ' <span class="text-success">(+' . (int)$d['meses_gratis'] . ' gratis)</span>' : '' ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($planes as $p): ?><tr><td><?= e($p['nombre']) ?></td><?php foreach ($duraciones as $d): ?><td><?= money(precio_ciclo($p, $d)) ?> <span class="text-muted">· <?= meses_ciclo($d) ?> m</span></td><?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table></div>
  </details>
</div>
