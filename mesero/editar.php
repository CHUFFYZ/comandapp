<?php
require_once __DIR__ . '/../includes/pedidos.php';
$user = require_role('MESERO'); $pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Cualquier mesero del mismo local puede modificar el pedido mientras esté en proceso
$st = $pdo->prepare("SELECT p.*, u.nombre AS mesero, c.nombre AS cli_nombre, c.telefono AS cli_tel
    FROM pedidos p JOIN usuarios u ON u.id_usuario = p.id_mesero LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
    WHERE p.id_pedido = ? AND p.id_local = ?");
$st->execute([$id, (int)$user['id_local']]); $ped = $st->fetch();
if (!$ped) { flash('Pedido no encontrado.', 'danger'); redirect('mesero/pedidos.php'); }
$num = (int)$ped['numero_pedido'];
if ($ped['estado'] !== 'en proceso') { flash("El pedido #$num ya está {$ped['estado']} y no se puede modificar.", 'warning'); redirect('mesero/pedidos.php'); }

// Opciones de regalo vigentes: si el pedido ya lleva regalo o el cliente tiene uno disponible
$lealtad = $ped['id_cliente'] ? lealtad_cliente($pdo, (int)$user['id_local'], (int)$ped['id_cliente']) : null;
$opciones = ($lealtad && $lealtad['promo'] && ($ped['promocion_aplicada'] || $lealtad['regalo'])) ? $lealtad['promo']['opciones'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT estado FROM pedidos WHERE id_pedido=? FOR UPDATE'); $s->execute([$id]);
        if ($s->fetchColumn() !== 'en proceso') throw new RuntimeException('El pedido ya fue terminado o cancelado: no se puede modificar.');

        $s = $pdo->prepare('SELECT id_producto, cantidad FROM detalle_pedidos WHERE id_pedido=? AND es_regalo=0'); $s->execute([$id]);
        $old = []; foreach ($s->fetchAll() as $r) $old[(int)$r['id_producto']] = (int)$r['cantidad'];
        $new = cantidades_post();
        if (!$new) throw new RuntimeException('El pedido debe tener al menos un producto. Si ya no se necesita, cancélalo.');

        $cambio = false;
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $idp) {
            $o = $old[$idp] ?? 0; $n = $new[$idp] ?? 0;
            if ($o === $n) continue;
            $cambio = true;
            if ($n > $o) {
                $s = $pdo->prepare('SELECT stock_actual FROM inventario_local WHERE id_local=? AND id_producto=? FOR UPDATE'); $s->execute([$user['id_local'], $idp]);
                if ((int)$s->fetchColumn() < $n - $o) throw new RuntimeException('No hay stock suficiente para agregar esa cantidad.');
            }
            // Los triggers ajustan stock y total en INSERT, UPDATE y DELETE de detalle_pedidos
            if ($o === 0) {
                $s = $pdo->prepare('SELECT precio_venta FROM productos WHERE id_producto=? AND id_empresa=? AND activo=1'); $s->execute([$idp, $user['id_empresa']]);
                $precio = $s->fetchColumn();
                if ($precio === false) throw new RuntimeException('Hay un producto no disponible.');
                $pdo->prepare('INSERT INTO detalle_pedidos (id_pedido,id_producto,cantidad,precio_unitario_historico) VALUES (?,?,?,?)')->execute([$id, $idp, $n, $precio]);
            } elseif ($n === 0) {
                $pdo->prepare('DELETE FROM detalle_pedidos WHERE id_pedido=? AND id_producto=? AND es_regalo=0')->execute([$id, $idp]);
            } else {
                $pdo->prepare('UPDATE detalle_pedidos SET cantidad=? WHERE id_pedido=? AND id_producto=? AND es_regalo=0')->execute([$n, $id, $idp]);
            }
        }
        $idCli = $ped['promocion_aplicada'] ? ($ped['id_cliente'] !== null ? (int)$ped['id_cliente'] : null) : cliente_desde_post($pdo, (int)$user['id_empresa']);
        if ($idCli !== ($ped['id_cliente'] !== null ? (int)$ped['id_cliente'] : null)) $cambio = true;

        // Cambiar, quitar o (si el cliente ya lo ganó) agregar el regalo de lealtad
        $promoFlag = (int)$ped['promocion_aplicada']; $opc = $_POST['regalo_opcion'] ?? 'keep';
        if ($opciones && $opc !== 'keep') {
            $idOpc = (int)$opc;
            $s = $pdo->prepare('SELECT id_producto, cantidad FROM detalle_pedidos WHERE id_pedido=? AND es_regalo=1 LIMIT 1'); $s->execute([$id]); $cur = $s->fetch();
            if ($idOpc === 0) {
                if ($cur) { $pdo->prepare('DELETE FROM detalle_pedidos WHERE id_pedido=? AND es_regalo=1')->execute([$id]); $promoFlag = 0; $cambio = true; }
            } else {
                $o = null; foreach ($opciones as $x) if ((int)$x['id_opcion'] === $idOpc) $o = $x;
                if (!$o) throw new RuntimeException('Esa opción de regalo no está disponible.');
                if (!$cur || (int)$cur['id_producto'] !== (int)$o['id_producto'] || (int)$cur['cantidad'] !== (int)$o['cantidad']) {
                    $pdo->prepare('DELETE FROM detalle_pedidos WHERE id_pedido=? AND es_regalo=1')->execute([$id]);   // el trigger devuelve su stock
                    $s = $pdo->prepare('SELECT stock_actual FROM inventario_local WHERE id_local=? AND id_producto=? FOR UPDATE'); $s->execute([$user['id_local'], $o['id_producto']]);
                    if ((int)$s->fetchColumn() < (int)$o['cantidad']) throw new RuntimeException('No hay stock suficiente para ese regalo.');
                    $pdo->prepare('INSERT INTO detalle_pedidos (id_pedido,id_producto,cantidad,precio_unitario_historico,es_regalo) VALUES (?,?,?,0,1)')->execute([$id, $o['id_producto'], $o['cantidad']]);
                    $promoFlag = 1; $cambio = true;
                }
            }
        }

        if (!$cambio) { $pdo->rollBack(); flash('No hiciste ningún cambio.', 'info'); redirect('mesero/pedidos.php'); }
        $pdo->prepare('UPDATE pedidos SET id_cliente=?, promocion_aplicada=?, fecha_modificacion=NOW(), id_mesero_mod=? WHERE id_pedido=?')->execute([$idCli, $promoFlag, $user['id'], $id]);
        $pdo->commit();
        flash("Pedido #$num modificado. Cocina lo verá marcado como modificado.");
        redirect('mesero/pedidos.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'No se pudo modificar el pedido.', 'danger');
        redirect('mesero/editar.php?id=' . $id);
    }
}

