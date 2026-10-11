<?php /* Vars: $id (id_pedido), $pagado. Marca/desmarca el pago del pedido (independiente de su estado). */ ?>
<form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php if ($pagado): ?><button class="btn btn-sm btn-success" name="accion" value="despagar" title="Quitar la marca de pagado"><i class="bi bi-cash-coin"></i> Pagado</button>
  <?php else: ?><button class="btn btn-sm btn-outline-success" name="accion" value="pagar"><i class="bi bi-cash-coin"></i> Marcar pagado</button><?php endif; ?>
</form>
