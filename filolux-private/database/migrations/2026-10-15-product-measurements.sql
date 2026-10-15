-- ACTUALIZACIÓN para tiendas existentes. Importar antes de subir los archivos PHP.
-- Agrega campos opcionales; conserva productos, pedidos y configuración.
-- Puede importarse de nuevo sin duplicar columnas.
SET @filolux_ddl = (
 SELECT IF(COUNT(*) = 0,
  'ALTER TABLE products ADD COLUMN dimensions VARCHAR(160) NOT NULL DEFAULT '''' AFTER description',
  'SELECT ''products.dimensions ya existe'' AS resultado')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'dimensions'
);
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl = (
 SELECT IF(COUNT(*) = 0,
  'ALTER TABLE order_items ADD COLUMN dimensions VARCHAR(160) NOT NULL DEFAULT '''' AFTER lead_time',
  'SELECT ''order_items.dimensions ya existe'' AS resultado')
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'dimensions'
);
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;
