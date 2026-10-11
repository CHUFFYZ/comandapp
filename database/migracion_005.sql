-- ====================================================================
-- MIGRACIÓN 005: notificaciones, suscripciones por duración, pago de pedidos,
--                consumo mínimo y opciones de regalo
-- ====================================================================
USE comandapp;

ALTER TABLE pedidos
    ADD COLUMN pagado TINYINT(1) NOT NULL DEFAULT 0 AFTER promocion_aplicada,
    ADD COLUMN fecha_pago TIMESTAMP NULL DEFAULT NULL AFTER pagado,
    ADD COLUMN id_usuario_pago INT NULL AFTER fecha_pago,
    ADD CONSTRAINT fk_pedidos_usuario_pago FOREIGN KEY (id_usuario_pago) REFERENCES usuarios(id_usuario) ON DELETE SET NULL;

-- Las columnas id_producto_regalo / cantidad_regalo quedan en desuso (ahora se usa promocion_opciones)
ALTER TABLE promociones_local ADD COLUMN consumo_minimo DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER visitas_requeridas;

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

-- Datos existentes: ciclo 0 mensual para cada local y su regalo único como primera opción
INSERT INTO ciclos_local (id_local, numero, mes_inicio, meses, fecha_inicio, id_plan, id_duracion, monto)
SELECT l.id_local, 0, 0, 1, l.fecha_alta, l.id_plan_activo, 1, ps.precio_mensual
FROM locales l JOIN planes_suscripcion ps ON ps.id_plan = l.id_plan_activo;

INSERT INTO promocion_opciones (id_local, id_producto, cantidad)
SELECT id_local, id_producto_regalo, cantidad_regalo FROM promociones_local WHERE id_producto_regalo IS NOT NULL;
