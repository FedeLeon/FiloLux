-- Colores opcionales y color comprado. Importar antes del deploy. Conserva datos existentes.
SET @filolux_ddl = (
 SELECT IF(COUNT(*)=0, 'ALTER TABLE products ADD COLUMN available_colors TEXT NULL', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='available_colors'
);
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl = (
 SELECT IF(COUNT(*)=0, 'ALTER TABLE order_items ADD COLUMN color VARCHAR(40) NOT NULL DEFAULT ''''', 'SELECT 1')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='order_items' AND COLUMN_NAME='color'
);
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

