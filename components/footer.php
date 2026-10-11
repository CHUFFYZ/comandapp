<?php global $MENU_PRINCIPAL; ?>
<footer class="site-footer">
  <div class="container">
    <div class="row gy-4">
      <div class="col-lg-5">
        <a class="brand brand-light" href="<?= url() ?>"><i class="bi bi-receipt-cutoff"></i> <?= e(APP_NAME) ?></a>
        <p class="mt-3"><?= e(APP_TAGLINE) ?>.</p>
      </div>
      <div class="col-6 col-lg-3">
        <h4>Producto</h4>
        <?php component('menu', ['items' => $MENU_PRINCIPAL, 'class' => 'footer-links']); ?>
      </div>
      <div class="col-6 col-lg-4">
        <h4>Cuenta</h4>
        <ul class="footer-links">
          <li><a href="<?= url('pages/login.php') ?>">Iniciar sesión</a></li>
          <li><a href="<?= url('pages/registro.php') ?>">Crear cuenta</a></li>
          <li><a href="mailto:contacto@comandapp.com">contacto@comandapp.com</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Todos los derechos reservados.</span>
      <span><a href="#">Privacidad</a> &nbsp; <a href="#">Términos</a></span>
    </div>
  </div>
</footer>
