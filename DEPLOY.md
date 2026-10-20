# Deploy automático de FiloLux en Hostinger

El workflow `.github/workflows/deploy-hostinger.yml` se ejecuta con cada **push a `main`**, y también desde GitHub → Actions → Deploy FiloLux to Hostinger → Run workflow. Un commit que queda solamente en tu computadora no dispara el deploy. El YAML debe estar en `.github/workflows/`, no en la raíz.

## Configuración inicial

La tienda debe estar instalada y funcionando con PHP 8.2+, con esta distribución en Hostinger:

```text
/home/TU_USUARIO/domains/filoluxlaser.com/
├── filolux-private/
│   ├── config.php       # configuración real existente
│   ├── .env             # hash del administrador existente
│   ├── app/
│   ├── views/
│   ├── vendor/
│   └── storage/
└── public_html/
    ├── index.php
    ├── assets/
    └── uploads/
```

Confirmá la ruta absoluta real con los datos SSH de hPanel; el nombre de usuario no se deduce del dominio. Si tu carpeta privada está en otra ubicación, este flujo necesita adaptar la ruta antes de ejecutarlo.

Activá SSH en tu hosting y autorizá allí la clave pública de una clave dedicada al deploy. Guardá su clave privada **sin passphrase** como secret de GitHub, no como archivo del repositorio. El servidor necesita `bash`, `rsync`, `realpath` y PHP CLI 8.2+ con las extensiones de la aplicación. El PHP de SSH puede tener una versión diferente al PHP seleccionado para la web. El flujo busca automáticamente un CLI compatible: primero `/opt/alt/php82/usr/bin/php`, luego PHP 8.3/8.4/8.5 en la misma estructura y, finalmente, los comandos PHP disponibles. Comprueba versión y extensiones antes de sincronizar; no modifica la versión global ni la configuración de la web. Si no encuentra ninguno compatible, se detiene.

Las descargas PDF y Excel requieren también las extensiones PHP `dom`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip` y `zlib`, habilitadas tanto en el PHP de la web como en el CLI elegido para el deploy. Las bibliotecas se instalan en GitHub y se sincronizan dentro de `vendor/`.

Si necesitás indicar otra ubicación, creá la **variable** de repositorio `HOSTINGER_PHP_BIN` en GitHub → Settings → Secrets and variables → Actions → Variables, con la ruta absoluta del ejecutable en Hostinger. Es opcional, no es un secret. Si la definís, se usa exclusivamente ese ejecutable y se valida igual.

En el repositorio de **FiloLux**, abrí **Settings → Secrets and variables → Actions → New repository secret** y configurá:

| Secret | Valor |
| --- | --- |
| `HOSTINGER_SSH_HOST` | IP o hostname SSH indicado por Hostinger; puede diferir de `filoluxlaser.com`. |
| `HOSTINGER_SSH_PORT` | Puerto SSH de hPanel. |
| `HOSTINGER_SSH_USER` | Usuario SSH de esta cuenta de hosting. |
| `HOSTINGER_SSH_PRIVATE_KEY` | Clave privada completa autorizada para esa cuenta, incluyendo sus líneas BEGIN/END. |
| `HOSTINGER_SSH_KNOWN_HOSTS` | Línea o líneas de `known_hosts` del servidor y puerto correctos. |
| `HOSTINGER_DEPLOY_PATH` | Ruta absoluta de la carpeta **pública** de FiloLux, por ejemplo `/home/u123456789/domains/filoluxlaser.com/public_html`. |

**Importante:** en el YAML anterior de Laravel, `HOSTINGER_DEPLOY_PATH` apuntaba a un repositorio Git remoto. En esta versión apunta a `public_html`. No reutilices el valor del otro proyecto. Los secrets se deben crear en este repositorio aunque tengan los mismos nombres.

Para obtener las claves públicas del servidor podés usar en tu computadora:

```bash
ssh-keyscan -p PUERTO HOST_SSH
```

Verificá su huella con una referencia confiable de tu servidor antes de guardarlas en `HOSTINGER_SSH_KNOWN_HOSTS`; `ssh-keyscan` por sí solo no confirma la identidad. El workflow exige esas claves y no acepta automáticamente una identidad nueva.

## Qué hace cada despliegue

1. Descarga el commit que disparó GitHub Actions y prepara PHP 8.2 y Composer en el runner de GitHub.
2. Instala las dependencias de producción desde `filolux-private/composer.lock` y verifica la sintaxis PHP.
3. Comprueba SSH, las carpetas existentes, PHP y que la URL de la configuración de destino corresponda a `filoluxlaser.com`.
4. Sincroniza `filolux-private/app/`, `views/`, `vendor/` y `public/assets/`. Elimina archivos obsoletos **solo dentro de esas carpetas de código**. También actualiza los manifiestos Composer, `index.php` y los `.htaccess` de la aplicación y de uploads.
5. Comprueba por HTTPS el inicio, el catálogo y el ingreso.

No requiere Git, Node ni Composer en Hostinger. No ejecuta Artisan. No genera ZIPs.

`config.php`, `.env`, fotos de `uploads/`, logs de `storage/`, archivos ajenos de las carpetas raíz y la base de datos quedan fuera de la sincronización. Los permisos de los secretos existentes no se modifican. Las migraciones SQL se aplican manualmente cuando una actualización las requiera, antes de desplegar el código que depende de ellas.

Los despliegues no se ejecutan simultáneamente. La sincronización se realiza por directorios, sin cambiar una versión completa de manera atómica; una actualización puede mostrar archivos de versiones diferentes brevemente. Si el envío o la comprobación HTTPS falla, Actions marca el deploy como fallido y no hace rollback automático. Conservá tus backups habituales; para volver a una versión anterior del código, revertí el commit y hacé push a `main`.

## Archivos del proyecto

- `.github/workflows/deploy-hostinger.yml`: disparadores, preparación de dependencias, SSH y comprobación HTTP.
- `scripts/deploy-hostinger.sh`: comprobaciones de destino y sincronización limitada a archivos de aplicación.

Estos archivos se suben a **GitHub**. No necesitás copiarlos a Hostinger manualmente. El deploy conserva el dominio y la configuración del servidor. La recepción real por SSH se comprueba en la primera ejecución con los secrets configurados.

Referencias: [sintaxis oficial de GitHub Actions](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax), [configuración de PHP y Composer](https://github.com/shivammathur/setup-php).

## Validación local del flujo

`python3 tests/deploy_hostinger.py` ejecuta el script con `rsync` real sobre carpetas temporales y un transporte SSH simulado. Verifica actualización, eliminación de código obsoleto, conservación de configuración/fotos/logs/permisos y rechazo de rutas inválidas, otro dominio y directorios de código enlazados. No usa las credenciales del hosting.
