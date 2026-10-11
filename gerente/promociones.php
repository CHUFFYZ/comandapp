<?php
require_once __DIR__ . '/../includes/tenant.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL'); $pdo = db(); $emp = (int)$user['id_empresa'];
$locales = locales_empresa($user); $ids = array_map(fn($l) => (int)$l['id_local'], $locales);   // el gerente de local solo ve el suyo

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = $_POST['accion'] ?? ''; $idl = (int)($_POST['id_local'] ?? 0);
    if (!in_array($idl, $ids, true)) flash('Local no válido.', 'danger');
    elseif ($a === 'config') {
        $vis = (int)($_POST['visitas_requeridas'] ?? 0); $min = (float)str_replace(',', '.', $_POST['consumo_minimo'] ?? '-1'); $activa = isset($_POST['activa']) ? 1 : 0;
        $n = $pdo->prepare('SELECT COUNT(*) FROM promocion_opciones WHERE id_local = ?'); $n->execute([$idl]); $nOpc = (int)$n->fetchColumn();
        if ($vis < 1 || $vis > 50 || $min < 0 || $min > 100000) flash('Revisa las visitas (1 a 50) y el consumo mínimo.', 'danger');
        elseif ($activa && !$nOpc) flash('Agrega al menos una opción de regalo para activar la promoción.', 'danger');
        else {
            $pdo->prepare('INSERT INTO promociones_local (id_local, activa, visitas_requeridas, consumo_minimo) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE activa=VALUES(activa), visitas_requeridas=VALUES(visitas_requeridas), consumo_minimo=VALUES(consumo_minimo)')
                ->execute([$idl, $activa, $vis, $min]);
            flash('Promoción guardada.');
        }
    } elseif ($a === 'add_opcion') {
        $prod = (int)($_POST['id_producto'] ?? 0); $cant = (int)($_POST['cantidad'] ?? 0);
        $ok = $pdo->prepare('SELECT 1 FROM productos WHERE id_producto=? AND id_empresa=?'); $ok->execute([$prod, $emp]);
        if (!$ok->fetchColumn() || $cant < 1 || $cant > 50) flash('Elige un producto y una cantidad de 1 a 50.', 'danger');
        else {
            $pdo->prepare('INSERT INTO promocion_opciones (id_local, id_producto, cantidad) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad)')->execute([$idl, $prod, $cant]);
            flash('Opción de regalo guardada.');
        }
    } elseif ($a === 'upd_opcion') {
        $cant = (int)($_POST['cantidad'] ?? 0);
        if ($cant < 1 || $cant > 50) flash('La cantidad debe ser de 1 a 50.', 'danger');
        else { $pdo->prepare('UPDATE promocion_opciones SET cantidad=? WHERE id_opcion=? AND id_local=?')->execute([$cant, (int)$_POST['id_opcion'], $idl]); flash('Opción actualizada.'); }
    } elseif ($a === 'del_opcion') {
        $pdo->prepare('DELETE FROM promocion_opciones WHERE id_opcion=? AND id_local=?')->execute([(int)$_POST['id_opcion'], $idl]);
        $n = $pdo->prepare('SELECT COUNT(*) FROM promocion_opciones WHERE id_local=?'); $n->execute([$idl]);
        if (!(int)$n->fetchColumn()) $pdo->prepare('UPDATE promociones_local SET activa=0 WHERE id_local=?')->execute([$idl]);   // sin opciones no puede estar activa
        flash('Opción eliminada.');
    }
    redirect('gerente/promociones.php');
}
$prods = $pdo->prepare('SELECT id_producto, nombre FROM productos WHERE id_empresa=? AND activo=1 ORDER BY nombre'); $prods->execute([$emp]); $prods = $prods->fetchAll();
$promos = []; $opciones = [];
if ($ids) {
    $m = implode(',', array_fill(0, count($ids), '?'));
    $s = $pdo->prepare("SELECT * FROM promociones_local WHERE id_local IN ($m)"); $s->execute($ids);
    foreach ($s->fetchAll() as $r) $promos[(int)$r['id_local']] = $r;
    $s = $pdo->prepare("SELECT o.*, p.nombre FROM promocion_opciones o JOIN productos p ON p.id_producto = o.id_producto WHERE o.id_local IN ($m) ORDER BY p.nombre"); $s->execute($ids);
    foreach ($s->fetchAll() as $r) $opciones[(int)$r['id_local']][] = $r;
}

