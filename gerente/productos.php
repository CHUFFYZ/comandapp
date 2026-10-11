<?php
require_once __DIR__ . '/../includes/tenant.php';
$user = require_role('GERENTE'); $pdo = db(); $emp = $user['id_empresa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = $_POST['accion'] ?? '';
    if ($a === 'categoria') {
        $n = trim($_POST['nombre_categoria'] ?? '');
        if ($n !== '') { $pdo->prepare('INSERT INTO categorias (id_empresa, nombre_categoria) VALUES (?,?)')->execute([$emp, $n]); flash('Categoría creada.'); }
    } elseif ($a === 'renombrar_categoria') {
        $n = trim($_POST['nombre_categoria'] ?? '');
        if ($n === '') flash('El nombre de la categoría no puede estar vacío.', 'danger');
        else { $pdo->prepare('UPDATE categorias SET nombre_categoria=? WHERE id_categoria=? AND id_empresa=?')->execute([$n, (int)$_POST['id'], $emp]); flash('Categoría renombrada.'); }
    } elseif ($a === 'producto' || $a === 'editar') {
        $n = trim($_POST['nombre'] ?? ''); $pr = (float)($_POST['precio_venta'] ?? -1); $cat = (int)($_POST['id_categoria'] ?? 0);
        $ok = $pdo->prepare('SELECT 1 FROM categorias WHERE id_categoria=? AND id_empresa=?'); $ok->execute([$cat, $emp]);
        if ($n === '' || $pr < 0 || !$ok->fetchColumn()) flash('Revisa nombre, precio y categoría.', 'danger');
        elseif ($a === 'producto') { $pdo->prepare('INSERT INTO productos (id_empresa,id_categoria,nombre,precio_venta) VALUES (?,?,?,?)')->execute([$emp,$cat,$n,$pr]); flash('Producto creado. Asigna su stock en Inventario.'); }
        else { $pdo->prepare('UPDATE productos SET nombre=?, precio_venta=?, id_categoria=? WHERE id_producto=? AND id_empresa=?')->execute([$n,$pr,$cat,(int)$_POST['id'],$emp]); flash('Producto actualizado.'); }
    } elseif ($a === 'toggle') {
        $pdo->prepare('UPDATE productos SET activo = NOT activo WHERE id_producto=? AND id_empresa=?')->execute([(int)$_POST['id'], $emp]);
        flash('Disponibilidad actualizada.');
    }
    redirect('gerente/productos.php');
}
$cats = $pdo->prepare('SELECT * FROM categorias WHERE id_empresa=? ORDER BY nombre_categoria'); $cats->execute([$emp]); $cats = $cats->fetchAll();
$prods = $pdo->prepare('SELECT p.*, c.nombre_categoria FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria WHERE p.id_empresa=? ORDER BY c.nombre_categoria, p.nombre');
$prods->execute([$emp]); $prods = $prods->fetchAll();

$titulo = 'Productos y categorías';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'productos.php','badge'=>'Gerente','menu'=>panel_menu('GERENTE')]);
$selCat = function ($sel = 0) use ($cats) { foreach ($cats as $c) echo '<option value="'.(int)$c['id_categoria'].'"'.($c['id_categoria']==$sel?' selected':'').'>'.e($c['nombre_categoria']).'</option>'; };
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="panel mb-4"><h2>Nueva categoría</h2>
      <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="accion" value="categoria">
        <input class="form-control" name="nombre_categoria" placeholder="Ej. Bebidas" maxlength="50" required><button class="btn btn-ca btn-ca-dark">Crear</button></form></div>
    <?php if ($cats): ?><div class="panel mb-4"><h2>Categorías</h2>
      <?php foreach ($cats as $c): ?>
        <form method="post" class="d-flex gap-2 mb-2"><?= csrf_field() ?><input type="hidden" name="accion" value="renombrar_categoria"><input type="hidden" name="id" value="<?= (int)$c['id_categoria'] ?>">
          <input class="form-control form-control-sm" name="nombre_categoria" value="<?= e($c['nombre_categoria']) ?>" maxlength="50" required aria-label="Nombre de la categoría">
          <button class="btn btn-sm btn-outline-dark">Guardar</button></form>
      <?php endforeach; ?></div><?php endif; ?>
    <div class="panel"><h2>Nuevo producto</h2>
      <?php if (!$cats): ?><p class="text-muted mb-0">Crea primero una categoría.</p><?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="producto">
        <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" maxlength="100" required></div>
        <div class="mb-2"><label class="form-label">Categoría</label><select class="form-select" name="id_categoria"><?php $selCat(); ?></select></div>
        <div class="mb-3"><label class="form-label">Precio de venta</label><input class="form-control" type="number" step="0.01" min="0" name="precio_venta" required></div>
        <button class="btn btn-ca btn-ca-primary w-100">Crear producto</button></form><?php endif; ?></div>
  </div>
  <div class="col-lg-8"><div class="panel"><h2>Catálogo (compartido por todos tus locales)</h2>
    <?php foreach ($prods as $p): ?>
      <form method="post" class="row g-2 align-items-center plan-row <?= $p['activo'] ? '' : 'opacity-50' ?>"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id_producto'] ?>"><input type="hidden" name="accion" value="editar">
        <div class="col-md-4"><input class="form-control form-control-sm" name="nombre" value="<?= e($p['nombre']) ?>" required></div>
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" name="id_categoria"><?php $selCat($p['id_categoria']); ?></select></div>
        <div class="col-6 col-md-2"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="precio_venta" value="<?= e($p['precio_venta']) ?>" required></div>
        <div class="col-md-3 text-end text-nowrap">
          <button class="btn btn-sm btn-outline-dark">Guardar</button>
          <button class="btn btn-sm <?= $p['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>" name="accion" value="toggle" formnovalidate><?= $p['activo'] ? 'Ocultar' : 'Mostrar' ?></button>
        </div>
      </form>
    <?php endforeach; if (!$prods): ?><p class="text-muted mb-0">Aún no hay productos.</p><?php endif; ?>
  </div></div>
</div>
<?php component('admin_footer'); ?>
