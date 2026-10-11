<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . ltrim($path, '/'); }
function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }
function money($n): string { return '$' . number_format((float)$n, 2); }

/** Carga un componente reutilizable pasándole variables. */
function component(string $name, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    include __DIR__ . '/../components/' . $name . '.php';
}

/** Carga una sección de la landing. */
function section(string $name, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    include __DIR__ . '/../sections/' . $name . '.php';
}

/**
 * Planes desde la tabla planes_suscripcion.
 * Si la BD no responde, usa los valores semilla para que la página siempre cargue.
 */
function get_planes(): array {
    try {
        return db()->query(
            'SELECT id_plan, nombre, limite_empleados, precio_mensual, precio_empleado_extra
             FROM planes_suscripcion ORDER BY precio_mensual ASC'
        )->fetchAll();
    } catch (Throwable $e) {
        return [
            ['id_plan'=>1,'nombre'=>'Básico','limite_empleados'=>3,'precio_mensual'=>19.99,'precio_empleado_extra'=>5.00],
            ['id_plan'=>2,'nombre'=>'Plus','limite_empleados'=>5,'precio_mensual'=>29.99,'precio_empleado_extra'=>4.50],
            ['id_plan'=>3,'nombre'=>'Premium','limite_empleados'=>15,'precio_mensual'=>59.99,'precio_empleado_extra'=>3.50],
        ];
    }
}

/** Estado de una fecha de vencimiento: [texto, clase bootstrap]. */
function estado_vencimiento(string $fecha): array {
    $dias = (int) floor((strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400);
    if ($dias < 0)  return ['Vencido hace ' . abs($dias) . ' d', 'danger'];
    if ($dias <= 7) return ['Vence en ' . $dias . ' d', 'warning'];
    return ['Vigente', 'success'];
}
