<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}

function redirect(string $path) { header('Location: ' . url($path)); exit; }

/** Mensaje flash: flash('texto','success') guarda; flash() lee y borra. */
function flash(?string $msg = null, string $tipo = 'success'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('La sesión expiró. Recarga la página e inténtalo de nuevo.');
    }
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

/** Página de inicio según rol (los demás paneles se construirán después). */
function home_for(string $rol): string {
    return [
        'SUPER_ADMIN' => 'admin/index.php', 'GERENTE' => 'gerente/index.php', 'GERENTE_LOCAL' => 'gerente/index.php',
        'MESERO' => 'mesero/index.php',     'COCINERO' => 'cocina/index.php',
    ][$rol] ?? 'pages/login.php';
}

/** Exige sesión y rol; si no cumple, redirige. */
/** Cierra la sesión si el usuario, la empresa o el local (servicio vencido) ya no están activos. */
function revalidar_sesion(array $u): void {
    $st = db()->prepare('SELECT (us.activo = 0) AS u_off, (e.activo = 0) AS e_off,
            (l.id_local IS NOT NULL AND (l.activo = 0 OR l.fecha_vencimiento < CURDATE())) AS l_off
        FROM usuarios us LEFT JOIN empresas e ON e.id_empresa = us.id_empresa
        LEFT JOIN locales l ON l.id_local = us.id_local WHERE us.id_usuario = ?');
    $st->execute([$u['id']]); $r = $st->fetch();
    if (!$r || $r['u_off'] || $r['e_off'] || $r['l_off']) {
        $_SESSION = [];
        flash($r && $r['l_off'] ? 'El servicio de tu sucursal está suspendido. Avisa a tu gerente.' : 'Tu sesión ya no es válida.', 'warning');
        redirect('pages/login.php');
    }
}

function require_role(string ...$roles): array {
    $u = current_user();
    if (!$u) redirect('pages/login.php');
    if ($u['rol'] !== 'SUPER_ADMIN') revalidar_sesion($u);
    if (!in_array($u['rol'], $roles, true)) redirect(home_for($u['rol']));
    return $u;
}

/** Valida credenciales. Devuelve ['user'=>...] o ['error'=>'mensaje']. */
function attempt_login(string $email, string $pass): array {
    $lock = $_SESSION['login_lock'] ?? 0;
    if ($lock > time()) return ['error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'];

    $st = db()->prepare(
        'SELECT u.*, r.nombre_rol, e.activo AS empresa_activa, l.activo AS local_activo, l.fecha_vencimiento
         FROM usuarios u
         JOIN roles r ON r.id_rol = u.id_rol
         LEFT JOIN empresas e ON e.id_empresa = u.id_empresa
         LEFT JOIN locales  l ON l.id_local   = u.id_local
         WHERE u.email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();

    // password_verify siempre se ejecuta para no revelar si el correo existe
    $ok = password_verify($pass, $u['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva');
    if (!$u || !$ok) {
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) { $_SESSION['login_lock'] = time() + 300; $_SESSION['login_fails'] = 0; }
        return ['error' => 'Correo o contraseña incorrectos.'];
    }
    if (!$u['activo']) return ['error' => 'Tu usuario está desactivado. Contacta a tu gerente.'];

    if ($u['nombre_rol'] !== 'SUPER_ADMIN') {
        if (!$u['empresa_activa']) return ['error' => 'La cuenta de tu empresa está suspendida.'];
        if ($u['id_local'] && (!$u['local_activo'] || $u['fecha_vencimiento'] < date('Y-m-d')))
            return ['error' => 'El plan de tu local está vencido o inactivo.'];
    }

    session_regenerate_id(true);
    $_SESSION['login_fails'] = 0;
    $_SESSION['user'] = [
        'id' => (int)$u['id_usuario'], 'nombre' => $u['nombre'], 'email' => $u['email'],
        'rol' => $u['nombre_rol'], 'id_empresa' => $u['id_empresa'], 'id_local' => $u['id_local'],
    ];
    return ['user' => $_SESSION['user']];
}
