<?php
// Roles del sistema (tabla roles): GERENTE, MESERO, COCINERO
$roles = [
  ['Gerente',  'Controla ventas, inventario, empleados y sucursales.'],
  ['Mesero',   'Toma pedidos, los envía a cocina y da seguimiento.'],
  ['Cocinero', 'Recibe las comandas y las marca como terminadas.'],
];
?>
<section id="como-funciona" class="section section-dark">
  <div class="container">
    <div class="section-title">
      <h2>Cada persona ve solo lo que necesita</h2>
      <p>Un mismo sistema, tres paneles. Cada rol entra con su usuario y trabaja sin estorbarse.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($roles as $i => [$rol, $desc]): ?>
        <div class="col-md-4">
          <div class="paso">
            <span class="paso-num"><?= $i + 1 ?></span>
            <h3><?= e($rol) ?></h3>
            <p><?= e($desc) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
