<?php
require_once __DIR__ . '/tenant.php';

/** Productos de la empresa con el stock del local indicado. */
function inventario_rows(int $idLocal, int $idEmpresa): array {
    $st = db()->prepare('SELECT p.id_producto, p.nombre, p.activo, c.nombre_categoria, COALESCE(il.stock_actual,0) AS stock
        FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN inventario_local il ON il.id_producto = p.id_producto AND il.id_local = ?
        WHERE p.id_empresa = ? ORDER BY c.nombre_categoria, p.nombre');
    $st->execute([$idLocal, $idEmpresa]);
    return $st->fetchAll();
}

/** Guarda $_POST['stock'][id_producto] = cantidad para el local (solo productos de la empresa). */
function inventario_guardar(int $idLocal, int $idEmpresa): void {
    $st = db()->prepare('INSERT INTO inventario_local (id_local, id_producto, stock_actual)
        SELECT ?, p.id_producto, ? FROM productos p WHERE p.id_producto = ? AND p.id_empresa = ?
        ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual)');
    foreach (($_POST['stock'] ?? []) as $idp => $s) $st->execute([$idLocal, max(0, (int)$s), (int)$idp, $idEmpresa]);
}
