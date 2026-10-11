<?php
require_once __DIR__ . '/../includes/usuarios.php';
$user = require_role('SUPER_ADMIN'); $pdo = db();
$idEmp = (int)($_GET['empresa'] ?? 0);
$empresas = $pdo->query('SELECT id_empresa, nombre_comercial FROM empresas ORDER BY nombre_comercial')->fetchAll();
$sql = "SELECT u.*, r.nombre_rol, e.nombre_comercial, l.nombre_sucursal FROM usuarios u
        JOIN roles r ON r.id_rol=u.id_rol JOIN empresas e ON e.id_empresa=u.id_empresa
        LEFT JOIN locales l ON l.id_local=u.id_local WHERE r.nombre_rol <> 'SUPER_ADMIN'";
$par = [];
if ($idEmp) { $sql .= ' AND u.id_empresa=?'; $par[] = $idEmp; }
$st = $pdo->prepare($sql . ' ORDER BY e.nombre_comercial, l.nombre_sucursal, r.id_rol, u.nombre'); $st->execute($par); $lista = $st->fetchAll();

$titulo = 'Usuarios';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'usuarios.php']);
?>
<form method="get" class="d-flex align-items-center gap-2 mb-3">
  <label class="form-label m-0" for="empresa">Empresa:</label>
  <select class="form-select w-auto" id="empresa" name="empresa" onchange="this.form.submit()">
    <option value="0">Todas</option>
    <?php foreach ($empresas as $e): ?><option value="<?= (int)$e['id_empresa'] ?>" <?= $e['id_empresa'] == $idEmp ? 'selected' : '' ?>><?= e($e['nombre_comercial']) ?></option><?php endforeach; ?>
  </select>
</form>
<div class="panel"><div class="table-responsive"><table class="table align-middle">
  <thead><tr><th>Usuario</th><th>Empresa</th><th>Rol</th><th>Local</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($lista as $u): ?><tr class="<?= $u['activo'] ? '' : 'text-muted' ?>">
    <td><strong><?= e($u['nombre']) ?></strong><br><small><?= e($u['email']) ?></small></td><td><?= e($u['nombre_comercial']) ?></td>
    <td><?= e(rol_label($u['nombre_rol'])) ?></td><td><?= e($u['nombre_sucursal'] ?? '—') ?></td>
    <td><span class="badge text-bg-<?= $u['activo'] ? 'success' : 'secondary' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="usuario_editar.php?id=<?= (int)$u['id_usuario'] ?>">Editar</a></td></tr>
  <?php endforeach; if (!$lista): ?><tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php component('admin_footer'); ?>
