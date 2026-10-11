<?php /* Vars: $vista (activos|concluidos) */ ?>
<ul class="nav nav-pills mb-2">
  <?php foreach (['activos' => 'Activos', 'concluidos' => 'Concluidos'] as $k => $t): ?>
    <li class="nav-item"><a class="nav-link <?= $vista === $k ? 'active' : '' ?>" href="<?= e(url_con(['vista' => $k])) ?>"><?= e($t) ?></a></li>
  <?php endforeach; ?>
</ul>
<p class="small text-muted"><?= $vista === 'activos' ? 'Pedidos en proceso o terminados que aún no se pagan.' : 'Pedidos pagados y terminados (y cancelados).' ?></p>
