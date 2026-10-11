<?php /* Vars: $texto, $href, $variante (primary|dark|ghost|outline), $icono, $extra */
$variante = $variante ?? 'primary'; ?>
<a href="<?= e($href ?? '#') ?>" class="btn btn-ca btn-ca-<?= e($variante) ?> <?= e($extra ?? '') ?>">
  <?= e($texto) ?><?php if (!empty($icono)): ?> <i class="bi bi-<?= e($icono) ?>"></i><?php endif; ?>
</a>
