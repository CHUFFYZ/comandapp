<?php
require_once __DIR__ . '/../includes/pedidos.php';
$user = require_role('MESERO'); $pdo = db();

if ($user['id_local'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $qty = cantidades_post();
    if (!$qty) { flash('Agrega al menos un producto.', 'danger'); redirect('mesero/index.php'); }
    try {
        $pdo->beginTransaction(); $items = [];
        foreach ($qty as $idp => $q) {
            $s = $pdo->prepare('SELECT precio_venta FROM productos WHERE id_producto=? AND id_empresa=? AND activo=1'); $s->execute([$idp, $user['id_empresa']]);
            $precio = $s->fetchColumn();
            if ($precio === false) throw new RuntimeException('Hay un producto no disponible.');
            $s = $pdo->prepare('SELECT stock_actual FROM inventario_local WHERE id_local=? AND id_producto=? FOR UPDATE'); $s->execute([$user['id_local'], $idp]);
            if ((int)$s->fetchColumn() < $q) throw new RuntimeException('No hay stock suficiente de uno de los productos.');
            $items[] = [$idp, $q, $precio];
        }
        $idCli = cliente_desde_post($pdo, (int)$user['id_empresa']);
        $regalo = null; $idOpc = (int)($_POST['regalo_opcion'] ?? 0);
        if ($idOpc > 0) {   // el mesero eligió una de las opciones de regalo del cliente
            if (!$idCli) throw new RuntimeException('Captura al cliente para aplicarle el regalo.');
            $l = lealtad_cliente($pdo, (int)$user['id_local'], $idCli);
            if (!$l['regalo']) throw new RuntimeException('Este cliente no tiene un regalo disponible.');
            foreach ($l['promo']['opciones'] as $o) if ((int)$o['id_opcion'] === $idOpc) $regalo = $o;
            if (!$regalo) throw new RuntimeException('Esa opción de regalo no está disponible.');
            $s = $pdo->prepare('SELECT stock_actual FROM inventario_local WHERE id_local=? AND id_producto=? FOR UPDATE'); $s->execute([$user['id_local'], $regalo['id_producto']]);
            if ((int)$s->fetchColumn() < (int)$regalo['cantidad'] + (int)($qty[(int)$regalo['id_producto']] ?? 0)) throw new RuntimeException('No hay stock suficiente para el regalo.');
        }
        // Consecutivo por local, atómico y dentro de la transacción (si falla, no se pierde el número)
        $pdo->prepare('UPDATE locales SET ultimo_pedido = LAST_INSERT_ID(ultimo_pedido + 1) WHERE id_local=?')->execute([$user['id_local']]);
        $num = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
        $pdo->prepare('INSERT INTO pedidos (id_local,id_mesero,id_cliente,numero_pedido,promocion_aplicada) VALUES (?,?,?,?,?)')->execute([$user['id_local'], $user['id'], $idCli, $num, $regalo ? 1 : 0]);
        $idPedido = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO detalle_pedidos (id_pedido,id_producto,cantidad,precio_unitario_historico) VALUES (?,?,?,?)');
        foreach ($items as [$idp, $q, $precio]) $ins->execute([$idPedido, $idp, $q, $precio]);   // los triggers descuentan stock y calculan el total
        if ($regalo) $pdo->prepare('INSERT INTO detalle_pedidos (id_pedido,id_producto,cantidad,precio_unitario_historico,es_regalo) VALUES (?,?,?,0,1)')->execute([$idPedido, $regalo['id_producto'], $regalo['cantidad']]);
        $pdo->commit();
        flash("Pedido #$num enviado a cocina." . ($regalo ? ' Regalo aplicado.' : ''));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'No se pudo registrar el pedido.', 'danger');
    }
    redirect('mesero/index.php');
}

$titulo = 'Nuevo pedido';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'index.php','badge'=>'Mesero','menu'=>panel_menu('MESERO')]);
if (!$user['id_local']) sin_local_y_salir();
component('pedido_form', ['porCat' => productos_menu($user['id_local'], $user['id_empresa']), 'accion' => 'crear', 'idPedido' => 0, 'cli' => []]);
component('admin_footer');
