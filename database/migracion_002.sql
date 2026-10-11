-- ====================================================================
-- MIGRACIÓN 002: rol GERENTE_LOCAL y modificación de pedidos
-- Ejecuta solo lo que aún no tengas en tu base de datos.
-- ====================================================================
USE comandapp;

INSERT INTO roles (id_rol, nombre_rol) VALUES (5, 'GERENTE_LOCAL');

ALTER TABLE pedidos
    ADD COLUMN fecha_modificacion TIMESTAMP NULL DEFAULT NULL AFTER fecha_pedido,
    ADD COLUMN id_mesero_mod INT NULL AFTER id_mesero,
    ADD CONSTRAINT fk_pedidos_mesero_mod FOREIGN KEY (id_mesero_mod) REFERENCES usuarios(id_usuario) ON DELETE SET NULL;

DELIMITER //

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
