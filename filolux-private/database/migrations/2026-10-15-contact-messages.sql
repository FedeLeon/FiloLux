-- Consultas a medida. Importar antes del deploy; no modifica datos existentes.
CREATE TABLE IF NOT EXISTS contact_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL, phone VARCHAR(40) NOT NULL, message TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
 submission_key CHAR(64) NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 responded_at DATETIME NULL,
 INDEX message_status_date (status, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
