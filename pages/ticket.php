<?php
require_once __DIR__ . '/../includes/ticket.php';
$user = require_role('GERENTE', 'GERENTE_LOCAL', 'MESERO', 'COCINERO');
$d = ticket_datos($user, (int)($_GET['id'] ?? 0));
if (!$d) { http_response_code(404); exit('Pedido no encontrado.'); }
$pdf = ticket_pdf($d['pedido'], $d['items']);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="ticket-' . (int)$d['pedido']['numero_pedido'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
