# FiloLux

Tienda en español para un emprendimiento de corte láser. PHP 8.2+, MySQL/MariaDB, vistas de servidor, JavaScript y CSS locales. Incluye catálogo, fotos, carrito, cuentas de clientes, pedidos, Mercado Pago Checkout Pro, SMTP y administrador.

## Instalación completamente manual en Hostinger

Los archivos están directamente en este proyecto: `public/` contiene la parte pública y `filolux-private/` la aplicación privada. No hay instalador web, códigos de activación ni creación automática de tablas. La carpeta privada incluye dependencias PHP, el `.env` privado con el hash del administrador y un `config.php` editable con una clave de cifrado aleatoria ya generada. No incluye credenciales MySQL reales, productos ni pedidos de prueba. No requiere terminal, Node ni Composer en Hostinger.

1. En hPanel, creá la base MySQL y su usuario, con permisos sobre esa base. Guardá el host indicado por Hostinger (normalmente `localhost`), el nombre completo de la base, el usuario completo y su contraseña. Activá HTTPS y la redirección a HTTPS para tu dominio.
2. Abrí `filolux-private/config.php` con un editor de texto y reemplazá:

   ```php
   'url' => 'https://tu-dominio.com', // tu dominio real, sin barra final ni rutas
   'db' => [
       'host' => 'localhost',        // el host que indique Hostinger
       'port' => 3306,
       'name' => 'TU_BASE_DE_DATOS',
       'user' => 'TU_USUARIO_MYSQL',
       'password' => 'TU_CONTRASENA_MYSQL',
   ],
   ```

   El archivo también contiene **`key`**, ya completada: conservá esa línea exactamente como viene. No pegues el ejemplo anterior como reemplazo de todo el archivo. Mantené las comillas y comas de PHP; si una contraseña contiene una comilla simple o una barra invertida, escapalas como `\'` y `\\`, respectivamente.
3. Abrí `filolux-private/database/filolux.sql`. Buscá `admin@filolux.invalid` y reemplazalo por tu correo real; podés cambiar también el nombre `Administrador` del registro inicial. Ese correo se usa para avisos y pruebas SMTP. El usuario de acceso seguirá siendo `admin` y la contraseña seguirá verificándose contra el hash de `.env`.
4. Abrí phpMyAdmin desde Hostinger, seleccioná tu **base vacía** y usá **Importar** para subir `filolux.sql`. Ese único archivo crea todas las tablas, el perfil administrador y los ajustes iniciales. No crees las tablas a mano ni ejecutes el SQL sobre la base de otra aplicación. El catálogo comienza vacío.
5. En el administrador de archivos, ubicá la carpeta pública real de tu dominio. Subí **el contenido** de `public/` dentro de ella, incluyendo `.htaccess`. Subí `filolux-private/` **fuera de todas las carpetas públicas**, como directorio hermano de la carpeta pública. Por ejemplo:

   ```text
   carpeta-del-dominio/
   ├── filolux-private/
   │   ├── .env
   │   ├── config.php
   │   ├── app/
   │   ├── database/filolux.sql
   │   ├── vendor/
   │   └── views/
   └── public_html/
       ├── .htaccess
       ├── index.php
       ├── assets/
       └── uploads/.htaccess
   ```

   `index.php` busca `../filolux-private` automáticamente. Si la carpeta privada queda en otra ubicación, ajustá `$private` en el `index.php` público a su ruta absoluta. Si un subdominio está dentro de otra web pública, no pongas los archivos privados dentro de esa otra web. La carpeta local `public/` corresponde a `public_html/` en Hostinger.
6. Usá PHP 8.2+ con `pdo_mysql`, `curl`, `openssl`, `fileinfo`, `gd` con WebP y `mbstring`. Recomendado: memoria de 256 MB o más, `upload_max_filesize=5M`, `post_max_size=48M` y `max_file_uploads=8` o más. La carpeta pública `uploads/` necesita permiso de escritura para PHP. Los archivos privados `config.php` y `.env` solo necesitan lectura; usá permisos 600 si el hosting lo permite. No uses permisos 777.
7. Abrí `https://tu-dominio.com/login` e ingresá como **admin** con la contraseña definida para esta entrega. Desde `/admin/configuracion`, completá datos comerciales, WhatsApp, redes, retiro, precio de envío y, cuando quieras habilitarlos, Mercado Pago y SMTP.

Si falta `config.php` o su estructura/clave es inválida, la web responde «El sitio todavía no está configurado» sin abrir un asistente. Una conexión MySQL incorrecta o tablas sin importar producen un error de aplicación: revisá configuración y logs PHP. La web no crea tablas ni usuarios al abrirla.

