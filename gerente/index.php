<?php
require_once __DIR__ . '/../includes/tenant.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL'); $pdo = db();
$locales = locales_empresa($user); $local = local_actual($locales);

if ($local && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = $pdo->prepare("UPDATE pedidos SET estado='cancelado' WHERE id_pedido=? AND id_local=? AND estado='en proceso'");
    $st->execute([(int)$_POST['id'], $local['id_local']]);
    flash($st->rowCount() ? 'Pedido cancelado y stock devuelto.' : 'No se pudo cancelar el pedido.', $st->rowCount() ? 'success' : 'danger');
    redirect('gerente/index.php');
}

$titulo = 'Panel del gerente';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'index.php','badge'=>rol_badge($user['rol']),'menu'=>panel_menu($user['rol'])]);
if (!$local) sin_local_y_salir();
component('local_selector', ['locales' => $locales, 'local' => $local]);
$L = $local['id_local'];

$q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
$hoy = $q("SELECT COALESCE(SUM(CASE WHEN estado='terminado' THEN monto_total END),0) ventas,
                  SUM(estado='terminado') term, SUM(estado='en proceso') proc, SUM(estado='cancelado') canc
           FROM pedidos WHERE id_local=? AND DATE(fecha_pedido)=CURDATE()", [$L])->fetch();
$bajo = (int)$q("SELECT COUNT(*) FROM productos p LEFT JOIN inventario_local il ON il.id_producto=p.id_producto AND il.id_local=?
                 WHERE p.id_empresa=? AND p.activo=1 AND COALESCE(il.stock_actual,0)<=5", [$L, $user['id_empresa']])->fetchColumn();
$top = $q("SELECT pr.nombre, SUM(dp.cantidad) total FROM detalle_pedidos dp
           JOIN pedidos p ON p.id_pedido=dp.id_pedido JOIN productos pr ON pr.id_producto=dp.id_producto
           WHERE p.id_local=? AND p.estado='terminado' AND DATE(p.fecha_pedido)=CURDATE()-INTERVAL 1 DAY
           GROUP BY pr.id_producto, pr.nombre ORDER BY total DESC LIMIT 8", [$L])->fetchAll();
$meseros = $q("SELECT u.nombre, COUNT(p.id_pedido) pedidos, SUM(p.monto_total) total FROM pedidos p
               JOIN usuarios u ON u.id_usuario=p.id_mesero
               WHERE p.id_local=? AND p.estado='terminado' AND p.fecha_pedido >= CURDATE()-INTERVAL 30 DAY
               GROUP BY u.id_usuario, u.nombre ORDER BY total DESC", [$L])->fetchAll();
$activos = $q("SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.monto_total, u.nombre mesero FROM pedidos p
               JOIN usuarios u ON u.id_usuario=p.id_mesero WHERE p.id_local=? AND p.estado='en proceso'
               ORDER BY p.fecha_pedido", [$L])->fetchAll();
$stats = [['Ventas de hoy', money($hoy['ventas']), 'bi-cash-coin'], ['Pedidos terminados', (int)$hoy['term'], 'bi-check2-circle'],
          ['En proceso', (int)$hoy['proc'], 'bi-hourglass-split'], ['Stock bajo (≤5)', $bajo, 'bi-box-seam']];
?>
<div class="row g-3 mb-4">
  <?php foreach ($stats as [$l, $v, $i]): ?><div class="col-6 col-lg-3"><div class="stat"><i class="bi <?= $i ?>"></i><strong><?= e($v) ?></strong><span><?= e($l) ?></span></div></div><?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-6"><div class="panel"><h2>Más vendidos ayer</h2>
    <table class="table table-sm"><tbody>
      <?php foreach ($top as $t): ?><tr><td><?= e($t['nombre']) ?></td><td class="text-end"><strong><?= (int)$t['total'] ?></strong></td></tr>
      <?php endforeach; if (!$top): ?><tr><td class="text-muted">Sin ventas terminadas ayer.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="col-lg-6"><div class="panel"><h2>Ventas por mesero (30 días)</h2>
    <table class="table table-sm"><thead><tr><th>Mesero</th><th>Pedidos</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($meseros as $m): ?><tr><td><?= e($m['nombre']) ?></td><td><?= (int)$m['pedidos'] ?></td><td class="text-end"><?= money($m['total']) ?></td></tr>
      <?php endforeach; if (!$meseros): ?><tr><td colspan="3" class="text-muted">Sin datos todavía.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="col-12"><div class="panel"><h2>Pedidos en proceso</h2>
    <table class="table align-middle"><thead><tr><th>#</th><th>Hora</th><th>Mesero</th><th>Monto</th><th></th></tr></thead><tbody>
      <?php foreach ($activos as $a): ?><tr><td>#<?= (int)$a['numero_pedido'] ?></td><td><?= e(date('H:i', strtotime($a['fecha_pedido']))) ?></td>
        <td><?= e($a['mesero']) ?></td><td><?= money($a['monto_total']) ?></td>
        <td class="text-end text-nowrap"><?php component('boton_ticket', ['id' => $a['id_pedido']]); ?> <?php component('form_accion', ['accion'=>'cancelar','id'=>$a['id_pedido'],'texto'=>'Cancelar','clase'=>'btn-outline-danger','confirmar'=>'¿Cancelar este pedido? El stock se devolverá.']); ?></td></tr>
      <?php endforeach; if (!$activos): ?><tr><td colspan="5" class="text-muted text-center py-3">No hay pedidos en proceso.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php component('admin_footer'); ?>
