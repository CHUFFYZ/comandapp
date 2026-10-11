<?php /* Vars: $locales, $local — solo se muestra si hay más de un local */
if (count($locales) > 1): ?>
<form method="get" class="mb-3 d-flex align-items-center gap-2">
  <label class="form-label m-0" for="local">Local:</label>
  <select id="local" name="local" class="form-select w-auto" onchange="this.form.submit()">
    <?php foreach ($locales as $l): ?><option value="<?= (int)$l['id_local'] ?>" <?= $l['id_local'] == $local['id_local'] ? 'selected' : '' ?>><?= e($l['nombre_sucursal']) ?></option><?php endforeach; ?>
  </select>
</form>
<?php elseif ($local): ?><p class="text-muted"><i class="bi bi-shop"></i> <?= e($local['nombre_sucursal']) ?></p><?php endif; ?>
