-- Registro de retornos y datos verificados de pagos. Importar antes del deploy. Conserva pedidos y pagos.
SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE orders ADD COLUMN payment_return_data TEXT NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='payment_return_data');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE payments ADD COLUMN details TEXT NULL','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='details');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