La configuración de conexión queda en `config.php`; las credenciales del administrador están en `.env`; los ajustes de la tienda y las credenciales cifradas de Mercado Pago/SMTP se guardan en MySQL.

## Actualizar tu tienda existente: medidas y productos sugeridos (15/10/2026)

Los cambios están en `filolux-private/` y `public/`. Para una web existente, reemplazá solamente los archivos modificados y conservá `config.php`, `.env` y las fotos.

1. Guardá una copia de tu base de datos y de los archivos que vas a reemplazar.
2. En phpMyAdmin, seleccioná la misma base que usa tu web e importá **`filolux-private/database/migrations/2026-10-15-product-measurements.sql`**. Importalo **antes** de subir los archivos PHP. Agrega campos opcionales a productos y a los artículos de pedidos, conserva los registros y admite repetirse sin duplicar columnas.
3. Subí los archivos modificados respetando las carpetas: el contenido de `public/` va a tu carpeta pública actual y el de `filolux-private/` a tu carpeta privada actual. Reemplazá solo esos archivos. Conservá tu configuración, tu `.env` y las fotos.
4. En **Productos → Editar**, completá el campo opcional **Medidas**, por ejemplo `30 × 20 × 5 cm` o `Ø 15 cm`. Se muestra en la ficha, el carrito y el resumen de compra. Los nuevos pedidos guardan una copia de las medidas compradas; los pedidos anteriores conservan el campo vacío.
5. Debajo de cada ficha aparecen hasta tres sugerencias de la misma categoría, solo productos publicados y sin repetir la pieza actual. Si no hay otras piezas publicadas o el producto no tiene categoría, no se muestra ese bloque.

No importes `filolux.sql` ni `initial-data.sql` para esta actualización. Para una tienda nueva, el SQL inicial ya incluye los campos de medidas.

## Actualizar el carrito desplegable (15/10/2026)

Los archivos del carrito están en las mismas carpetas `filolux-private/` y `public/`. Las medidas y sugerencias de productos forman parte de esta versión.

1. Respaldá archivos y base de datos. Si todavía no importaste la actualización de medidas, importá primero en phpMyAdmin `database/migrations/2026-10-15-product-measurements.sql`, disponible en `filolux-private/database/migrations/`. Si ya la importaste, no hay nuevos cambios de base de datos para el carrito.
2. Subí los archivos modificados respetando las carpetas privada y pública. Reemplazá esos archivos y recargá la página para recibir el JavaScript y CSS actualizados.
3. Al agregar una pieza, se abre un panel debajo del carrito y se conserva la página actual. El panel muestra productos, cantidades, medidas, subtotal y confirmación. Se puede cerrar con la cruz, Escape, un clic afuera o «Seguir navegando»; el icono permite volver a abrirlo.
4. La papelera debajo del precio quita toda la línea del producto y actualiza contador, subtotal y lista. Funciona en el panel y en la página del carrito. Las cantidades siguen editándose desde el carrito completo.

Los importes y el estado del carrito se obtienen desde el servidor y las acciones conservan la protección CSRF. Sin JavaScript, los formularios siguen funcionando y llevan a la página del carrito. Ante un problema de red, el panel ofrece abrir el carrito completo para verificar el resultado antes de reintentar.

## Configurar Mercado Pago

1. En Mercado Pago Developers, creá una aplicación para **Checkout Pro con Preferences API**. El servidor usa `/checkout/preferences` y `/v1/payments`; no utiliza la integración Orders API.
2. En las notificaciones Webhooks, suscribí el evento **pagos** y configurá `https://tu-subdominio/webhook/mercadopago`. Copiá el secreto de firma del entorno correspondiente.
3. En el administrador guardá Access Token y secreto de webhook de pruebas. Elegí **Pruebas**. El Access Token es privado; esta redirección no requiere Public Key ni Client Secret en el navegador.
4. Con usuarios y medios de pago de prueba provistos por Mercado Pago, creá una compra y verificá el regreso a la tienda y la notificación pública. No uses compradores reales en pruebas. Las credenciales de prueba y producción pueden tener restricciones distintas según la aplicación; seguí la documentación vigente de tu cuenta.
5. Comprobá importe, moneda ARS, cuenta receptora y estado en el panel de Mercado Pago y en el pedido. También probá un rechazo y un pago pendiente. La firma admite timestamps en segundos o milisegundos y una tolerancia de diez minutos; el reloj del servidor debe estar sincronizado.
6. Cargá las credenciales de producción y seleccioná **Producción** cuando termines las pruebas. Conservá las credenciales del entorno anterior mientras tenga pagos pendientes. Las credenciales de una misma cuenta no deben rotarse mientras queden notificaciones sin procesar; coordiná la rotación y probala de nuevo.

