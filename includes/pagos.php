<?php
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/suscripciones.php';

const COMPROBANTE_MAX = 5 * 1024 * 1024;

/** Pagos de varios locales: [id_local][periodo] => fila. */
function pagos_de_locales(array $ids): array {
    if (!$ids) return [];
    $st = db()->prepare('SELECT * FROM pagos_local WHERE id_local IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute(array_values($ids));
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['id_local']][(int)$r['periodo']] = $r;
    return $out;
}

/**
 * Situación de cobro de un local en su ciclo actual.
 * nivel: ok | atencion | urgente (<=3 días) | vencido (servicio suspendido).
 * El comprobante del ciclo vence a los 7 días de su inicio; al aprobarse, el servicio llega hasta 7 días después de su fin.
 */
function cobro_info(array $local, array $pagosLocal, array $ciclosLocal): array {
    $hoy = new DateTimeImmutable('today'); $alta = $local['fecha_alta']; $actual = null;
    foreach ($ciclosLocal as $c) if (corte_n($alta, (int)$c['mes_inicio']) <= $hoy) $actual = $c;
    if (!$actual) $actual = $ciclosLocal ? reset($ciclosLocal) : ['numero' => 0, 'mes_inicio' => 0, 'meses' => 1, 'monto' => 0, 'plan_nombre' => '', 'dur_nombre' => '', 'id_plan' => 0, 'id_duracion' => 0];
    $k = (int)$actual['numero'];
    $corte = corte_n($alta, (int)$actual['mes_inicio']);
    $fin = corte_n($alta, (int)$actual['mes_inicio'] + max(1, (int)$actual['meses']));
    $limite = $corte->modify('+' . DIAS_GRACIA . ' days');
    $pago = $pagosLocal[$k] ?? null;
    $estado = $pago['estado'] ?? 'sin_envio';
    $susp = $local['fecha_vencimiento'] < $hoy->format('Y-m-d');
    $dias = (int)$hoy->diff($limite)->format('%r%a');
    $nivel = $estado === 'aprobado' ? 'ok' : ($susp ? 'vencido' : ($dias <= 3 ? 'urgente' : 'atencion'));
    return ['periodo' => $k, 'ciclo' => $actual, 'corte' => $corte, 'limite' => $limite, 'pago' => $pago, 'estado' => $estado,
            'suspendido' => $susp, 'dias' => $dias, 'nivel' => $nivel, 'prox_corte' => $fin, 'prox_limite' => $fin->modify('+' . DIAS_GRACIA . ' days')];
}

function cobro_badge(string $estado): string {
    [$t, $c] = ['sin_envio' => ['Sin comprobante', 'secondary'], 'pendiente' => ['En revisión', 'info'],
                'rechazado' => ['Rechazado', 'danger'], 'aprobado' => ['Aprobado', 'success']][$estado] ?? [$estado, 'light'];
    return '<span class="badge text-bg-' . $c . '">' . e($t) . '</span>';
}

/** Sincroniza ciclos y agrega 'cobro' a cada fila de local (id_local, id_empresa, fecha_alta, fecha_vencimiento, id_plan_activo...). */
function cobro_de_locales(array $locales): array {
    sincronizar_ciclos($locales);
    $ids = array_column($locales, 'id_local');
    $pg = pagos_de_locales($ids); $cl = ciclos_de_locales($ids);
    return array_map(function ($l) use ($pg, $cl) { $i = (int)$l['id_local']; $l['cobro'] = cobro_info($l, $pg[$i] ?? [], $cl[$i] ?? []); return $l; }, $locales);
}

