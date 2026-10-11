CREATE DATABASE IF NOT EXISTS comandapp CHARACTER SET utf8mb4;
USE comandapp;

-- ====================================================================
-- SISTEMA DE GESTIÓN DE PEDIDOS Y MULTI-LOCAL (SaaS)
-- Base de datos para MySQL / MariaDB
-- ====================================================================

-- --------------------------------------------------------------------
-- 1. MÓDULO DE SUPERUSUARIO Y PLANES DE SUSCRIPCIÓN
-- --------------------------------------------------------------------

CREATE TABLE planes_suscripcion (
    id_plan INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    limite_empleados INT NOT NULL,
    precio_mensual DECIMAL(10,2) NOT NULL,
    precio_empleado_extra DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE empresas (
    id_empresa INT AUTO_INCREMENT PRIMARY KEY,
    nombre_comercial VARCHAR(100) NOT NULL,
    razon_social VARCHAR(150) NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --------------------------------------------------------------------
-- 2. MÓDULO MULTI-LOCAL, ROLES Y USUARIOS
-- --------------------------------------------------------------------

CREATE TABLE locales (
    id_local INT AUTO_INCREMENT PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre_sucursal VARCHAR(100) NOT NULL,
    id_plan_activo INT NOT NULL,
    fecha_alta DATE NOT NULL,               -- inicio del ciclo mensual de cobro
    fecha_vencimiento DATE NOT NULL,        -- servicio válido hasta esta fecha
    ultimo_pedido INT NOT NULL DEFAULT 0,   -- contador de pedidos del local
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE,
    FOREIGN KEY (id_plan_activo) REFERENCES planes_suscripcion(id_plan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_empresa INT NULL,                  -- NULL únicamente para el SUPER_ADMIN
    id_local INT NULL,                    -- NULL para GERENTE general o SUPER_ADMIN
    id_rol INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE SET NULL,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --------------------------------------------------------------------
-- 3. MÓDULO DE PRODUCTOS E INVENTARIO POR LOCAL
-- --------------------------------------------------------------------

CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre_categoria VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    id_empresa INT NOT NULL,
    id_categoria INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventario físico independiente por sucursal
CREATE TABLE inventario_local (
    id_inventario INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    id_producto INT NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    UNIQUE KEY uk_local_producto (id_local, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --------------------------------------------------------------------
-- 4. MÓDULO DE CLIENTES, PEDIDOS Y CONSUMOS
-- --------------------------------------------------------------------

CREATE TABLE clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    numero_pedido INT NOT NULL DEFAULT 0,   -- consecutivo por local
    promocion_aplicada TINYINT(1) NOT NULL DEFAULT 0,
    pagado TINYINT(1) NOT NULL DEFAULT 0,        -- independiente del estado del pedido
    fecha_pago TIMESTAMP NULL DEFAULT NULL,
    id_usuario_pago INT NULL,
    id_mesero INT NOT NULL,
    id_mesero_mod INT NULL,               -- último mesero que modificó el pedido
    id_cliente INT NULL,                  -- NULL para clientes anónimos
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP NULL DEFAULT NULL,
    estado ENUM('en proceso', 'cancelado', 'terminado') NOT NULL DEFAULT 'en proceso',
    monto_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (id_local) REFERENCES locales(id_local),
    FOREIGN KEY (id_mesero) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_mesero_mod) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_usuario_pago) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL,
    UNIQUE KEY uk_pedido_local_numero (id_local, numero_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE detalle_pedidos (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario_historico DECIMAL(10,2) NOT NULL,
    es_regalo TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --------------------------------------------------------------------
-- 4b. PAGOS POR SUCURSAL Y PROMOCIONES
-- --------------------------------------------------------------------

-- Comprobantes de pago por local y periodo mensual (pendiente por defecto)
CREATE TABLE pagos_local (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    periodo INT NOT NULL,                 -- 0 = alta del local; 1 = primer corte mensual, etc.
    fecha_corte DATE NOT NULL,
    fecha_limite DATE NOT NULL,           -- corte + 7 días; NO se aplaza al reenviar
    archivo VARCHAR(255) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_revision TIMESTAMP NULL DEFAULT NULL,
    id_revisor INT NULL,
    nota_revision VARCHAR(255) NULL,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    FOREIGN KEY (id_revisor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    UNIQUE KEY uk_pago_local_periodo (id_local, periodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Promoción de lealtad por local: cada N visitas, regalo de X piezas de un producto
CREATE TABLE promociones_local (
    id_promocion INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    activa BOOLEAN NOT NULL DEFAULT FALSE,
    visitas_requeridas INT NOT NULL DEFAULT 3,
    consumo_minimo DECIMAL(10,2) NOT NULL DEFAULT 0,   -- consumo mínimo para que una visita cuente
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    UNIQUE KEY uk_promocion_local (id_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Notificaciones por usuario (únicas por usuario+clave para no duplicarse)
CREATE TABLE notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    clave VARCHAR(120) NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'sistema',
    titulo VARCHAR(120) NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    enlace VARCHAR(150) NULL,
    leida BOOLEAN NOT NULL DEFAULT FALSE,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY uk_notif_usuario_clave (id_usuario, clave),
    KEY idx_notif_usuario (id_usuario, leida, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Duraciones de suscripción (las configura el superusuario)
CREATE TABLE duraciones_suscripcion (
    id_duracion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL,
    meses INT NOT NULL,
    descuento_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    meses_gratis INT NOT NULL DEFAULT 0,
    activa BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO duraciones_suscripcion (nombre, meses, descuento_pct, meses_gratis) VALUES
('Mensual', 1, 0, 0), ('Semestral', 6, 10, 0), ('Anual', 12, 20, 1);

-- Periodos de cobro de cada local (cada uno cubre N meses con un plan y un monto fijados al crearse)
CREATE TABLE ciclos_local (
    id_ciclo INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    numero INT NOT NULL,
    mes_inicio INT NOT NULL,              -- meses transcurridos desde locales.fecha_alta
    meses INT NOT NULL,                   -- meses cubiertos (pagados + gratis)
    fecha_inicio DATE NOT NULL,
    id_plan INT NOT NULL,
    id_duracion INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    FOREIGN KEY (id_plan) REFERENCES planes_suscripcion(id_plan),
    FOREIGN KEY (id_duracion) REFERENCES duraciones_suscripcion(id_duracion),
    UNIQUE KEY uk_ciclo_local_numero (id_local, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cambios de plan/duración programados: se aplican al terminar el ciclo en curso
CREATE TABLE cambios_suscripcion (
    id_cambio INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    id_plan INT NOT NULL,
    id_duracion INT NOT NULL,
    estado ENUM('programado','aplicado','cancelado') NOT NULL DEFAULT 'programado',
    solicitado_por INT NULL,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    FOREIGN KEY (id_plan) REFERENCES planes_suscripcion(id_plan),
    FOREIGN KEY (id_duracion) REFERENCES duraciones_suscripcion(id_duracion),
    FOREIGN KEY (solicitado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    KEY idx_cambio_local (id_local, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Opciones de regalo de la promoción de cada local
CREATE TABLE promocion_opciones (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY,
    id_local INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
    UNIQUE KEY uk_opcion_local_producto (id_local, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- --------------------------------------------------------------------
-- 5. ÍNDICES DE OPTIMIZACIÓN (PARA LOS PANELES Y CONSULTAS RÁPIDAS)
-- --------------------------------------------------------------------

-- Módulo Cocina: Pedidos activos por local
CREATE INDEX idx_pedidos_cocina ON pedidos (id_local, estado, fecha_pedido);

-- Módulo Mesero: Pedidos levantados por mesero
CREATE INDEX idx_pedidos_mesero ON pedidos (id_mesero, estado);

-- Búsqueda rápida de clientes para el programa de descuentos
CREATE INDEX idx_clientes_contacto ON clientes (id_empresa, telefono, email);

-- Búsqueda de inventario
CREATE INDEX idx_inventario_local ON inventario_local (id_local, id_producto);


-- --------------------------------------------------------------------
-- 6. DATOS INICIALES (SEMILLAS / MAESTROS)
-- --------------------------------------------------------------------

INSERT INTO roles (nombre_rol) VALUES 
('SUPER_ADMIN'), 
('GERENTE'), 
('COCINERO'), 
('MESERO'),
('GERENTE_LOCAL');

INSERT INTO planes_suscripcion (nombre, limite_empleados, precio_mensual, precio_empleado_extra) VALUES 
('Básico', 3, 19.99, 5.00),
('Plus', 5, 29.99, 4.50),
('Premium', 15, 59.99, 3.50);


-- --------------------------------------------------------------------
-- 7. TRIGGERS AUTOMÁTICOS DE INVENTARIO Y TOTALLIZACIÓN
-- --------------------------------------------------------------------

DELIMITER //

-- TRIGGER A: Descontar stock al insertar un producto al pedido
CREATE TRIGGER tr_restar_stock_pedido
AFTER INSERT ON detalle_pedidos
FOR EACH ROW
BEGIN
    DECLARE v_id_local INT;
    
    SELECT id_local INTO v_id_local 
    FROM pedidos 
    WHERE id_pedido = NEW.id_pedido;
    
    UPDATE inventario_local 
    SET stock_actual = stock_actual - NEW.cantidad
    WHERE id_local = v_id_local AND id_producto = NEW.id_producto;
END //

-- TRIGGER B: Recalcular monto_total del pedido en automático
CREATE TRIGGER tr_actualizar_monto_pedido
AFTER INSERT ON detalle_pedidos
FOR EACH ROW
BEGIN
    UPDATE pedidos 
    SET monto_total = (
        SELECT COALESCE(SUM(cantidad * precio_unitario_historico), 0)
        FROM detalle_pedidos
        WHERE id_pedido = NEW.id_pedido
    )
    WHERE id_pedido = NEW.id_pedido;
END //

-- TRIGGER C: Devolver stock al cancelar un pedido
CREATE TRIGGER tr_devolver_stock_cancelado
AFTER UPDATE ON pedidos
FOR EACH ROW
BEGIN
    IF NEW.estado = 'cancelado' AND OLD.estado != 'cancelado' THEN
        UPDATE inventario_local il
        JOIN detalle_pedidos dp ON il.id_producto = dp.id_producto
        SET il.stock_actual = il.stock_actual + dp.cantidad
        WHERE dp.id_pedido = NEW.id_pedido AND il.id_local = NEW.id_local;
    END IF;
END //

-- TRIGGER D: Ajustar stock y total al cambiar la cantidad de una línea del pedido
CREATE TRIGGER tr_ajustar_stock_update
AFTER UPDATE ON detalle_pedidos
FOR EACH ROW
BEGIN
    DECLARE v_id_local INT;
    SELECT id_local INTO v_id_local FROM pedidos WHERE id_pedido = NEW.id_pedido;
    UPDATE inventario_local
    SET stock_actual = stock_actual + OLD.cantidad - NEW.cantidad
    WHERE id_local = v_id_local AND id_producto = NEW.id_producto;
    UPDATE pedidos
    SET monto_total = (SELECT COALESCE(SUM(cantidad * precio_unitario_historico), 0) FROM detalle_pedidos WHERE id_pedido = NEW.id_pedido)
    WHERE id_pedido = NEW.id_pedido;
END //

-- TRIGGER E: Devolver stock y recalcular total al quitar una línea del pedido
CREATE TRIGGER tr_devolver_stock_linea_eliminada
AFTER DELETE ON detalle_pedidos
FOR EACH ROW
BEGIN
    DECLARE v_id_local INT;
    SELECT id_local INTO v_id_local FROM pedidos WHERE id_pedido = OLD.id_pedido;
    UPDATE inventario_local
    SET stock_actual = stock_actual + OLD.cantidad
    WHERE id_local = v_id_local AND id_producto = OLD.id_producto;
    UPDATE pedidos
    SET monto_total = (SELECT COALESCE(SUM(cantidad * precio_unitario_historico), 0) FROM detalle_pedidos WHERE id_pedido = OLD.id_pedido)
    WHERE id_pedido = OLD.id_pedido;
END //

DELIMITER ;


-- --------------------------------------------------------------------
-- 8. CONSULTAS REUTILIZABLES PARA CADA MÓDULO (EJEMPLOS)
-- --------------------------------------------------------------------

-- PANEL COCINA: Ver comandas pendientes por local (ordenadas por tiempo de llegada)
-- SELECT p.id_pedido, p.fecha_pedido, pr.nombre AS producto, dp.cantidad 
-- FROM pedidos p
-- JOIN detalle_pedidos dp ON p.id_pedido = dp.id_pedido
-- JOIN productos pr ON dp.id_producto = pr.id_producto
-- WHERE p.id_local = 1 AND p.estado = 'en proceso'
-- ORDER BY p.fecha_pedido ASC;

-- PANEL GERENTE: Recuento de productos vendidos ayer en un local
-- SELECT pr.nombre, SUM(dp.cantidad) AS total_vendido
-- FROM detalle_pedidos dp
-- JOIN pedidos p ON dp.id_pedido = p.id_pedido
-- JOIN productos pr ON dp.id_producto = pr.id_producto
-- WHERE p.id_local = 1 AND p.estado = 'terminado' 
--   AND DATE(p.fecha_pedido) = CURDATE() - INTERVAL 1 DAY
-- GROUP BY pr.id_producto ORDER BY total_vendido DESC;

-- PANEL GERENTE: Rendimiento y ventas por mesero
-- SELECT u.nombre AS mesero, COUNT(p.id_pedido) AS total_pedidos, SUM(p.monto_total) AS total_recaudado
-- FROM pedidos p
-- JOIN usuarios u ON p.id_mesero = u.id_usuario
-- WHERE p.id_local = 1 AND p.estado = 'terminado'
-- GROUP BY u.id_usuario;

-- PANEL SUPERUSUARIO: Control de uso de empleados por local según su plan
-- SELECT e.nombre_comercial, l.nombre_sucursal, ps.nombre AS plan, ps.limite_empleados,
--        COUNT(u.id_usuario) AS total_empleados_registrados,
--        GREATEST(0, COUNT(u.id_usuario) - ps.limite_empleados) AS empleados_extra
-- FROM locales l
-- JOIN empresas e ON l.id_empresa = e.id_empresa
-- JOIN planes_suscripcion ps ON l.id_plan_activo = ps.id_plan
-- LEFT JOIN usuarios u ON u.id_local = l.id_local AND u.activo = TRUE
-- GROUP BY l.id_local;