Los pedidos guardan su entorno, precio, envío y plazos originales. El webhook consulta el pago a Mercado Pago, valida firma, referencia, importe, moneda, cuenta receptora y modo de prueba/producción. Los pagos repetidos no duplican registros y un intento rechazado no reemplaza un pago aprobado. El regreso del navegador **no confirma el pago**. Un error temporal de API devuelve 503 para permitir reintentos del proveedor. No se guardan tarjetas.

En caso de fallo al abrir Mercado Pago, el pedido queda guardado y el cliente puede reintentar desde su cuenta. Los pagos o avisos de correo pueden tardar; revisá las notificaciones en Mercado Pago si un pedido sigue pendiente. No hay conciliación programada: el estado se actualiza por webhook. Cancelar un pedido desde el administrador **no reintegra dinero**; los reintegros y disputas se gestionan desde Mercado Pago y se reflejan cuando se notifica el pago.

Referencias: [preferencias](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro-preferences/create-payment-preference), [notificaciones](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro-preferences/payment-notifications).

## SMTP y recuperación

Configurá host, puerto, usuario, contraseña y correo remitente autorizado por tu proveedor. Usá STARTTLS (habitualmente 587) o TLS implícito (habitualmente 465). Guardá y pulsá **Enviar correo de prueba**. La verificación del certificado está habilitada.

Con SMTP configurado se envían enlaces de recuperación válidos por una hora, avisos al registrar pedidos, cambios de fabricación y cambios del pago. Sin SMTP, la tienda permite comprar pero no envía correos; el formulario de recuperación mantiene una respuesta genérica. Los tokens se almacenan con hash y son de un solo uso. Los fallos SMTP no revierten pedidos o pagos y se registran sin credenciales; no hay cola de reenvío automático.

## Catálogo público

Las categorías se muestran como botones y conservan la búsqueda y el orden elegido. Se puede ordenar por destacados, más nuevos, más antiguos, menor/mayor precio y nombre A–Z/Z–A. Al cambiar búsqueda, categoría u orden se vuelve a la primera página; la paginación conserva los filtros. Los trabajos personalizados sin precio aparecen al final al ordenar por precio.

## Uso del administrador

El menú se muestra en una columna lateral izquierda, con la sección actual resaltada. En celular se abre con el botón de menú. La lista de productos permite filtrar con botones por categoría, ver todas las categorías o solo los productos sin categoría; incluye tanto productos publicados como ocultos.

- **Productos:** hasta ocho fotos, precio, categoría, medidas opcionales, plazo y publicación. Ocultar retira el producto del catálogo y de nuevos carritos sin afectar pedidos anteriores. La primera foto conservada es la portada.
- **Modalidad:** compra directa con precio positivo o consulta personalizada por WhatsApp. Los personalizados no se agregan al carrito.
- **Pedidos:** pago y fabricación son independientes. Solo se permite avanzar fabricación con pago aprobado. Retiro gratuito o envío con tarifa única. Los costos de entrega se guardan al confirmar el pedido.
- **Configuración:** los campos secretos vacíos conservan el valor guardado; la casilla de eliminar lo borra. El logo y las fotos se validan y convierten a WebP. El acceso administrador usa `ADMIN_USERNAME` y `ADMIN_PASSWORD_HASH` de `.env`; el correo del perfil no sirve para iniciar sesión como administrador. La recuperación por correo corresponde a clientes. Las cuentas de clientes no acceden a `/admin` ni a pedidos ajenos.

## Logo y temas visuales

El logo de FiloLux está vectorizado en SVG, con fondo transparente y letras convertidas en trazados, sin dependencia de tipografías. Los archivos fuente públicos están en `public/assets/brand/`:

- `logo-white.svg`: logo blanco para modo oscuro.
- `logo-black.svg`: logo negro para modo claro.
- `icon-white.svg` e `icon-black.svg`: destello de la marca como isotipo/favicón.

El logo aparece en cabecera y pie. Los acentos gráficos usan el mismo destello. La paleta definitiva es grafito y dorado, en modo oscuro y claro. El botón de sol/luna alterna entre ambos modos y conserva la elección en el navegador para la tienda y el administrador. El logo se adapta al contraste y la ilustración mantiene la paleta dorada. El modo inicial es oscuro; las preferencias guardadas durante la prueba de colores se migran conservando el modo claro u oscuro, sin mantener otras paletas. Un logo personalizado subido desde configuración reemplaza el logo predeterminado en cabecera y pie.

## Credenciales del administrador en archivo privado

El archivo `filolux-private/.env` contiene `ADMIN_USERNAME=admin` y `ADMIN_PASSWORD_HASH`. Este último es un hash generado con `password_hash()`, verificado con `password_verify()`. La contraseña en texto plano no se escribe en el archivo ni se incorpora al código.

