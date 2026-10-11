<?php
require_once __DIR__ . '/../includes/tenant.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL'); $pdo = db(); $emp = $user['id_empresa'];
$locales = locales_empresa($user); $ids = array_map(fn($l) => (int)$l['id_local'], $locales);
// El gerente general también puede crear gerentes de local; el gerente de local solo meseros y cocineros
$rolesGest = $user['rol'] === 'GERENTE' ? ['COCINERO', 'MESERO', 'GERENTE_LOCAL'] : ['COCINERO', 'MESERO'];
$marcR = implode(',', array_fill(0, count($rolesGest), '?'));
$marcL = implode(',', array_fill(0, max(1, count($ids)), '?'));
$idsSql = $ids ?: [0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $a = $_POST['accion'] ?? '';
    if ($a === 'toggle') {
        $pdo->prepare("UPDATE usuarios u JOIN roles r ON r.id_rol=u.id_rol SET u.activo = NOT u.activo
                       WHERE u.id_usuario=? AND u.id_empresa=? AND r.nombre_rol IN ($marcR) AND u.id_local IN ($marcL)")
            ->execute(array_merge([(int)$_POST['id'], $emp], $rolesGest, $idsSql));
        flash('Estado del empleado actualizado.');
    } elseif ($a === 'crear') {
        $n = trim($_POST['nombre'] ?? ''); $m = trim($_POST['email'] ?? ''); $pw = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? ''; $idl = (int)($_POST['id_local'] ?? 0);
        if ($n === '' || !filter_var($m, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8 || !in_array($rol, $rolesGest, true) || !in_array($idl, $ids, true)) {
            flash('Revisa los datos. La contraseña debe tener al menos 8 caracteres.', 'danger');
        } else {
            try {
                $c = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE id_local=? AND activo=1'); $c->execute([$idl]); $total = (int)$c->fetchColumn();
                $pdo->prepare('INSERT INTO usuarios (id_empresa,id_local,id_rol,nombre,email,password_hash) VALUES (?,?,(SELECT id_rol FROM roles WHERE nombre_rol=?),?,?,?)')
                    ->execute([$emp, $idl, $rol, $n, $m, password_hash($pw, PASSWORD_DEFAULT)]);
                $l = current(array_filter($locales, fn($x) => $x['id_local'] == $idl));
                $extra = $total >= $l['limite_empleados'] ? ' Superaste el límite del plan: se cobrará ' . money($l['precio_empleado_extra']) . '/mes por este empleado extra.' : '';
                flash('Empleado creado.' . $extra, $extra ? 'warning' : 'success');
            } catch (PDOException $e) { flash($e->getCode() === '23000' ? 'Ese correo ya está registrado.' : 'No se pudo crear el empleado.', 'danger'); }
        }
    }
    redirect('gerente/empleados.php');
}
$st = $pdo->prepare("SELECT u.*, r.nombre_rol, l.nombre_sucursal FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol
    LEFT JOIN locales l ON l.id_local=u.id_local
    WHERE u.id_empresa=? AND r.nombre_rol IN ($marcR) AND u.id_local IN ($marcL) ORDER BY l.nombre_sucursal, u.nombre");
$st->execute(array_merge([$emp], $rolesGest, $idsSql)); $lista = $st->fetchAll();

$titulo = 'Empleados';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('panel_header', ['user'=>$user,'titulo'=>$titulo,'activo'=>'empleados.php','badge'=>rol_badge($user['rol']),'menu'=>panel_menu($user['rol'])]);
?>
<div class="row g-4">
  <div class="col-lg-4"><div class="panel"><h2>Nuevo empleado</h2>
    <?php if (!$locales): ?><p class="text-muted mb-0">No hay locales activos asignados.</p><?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="crear">
      <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" maxlength="100" required></div>
      <div class="mb-2"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" maxlength="100" required></div>
      <div class="mb-2"><label class="form-label">Contraseña inicial</label><input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
      <div class="mb-2"><label class="form-label">Rol</label><select class="form-select" name="rol"><?php foreach ($rolesGest as $r): ?><option value="<?= e($r) ?>"><?= e(rol_label($r)) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">Local</label><select class="form-select" name="id_local"><?php foreach ($locales as $l): ?><option value="<?= (int)$l['id_local'] ?>"><?= e($l['nombre_sucursal']) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-ca btn-ca-primary w-100">Crear empleado</button></form><?php endif; ?></div></div>
  <div class="col-lg-8"><div class="panel"><h2>Equipo</h2>
    <table class="table align-middle"><thead><tr><th>Nombre</th><th>Rol</th><th>Local</th><th></th></tr></thead><tbody>
    <?php foreach ($lista as $u): ?><tr class="<?= $u['activo'] ? '' : 'text-muted' ?>"><td><strong><?= e($u['nombre']) ?></strong><br><small><?= e($u['email']) ?></small></td>
      <td><?= e(rol_label($u['nombre_rol'])) ?></td><td><?= e($u['nombre_sucursal'] ?? '—') ?></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="usuario_editar.php?id=<?= (int)$u['id_usuario'] ?>">Editar</a> <?php component('form_accion', ['accion'=>'toggle','id'=>$u['id_usuario'],'texto'=>$u['activo'] ? 'Desactivar' : 'Activar']); ?></td></tr>
    <?php endforeach; if (!$lista): ?><tr><td colspan="4" class="text-muted text-center py-3">Aún no hay empleados.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php component('admin_footer'); ?>
