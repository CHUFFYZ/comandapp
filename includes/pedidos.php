<?php
require_once __DIR__ . '/tenant.php';

/**
 * Menú para tomar/editar pedidos, agrupado por categoría.
 * qty_actual = cantidad ya incluida en el pedido $idPedido (0 si es nuevo).
 * En edición se incluyen también productos ocultos que ya estaban en el pedido.
 */
function productos_menu(int $idLocal, int $idEmpresa, int $idPedido = 0): array {
    $st = db()->prepare('SELECT p.id_producto, p.nombre, p.precio_venta, c.nombre_categoria,
            COALESCE(il.stock_actual,0) AS stock, COALESCE(dp.cantidad,0) AS qty_actual
        FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN inventario_local il ON il.id_producto = p.id_producto AND il.id_local = ?
        LEFT JOIN detalle_pedidos dp ON dp.id_producto = p.id_producto AND dp.id_pedido = ? AND dp.es_regalo = 0
        WHERE p.id_empresa = ? AND (p.activo = 1 OR dp.id_detalle IS NOT NULL)
        ORDER BY c.nombre_categoria, p.nombre');
    $st->execute([$idLocal, $idPedido, $idEmpresa]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[$r['nombre_categoria']][] = $r;
    return $out;
}

/** Cantidades enviadas por el formulario: [id_producto => cantidad > 0]. */
function cantidades_post(): array {
    return array_filter(array_map('intval', $_POST['qty'] ?? []), fn($q) => $q > 0);
}

/**
 * Busca (o crea) el cliente. Basta con el teléfono, con el nombre o con ambos.
 * Con teléfono se busca por teléfono; solo con nombre, por nombre (sin distinguir mayúsculas/acentos).
 * Devuelve null si no se capturó ninguno.
 */
function cliente_desde_post(PDO $pdo, int $idEmpresa): ?int {
    $idSel = (int)($_POST['id_cliente'] ?? 0);      // cliente elegido con el buscador
    if ($idSel > 0) {
        $s = $pdo->prepare('SELECT id_cliente FROM clientes WHERE id_cliente=? AND id_empresa=?'); $s->execute([$idSel, $idEmpresa]);
        if ($s->fetchColumn()) return $idSel;
    }
    $tel = trim($_POST['cliente_tel'] ?? ''); $nom = trim($_POST['cliente_nombre'] ?? '');
    if ($tel === '' && $nom === '') return null;
    if ($tel !== '') {
        $s = $pdo->prepare('SELECT id_cliente FROM clientes WHERE id_empresa=? AND telefono=? LIMIT 1');
        $s->execute([$idEmpresa, $tel]);
    } else {
        $s = $pdo->prepare('SELECT id_cliente FROM clientes WHERE id_empresa=? AND telefono IS NULL AND nombre COLLATE utf8mb4_general_ci = ? LIMIT 1');
        $s->execute([$idEmpresa, $nom]);
    }
    $id = $s->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare('INSERT INTO clientes (id_empresa,nombre,telefono) VALUES (?,?,?)')
        ->execute([$idEmpresa, $nom !== '' ? $nom : $tel, $tel !== '' ? $tel : null]);
    return (int)$pdo->lastInsertId();
}

/** Promoción activa del local con sus opciones de regalo (o null si no está activa o no tiene opciones). */
function promo_local(PDO $pdo, int $idLocal): ?array {
    $st = $pdo->prepare('SELECT * FROM promociones_local WHERE id_local = ? AND activa = 1');
    $st->execute([$idLocal]); $promo = $st->fetch();
    if (!$promo) return null;
    $st = $pdo->prepare('SELECT o.id_opcion, o.id_producto, o.cantidad, p.nombre AS producto FROM promocion_opciones o
        JOIN productos p ON p.id_producto = o.id_producto WHERE o.id_local = ? AND p.activo = 1 ORDER BY p.nombre');
    $st->execute([$idLocal]); $promo['opciones'] = $st->fetchAll();
    return $promo['opciones'] ? $promo : null;
}

/**
 * Lealtad de un cliente en un local. Una visita cuenta si el pedido está TERMINADO, no usó regalo y su total
 * alcanza el consumo mínimo. Al completar N visitas queda un regalo disponible para el siguiente pedido.
 */
function lealtad_cliente(PDO $pdo, int $idLocal, int $idCliente): array {
    $promo = promo_local($pdo, $idLocal);
    if (!$promo) return ['promo' => null, 'visitas' => 0, 'requeridas' => 0, 'minimo' => 0.0, 'regalo' => false];
    $min = (float)$promo['consumo_minimo'];
    $st = $pdo->prepare("SELECT COALESCE(SUM(estado='terminado' AND promocion_aplicada=0 AND monto_total >= ?),0) AS v,
                                COALESCE(SUM(promocion_aplicada=1 AND estado<>'cancelado'),0) AS c
                         FROM pedidos WHERE id_local=? AND id_cliente=?");
    $st->execute([$min, $idLocal, $idCliente]); $r = $st->fetch();
    $n = max(1, (int)$promo['visitas_requeridas']); $v = (int)$r['v'];
    $disp = intdiv($v, $n) - (int)$r['c'] > 0;
    return ['promo' => $promo, 'visitas' => $disp ? $n : $v % $n, 'requeridas' => $n, 'minimo' => $min, 'regalo' => $disp];
}

/** Marca un pedido como pagado / no pagado (independiente de su estado). Mesero, cocinero y gerentes del local. */
function pedido_marcar_pago(array $user, int $idPedido, bool $pagado): bool {
    $st = db()->prepare('SELECT p.id_local, p.estado, l.id_empresa FROM pedidos p JOIN locales l ON l.id_local = p.id_local WHERE p.id_pedido = ?');
    $st->execute([$idPedido]); $p = $st->fetch();
    if (!$p || $p['estado'] === 'cancelado' || (int)$p['id_empresa'] !== (int)$user['id_empresa']) return false;
    if ($user['rol'] !== 'GERENTE' && (int)$p['id_local'] !== (int)$user['id_local']) return false;
    $f = $pagado ? 1 : 0;
    db()->prepare('UPDATE pedidos SET pagado = ?, fecha_pago = IF(? = 1, NOW(), NULL), id_usuario_pago = IF(? = 1, ?, NULL) WHERE id_pedido = ?')
        ->execute([$f, $f, $f, $user['id'], $idPedido]);
    return true;
}

/** Atiende POST accion=pagar|despagar. Devuelve true si lo procesó. */
function manejar_post_pago(array $user): bool {
    $a = $_POST['accion'] ?? '';
    if (!in_array($a, ['pagar', 'despagar'], true)) return false;
    $ok = pedido_marcar_pago($user, (int)($_POST['id'] ?? 0), $a === 'pagar');
    flash($ok ? ($a === 'pagar' ? 'Pedido marcado como pagado.' : 'Pedido marcado como NO pagado.') : 'No se pudo actualizar el pago.', $ok ? 'success' : 'danger');
    return true;
}

/** Activos = en proceso o terminados sin pagar. Concluidos = cancelados o (terminados y pagados). */
function sql_vista(string $vista): string {
    return $vista === 'concluidos' ? " AND (p.estado='cancelado' OR (p.estado='terminado' AND p.pagado=1))"
                                   : " AND (p.estado='en proceso' OR (p.estado='terminado' AND p.pagado=0))";
}

function badge_pago(bool $pagado, string $estado): string {
    if ($estado === 'cancelado') return '';
    return $pagado ? ' <span class="badge text-bg-success">Pagado</span>' : ' <span class="badge text-bg-light border text-danger">Sin pagar</span>';
}

/** Pedidos de un local. Filtros: mesero, otros (id a excluir), estado, q (cliente/teléfono/#), hoy. */
function listar_pedidos(PDO $pdo, int $idLocal, string $vista, array $f = []): array {
    $sql = "SELECT p.*, c.nombre AS cli_nombre, u.nombre AS mesero, um.nombre AS mod_por,
        (SELECT GROUP_CONCAT(CONCAT(d.cantidad,'× ',pr.nombre,IF(d.es_regalo=1,' (regalo)','')) ORDER BY pr.nombre SEPARATOR ', ')
           FROM detalle_pedidos d JOIN productos pr ON pr.id_producto = d.id_producto WHERE d.id_pedido = p.id_pedido) AS resumen
        FROM pedidos p JOIN usuarios u ON u.id_usuario = p.id_mesero
        LEFT JOIN usuarios um ON um.id_usuario = p.id_mesero_mod LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
        WHERE p.id_local = ?" . sql_vista($vista);
    $par = [$idLocal];
    if (!empty($f['mesero'])) { $sql .= ' AND p.id_mesero = ?';  $par[] = $f['mesero']; }
    if (!empty($f['otros']))  { $sql .= ' AND p.id_mesero <> ?'; $par[] = $f['otros']; }
    if (!empty($f['estado'])) { $sql .= ' AND p.estado = ?';     $par[] = $f['estado']; }
    if (!empty($f['hoy']))    { $sql .= ' AND p.fecha_pedido >= CURDATE()'; }
    $q = trim($f['q'] ?? '');
    if ($q !== '') {   // la collation _ci ignora mayúsculas y acentos
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $sql .= ' AND (c.nombre COLLATE utf8mb4_general_ci LIKE ? OR c.telefono LIKE ?'; array_push($par, $like, $like);
        if (ctype_digit($q)) { $sql .= ' OR p.numero_pedido = ?'; $par[] = (int)$q; }
        $sql .= ')';
    }
    $st = $pdo->prepare($sql . ' ORDER BY p.fecha_pedido DESC LIMIT 100');
    $st->execute($par);
    return $st->fetchAll();
}

function volver_a(string $path) { redirect($path . ($_GET ? '?' . http_build_query($_GET) : '')); }
function url_con(array $cambios): string { return '?' . http_build_query(array_merge($_GET, $cambios)); }
