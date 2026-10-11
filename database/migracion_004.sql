-- ====================================================================
-- MIGRACIÓN 004: pagos por sucursal, promociones de lealtad y regalos
-- ====================================================================
USE comandapp;

ALTER TABLE locales ADD COLUMN fecha_alta DATE NULL AFTER id_plan_activo;
UPDATE locales SET fecha_alta = CURDATE();          -- los locales existentes empiezan su ciclo hoy
ALTER TABLE locales MODIFY fecha_alta DATE NOT NULL;

ALTER TABLE pedidos ADD COLUMN promocion_aplicada TINYINT(1) NOT NULL DEFAULT 0 AFTER numero_pedido;
ALTER TABLE detalle_pedidos ADD COLUMN es_regalo TINYINT(1) NOT NULL DEFAULT 0 AFTER precio_unitario_historico;

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
    id_producto_regalo INT NULL,
    cantidad_regalo INT NOT NULL DEFAULT 1,
    FOREIGN KEY (id_local) REFERENCES locales(id_local) ON DELETE CASCADE,
    FOREIGN KEY (id_producto_regalo) REFERENCES productos(id_producto) ON DELETE SET NULL,
    UNIQUE KEY uk_promocion_local (id_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
