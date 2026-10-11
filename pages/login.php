<?php
require_once __DIR__ . '/../includes/auth.php';
if ($u = current_user()) redirect(home_for($u['rol']));

$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    try {
        $r = attempt_login($email, $_POST['password'] ?? '');
        if (isset($r['user'])) redirect(home_for($r['user']['rol']));
        $error = $r['error'];
    } catch (Throwable $e) { $error = 'No se pudo conectar con la base de datos.'; }
}
component('head', ['titulo' => 'Iniciar sesión | ' . APP_NAME, 'css' => ['css/auth.css']]);
?>
<main class="auth-page">
  <div class="auth-card">
    <a class="brand mb-4 d-inline-block" href="<?= url() ?>"><i class="bi bi-receipt-cutoff"></i> <?= e(APP_NAME) ?></a>
    <h1>Iniciar sesión</h1>
    <p class="text-secondary">Entra con el correo y la contraseña que te asignaron.</p>
    <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['tipo']) ?>" role="status"><?= e($f['msg']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="email">Correo</label>
        <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
      </div>
      <div class="mb-4">
        <label class="form-label" for="password">Contraseña</label>
        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-ca btn-ca-primary w-100" type="submit">Entrar</button>
    </form>
    <p class="text-center mt-4 mb-1">¿Aún no tienes cuenta? <a href="<?= url('pages/registro.php') ?>">Registra tu empresa</a></p>
    <p class="text-center mb-0"><a href="<?= url() ?>">Volver al inicio</a></p>
  </div>
</main>
<?php component('scripts'); ?>
