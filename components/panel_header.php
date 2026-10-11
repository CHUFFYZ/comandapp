<?php /* Vars: $user, $titulo, $activo, $menu, $badge */
require_once __DIR__ . '/../includes/notificaciones.php';
generar_notificaciones($user);
$notif = notif_resumen((int)$user['id']);
$flash = flash(); ?>
<header class="admin-nav">
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-xl">
      <a class="navbar-brand brand brand-light" href="<?= url(home_for($user['rol'])) ?>"><i class="bi bi-receipt-cutoff"></i> <?= e(APP_NAME) ?> <small class="badge text-bg-warning ms-1"><?= e($badge ?? '') ?></small></a>
      <?php component('notif_campana', ['notif' => $notif]); ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPanel" aria-label="Abrir menú"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="menuPanel">
        <?php component('menu', ['items' => $menu, 'class' => 'navbar-nav me-auto', 'activo' => $activo ?? '']); ?>
        <form method="post" action="<?= url('pages/logout.php') ?>" class="d-flex align-items-center gap-3 text-white-50">
          <?= csrf_field() ?><span><?= e($user['nombre']) ?></span>
          <button class="btn btn-sm btn-outline-light">Cerrar sesión</button>
        </form>
      </div>
    </div>
  </nav>
</header>
<main class="container-xl admin-main">
  <h1 class="admin-title"><?= e($titulo) ?></h1>
  <?php if ($flash): ?><div class="alert alert-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
