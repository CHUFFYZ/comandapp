<?php
require_once __DIR__ . '/notificar.php';

const DIAS_GRACIA = 7;   // días después de cada corte para enviar/aprobar el comprobante

function fdate($d): string { return ($d instanceof DateTimeInterface ? $d : new DateTimeImmutable((string)$d))->format('d/m/Y'); }

/** Fecha = alta + N meses (si el mes es más corto, usa su último día). */
function corte_n(string $alta, int $meses): DateTimeImmutable {
    $a = new DateTimeImmutable($alta);
    $m0 = (int)$a->format('n') - 1 + $meses;
    $y = (int)$a->format('Y') + intdiv($m0, 12); $m = $m0 % 12 + 1;
    $ult = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, min((int)$a->format('j'), $ult)));
}

function plan_por_id(int $id): ?array {
    static $c = [];
    if (!array_key_exists($id, $c)) { $st = db()->prepare('SELECT * FROM planes_suscripcion WHERE id_plan=?'); $st->execute([$id]); $c[$id] = $st->fetch() ?: null; }
    return $c[$id];
}
function get_duraciones(bool $soloActivas = false): array {
    return db()->query('SELECT * FROM duraciones_suscripcion' . ($soloActivas ? ' WHERE activa=1' : '') . ' ORDER BY meses')->fetchAll();
}
function duracion_por_id(int $id): ?array {
    $st = db()->prepare('SELECT * FROM duraciones_suscripcion WHERE id_duracion=?'); $st->execute([$id]); return $st->fetch() ?: null;
}
/** Duración elegible (activa) o null. */
function duracion_valida(int $id): ?array { $d = duracion_por_id($id); return $d && $d['activa'] ? $d : null; }
function duracion_base(): array { $d = get_duraciones(true)[0] ?? get_duraciones()[0] ?? null; if (!$d) throw new RuntimeException('No hay duraciones configuradas.'); return $d; }
function meses_ciclo(array $d): int { return max(1, (int)$d['meses']) + max(0, (int)$d['meses_gratis']); }
/** Precio del periodo: meses pagados × precio mensual − descuento. Los meses gratis no se cobran. */
function precio_ciclo(array $plan, array $d): float { return round((float)$plan['precio_mensual'] * max(1, (int)$d['meses']) * (1 - (float)$d['descuento_pct'] / 100), 2); }
function duracion_texto(array $d): string {
    $t = $d['nombre'] . ' · ' . (int)$d['meses'] . ((int)$d['meses'] === 1 ? ' mes' : ' meses');
    if ((float)$d['descuento_pct'] > 0) $t .= ' · ' . rtrim(rtrim(number_format((float)$d['descuento_pct'], 2), '0'), '.') . '% de descuento';
    if ((int)$d['meses_gratis'] > 0) $t .= ' · +' . (int)$d['meses_gratis'] . ' gratis';
    return $t;
}