$titulo = 'Promociones de lealtad';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'promociones.php','badge'=>rol_badge($user['rol']),'menu'=>panel_menu($user['rol'])]);
?>
<p class="text-muted">Una <strong>visita</strong> cuenta cuando el pedido del cliente se termina y su total alcanza el <strong>consumo mínimo</strong>. Al completar las visitas indicadas, el mesero puede ofrecerle <strong>una</strong> de las opciones de regalo en su siguiente pedido. Se configura por local.</p>
<?php foreach ($locales as $l): $idl = (int)$l['id_local']; $p = $promos[$idl] ?? ['activa' => 0, 'visitas_requeridas' => 3, 'consumo_minimo' => 0]; $ops = $opciones[$idl] ?? []; ?>
  <div class="panel mb-3"><h2><?= e($l['nombre_sucursal']) ?></h2>
    <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="accion" value="config"><input type="hidden" name="id_local" value="<?= $idl ?>">
      <div class="col-6 col-md-3"><label class="form-label">Cada N visitas</label><input class="form-control" type="number" min="1" max="50" name="visitas_requeridas" value="<?= (int)$p['visitas_requeridas'] ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label">Consumo mínimo por visita ($)</label><input class="form-control" type="number" step="0.01" min="0" name="consumo_minimo" value="<?= e($p['consumo_minimo']) ?>" required></div>
      <div class="col-6 col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="activa" id="a<?= $idl ?>" <?= $p['activa'] ? 'checked' : '' ?>><label class="form-check-label" for="a<?= $idl ?>">Promoción activa</label></div></div>
      <div class="col-6 col-md-2"><button class="btn btn-ca btn-ca-dark w-100">Guardar</button></div>
    </form>
    <h3 class="h6 mt-4">Opciones de regalo (el cliente elige una)</h3>
    <?php foreach ($ops as $o): ?>
      <form method="post" class="row g-2 align-items-center mb-1"><?= csrf_field() ?><input type="hidden" name="id_local" value="<?= $idl ?>"><input type="hidden" name="id_opcion" value="<?= (int)$o['id_opcion'] ?>">
        <div class="col-md-5"><strong><?= e($o['nombre']) ?></strong></div>
        <div class="col-5 col-md-2"><input class="form-control form-control-sm" type="number" min="1" max="50" name="cantidad" value="<?= (int)$o['cantidad'] ?>" aria-label="Cantidad de <?= e($o['nombre']) ?>"></div>
        <div class="col-7 col-md-5 text-end"><button class="btn btn-sm btn-outline-dark" name="accion" value="upd_opcion">Guardar</button> <button class="btn btn-sm btn-outline-danger" name="accion" value="del_opcion" formnovalidate onclick="return confirm('¿Eliminar esta opción?')">Eliminar</button></div>
      </form>
    <?php endforeach; if (!$ops): ?><p class="text-muted small">Aún no hay opciones. Agrega al menos una para activar la promoción.</p><?php endif; ?>
    <form method="post" class="row g-2 align-items-end mt-2"><?= csrf_field() ?><input type="hidden" name="accion" value="add_opcion"><input type="hidden" name="id_local" value="<?= $idl ?>">
      <div class="col-md-5"><label class="form-label small mb-0">Producto</label><select class="form-select form-select-sm" name="id_producto" required><option value="">— Elige —</option><?php foreach ($prods as $pr): ?><option value="<?= (int)$pr['id_producto'] ?>"><?= e($pr['nombre']) ?></option><?php endforeach; ?></select></div>
      <div class="col-5 col-md-2"><label class="form-label small mb-0">Cantidad</label><input class="form-control form-control-sm" type="number" min="1" max="50" name="cantidad" value="1" required></div>
      <div class="col-7 col-md-3"><button class="btn btn-sm btn-ca btn-ca-primary w-100">Agregar opción</button></div>
    </form>
  </div>
<?php endforeach; if (!$locales): ?><div class="alert alert-warning">No hay locales con servicio activo.</div><?php endif; ?>
<?php component('admin_footer'); ?>