$rg = $pdo->prepare('SELECT d.id_producto, d.cantidad, pr.nombre FROM detalle_pedidos d JOIN productos pr ON pr.id_producto = d.id_producto WHERE d.id_pedido=? AND d.es_regalo=1');
$rg->execute([$id]); $regalos = $rg->fetchAll();
$regaloActual = '0';
if ($regalos) {
    $regaloActual = 'keep';
    foreach ($opciones as $o) if ((int)$o['id_producto'] === (int)$regalos[0]['id_producto'] && (int)$o['cantidad'] === (int)$regalos[0]['cantidad']) $regaloActual = (string)$o['id_opcion'];
}
$titulo = 'Modificar pedido #' . $num;
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'pedidos.php','badge'=>'Mesero','menu'=>panel_menu('MESERO')]);
?>
<p class="text-muted">Creado por <strong><?= e($ped['mesero']) ?></strong> el <?= e(date('d/m H:i', strtotime($ped['fecha_pedido']))) ?>. Al guardar, la cocina verá el pedido marcado como modificado.</p>
<?php
component('pedido_form', ['porCat' => productos_menu($user['id_local'], $user['id_empresa'], $id), 'accion' => 'editar', 'idPedido' => $id,
    'cli' => ['id' => $ped['id_cliente'], 'tel' => $ped['cli_tel'] ?? '', 'nombre' => $ped['cli_nombre'] ?? ''],
    'bloqCli' => (bool)$ped['promocion_aplicada'], 'regalos' => $regalos, 'opcionesRegalo' => $opciones, 'regaloActual' => $regaloActual]);
component('admin_footer');