/** Crea el local con su primer ciclo. Opera de inmediato; 7 días para el primer comprobante. */
function crear_local(PDO $pdo, int $idEmpresa, string $nombre, int $idPlan, int $idDur): int {
    $plan = plan_por_id($idPlan); $dur = duracion_valida($idDur);
    if (!$plan || !$dur) throw new RuntimeException('Plan o duración no válidos.');
    $pdo->prepare('INSERT INTO locales (id_empresa, nombre_sucursal, id_plan_activo, fecha_alta, fecha_vencimiento) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ' . DIAS_GRACIA . ' DAY))')
        ->execute([$idEmpresa, $nombre, $idPlan]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO ciclos_local (id_local, numero, mes_inicio, meses, fecha_inicio, id_plan, id_duracion, monto) VALUES (?, 0, 0, ?, CURDATE(), ?, ?, ?)')
        ->execute([$id, meses_ciclo($dur), $idPlan, $idDur, precio_ciclo($plan, $dur)]);
    return $id;
}

/**
 * Crea los ciclos que ya correspondan (renovaciones). Al iniciar un ciclo se aplica el cambio de
 * plan/duración programado, si existe; si no, se renueva igual que el anterior.
 * $locales: filas con id_local, id_empresa, nombre_sucursal, fecha_alta, id_plan_activo.
 */
function sincronizar_ciclos(array $locales): void {
    $pdo = db(); $hoy = new DateTimeImmutable('today');
    foreach ($locales as $l) {
        try {
            $idL = (int)$l['id_local']; $alta = $l['fecha_alta'];
            $ult = $pdo->prepare('SELECT * FROM ciclos_local WHERE id_local=? ORDER BY numero DESC LIMIT 1');
            $ult->execute([$idL]); $u = $ult->fetch();
            if (!$u) {   // local anterior a los ciclos: arranca mensual con su plan actual
                $plan = plan_por_id((int)$l['id_plan_activo']); $dur = duracion_base();
                $pdo->prepare('INSERT INTO ciclos_local (id_local, numero, mes_inicio, meses, fecha_inicio, id_plan, id_duracion, monto) VALUES (?, 0, 0, ?, ?, ?, ?, ?)')
                    ->execute([$idL, meses_ciclo($dur), $alta, $plan['id_plan'], $dur['id_duracion'], precio_ciclo($plan, $dur)]);
                $ult->execute([$idL]); $u = $ult->fetch();
            }
            while (corte_n($alta, (int)$u['mes_inicio'] + max(1, (int)$u['meses'])) <= $hoy) {
                $planId = (int)$u['id_plan']; $durId = (int)$u['id_duracion'];
                $c = $pdo->prepare("SELECT * FROM cambios_suscripcion WHERE id_local=? AND estado='programado' ORDER BY id_cambio DESC LIMIT 1");
                $c->execute([$idL]); $cam = $c->fetch();
                if ($cam) { $planId = (int)$cam['id_plan']; $durId = (int)$cam['id_duracion']; }
                $plan = plan_por_id($planId); $dur = duracion_por_id($durId);
                $ini = (int)$u['mes_inicio'] + max(1, (int)$u['meses']); $num = (int)$u['numero'] + 1;
                $monto = precio_ciclo($plan, $dur);
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO ciclos_local (id_local, numero, mes_inicio, meses, fecha_inicio, id_plan, id_duracion, monto) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$idL, $num, $ini, meses_ciclo($dur), corte_n($alta, $ini)->format('Y-m-d'), $planId, $durId, $monto]);
                if ($cam) {
                    $pdo->prepare("UPDATE cambios_suscripcion SET estado='aplicado', fecha_resolucion=NOW() WHERE id_cambio=?")->execute([$cam['id_cambio']]);
                    $pdo->prepare('UPDATE locales SET id_plan_activo=? WHERE id_local=?')->execute([$planId, $idL]);
                }
                $pdo->commit();
                if ($cam) notificar_gerentes((int)$l['id_empresa'], "ciclo:$idL:$num", 'suscripcion', 'Tu nueva suscripción ya está activa — ' . ($l['nombre_sucursal'] ?? ''),
                    $plan['nombre'] . ' · ' . $dur['nombre'] . '. Monto a pagar: $' . number_format($monto, 2) . '. Sube tu comprobante antes del ' . fdate(corte_n($alta, $ini)->modify('+' . DIAS_GRACIA . ' days')) . '.', 'gerente/sucursales.php');
                $ult->execute([$idL]); $u = $ult->fetch();
            }
        } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
    }
}

/** Ciclos de varios locales: [id_local][numero] => fila (con plan_nombre y dur_nombre). */
function ciclos_de_locales(array $ids): array {
    if (!$ids) return [];
    $st = db()->prepare('SELECT c.*, p.nombre AS plan_nombre, d.nombre AS dur_nombre FROM ciclos_local c
        JOIN planes_suscripcion p ON p.id_plan = c.id_plan JOIN duraciones_suscripcion d ON d.id_duracion = c.id_duracion
        WHERE c.id_local IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY c.id_local, c.numero');
    $st->execute(array_values($ids));
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['id_local']][(int)$r['numero']] = $r;
    return $out;
}

