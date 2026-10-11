<?php
require_once __DIR__ . '/../includes/pedidos.php';
$user = require_role('MESERO'); $pdo = db();
header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? ''); $tel = trim($_GET['tel'] ?? ''); $rows = [];
$base = 'SELECT id_cliente, nombre, telefono FROM clientes WHERE id_empresa=? AND ';
if ($tel !== '') { $st = $pdo->prepare($base . 'telefono=? LIMIT 1'); $st->execute([$user['id_empresa'], $tel]); $rows = $st->fetchAll(); }
elseif (mb_strlen($q) >= 2) {   // la collation _ci ignora mayúsculas y acentos
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st = $pdo->prepare($base . '(nombre COLLATE utf8mb4_general_ci LIKE ? OR telefono LIKE ?) ORDER BY nombre LIMIT 8');
    $st->execute([$user['id_empresa'], $like, $like]); $rows = $st->fetchAll();
}
$out = [];
foreach ($rows as $r) {
    $l = lealtad_cliente($pdo, (int)$user['id_local'], (int)$r['id_cliente']);
    $out[] = ['id' => (int)$r['id_cliente'], 'nombre' => $r['nombre'], 'telefono' => $r['telefono'], 'promo' => (bool)$l['promo'],
              'visitas' => $l['visitas'], 'requeridas' => $l['requeridas'], 'minimo' => (float)$l['minimo'], 'regalo' => $l['regalo'],
              'opciones' => array_map(fn($o) => ['id' => (int)$o['id_opcion'], 'txt' => $o['cantidad'] . ' × ' . $o['producto']], $l['promo']['opciones'] ?? [])];
}
echo json_encode(['clientes' => $out], JSON_UNESCAPED_UNICODE);
