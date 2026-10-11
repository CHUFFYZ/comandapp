# ComandApp
Estructura:
- config/      configuración y conexión PDO
- includes/    funciones auxiliares (e, url, asset, component, section, get_planes)
- components/  head, header, menu, button, plan_card, footer, scripts
- sections/    bloques de la landing (hero, features, roles, pricing, faq, cta)
- pages/       login, registro y paneles (siguientes pasos)
- assets/      css, js, img
- database/    schema.sql
Instalación: importa database/schema.sql, ajusta config/config.php (BASE_URL y credenciales) y abre /comandapp/ en XAMPP/Laragon.

## Superusuario
1. Crear: php database/crear_superadmin.php "Nombre" correo@dominio.com "contraseña"
2. Entrar en /comandapp/pages/login.php → te lleva a /admin/
Requiere PHP 8+.



## Migraciones
- database/migracion_002.sql: rol GERENTE_LOCAL y modificación de pedidos
- database/migracion_003.sql: número de pedido consecutivo por local
## Registro público
- /pages/registro.php crea empresa + primer local (30 días) + gerente general y lo deja dentro del panel.

## Migración 004 y cobros
- database/migracion_004.sql: fecha_alta, pagos_local, promociones_local, regalos.
- Cobro: alta + 7 días para el 1.er comprobante; luego corte mensual (+7 días). Superusuario aprueba en /admin/pagos.php.
- Comprobantes en storage/comprobantes (bloqueado por .htaccess; se sirve con pages/comprobante.php).
- Edita PAGO_INSTRUCCIONES en config/config.php. Si subes archivos >2 MB, sube upload_max_filesize en php.ini.
- Ticket PDF: pages/ticket.php?id=ID (papel 80 mm).

## Migración 005
- database/migracion_005.sql: notificaciones, duraciones y ciclos de suscripción, cambios programados, pago de pedidos, consumo mínimo y opciones de regalo.
- Notificaciones: campana en todos los paneles (se generan al navegar, cada 5 min por sesión) + pages/notificaciones.php.
- Suscripciones: el gerente y el superusuario programan cambios de plan/duración que se activan al terminar el ciclo en curso (cancelables antes).
- Pedidos: "pagado" es independiente del estado. Concluidos = pagados y terminados (y cancelados).
