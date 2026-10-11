<?php
require_once __DIR__ . '/auth.php';

/** Menús por rol (hrefs relativos a la carpeta de cada panel). */
function panel_menu(string $rol): array {
    $m = fn(string $l, string $h) => ['label' => $l, 'href' => $h];
    return [
        'SUPER_ADMIN'   => [$m('Panel','index.php'), $m('Empresas','empresas.php'), $m('Usuarios','usuarios.php'), $m('Locales','locales.php'), $m('Pagos','pagos.php'), $m('Planes','planes.php')],
        'GERENTE'       => [$m('Panel','index.php'), $m('Pedidos','pedidos.php'), $m('Productos','productos.php'), $m('Inventario','inventario.php'),
                            $m('Empleados','empleados.php'), $m('Promociones','promociones.php'), $m('Sucursales','sucursales.php'), $m('Mi perfil','usuario_editar.php')],
        'GERENTE_LOCAL' => [$m('Panel','index.php'), $m('Pedidos','pedidos.php'), $m('Inventario','inventario.php'), $m('Empleados','empleados.php'),
                            $m('Promociones','promociones.php'), $m('Mi perfil','usuario_editar.php')],
        'MESERO'        => [$m('Nuevo pedido','index.php'), $m('Pedidos','pedidos.php')],
        'COCINERO'      => [$m('Comandas','index.php'), $m('Pedidos del día','pedidos.php'), $m('Inventario','inventario.php')],
    ][$rol] ?? [];
}

function rol_label(string $rol): string {
    return ['SUPER_ADMIN'=>'Admin','GERENTE'=>'Gerente','GERENTE_LOCAL'=>'Gerente de local','MESERO'=>'Mesero','COCINERO'=>'Cocinero'][$rol] ?? $rol;
}
function rol_badge(string $rol): string { return $rol === 'GERENTE_LOCAL' ? 'Gerente local' : rol_label($rol); }

/** Locales con servicio vigente (activos y con fecha_vencimiento no vencida) del usuario. */
function locales_empresa(array $u): array {
    $sql = 'SELECT l.*, ps.nombre AS plan, ps.limite_empleados, ps.precio_empleado_extra
            FROM locales l JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo
            WHERE l.id_empresa = ? AND l.activo = 1 AND l.fecha_vencimiento >= CURDATE()';
    $p = [$u['id_empresa']];
    if ($u['rol'] === 'GERENTE_LOCAL' && !$u['id_local']) return [];
    if ($u['id_local']) { $sql .= ' AND l.id_local = ?'; $p[] = $u['id_local']; }
    $st = db()->prepare($sql . ' ORDER BY l.nombre_sucursal');
    $st->execute($p);
    return $st->fetchAll();
}

/** Local seleccionado (GET ?local= se recuerda en sesión). Siempre uno de $locales. */
function local_actual(array $locales): ?array {
    if (!$locales) return null;
    if (isset($_GET['local'])) $_SESSION['local_sel'] = (int)$_GET['local'];
    foreach ($locales as $l) if ((int)$l['id_local'] === (int)($_SESSION['local_sel'] ?? 0)) return $l;
    return $locales[0];
}

/** Mensaje y cierre de página cuando no hay local con servicio activo. */
function sin_local_y_salir(): void {
    $u = current_user();
    echo '<div class="alert alert-warning">No hay un local con servicio activo.'
       . ($u && $u['rol'] === 'GERENTE' ? ' Revisa el estado de pago en <a href="sucursales.php">Sucursales</a>.' : ' Contacta a tu gerente.') . '</div>';
    component('admin_footer');
    exit;
}

function badge_estado(string $estado): string {
    $c = ['en proceso' => 'warning', 'terminado' => 'success', 'cancelado' => 'secondary'][$estado] ?? 'light';
    return '<span class="badge text-bg-' . $c . '">' . e($estado) . '</span>';
}

/** Carpeta del panel de cada rol. */
function panel_base(string $rol): string {
    return ['SUPER_ADMIN' => 'admin', 'GERENTE' => 'gerente', 'GERENTE_LOCAL' => 'gerente', 'MESERO' => 'mesero', 'COCINERO' => 'cocina'][$rol] ?? '';
}
