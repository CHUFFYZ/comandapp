<?php /* Vars: $t (usuario), $op (usuario_opciones), $esYo, $volver */ ?>
<div class="panel" style="max-width:640px">
  <form method="post"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$t['id_usuario'] ?>">
    <p class="text-muted">Empresa: <strong><?= e($t['nombre_comercial'] ?? '—') ?></strong> · Rol actual: <strong><?= e(rol_label($t['nombre_rol'])) ?></strong></p>
    <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= e($t['nombre']) ?>" maxlength="100" required></div>
    <div class="mb-2"><label class="form-label">Correo (usuario de acceso)</label><input class="form-control" type="email" name="email" value="<?= e($t['email']) ?>" maxlength="100" required></div>
    <?php if ($op['roles']): ?>
      <div class="mb-2"><label class="form-label">Rol</label><select class="form-select" name="rol">
        <?php foreach ($op['roles'] as $r): ?><option value="<?= e($r) ?>" <?= $r === $t['nombre_rol'] ? 'selected' : '' ?>><?= e(rol_label($r)) ?></option><?php endforeach; ?></select></div>
    <?php endif; if ($op['locales']): ?>
      <div class="mb-2"><label class="form-label">Local</label><select class="form-select" name="id_local">
        <?php foreach ($op['locales'] as $l): ?><option value="<?= (int)$l['id_local'] ?>" <?= (int)$l['id_local'] === (int)$t['id_local'] ? 'selected' : '' ?>><?= e($l['nombre_sucursal']) ?></option><?php endforeach; ?></select></div>
    <?php endif; if (!$esYo): ?>
      <div class="form-check my-3"><input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $t['activo'] ? 'checked' : '' ?>><label class="form-check-label" for="activo">Usuario activo (puede iniciar sesión)</label></div>
    <?php endif; ?>
    <hr><h3 class="h6">Cambiar contraseña <small class="text-muted">(déjalo vacío para conservar la actual)</small></h3>
    <?php if ($esYo): ?><div class="mb-2"><input class="form-control" type="password" name="password_actual" placeholder="Contraseña actual" autocomplete="current-password"></div><?php endif; ?>
    <div class="mb-3"><input class="form-control" type="password" name="password" placeholder="Nueva contraseña (mín. 8)" minlength="8" autocomplete="new-password"></div>
    <div class="d-flex gap-2"><button class="btn btn-ca btn-ca-primary">Guardar cambios</button><a class="btn btn-ca btn-ca-ghost" href="<?= e($volver) ?>">Volver</a></div>
  </form>
</div>
