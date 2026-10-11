<?php
require_once __DIR__ . '/tenant.php';

/** Generador mínimo de PDF para tickets (fuente Courier, papel de 80 mm, alto automático). Sin librerías externas. */
final class TicketPdf {
    const COLS = 40, W = 226.77, M = 8.0, LEAD = 11.0, SIZE = 8;
    private array $l = [];

    public function add(string $t, bool $bold = false): void { $this->l[] = [$t, $bold]; }
    public function text(string $t, bool $bold = false): void { foreach (self::wrap($t) as $x) $this->add($x, $bold); }
    public function center(string $t, bool $bold = false): void {
        foreach (self::wrap($t) as $x) $this->add(str_repeat(' ', max(0, intdiv(self::COLS - mb_strlen($x), 2))) . $x, $bold);
    }
    public function rule(string $c = '-'): void { $this->add(str_repeat($c, self::COLS)); }
    public function row(string $left, string $right, bool $bold = false): void {
        $room = self::COLS - mb_strlen($right) - 1;
        foreach (self::wrap($left, $room) as $i => $x)
            $this->add($i === 0 ? $x . str_repeat(' ', max(0, $room - mb_strlen($x))) . ' ' . $right : $x, $bold);
    }
    public static function wrap(string $t, int $w = self::COLS): array {
        $t = trim((string)preg_replace('/\s+/u', ' ', $t));
        if ($t === '') return [''];
        $out = []; $cur = '';
        foreach (explode(' ', $t) as $word) {
            while (mb_strlen($word) > $w) { if ($cur !== '') { $out[] = $cur; $cur = ''; } $out[] = mb_substr($word, 0, $w); $word = mb_substr($word, $w); }
            if ($cur === '') $cur = $word;
            elseif (mb_strlen($cur) + 1 + mb_strlen($word) <= $w) $cur .= ' ' . $word;
            else { $out[] = $cur; $cur = $word; }
        }
        if ($cur !== '') $out[] = $cur;
        return $out;
    }
    private static function enc(string $s): string {
        $c = function_exists('iconv') ? @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s) : false;
        if ($c === false) $c = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $c);
    }
    public function output(): string {
        $h = round(self::M * 2 + count($this->l) * self::LEAD + 6, 2);
        $y = $h - self::M - self::SIZE; $c = "BT\n";
        foreach ($this->l as [$t, $b]) {
            $c .= sprintf("/F%d %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj\n", $b ? 2 : 1, self::SIZE, self::M, $y, self::enc($t));
            $y -= self::LEAD;
        }
        $c .= 'ET';
        $o = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
              3 => sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>', self::W, $h),
              4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
              5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
              6 => '<< /Length ' . strlen($c) . " >>\nstream\n" . $c . "\nendstream"];
        $pdf = "%PDF-1.4\n"; $off = [];
        foreach ($o as $i => $body) { $off[$i] = strlen($pdf); $pdf .= "$i 0 obj\n$body\nendobj\n"; }
        $x = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        foreach ($off as $v) $pdf .= sprintf("%010d 00000 n \n", $v);
        return $pdf . "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
    }
}

/** Pedido + líneas si el usuario puede verlo (gerente: su empresa; resto: su local). Cualquier estado. */
function ticket_datos(array $user, int $idPedido): ?array {
    $st = db()->prepare('SELECT p.*, l.nombre_sucursal, e.nombre_comercial, u.nombre AS mesero, um.nombre AS mod_por, c.nombre AS cliente
        FROM pedidos p JOIN locales l ON l.id_local = p.id_local JOIN empresas e ON e.id_empresa = l.id_empresa
        JOIN usuarios u ON u.id_usuario = p.id_mesero LEFT JOIN usuarios um ON um.id_usuario = p.id_mesero_mod
        LEFT JOIN clientes c ON c.id_cliente = p.id_cliente WHERE p.id_pedido = ? AND l.id_empresa = ?');
    $st->execute([$idPedido, $user['id_empresa']]); $p = $st->fetch();
    if (!$p) return null;
    if ($user['rol'] !== 'GERENTE' && (int)$p['id_local'] !== (int)$user['id_local']) return null;
    $st = db()->prepare('SELECT d.cantidad, d.precio_unitario_historico, d.es_regalo, pr.nombre FROM detalle_pedidos d
        JOIN productos pr ON pr.id_producto = d.id_producto WHERE d.id_pedido = ? ORDER BY d.es_regalo, pr.nombre');
    $st->execute([$idPedido]);
    return ['pedido' => $p, 'items' => $st->fetchAll()];
}

function ticket_pdf(array $p, array $items): string {
    $t = new TicketPdf();
    $t->center($p['nombre_comercial'], true); $t->center($p['nombre_sucursal']); $t->rule('=');
    $t->row('Pedido #' . $p['numero_pedido'], date('d/m/Y H:i', strtotime($p['fecha_pedido'])), true);
    $t->text('Mesero: ' . $p['mesero']);
    if ($p['cliente']) $t->text('Cliente: ' . $p['cliente']);
    $t->text('Estado: ' . strtoupper($p['estado']));
    $t->text('Pago: ' . ($p['estado'] === 'cancelado' ? '-' : ($p['pagado'] ? 'PAGADO' : 'PENDIENTE DE PAGO')));
    if ($p['fecha_modificacion']) $t->text('Modificado ' . date('H:i', strtotime($p['fecha_modificacion'])) . ($p['mod_por'] ? ' por ' . $p['mod_por'] : ''));
    $t->rule();
    foreach ($items as $i) $t->row($i['cantidad'] . ' x ' . $i['nombre'] . ($i['es_regalo'] ? ' (REGALO)' : ''), money($i['cantidad'] * $i['precio_unitario_historico']));
    $t->rule(); $t->row('TOTAL', money($p['monto_total']), true); $t->rule('=');
    $t->center('Gracias por su visita'); $t->center('Ticket sin validez fiscal');
    return $t->output();
}
