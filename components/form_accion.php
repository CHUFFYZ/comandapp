<?php /* Botón de acción POST con CSRF. Vars: $accion, $id, $texto, $clase, $confirmar */ ?>
<form method="post" class="d-inline"<?= !empty($confirmar) ? ' onsubmit="return confirm(\'' . e($confirmar) . '\')"' : '' ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="accion" value="<?= e($accion) ?>">
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <button class="btn btn-sm <?= e($clase ?? 'btn-outline-dark') ?>"><?= e($texto) ?></button>
</form>
