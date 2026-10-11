<?php
require_once __DIR__ . '/../includes/pagos.php';
$user = require_role('SUPER_ADMIN', 'GERENTE');
$st = db()->prepare('SELECT p.*, l.id_empresa FROM pagos_local p JOIN locales l ON l.id_local = p.id_local WHERE p.id_pago = ?');
$st->execute([(int)($_GET['id'] ?? 0)]); $p = $st->fetch();
if (!$p || ($user['rol'] === 'GERENTE' && (int)$p['id_empresa'] !== (int)$user['id_empresa'])) { http_response_code(404); exit('No encontrado.'); }
$file = __DIR__ . '/../storage/comprobantes/' . basename($p['archivo']);
if (!is_file($file)) { http_response_code(404); exit('Archivo no disponible.'); }
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file));
header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\-]/', '_', $p['nombre_original']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($file));
readfile($file);
