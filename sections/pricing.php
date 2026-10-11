<?php $planes = get_planes(); ?>
<section id="precios" class="section">
  <div class="container">
    <div class="section-title">
      <h2>Precios simples, por local</h2>
      <p>Elige el plan según el tamaño del equipo de cada sucursal. Si crece, pagas solo por cada empleado extra.</p>
    </div>
    <div class="row g-4 justify-content-center">
      <?php foreach ($planes as $i => $plan): ?>
        <div class="col-md-6 col-lg-4">
          <?php component('plan_card', ['plan' => $plan, 'destacado' => ($i === 1)]); ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-center small-note">Precios en <?= e(CURRENCY) ?>. Cada local tiene su propio plan y fecha de vencimiento.</p>
  </div>
</section>
