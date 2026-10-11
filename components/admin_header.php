<?php /* Panel del superusuario: reutiliza panel_header */
require_once __DIR__ . '/../includes/tenant.php';
component('panel_header', ['user' => $user, 'titulo' => $titulo, 'activo' => $activo ?? '', 'badge' => 'Admin', 'menu' => panel_menu('SUPER_ADMIN')]);
