<?php
declare(strict_types=1);
// Configuración privada. config.php contiene la clave de cifrado de esta instalación.
// Editar URL y los datos MySQL manualmente antes de publicar.
return [
    'url' => 'https://tu-dominio.com',
    'key' => '', // Base64 de 32 bytes aleatorios; conservar al actualizar.
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'TU_BASE_DE_DATOS',
        'user' => 'TU_USUARIO_MYSQL',
        'password' => 'TU_CONTRASENA_MYSQL',
    ],
];
