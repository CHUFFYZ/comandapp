<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/suscripciones.php';
if ($u = current_user()) redirect(home_for($u['rol']));

$planes = get_planes();
$f = ['plan' => (int)($_GET['plan'] ?? 0), 'empresa' => '', 'razon' => '', 'sucursal' => '', 'nombre' => '', 'email' => ''];
if (!array_filter($planes, fn($p) => (int)$p['id_plan'] === $f['plan'])) $f['plan'] = (int)($planes[1]['id_plan'] ?? $planes[0]['id_plan']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_POST['web'])) redirect('');   // campo trampa para bots
    $f = ['plan' => (int)($_POST['plan'] ?? 0), 'empresa' => trim($_POST['empresa'] ?? ''), 'razon' => trim($_POST['razon'] ?? ''),
          'sucursal' => trim($_POST['sucursal'] ?? ''), 'nombre' => trim($_POST['nombre'] ?? ''), 'email' => trim($_POST['email'] ?? '')];
    $pass = $_POST['password'] ?? '';
    $dur = (int)($_POST['duracion'] ?? 0);
    $planOk = (bool)array_filter($planes, fn($p) => (int)$p['id_plan'] === $f['plan']);

    if (!$planOk || !duracion_valida($dur)) $error = 'Elige un plan y una duración.';
    elseif ($f['empresa'] === '' || $f['sucursal'] === '' || $f['nombre'] === '') $error = 'Completa los datos de tu negocio y de tu usuario.';
    elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $error = 'Escribe un correo válido.';
    elseif (strlen($pass) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
    elseif ($pass !== ($_POST['password2'] ?? '')) $error = 'Las contraseñas no coinciden.';
    elseif (empty($_POST['acepto'])) $error = 'Debes aceptar los términos y la política de privacidad.';
    else {
        $pdo = null;
        try {
            $pdo = db(); $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO empresas (nombre_comercial, razon_social) VALUES (?, ?)')->execute([$f['empresa'], $f['razon'] ?: null]);
            $idEmp = (int)$pdo->lastInsertId();
            crear_local($pdo, $idEmp, $f['sucursal'], $f['plan'], $dur);
            $pdo->prepare("INSERT INTO usuarios (id_empresa, id_local, id_rol, nombre, email, password_hash)
                           VALUES (?, NULL, (SELECT id_rol FROM roles WHERE nombre_rol = 'GERENTE'), ?, ?, ?)")
                ->execute([$idEmp, $f['nombre'], $f['email'], password_hash($pass, PASSWORD_DEFAULT)]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
            $error = ($e instanceof PDOException && $e->getCode() === '23000')
                ? 'Ese correo ya está registrado. ¿Quieres iniciar sesión?' : 'No se pudo completar el registro. Inténtalo de nuevo.';
        }
        if (!$error) {
            $r = attempt_login($f['email'], $pass);
            flash('¡Bienvenido! Ya puedes trabajar. Tienes 7 días para subir el comprobante de pago de tu sucursal en la sección Sucursales.');
            redirect(isset($r['user']) ? home_for($r['user']['rol']) : 'pages/login.php');
        }
    }
}
component('head', ['titulo' => 'Crear cuenta | ' . APP_NAME, 'css' => ['css/auth.css']]);
?>
<main class="auth-page">
  <div class="auth-card auth-card-wide">
    <a class="brand mb-3 d-inline-block" href="<?= url() ?>"><i class="bi bi-receipt-cutoff"></i> <?= e(APP_NAME) ?></a>
    <h1>Crea tu cuenta</h1>
    <p class="text-secondary">Registra tu negocio y su primer local. Podrás agregar más sucursales después.</p>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="web" tabindex="-1" autocomplete="off"></div>

      <h2 class="h6 mt-3">1. Elige tu plan <small class="text-muted">(por local)</small></h2>
      <p class="small text-muted">Empiezas a trabajar de inmediato y tienes 7 días para subir tu comprobante de pago.</p>
      <div class="row g-2 mb-3">
        <?php foreach ($planes as $p): ?>
          <div class="col-md-4"><label class="plan-opt">
            <input type="radio" name="plan" value="<?= (int)$p['id_plan'] ?>" <?= $f['plan'] === (int)$p['id_plan'] ? 'checked' : '' ?> required>
            <span class="plan-opt-body"><strong><?= e($p['nombre']) ?></strong>
              <span class="plan-opt-precio"><?= money($p['precio_mensual']) ?><small>/mes</small></span>
              <small>Hasta <?= (int)$p['limite_empleados'] ?> empleados · extra <?= money($p['precio_empleado_extra']) ?></small></span>
          </label></div>
        <?php endforeach; ?>
      </div>

      <h2 class="h6">Duración de la suscripción</h2>
      <select class="form-select mb-3" name="duracion" aria-label="Duración de la suscripción">
        <?php foreach (get_duraciones(true) as $d): ?><option value="<?= (int)$d['id_duracion'] ?>" <?= (int)($_POST['duracion'] ?? 0) === (int)$d['id_duracion'] ? 'selected' : '' ?>><?= e(duracion_texto($d)) ?></option><?php endforeach; ?>
      </select>

      <h2 class="h6">2. Tu negocio</h2>
      <div class="row g-2 mb-3">
        <div class="col-md-6"><label class="form-label" for="empresa">Nombre comercial</label><input class="form-control" id="empresa" name="empresa" value="<?= e($f['empresa']) ?>" maxlength="100" required></div>
        <div class="col-md-6"><label class="form-label" for="razon">Razón social <small class="text-muted">(opcional)</small></label><input class="form-control" id="razon" name="razon" value="<?= e($f['razon']) ?>" maxlength="150"></div>
        <div class="col-12"><label class="form-label" for="sucursal">Nombre de tu primer local</label><input class="form-control" id="sucursal" name="sucursal" value="<?= e($f['sucursal']) ?>" placeholder="Ej. Sucursal Centro" maxlength="100" required></div>
      </div>

      <h2 class="h6">3. Tu usuario (gerente general)</h2>
      <div class="row g-2 mb-3">
        <div class="col-md-6"><label class="form-label" for="nombre">Nombre completo</label><input class="form-control" id="nombre" name="nombre" value="<?= e($f['nombre']) ?>" maxlength="100" required autocomplete="name"></div>
        <div class="col-md-6"><label class="form-label" for="email">Correo</label><input class="form-control" type="email" id="email" name="email" value="<?= e($f['email']) ?>" maxlength="100" required autocomplete="email"></div>
        <div class="col-md-6"><label class="form-label" for="password">Contraseña</label><input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password"></div>
        <div class="col-md-6"><label class="form-label" for="password2">Repite la contraseña</label><input class="form-control" type="password" id="password2" name="password2" minlength="8" required autocomplete="new-password"></div>
      </div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="acepto" name="acepto" required><label class="form-check-label" for="acepto">Acepto los términos y la política de privacidad.</label></div>
      <button class="btn btn-ca btn-ca-primary w-100" type="submit">Crear cuenta</button>
    </form>
    <p class="text-center mt-4 mb-0">¿Ya tienes cuenta? <a href="<?= url('pages/login.php') ?>">Inicia sesión</a></p>
  </div>
</main>
<?php component('scripts'); ?>
