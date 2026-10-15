<?php
/** Rebuild initial SQL for new installations. Never alters config or creates archives. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__);
$private = $root . '/filolux-private';
foreach (['app/bootstrap.php', 'views/layout.php', 'vendor/autoload.php', 'config.php', '.env', 'database/schema.sql', 'database/initial-data.sql'] as $relative) {
    if (!is_file($private . '/' . $relative)) throw new RuntimeException('Falta filolux-private/' . $relative);
}
$sql = "-- FiloLux: importar solo en una base MySQL vacía.\n-- Editar el correo del administrador antes de importar.\n\n" .
    file_get_contents($private . '/database/schema.sql') . "\n" . file_get_contents($private . '/database/initial-data.sql');
file_put_contents($private . '/database/filolux.sql', $sql);
echo "Archivos listos para subir manualmente, sin ZIP.\npublic/ -> contenido de public_html/\nfilolux-private/ -> carpeta privada hermana de public_html/\nAl actualizar, conservar config.php, .env y uploads/ del servidor.\n";
