<?php
$faqs = [
  ['¿Puedo manejar varias sucursales?', 'Sí. Cada sucursal tiene su inventario, su equipo y su plan. Como gerente general puedes ver todas desde una sola cuenta.'],
  ['¿Qué pasa si tengo más empleados que los incluidos en mi plan?', 'Se cobra un precio mensual por cada empleado extra, según el plan de ese local. No tienes que cambiar de plan.'],
  ['¿El inventario se actualiza solo?', 'Sí. Al agregar un producto a un pedido se descuenta del stock del local, y si el pedido se cancela el stock se devuelve.'],
  ['¿Necesito instalar algo?', 'No. Funciona desde el navegador en computadora, tablet o celular.'],
  ['¿Qué pasa cuando vence mi plan?', 'Cada local tiene una fecha de vencimiento. Antes de esa fecha podrás renovar desde tu panel.'],
  ['¿Puedo registrar a mis clientes frecuentes?', 'Sí. Guarda nombre, teléfono o correo para ubicarlos rápido y aplicar tu programa de descuentos.'],
];
?>
<section id="faq" class="section section-soft">
  <div class="container faq-wrap">
    <div class="section-title"><h2>Preguntas frecuentes</h2></div>
    <div class="accordion" id="faqAcordeon">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>"><?= e($q) ?></button>
          </h3>
          <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqAcordeon">
            <div class="accordion-body"><?= e($a) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
