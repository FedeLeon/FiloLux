-- Eliminación del catálogo y del panel admin. Conserva pedidos, pagos y datos históricos. Repetible.
SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='deleted_at');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE orders ADD COLUMN admin_deleted_at DATETIME NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='admin_deleted_at');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

