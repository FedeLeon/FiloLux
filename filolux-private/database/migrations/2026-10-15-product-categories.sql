-- Ejecutar en la base existente antes del deploy. Conserva las categorías actuales.
CREATE TABLE IF NOT EXISTS product_categories (
 product_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (product_id, category_id), INDEX category_products (category_id, product_id),
 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO product_categories (product_id, category_id)
SELECT id, category_id FROM products WHERE category_id IS NOT NULL;
