<?php /* Vars: $rows (de inventario_rows) */ ?>
<div class="panel">
  <form method="post"><?= csrf_field() ?>
    <table class="table align-middle"><thead><tr><th>Producto</th><th>Categoría</th><th style="width:140px">Stock</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['activo'] ? '' : 'text-muted' ?>"><td><?= e($r['nombre']) ?></td><td><?= e($r['nombre_categoria']) ?></td>
        <td><input class="form-control form-control-sm <?= $r['stock'] <= 5 ? 'border-warning' : '' ?>" type="number" min="0" name="stock[<?= (int)$r['id_producto'] ?>]" value="<?= (int)$r['stock'] ?>"></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="3" class="text-muted text-center py-3">Aún no hay productos cargados.</td></tr><?php endif; ?>
    </tbody></table>
    <?php if ($rows): ?><button class="btn btn-ca btn-ca-primary">Guardar inventario</button><?php endif; ?>
  </form>
</div>
