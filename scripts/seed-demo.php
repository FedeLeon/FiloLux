<?php
declare(strict_types=1);

// Explicit, repeatable local seed. Never executed by the app or the deploy.
if (PHP_SAPI !== 'cli') exit;
define('ROOT', dirname(__DIR__).'/filolux-private');
$config = require ROOT.'/config.php';
$host = parse_url($config['url'] ?? '', PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1'], true)
    || !in_array($config['db']['host'] ?? '', ['localhost', '127.0.0.1'], true)
    || ($config['db']['name'] ?? '') !== 'filolux'
    || (int)($config['db']['port'] ?? 0) !== 3306) {
    fwrite(STDERR, "Solo se permite cargar ejemplos en la base local filolux, puerto 3306 y URL local.\n");
    exit(1);
}
$GLOBALS['config'] = $config;
require ROOT.'/app/core.php';

// Name, amount in ARS, dimensions, description. A null price means WhatsApp quote.
$catalog = [
    'Decoración' => [
        ['Lámpara geométrica', 24500, '20 × 20 × 30 cm', 'Lámpara de MDF con trama geométrica. Luz cálida y acabado natural.'],
        ['Cuadro botánico', 18900, '40 × 30 cm', 'Cuadro calado con hojas y marco de madera, para decorar una pared.'],
        ['Reloj de pared circular', 21500, 'Ø 30 cm', 'Reloj con números cortados a láser y diseño minimalista.'],
        ['Panel decorativo a medida', null, 'Medidas a definir', 'Panel calado personalizado para ambientar tu espacio. Consultá diseños y medidas.'],
    ],
    'Regalos personalizados' => [
        ['Llavero con nombre', 2500, '8 × 3 cm', 'Llavero de madera con nombre grabado y argolla metálica.'],
        ['Portarretrato especial', 9800, '22 × 17 cm', 'Marco para una foto de 10 × 15 cm con detalles grabados.'],
        ['Caja de regalo', 12500, '20 × 15 × 10 cm', 'Caja de MDF con tapa calada para presentar un regalo especial.'],
        ['Mapa de una fecha especial', null, '30 × 40 cm, adaptable', 'Una pieza personalizada para recordar una fecha y un lugar. Consultá las opciones.'],
    ],
    'Organización' => [
        ['Organizador de escritorio', 16800, '25 × 15 × 12 cm', 'Compartimentos para lápices, notas y accesorios en MDF natural.'],
        ['Soporte para celular', 6500, '9 × 10 × 15 cm', 'Soporte encastrable para tener el celular a mano en tu escritorio.'],
        ['Bandeja para accesorios', 8900, '22 × 16 × 4 cm', 'Bandeja de madera para organizar llaves y pequeños objetos.'],
        ['Organizador modular a medida', null, 'Medidas a definir', 'Diseñamos los compartimentos según el espacio y los objetos que necesitás guardar.'],
    ],
    'Eventos' => [
        ['Topper Feliz cumpleaños', 4200, '18 × 22 cm', 'Decoración de madera para torta con una frase calada.'],
        ['Número de mesa', 3800, '10 × 15 cm', 'Número de madera con base encastrable para ubicar a tus invitados.'],
        ['Souvenirs: pack de 10', 14500, '6 × 6 cm por pieza', 'Pack de diez recuerdos de madera con un motivo floral grabado.'],
        ['Cartel de bienvenida para evento', null, 'Desde 50 × 70 cm', 'Cartel personalizado con nombres, fecha y estilo del evento. Consultá materiales.'],
    ],
    'Para tu negocio' => [
        ['Cartel Abierto y cerrado', 11000, '25 × 15 cm', 'Cartel reversible de madera para la puerta de tu local.'],
        ['Exhibidor de aros', 13500, '20 × 10 × 30 cm', 'Exhibidor con base encastrable y perforaciones para accesorios.'],
        ['Porta QR de mostrador', 7500, '12 × 8 × 18 cm', 'Soporte de madera con espacio para una tarjeta QR impresa de ejemplo.'],
        ['Logo corpóreo para tu marca', null, 'Medidas a definir', 'Letras y símbolos cortados a láser para la identidad de tu negocio. Consultá opciones.'],
    ],
];

$createdCategories = $createdProducts = $existingProducts = 0;
$pdo = db();
$pdo->beginTransaction();
try {
    foreach ($catalog as $category => $products) {
        $row = one('SELECT id FROM categories WHERE name=?', [$category]);
        if ($row) {
            $categoryId = (int)$row['id'];
        } else {
            query('INSERT INTO categories (name) VALUES (?)', [$category]);
            $categoryId = (int)$pdo->lastInsertId();
            $createdCategories++;
        }
        foreach ($products as $index => [$name, $price, $dimensions, $description]) {
            $name .= ' (Demo)';
            if (one('SELECT id FROM products WHERE name=? LIMIT 1', [$name])) {
                $existingProducts++;
                continue;
            }
            query('INSERT INTO products (category_id,name,description,dimensions,price_cents,lead_time,mode,active,featured) VALUES (?,?,?,?,?,?,?,?,?)', [
                $categoryId, $name,
                "PRODUCTO FICTICIO PARA PRUEBAS LOCALES.\n\n".$description,
                $dimensions, $price === null ? 0 : $price * 100,
                $price === null ? 'Plazo a coordinar según el diseño' : '7 a 10 días hábiles',
                $price === null ? 'quote' : 'buy', 1, $index === 0 ? 1 : 0,
            ]);
            if(hasProductCategories()) query('INSERT INTO product_categories (product_id,category_id) VALUES (?,?)',[(int)$pdo->lastInsertId(),$categoryId]);
            $createdProducts++;
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
echo "Datos de ejemplo cargados solo en la base local filolux.\n";
echo "Categorías nuevas: $createdCategories. Productos nuevos: $createdProducts. Ya existentes: $existingProducts.\n";
