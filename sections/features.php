<?php
$funciones = [
  ['bi-shop',          'Varios locales, una sola cuenta',  'Administra todas tus sucursales con inventario y equipo independientes por local.'],
  ['bi-egg-fried',     'Panel de cocina en vivo',          'Los cocineros ven las comandas pendientes ordenadas por hora de llegada.'],
  ['bi-phone',         'Pedidos desde el celular',         'Los meseros levantan pedidos en segundos y consultan su historial.'],
  ['bi-box-seam',      'Inventario que se descuenta solo', 'Cada producto vendido baja del stock; si cancelas, el stock regresa.'],
  ['bi-graph-up-arrow','Reportes para el gerente',         'Productos más vendidos, ventas por mesero y cierres por sucursal.'],
  ['bi-people',        'Clientes y descuentos',            'Registra clientes frecuentes por teléfono o correo para tus promociones.'],
];
?>
<section id="funciones" class="section">
  <div class="container">
    <div class="section-title">
      <h2>Todo lo que tu operación necesita</h2>
      <p>Pensado para restaurantes, cafeterías y cadenas que quieren dejar de operar con libretas y mensajes sueltos.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($funciones as [$icono, $titulo, $texto]): ?>
        <div class="col-md-6 col-lg-4">
          <div class="feature">
            <i class="bi <?= e($icono) ?>"></i>
            <h3><?= e($titulo) ?></h3>
            <p><?= e($texto) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
