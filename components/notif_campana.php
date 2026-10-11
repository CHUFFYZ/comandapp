<?php /* Vars: $notif ['no_leidas','ultimas'] — campana con desplegable de notificaciones */
$n = (int)$notif['no_leidas']; ?>
<div class="dropdown ms-auto ms-lg-2 me-2 me-lg-0 order-lg-last">
  <button class="btn btn-sm btn-outline-light position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
          aria-label="Notificaciones<?= $n ? ' (' . $n . ' sin leer)' : '' ?>">
    <i class="bi bi-bell<?= $n ? '-fill' : '' ?>"></i>
    <?php if ($n): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= $n > 99 ? '99+' : $n ?></span><?php endif; ?>
  </button>
  <div class="dropdown-menu dropdown-menu-end notif-menu p-0">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"><strong>Notificaciones</strong><a class="small" href="<?= url('pages/notificaciones.php') ?>">Ver todas</a></div>
    <?php foreach ($notif['ultimas'] as $x): ?>
      <a class="dropdown-item notif-item <?= $x['leida'] ? '' : 'notif-no-leida' ?>" href="<?= url('pages/notificaciones.php?ir=' . (int)$x['id_notificacion']) ?>">
        <div class="d-flex gap-2"><i class="bi <?= e(notif_icono($x['tipo'])) ?>"></i>
          <div><div class="fw-bold small"><?= e($x['titulo']) ?></div><div class="small text-muted notif-msg"><?= e($x['mensaje']) ?></div><div class="small text-muted"><?= e(notif_tiempo($x['fecha'])) ?></div></div></div>
      </a>
    <?php endforeach; if (!$notif['ultimas']): ?><div class="px-3 py-4 text-center text-muted small">No tienes notificaciones.</div><?php endif; ?>
  </div>
</div>
