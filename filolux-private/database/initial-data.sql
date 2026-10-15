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
