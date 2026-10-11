<?php global $MENU_PRINCIPAL; ?>
<header class="site-header sticky-top">
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand brand" href="<?= url() ?>"><i class="bi bi-receipt-cutoff"></i> <?= e(APP_NAME) ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="menuPrincipal">
        <?php component('menu', ['items' => $MENU_PRINCIPAL, 'class' => 'navbar-nav mx-auto']); ?>
        <div class="d-flex gap-2 mt-3 mt-lg-0">
          <?php component('button', ['texto' => 'Iniciar sesión', 'href' => url('pages/login.php'), 'variante' => 'ghost']); ?>
          <?php component('button', ['texto' => 'Probar el sistema', 'href' => url('pages/registro.php'), 'variante' => 'primary']); ?>
        </div>
      </div>
    </div>
  </nav>
</header>
