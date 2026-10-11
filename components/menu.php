<?php /* Vars: $items [label, href], $class, $activo (href activo) */ ?>
<ul class="<?= e($class ?? 'nav') ?>">
  <?php foreach ($items as $it): ?>
    <li class="nav-item"><a class="nav-link <?= (($activo ?? null) === $it['href']) ? 'active' : '' ?>" href="<?= e($it['href']) ?>"><?= e($it['label']) ?></a></li>
  <?php endforeach; ?>
</ul>