Para cambiar las credenciales, generá un hash nuevo con `password_hash()` y reemplazá `ADMIN_USERNAME` y/o `ADMIN_PASSWORD_HASH` en ese archivo privado. Mantené el hash entre comillas dobles, no lo pegues en el campo de contraseña del login. El cambio invalida las sesiones administradoras anteriores. Un `.env` ausente o inválido bloquea el acceso administrador; no activa una contraseña alternativa de la base de datos. El correo del perfil se conserva para pedidos y avisos, sin habilitar un segundo acceso administrador.

El `.env` está excluido de Git y está dentro de `filolux-private/` para la instalación manual. Debe quedar fuera de la carpeta pública y de sus copias públicas. Guardá una copia privada: la recuperación de contraseña por correo se aplica a clientes; para el administrador se reemplaza el hash de este archivo.

## Desarrollo local

```bash
composer --working-dir=filolux-private install --no-interaction
php -S 127.0.0.1:8080 -t public public/router.php
```

Antes de abrir `http://127.0.0.1:8080`, usá `filolux-private/config.php` (o copiá allí `filolux-private/config.example.php` si no existe), completá URL y conexión y conservá la clave existente; si partís de un ejemplo vacío, generala con `base64_encode(random_bytes(32))`. Importá manualmente `filolux-private/database/schema.sql` y luego `filolux-private/database/initial-data.sql` en una base local vacía. `filolux-private/` ya incluye una configuración con clave generada y ambos SQL combinados. Mercado Pago requiere URL HTTPS y credenciales.

Los archivos reales de la aplicación están en `filolux-private/`; los públicos, en `public/`. La raíz conserva documentación, herramientas de desarrollo y pruebas vigentes.

No se generan ZIPs. En cada cambio se indican los archivos que debés subir. Conservá siempre el `config.php` y `.env` de tu servidor: la copia local de configuración usa datos MySQL de ejemplo y una clave propia, y no reemplaza la clave de una tienda instalada. Para una actualización no importes nuevamente el SQL inicial.

`php scripts/prepare-sql.php` regenera únicamente `filolux-private/database/filolux.sql` a partir del esquema y los datos iniciales, para instalaciones nuevas. No modifica configuración, no importa SQL y no genera comprimidos. No necesitás ejecutarlo en el hosting.

## Verificaciones reproducibles

`tests/prepare.php` utiliza exclusivamente una instancia MySQL desechable en el socket `/tmp/filolux-mysql/mysql.sock` (o `FILOLUX_TEST_SOCKET`) y puerto 13367. Crea/reinicia **solo `filolux_test`**, con configuración aislada en `tests/.runtime/config.php`. No utiliza `config.php` de producción. No ejecutes esas pruebas contra el servidor de tu negocio.

```bash
php tests/prepare.php
FILOLUX_CONFIG_PATH="$PWD/tests/.runtime/config.php" FILOLUX_ENV_PATH="$PWD/tests/.runtime/admin.env" php -d opcache.enable=0 -S 127.0.0.1:8087 -t public public/router.php
# En otra terminal:
php tests/domain.php
php tests/measurements_migration.php
python3 tests/product_details.py
python3 tests/cart_ajax.py
python3 tests/admin_auth.py
python3 tests/integration_http.py
python3 tests/admin_settings.py
# Requiere requests para Python; Playwright instalado solo en el equipo de desarrollo:
node tests/browser.cjs
```

Playwright acepta `FILOLUX_PLAYWRIGHT` con la ruta del módulo y `FILOLUX_CHROMIUM` con la ruta del navegador. Las capturas quedan en `tests/.runtime/`. `tests/mail.py` y `tests/manual_deploy.py` verifican SMTP/recuperación con TLS local y el despliegue manual de las carpetas en una base vacía; sus instrucciones están en sus cabeceras.

Las pruebas de firma y conciliación usan respuestas sintéticas de pagos: **no sustituyen una compra real de prueba ni la recepción del webhook en Hostinger**. La integración real requiere tus credenciales y un dominio HTTPS público.

## Copias de seguridad y operación

Respaldá juntos la base MySQL, la carpeta pública `uploads/` y los archivos privados `config.php` y `.env`. La clave de `config.php` permite descifrar las credenciales: perderla requiere volver a cargarlas; no la regeneres al actualizar. Conservá backups privados fuera de la web y verificá restauración en otra base antes de reemplazar datos.

Revisá periódicamente errores PHP y entregabilidad SMTP en Hostinger, notificaciones fallidas en Mercado Pago y el espacio ocupado por imágenes. Antes de publicar, completá tus datos comerciales y condiciones de compra, fabricación, entrega y devoluciones según tu operación. El perfil administrador se crea al importar el SQL inicial; esta versión no incluye altas de administradores desde la web.
