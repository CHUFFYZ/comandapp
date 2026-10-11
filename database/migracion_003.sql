-- ====================================================================
-- MIGRACIÓN 003: número de pedido consecutivo POR LOCAL
-- Requiere MySQL 8+ o MariaDB 10.2+ (usa ROW_NUMBER para los pedidos existentes)
-- ====================================================================
USE comandapp;

ALTER TABLE locales ADD COLUMN ultimo_pedido INT NOT NULL DEFAULT 0 AFTER fecha_vencimiento;
ALTER TABLE pedidos ADD COLUMN numero_pedido INT NOT NULL DEFAULT 0 AFTER id_local;

-- Numera los pedidos existentes: 1, 2, 3... dentro de cada local
UPDATE pedidos p
JOIN (SELECT id_pedido, ROW_NUMBER() OVER (PARTITION BY id_local ORDER BY id_pedido) AS rn FROM pedidos) x
  ON x.id_pedido = p.id_pedido
SET p.numero_pedido = x.rn;

UPDATE locales l
SET l.ultimo_pedido = (SELECT COALESCE(MAX(p.numero_pedido), 0) FROM pedidos p WHERE p.id_local = l.id_local);

ALTER TABLE pedidos ADD UNIQUE KEY uk_pedido_local_numero (id_local, numero_pedido);
