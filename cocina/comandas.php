<?php
// Endpoint de la cocina: GET devuelve el HTML de las comandas; POST marca una como terminada.
require_once __DIR__ . '/../includes/tenant.php';
$user = require_role('COCINERO'); $pdo = db();
if (!$user['id_local']) { http_response_code(409); exit('<div class="alert alert-warning">No tienes un local asignado.</div>'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); header('Content-Type: application/json');
    $st = $pdo->prepare("UPDATE pedidos SET estado='terminado' WHERE id_pedido=? AND id_local=? AND estado='en proceso'");
    $st->execute([(int)$_POST['id'], $user['id_local']]);
    echo json_encode(['ok' => $st->rowCount() > 0]); exit;
}
$st = $pdo->prepare("SELECT p.id_pedido, p.numero_pedido, p.pagado, TIMESTAMPDIFF(MINUTE, p.fecha_pedido, NOW()) AS min, p.fecha_modificacion, um.nombre AS mod_por,
        pr.nombre, dp.cantidad, dp.es_regalo
    FROM pedidos p JOIN detalle_pedidos dp ON dp.id_pedido=p.id_pedido JOIN productos pr ON pr.id_producto=dp.id_producto
    LEFT JOIN usuarios um ON um.id_usuario=p.id_mesero_mod
    WHERE p.id_local=? AND p.estado='en proceso' ORDER BY p.fecha_pedido ASC, dp.id_detalle ASC");
$st->execute([$user['id_local']]);
$ped = [];
foreach ($st->fetchAll() as $r) {
    $ped[$r['id_pedido']]['min'] = (int)$r['min'];
    $ped[$r['id_pedido']]['num'] = (int)$r['numero_pedido'];
    $ped[$r['id_pedido']]['pag'] = (bool)$r['pagado'];
    $ped[$r['id_pedido']]['mod'] = $r['fecha_modificacion'] ? ['hora' => date('H:i', strtotime($r['fecha_modificacion'])), 'por' => $r['mod_por']] : null;
    $ped[$r['id_pedido']]['items'][] = $r;
}
if (!$ped) { echo '<div class="alert alert-light border text-center py-5 mb-0">No hay comandas pendientes. 🍽️</div>'; exit; }
echo '<div class="row g-3">';
foreach ($ped as $id => $c): ?>
  <div class="col-md-6 col-xl-4"><div class="comanda <?= $c['mod'] ? 'comanda-mod' : '' ?> <?= $c['min'] >= 15 ? 'comanda-tarde' : '' ?>">
    <?php if ($c['mod']): ?><div class="aviso-mod"><i class="bi bi-pencil-square"></i> PEDIDO MODIFICADO · <?= e($c['mod']['hora']) ?><?= $c['mod']['por'] ? ' · ' . e($c['mod']['por']) : '' ?></div><?php endif; ?>
    <div class="comanda-head"><strong>Pedido #<?= (int)$c['num'] ?></strong><?= $c['pag'] ? ' <span class="badge text-bg-success">Pagado</span>' : '' ?><span><?= $c['min'] ?> min</span></div>
    <ul><?php foreach ($c['items'] as $i): ?><li><b><?= (int)$i['cantidad'] ?>×</b> <?= e($i['nombre']) ?><?= $i['es_regalo'] ? ' 🎁 <small>(regalo)</small>' : '' ?></li><?php endforeach; ?></ul>
    <button class="btn btn-ca btn-ca-dark w-100 js-terminar" data-id="<?= (int)$id ?>">Marcar como terminado</button>
    <div class="mt-2 text-center"><?php component('boton_ticket', ['id' => $id]); ?></div>
  </div></div>
<?php endforeach; echo '</div>';
