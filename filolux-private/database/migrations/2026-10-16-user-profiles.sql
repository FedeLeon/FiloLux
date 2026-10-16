-- Perfiles y direcciones opcionales. Importar antes del deploy; conserva usuarios y pedidos.
SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN street VARCHAR(160) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='street');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN street_number VARCHAR(20) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='street_number');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN apartment VARCHAR(100) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='apartment');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='city');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN province VARCHAR(100) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='province');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

SET @filolux_ddl=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN postal_code VARCHAR(12) NOT NULL DEFAULT ''''','SELECT 1') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='postal_code');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;

