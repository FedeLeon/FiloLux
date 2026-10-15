-- FiloLux: importar solo en una base MySQL vacía.
-- Editar el correo del administrador antes de importar.

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'customer', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(80) PRIMARY KEY, `value` MEDIUMTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id BIGINT UNSIGNED NULL, name VARCHAR(160) NOT NULL,
 description TEXT NOT NULL, dimensions VARCHAR(160) NOT NULL DEFAULT '', price_cents BIGINT UNSIGNED NOT NULL DEFAULT 0, lead_time VARCHAR(160) NOT NULL,
 mode VARCHAR(20) NOT NULL DEFAULT 'buy', active TINYINT NOT NULL DEFAULT 1, featured TINYINT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS product_categories (
 product_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (product_id, category_id), INDEX category_products (category_id, product_id),
 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS product_images (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, checkout_key CHAR(64) NOT NULL UNIQUE,
 customer_name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL,
 delivery VARCHAR(20) NOT NULL, address TEXT NOT NULL, notes TEXT NOT NULL,
 subtotal_cents BIGINT UNSIGNED NOT NULL, shipping_cents BIGINT UNSIGNED NOT NULL, total_cents BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'pendiente', payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
 payment_mode VARCHAR(20) NOT NULL, preference_id VARCHAR(150) NULL, checkout_url TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS order_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, quantity INT UNSIGNED NOT NULL, price_cents BIGINT UNSIGNED NOT NULL, lead_time VARCHAR(160) NOT NULL, dimensions VARCHAR(160) NOT NULL DEFAULT '', FOREIGN KEY(order_id) REFERENCES orders(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payments (id VARCHAR(100) PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, status VARCHAR(30) NOT NULL, amount_cents BIGINT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES orders(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS password_resets (email VARCHAR(190) PRIMARY KEY, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS rate_limits (bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 1, expires_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL, phone VARCHAR(40) NOT NULL, message TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
 submission_key CHAR(64) NOT NULL UNIQUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 responded_at DATETIME NULL,
 INDEX message_status_date (status, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DATOS INICIALES: importar después de schema.sql, solo para una tienda nueva.
-- Cambiar admin@filolux.invalid por tu correo real ANTES de importar.
-- El acceso admin se verifica exclusivamente con el hash del archivo privado .env.
INSERT INTO users (name, email, password, role)
SELECT 'Administrador', 'admin@filolux.invalid', 'ENV_MANAGED', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin');

-- No modifica configuraciones que ya existan.
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('brand', 'FiloLux'),
('hero_title', 'De una idea a algo extraordinario.'),
('hero_text', 'Diseño y corte láser a medida. Creamos piezas únicas para regalar, emprender y transformar tus espacios.'),
('about', 'Cada pieza empieza con una idea. La transformamos con precisión, diseño y dedicación, fabricando especialmente para vos.'),
('shipping_price', '0'),
('mp_mode', 'test'),
('pickup', 'Coordinamos el retiro por WhatsApp.');
