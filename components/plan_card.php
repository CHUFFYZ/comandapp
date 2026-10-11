<?php /* Vars: $plan (fila de BD), $destacado (bool) */ ?>
<div class="plan <?= !empty($destacado) ? 'plan-destacado' : '' ?>">
  <?php if (!empty($destacado)): ?><span class="plan-badge">Más elegido</span><?php endif; ?>
  <h3><?= e($plan['nombre']) ?></h3>
  <p class="plan-precio"><?= money($plan['precio_mensual']) ?><small> / mes por local</small></p>
  <ul class="plan-lista">
    <li><i class="bi bi-check2"></i> Hasta <strong><?= (int)$plan['limite_empleados'] ?> empleados</strong> por local</li>
    <li><i class="bi bi-check2"></i> Empleado extra: <strong><?= money($plan['precio_empleado_extra']) ?></strong> / mes</li>
    <li><i class="bi bi-check2"></i> Panel de cocina y meseros</li>
    <li><i class="bi bi-check2"></i> Inventario por sucursal</li>
    <li><i class="bi bi-check2"></i> Reportes de ventas</li>
  </ul>
  <?php component('button', [
      'texto' => 'Elegir ' . $plan['nombre'],
      'href'  => url('pages/registro.php?plan=' . (int)$plan['id_plan']),
      'variante' => !empty($destacado) ? 'primary' : 'outline',
      'extra' => 'w-100'
  ]); ?>
</div>
