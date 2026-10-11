<?php
// Configuración general de la aplicación
define('APP_NAME', 'ComandApp');
define('APP_TAGLINE', 'Pedidos, cocina e inventario de todos tus locales en un solo lugar');
define('BASE_URL', '/'); // Ajusta según tu servidor (ej. '/' en producción)
define('CURRENCY', 'MXN');

// Base de datos
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'gecomanda');
define('DB_USER', 'root');
define('DB_PASS', '');

// Menú principal (reutilizado por header y footer)
$MENU_PRINCIPAL = [
    ['label' => 'Inicio',        'href' => '#inicio'],
    ['label' => 'Funciones',     'href' => '#funciones'],
    ['label' => 'Cómo funciona', 'href' => '#como-funciona'],
    ['label' => 'Precios',       'href' => '#precios'],
    ['label' => 'Preguntas',     'href' => '#faq'],
];


// Texto que ve el gerente general en "Sucursales" con los datos para pagar (edítalo con tu cuenta/CLABE)
define('PAGO_INSTRUCCIONES', 'Realiza tu transferencia o depósito a la cuenta indicada por soporte y sube aquí el comprobante (PDF, JPG o PNG, máx. 5 MB).');
