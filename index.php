<?php
require_once __DIR__ . '/includes/functions.php';

component('head', [
    'titulo'      => APP_NAME . ' | Sistema de pedidos para restaurantes con varios locales',
    'descripcion' => APP_TAGLINE,
]);
component('header');
?>
<main>
  <?php
    section('hero');
    section('features');
    section('roles');
    section('pricing');
    section('faq');
    section('cta');
  ?>
</main>
<?php
component('footer');
component('scripts');
