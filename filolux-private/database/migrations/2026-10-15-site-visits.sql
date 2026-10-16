-- Visitas y actividad. Repetible; no modifica productos, clientes ni pedidos.
CREATE TABLE IF NOT EXISTS visit_locations (
 ip VARCHAR(45) PRIMARY KEY,
 country VARCHAR(100) NOT NULL DEFAULT '', region VARCHAR(100) NOT NULL DEFAULT '', city VARCHAR(100) NOT NULL DEFAULT '',
 status VARCHAR(20) NOT NULL DEFAULT 'pending', checked_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS site_visits (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token CHAR(64) NOT NULL UNIQUE,
 ip VARCHAR(45) NOT NULL, device VARCHAR(20) NOT NULL, browser VARCHAR(40) NOT NULL, os VARCHAR(40) NOT NULL,
 user_agent VARCHAR(512) NOT NULL, started_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL,
 INDEX visits_started (started_at, id), INDEX visits_last_seen (last_seen_at), INDEX visits_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS visit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, visit_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(30) NOT NULL, path VARCHAR(190) NOT NULL, product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(160) NOT NULL DEFAULT '', quantity SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 INDEX visit_timeline (visit_id, id), INDEX event_type_date (type, created_at), INDEX event_retention (created_at),
 FOREIGN KEY (visit_id) REFERENCES site_visits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
