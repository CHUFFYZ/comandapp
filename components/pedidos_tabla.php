<?php /* Vars: $pedidos, $user, $opt ['mesero'=>bool, 'modificar'=>bool, 'cancelar_propio'=>bool, 'vacio'=>string] */
$opt = $opt ?? []; ?>
<div class="panel"><div class="table-responsive"><table class="table align-middle">
  <thead><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Pedido</th><?php if (!empty($opt['mesero'])): ?><th>Mesero</th><?php endif; ?><th>Total</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($pedidos as $p): $propio = (int)$p['id_mesero'] === (int)$user['id']; $enProceso = $p['estado'] === 'en proceso'; ?>
    <tr class="<?= $p['fecha_modificacion'] && $enProceso ? 'fila-mod' : '' ?>">
      <td>#<?= (int)$p['numero_pedido'] ?></td><td><?= e(date('d/m H:i', strtotime($p['fecha_pedido']))) ?></td>
      <td><?= e($p['cli_nombre'] ?? '—') ?></td><td class="small"><?= e($p['resumen'] ?? '') ?></td>
      <?php if (!empty($opt['mesero'])): ?><td><?= $propio ? '<strong>Tú</strong>' : e($p['mesero']) ?></td><?php endif; ?>
      <td><?= money($p['monto_total']) ?></td>
      <td><?= badge_estado($p['estado']) ?><?= badge_pago((bool)$p['pagado'], $p['estado']) ?>
        <?php if ($p['fecha_modificacion']): ?><br><span class="badge text-bg-primary mt-1">Modificado <?= e(date('H:i', strtotime($p['fecha_modificacion']))) ?> · <?= e($p['mod_por'] ?? '') ?></span><?php endif; ?></td>
      <td class="text-end text-nowrap">
        <?php component('boton_ticket', ['id' => $p['id_pedido']]); ?>
        <?php if ($p['estado'] !== 'cancelado') component('boton_pago', ['id' => $p['id_pedido'], 'pagado' => (bool)$p['pagado']]); ?>
        <?php if (!empty($opt['modificar']) && $enProceso): ?><a class="btn btn-sm btn-outline-primary" href="editar.php?id=<?= (int)$p['id_pedido'] ?>">Modificar</a><?php endif; ?>
        <?php if (!empty($opt['cancelar_propio']) && $propio && $enProceso) component('form_accion', ['accion' => 'cancelar', 'id' => $p['id_pedido'], 'texto' => 'Cancelar', 'clase' => 'btn-outline-danger', 'confirmar' => '¿Cancelar este pedido?']); ?>
      </td></tr>
  <?php endforeach; if (!$pedidos): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e($opt['vacio'] ?? 'No hay pedidos para mostrar.') ?></td></tr><?php endif; ?>
  </tbody></table></div></div>
