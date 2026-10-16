-- Notificaciones y pedidos nuevos. Repetible; los pedidos existentes se consideran revisados solo en la primera importación.
SET @filolux_new_column=(SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='admin_reviewed_at');
SET @filolux_ddl=IF(@filolux_new_column,'ALTER TABLE orders ADD COLUMN admin_reviewed_at DATETIME NULL, ADD INDEX unread_orders (admin_reviewed_at,id)','SELECT 1');
PREPARE filolux_statement FROM @filolux_ddl;
EXECUTE filolux_statement;
DEALLOCATE PREPARE filolux_statement;
UPDATE orders SET admin_reviewed_at=CURRENT_TIMESTAMP WHERE @filolux_new_column=1 AND admin_reviewed_at IS NULL;

CREATE TABLE IF NOT EXISTS live_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_key CHAR(64) NOT NULL UNIQUE,
 audience VARCHAR(20) NOT NULL, user_id BIGINT UNSIGNED NULL, type VARCHAR(30) NOT NULL,
 title VARCHAR(160) NOT NULL, body VARCHAR(500) NOT NULL, href VARCHAR(200) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX audience_events (audience,id), INDEX customer_events (user_id,audience,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
