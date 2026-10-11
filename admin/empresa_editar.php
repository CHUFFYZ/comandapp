<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('SUPER_ADMIN'); $pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM empresas WHERE id_empresa=?'); $st->execute([$id]); $emp = $st->fetch();
if (!$emp) { flash('Empresa no encontrada.', 'danger'); redirect('admin/empresas.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $n = trim($_POST['nombre_comercial'] ?? ''); $r = trim($_POST['razon_social'] ?? '');
    if ($n === '') { flash('El nombre comercial es obligatorio.', 'danger'); redirect('admin/empresa_editar.php?id=' . $id); }
    $pdo->prepare('UPDATE empresas SET nombre_comercial=?, razon_social=? WHERE id_empresa=?')->execute([$n, $r ?: null, $id]);
    flash('Datos de la empresa actualizados.'); redirect('admin/empresas.php');
}
$titulo = 'Editar empresa';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'empresas.php']);
?>
<div class="panel" style="max-width:640px">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$emp['id_empresa'] ?>">
    <div class="mb-2"><label class="form-label">Nombre comercial</label><input class="form-control" name="nombre_comercial" value="<?= e($emp['nombre_comercial']) ?>" maxlength="100" required></div>
    <div class="mb-3"><label class="form-label">Razón social <small class="text-muted">(opcional)</small></label><input class="form-control" name="razon_social" value="<?= e($emp['razon_social'] ?? '') ?>" maxlength="150"></div>
    <p class="text-muted small">Registrada el <?= e(date('d/m/Y', strtotime($emp['fecha_registro']))) ?>.</p>
    <div class="d-flex gap-2"><button class="btn btn-ca btn-ca-primary">Guardar cambios</button><a class="btn btn-ca btn-ca-ghost" href="empresas.php">Volver</a></div>
  </form>
</div>
<?php component('admin_footer'); ?>
