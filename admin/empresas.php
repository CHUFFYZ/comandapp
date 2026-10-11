<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('SUPER_ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'toggle') {
        $pdo->prepare('UPDATE empresas SET activo = NOT activo WHERE id_empresa = ?')->execute([(int)$_POST['id']]);
        flash('Estado de la empresa actualizado.');
    } elseif ($accion === 'crear') {
        $nom = trim($_POST['nombre_comercial'] ?? ''); $razon = trim($_POST['razon_social'] ?? '');
        $gNom = trim($_POST['gerente_nombre'] ?? ''); $gMail = trim($_POST['gerente_email'] ?? ''); $gPass = $_POST['gerente_password'] ?? '';
        if ($nom === '' || $gNom === '' || !filter_var($gMail, FILTER_VALIDATE_EMAIL) || strlen($gPass) < 8) {
            flash('Completa todos los campos. La contraseña debe tener al menos 8 caracteres.', 'danger');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO empresas (nombre_comercial, razon_social) VALUES (?, ?)')->execute([$nom, $razon ?: null]);
                $idEmp = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO usuarios (id_empresa, id_local, id_rol, nombre, email, password_hash)
                               VALUES (?, NULL, (SELECT id_rol FROM roles WHERE nombre_rol = 'GERENTE'), ?, ?, ?)")
                    ->execute([$idEmp, $gNom, $gMail, password_hash($gPass, PASSWORD_DEFAULT)]);
                $pdo->commit();
                flash('Empresa creada. Ahora agrégale un local.');
            } catch (PDOException $e) {
                $pdo->rollBack();
                flash($e->getCode() === '23000' ? 'Ese correo ya está registrado.' : 'No se pudo crear la empresa.', 'danger');
            }
        }
    }
    redirect('admin/empresas.php');
}

$empresas = $pdo->query(
  "SELECT e.*, (SELECT COUNT(*) FROM locales l WHERE l.id_empresa = e.id_empresa) AS locales,
          (SELECT u.email FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.id_empresa = e.id_empresa AND r.nombre_rol = 'GERENTE' LIMIT 1) AS gerente
   FROM empresas e ORDER BY e.fecha_registro DESC")->fetchAll();

$titulo = 'Empresas';
component('head', ['titulo' => $titulo . ' | ' . APP_NAME, 'css' => ['css/admin.css']]);
component('admin_header', ['user' => $user, 'titulo' => $titulo, 'activo' => 'empresas.php']);
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="panel">
      <h2>Nueva empresa</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
        <div class="mb-2"><label class="form-label">Nombre comercial</label><input class="form-control" name="nombre_comercial" required maxlength="100"></div>
        <div class="mb-3"><label class="form-label">Razón social <small class="text-muted">(opcional)</small></label><input class="form-control" name="razon_social" maxlength="150"></div>
        <h3 class="h6 mt-4">Gerente general</h3>
        <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="gerente_nombre" required maxlength="100"></div>
        <div class="mb-2"><label class="form-label">Correo</label><input class="form-control" type="email" name="gerente_email" required maxlength="100"></div>
        <div class="mb-3"><label class="form-label">Contraseña inicial</label><input class="form-control" type="password" name="gerente_password" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-ca btn-ca-primary w-100">Crear empresa</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="panel">
      <h2>Empresas registradas</h2>
      <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Empresa</th><th>Gerente</th><th>Locales</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($empresas as $e): ?>
          <tr>
            <td><strong><?= e($e['nombre_comercial']) ?></strong><br><small class="text-muted"><?= e($e['razon_social'] ?? '') ?></small></td>
            <td><?= e($e['gerente'] ?? '—') ?></td>
            <td><?= (int)$e['locales'] ?></td>
            <td><span class="badge text-bg-<?= $e['activo'] ? 'success' : 'secondary' ?>"><?= $e['activo'] ? 'Activa' : 'Suspendida' ?></span></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="empresa_editar.php?id=<?= (int)$e['id_empresa'] ?>">Editar</a> <a class="btn btn-sm btn-outline-dark" href="usuarios.php?empresa=<?= (int)$e['id_empresa'] ?>">Usuarios</a> <?php component('form_accion', ['accion' => 'toggle', 'id' => $e['id_empresa'],
                'texto' => $e['activo'] ? 'Suspender' : 'Activar', 'confirmar' => $e['activo'] ? 'Sus usuarios no podrán iniciar sesión. ¿Continuar?' : '']); ?></td>
          </tr>
        <?php endforeach; if (!$empresas): ?><tr><td colspan="5" class="text-center text-muted py-4">Todavía no hay empresas.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php component('admin_footer'); ?>
