<?php
require_once __DIR__ . '/pagos.php';

const STOCK_BAJO = 5;

/** Genera (como máximo cada 5 min por sesión) las alertas automáticas del usuario: pagos, stock bajo, comprobantes por revisar. */
function generar_notificaciones(array $u): void {
    if (($_SESSION['notif_ts'] ?? 0) > time() - 300) return;
    $_SESSION['notif_ts'] = time();
    try {
        $pdo = db(); $rol = $u['rol']; $uid = (int)$u['id'];
        if ($rol === 'SUPER_ADMIN') {
            $st = $pdo->query("SELECT p.id_pago, p.fecha_envio, l.nombre_sucursal, e.nombre_comercial FROM pagos_local p
                JOIN locales l ON l.id_local = p.id_local JOIN empresas e ON e.id_empresa = l.id_empresa WHERE p.estado = 'pendiente'");
            foreach ($st->fetchAll() as $r)
                notificar($uid, 'revisar:' . $r['id_pago'] . ':' . strtotime($r['fecha_envio']), 'pago', 'Comprobante por revisar', $r['nombre_comercial'] . ' · ' . $r['nombre_sucursal'], 'admin/pagos.php?f=revisar');
            $n = (int)$pdo->query('SELECT COUNT(*) FROM locales WHERE activo = 1 AND fecha_vencimiento < CURDATE()')->fetchColumn();
            if ($n) notificar($uid, 'susp:' . date('Y-m-d'), 'pago', "$n sucursal(es) suspendidas por falta de pago", 'Revisa sus comprobantes.', 'admin/pagos.php?f=suspendidos');
        } else {
            if ($rol === 'GERENTE') {
                foreach (locales_con_cobro((int)$u['id_empresa']) as $l) {
                    $c = $l['cobro'];
                    if (!$l['activo'] || !in_array($c['estado'], ['sin_envio', 'rechazado'], true)) continue;
                    $plazo = $c['suspendido'] ? 'Servicio suspendido.' : 'Plazo: ' . fdate($c['limite']) . ' (' . max(0, $c['dias']) . ' d).';
                    notificar($uid, 'pago:' . $l['id_local'] . ':' . $c['periodo'] . ':' . $c['estado'] . ':' . $c['nivel'], 'pago',
                        ($c['estado'] === 'rechazado' ? 'Comprobante rechazado — ' : 'Pago pendiente — ') . $l['nombre_sucursal'],
                        ($c['estado'] === 'rechazado' ? 'Vuelve a enviarlo. ' : 'Falta el comprobante de $' . number_format((float)$c['ciclo']['monto'], 2) . '. ') . $plazo, 'gerente/sucursales.php');
                }
            }
            $enlace = ['GERENTE' => 'gerente/inventario.php', 'GERENTE_LOCAL' => 'gerente/inventario.php', 'COCINERO' => 'cocina/inventario.php'][$rol] ?? null;
            $s = $pdo->prepare('SELECT p.nombre, il.stock_actual FROM inventario_local il JOIN productos p ON p.id_producto = il.id_producto
                WHERE il.id_local = ? AND p.activo = 1 AND il.stock_actual <= ? ORDER BY il.stock_actual, p.nombre');
            foreach (locales_empresa($u) as $l) {
                $s->execute([$l['id_local'], STOCK_BAJO]); $rows = $s->fetchAll();
                if (!$rows) continue;
                $lista = array_map(fn($r) => $r['nombre'] . ' (' . (int)$r['stock_actual'] . ')', array_slice($rows, 0, 8));
                $extra = count($rows) - 8;
                notificar($uid, 'stock:' . $l['id_local'] . ':' . date('Y-m-d'), 'stock', 'Stock bajo — ' . $l['nombre_sucursal'],
                    implode(', ', $lista) . ($extra > 0 ? " y $extra más" : ''), $enlace);
            }
        }
        $pdo->prepare('DELETE FROM notificaciones WHERE id_usuario = ? AND leida = 1 AND fecha < DATE_SUB(NOW(), INTERVAL 30 DAY)')->execute([$uid]);
    } catch (Throwable $e) { /* las alertas nunca deben romper la página */ }
}

/** Contador de no leídas y las últimas 6 para la campana. */
function notif_resumen(int $idUsuario): array {
    try {
        $pdo = db();
        $n = $pdo->prepare('SELECT COUNT(*) FROM notificaciones WHERE id_usuario = ? AND leida = 0'); $n->execute([$idUsuario]);
        $l = $pdo->prepare('SELECT * FROM notificaciones WHERE id_usuario = ? ORDER BY leida ASC, fecha DESC LIMIT 6'); $l->execute([$idUsuario]);
        return ['no_leidas' => (int)$n->fetchColumn(), 'ultimas' => $l->fetchAll()];
    } catch (Throwable $e) { return ['no_leidas' => 0, 'ultimas' => []]; }
}

function notif_icono(string $tipo): string {
    return ['pago' => 'bi-cash-coin', 'stock' => 'bi-box-seam', 'suscripcion' => 'bi-arrow-repeat'][$tipo] ?? 'bi-info-circle';
}
function notif_tiempo(string $fecha): string {
    $s = time() - strtotime($fecha);
    if ($s < 60) return 'ahora';
    if ($s < 3600) return 'hace ' . intdiv($s, 60) . ' min';
    if ($s < 86400) return 'hace ' . intdiv($s, 3600) . ' h';
    return date('d/m H:i', strtotime($fecha));
}