function cambio_programado(int $idLocal): ?array {
    $st = db()->prepare("SELECT c.*, p.nombre AS plan_nombre, p.precio_mensual, d.nombre AS dur_nombre, d.meses, d.descuento_pct, d.meses_gratis
        FROM cambios_suscripcion c JOIN planes_suscripcion p ON p.id_plan = c.id_plan JOIN duraciones_suscripcion d ON d.id_duracion = c.id_duracion
        WHERE c.id_local = ? AND c.estado = 'programado' ORDER BY c.id_cambio DESC LIMIT 1");
    $st->execute([$idLocal]); return $st->fetch() ?: null;
}

/** Programa un cambio de plan/duración que se aplicará al terminar el ciclo en curso. Reemplaza uno anterior. */
function programar_cambio(int $idLocal, int $idPlan, int $idDur, int $idUser): ?string {
    $plan = plan_por_id($idPlan); $dur = duracion_valida($idDur);
    if (!$plan || !$dur) return 'Plan o duración no válidos.';
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM locales WHERE id_local=?'); $st->execute([$idLocal]); $l = $st->fetch();
    if (!$l) return 'Sucursal no encontrada.';
    sincronizar_ciclos([$l]);
    $st = $pdo->prepare('SELECT * FROM ciclos_local WHERE id_local=? ORDER BY numero DESC LIMIT 1'); $st->execute([$idLocal]); $u = $st->fetch();
    if ($u && (int)$u['id_plan'] === $idPlan && (int)$u['id_duracion'] === $idDur)
        return 'Esa ya es la suscripción actual y se renovará igual. Elige otro plan o duración.';
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE cambios_suscripcion SET estado='cancelado', fecha_resolucion=NOW() WHERE id_local=? AND estado='programado'")->execute([$idLocal]);
    $pdo->prepare('INSERT INTO cambios_suscripcion (id_local, id_plan, id_duracion, solicitado_por) VALUES (?,?,?,?)')->execute([$idLocal, $idPlan, $idDur, $idUser]);
    $pdo->commit();
    return null;
}

/** Cancela el cambio programado (solo si todavía no se activó). */
function cancelar_cambio(int $idLocal): bool {
    $st = db()->prepare("UPDATE cambios_suscripcion SET estado='cancelado', fecha_resolucion=NOW() WHERE id_local=? AND estado='programado'");
    $st->execute([$idLocal]); return $st->rowCount() > 0;
}

/** Procesa POST accion=programar_cambio|cancelar_cambio para un local dentro de los permitidos. */
function suscripcion_post(int $idUser, array $idsPermitidos, bool $avisarGerentes = false): void {
    $a = $_POST['accion'] ?? ''; $idl = (int)($_POST['id_local'] ?? 0);
    if (!in_array($idl, $idsPermitidos, true)) { flash('Sucursal no válida.', 'danger'); return; }
    if ($a === 'programar_cambio') {
        $err = programar_cambio($idl, (int)($_POST['id_plan'] ?? 0), (int)($_POST['id_duracion'] ?? 0), $idUser);
        flash($err ?? 'Cambio programado: se activará cuando termine la suscripción en curso. Puedes cancelarlo antes.', $err ? 'danger' : 'success');
        $ok = !$err; $msg = 'Se programó un cambio de plan o duración para tu sucursal.';
    } else {
        $ok = cancelar_cambio($idl);
        flash($ok ? 'Cambio cancelado. La suscripción actual sigue igual.' : 'No hay un cambio pendiente que cancelar.', $ok ? 'success' : 'danger');
        $msg = 'Se canceló el cambio de suscripción programado para tu sucursal.';
    }
    if ($avisarGerentes && $ok) {
        $st = db()->prepare('SELECT id_empresa, nombre_sucursal FROM locales WHERE id_local=?'); $st->execute([$idl]); $l = $st->fetch();
        if ($l) notificar_gerentes((int)$l['id_empresa'], "cambio:$idl:" . time(), 'suscripcion', 'Suscripción — ' . $l['nombre_sucursal'], $msg . ' (realizado por soporte)', 'gerente/sucursales.php');
    }
}