/** Todos los locales de una empresa con su situación de cobro. */
function locales_con_cobro(int $idEmpresa): array {
    $st = db()->prepare('SELECT l.*, ps.nombre AS plan, ps.precio_mensual FROM locales l
        JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo WHERE l.id_empresa = ? ORDER BY l.nombre_sucursal');
    $st->execute([$idEmpresa]);
    return cobro_de_locales($st->fetchAll());
}

/** Guarda el comprobante del ciclo actual de un local (fila de locales_con_cobro). Devuelve error o null. */
function guardar_comprobante(array $local, array $file): ?string {
    $c = $local['cobro'];
    if ($c['estado'] === 'aprobado') return 'El pago de este periodo ya fue aprobado.';
    if ($c['estado'] === 'pendiente') return 'Tu comprobante ya está en revisión.';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return 'Selecciona un archivo (PDF, JPG o PNG de hasta 5 MB).';
    if ($file['size'] > COMPROBANTE_MAX) return 'El archivo supera los 5 MB.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) return 'Formato no permitido. Usa PDF, JPG, PNG o WEBP.';
    $dir = __DIR__ . '/../storage/comprobantes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nombre = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) return 'No se pudo guardar el archivo.';
    $viejo = $c['pago']['archivo'] ?? null;
    // fecha_limite solo se fija en el primer envío: reenviar tras un rechazo NO aplaza el plazo
    db()->prepare("INSERT INTO pagos_local (id_local,periodo,fecha_corte,fecha_limite,archivo,nombre_original,estado) VALUES (?,?,?,?,?,?,'pendiente')
        ON DUPLICATE KEY UPDATE archivo=VALUES(archivo), nombre_original=VALUES(nombre_original), estado='pendiente',
            fecha_envio=NOW(), fecha_revision=NULL, id_revisor=NULL, nota_revision=NULL")
        ->execute([$local['id_local'], $c['periodo'], $c['corte']->format('Y-m-d'), $c['limite']->format('Y-m-d'), $nombre, mb_substr(basename((string)$file['name']), 0, 255)]);
    if ($viejo && is_file($dir . $viejo)) @unlink($dir . $viejo);
    return null;
}

/** Aprobar/rechazar un comprobante. Aprobar extiende el servicio hasta 7 días después del fin del ciclo. Avisa al gerente. */
function revisar_pago(int $idPago, string $decision, int $idRevisor, string $nota = ''): bool {
    $pdo = db();
    $st = $pdo->prepare('SELECT p.*, l.fecha_alta, l.id_empresa, l.nombre_sucursal, c.mes_inicio, c.meses FROM pagos_local p
        JOIN locales l ON l.id_local = p.id_local JOIN ciclos_local c ON c.id_local = p.id_local AND c.numero = p.periodo WHERE p.id_pago = ?');
    $st->execute([$idPago]); $p = $st->fetch();
    if (!$p || $p['estado'] === 'aprobado') return false;
    if ($decision === 'rechazar' && $p['estado'] !== 'pendiente') return false;
    $pdo->beginTransaction();
    if ($decision === 'aprobar') {
        $pdo->prepare("UPDATE pagos_local SET estado='aprobado', fecha_revision=NOW(), id_revisor=?, nota_revision=NULL WHERE id_pago=?")->execute([$idRevisor, $idPago]);
        $vence = corte_n($p['fecha_alta'], (int)$p['mes_inicio'] + max(1, (int)$p['meses']))->modify('+' . DIAS_GRACIA . ' days')->format('Y-m-d');
        $pdo->prepare('UPDATE locales SET fecha_vencimiento = GREATEST(fecha_vencimiento, ?) WHERE id_local = ?')->execute([$vence, $p['id_local']]);
    } else {
        $pdo->prepare("UPDATE pagos_local SET estado='rechazado', fecha_revision=NOW(), id_revisor=?, nota_revision=? WHERE id_pago=?")->execute([$idRevisor, $nota ?: null, $idPago]);
    }
    $pdo->commit();
    $ok = $decision === 'aprobar';
    notificar_gerentes((int)$p['id_empresa'], "rev:$idPago:" . time(), 'pago',
        ($ok ? 'Pago aprobado — ' : 'Comprobante rechazado — ') . $p['nombre_sucursal'],
        $ok ? 'Tu pago fue aprobado y el servicio está al día.' : 'Vuelve a enviarlo antes del ' . fdate($p['fecha_limite']) . ($nota ? '. Motivo: ' . $nota : '.'), 'gerente/sucursales.php');
    return true;
}
