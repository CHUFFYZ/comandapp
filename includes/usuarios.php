<?php
require_once __DIR__ . '/tenant.php';

/**
 * Devuelve el usuario $id solo si el actor tiene permiso de editarlo:
 *  - SUPER_ADMIN: cualquier usuario excepto otros superusuarios.
 *  - GERENTE: sus propios datos y los de COCINERO/MESERO/GERENTE_LOCAL de su empresa.
 *  - GERENTE_LOCAL: sus propios datos y los de COCINERO/MESERO de su local.
 */
function usuario_para_editar(array $actor, int $id): ?array {
    $st = db()->prepare('SELECT u.*, r.nombre_rol, e.nombre_comercial FROM usuarios u
        JOIN roles r ON r.id_rol = u.id_rol LEFT JOIN empresas e ON e.id_empresa = u.id_empresa WHERE u.id_usuario = ?');
    $st->execute([$id]); $t = $st->fetch();
    if (!$t || $t['nombre_rol'] === 'SUPER_ADMIN') return null;
    if ($actor['rol'] === 'SUPER_ADMIN') return $t;
    if ((int)$t['id_usuario'] === (int)$actor['id']) return $t;
    if ((int)$t['id_empresa'] !== (int)$actor['id_empresa']) return null;
    if ($actor['rol'] === 'GERENTE') return in_array($t['nombre_rol'], ['COCINERO', 'MESERO', 'GERENTE_LOCAL'], true) ? $t : null;
    if ($actor['rol'] === 'GERENTE_LOCAL')
        return (in_array($t['nombre_rol'], ['COCINERO', 'MESERO'], true) && $actor['id_local'] && (int)$t['id_local'] === (int)$actor['id_local']) ? $t : null;
    return null;
}

function usuario_es_yo(array $actor, array $t): bool { return (int)$t['id_usuario'] === (int)$actor['id']; }

/** Qué puede cambiar el actor además de nombre/correo: roles permitidos y locales disponibles. */
function usuario_opciones(array $actor, array $t): array {
    $roles = []; $locales = [];
    if (!usuario_es_yo($actor, $t) && in_array($t['nombre_rol'], ['COCINERO', 'MESERO', 'GERENTE_LOCAL'], true)) {
        $roles = $actor['rol'] === 'GERENTE_LOCAL' ? ['COCINERO', 'MESERO'] : ['COCINERO', 'MESERO', 'GERENTE_LOCAL'];
        if ($actor['rol'] !== 'GERENTE_LOCAL') {   // el gerente de local no puede mover empleados de local
            $st = db()->prepare('SELECT id_local, nombre_sucursal FROM locales WHERE id_empresa=? ORDER BY nombre_sucursal');
            $st->execute([$t['id_empresa']]); $locales = $st->fetchAll();
        }
    }
    return ['roles' => $roles, 'locales' => $locales];
}

/** Guarda los datos enviados por POST. Devuelve un mensaje de error o null si todo salió bien. */
function usuario_guardar(array $actor, array $t, array $op): ?string {
    $yo = usuario_es_yo($actor, $t);
    $nombre = trim($_POST['nombre'] ?? ''); $email = trim($_POST['email'] ?? ''); $pw = $_POST['password'] ?? '';
    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Revisa el nombre y el correo.';
    if ($pw !== '' && strlen($pw) < 8) return 'La nueva contraseña debe tener al menos 8 caracteres.';
    if ($pw !== '' && $yo && !password_verify($_POST['password_actual'] ?? '', $t['password_hash'])) return 'La contraseña actual no es correcta.';

    $rol = $t['nombre_rol']; $idLocal = $t['id_local'];
    if ($op['roles']) { $rol = $_POST['rol'] ?? ''; if (!in_array($rol, $op['roles'], true)) return 'Rol no válido.'; }
    if ($op['locales']) {
        $idl = (int)($_POST['id_local'] ?? 0);
        if (!in_array($idl, array_map(fn($l) => (int)$l['id_local'], $op['locales']), true)) return 'Local no válido.';
        $idLocal = $idl;
    }
    $activo = $yo ? (int)$t['activo'] : (isset($_POST['activo']) ? 1 : 0);

    $sql = 'UPDATE usuarios SET nombre=?, email=?, id_rol=(SELECT id_rol FROM roles WHERE nombre_rol=?), id_local=?, activo=?';
    $par = [$nombre, $email, $rol, $idLocal, $activo];
    if ($pw !== '') { $sql .= ', password_hash=?'; $par[] = password_hash($pw, PASSWORD_DEFAULT); }
    try {
        db()->prepare($sql . ' WHERE id_usuario=?')->execute(array_merge($par, [(int)$t['id_usuario']]));
    } catch (PDOException $e) {
        return $e->getCode() === '23000' ? 'Ese correo ya está registrado por otro usuario.' : 'No se pudieron guardar los cambios.';
    }
    if ($yo) { $_SESSION['user']['nombre'] = $nombre; $_SESSION['user']['email'] = $email; }
    return null;
}
