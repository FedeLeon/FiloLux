<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
if (!is_dir(__DIR__.'/.runtime')) mkdir(__DIR__.'/.runtime',0700,true);
// Dedicated disposable MySQL instance only; never points to the application's config.php.
$socket=getenv('FILOLUX_TEST_SOCKET')?:'/tmp/filolux-mysql/mysql.sock';
$pdo=new PDO('mysql:unix_socket='.$socket.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS filolux_test CHARACTER SET utf8mb4');
$password=bin2hex(random_bytes(20)); $pdo->exec("CREATE USER IF NOT EXISTS 'filolux_test'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password)); $pdo->exec("ALTER USER 'filolux_test'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($password)); $pdo->exec("GRANT ALL ON filolux_test.* TO 'filolux_test'@'127.0.0.1'");
$config=['url'=>'http://127.0.0.1:8087','key'=>base64_encode(random_bytes(32)),'db'=>['host'=>'127.0.0.1','port'=>13367,'name'=>'filolux_test','user'=>'filolux_test','password'=>$password]];
file_put_contents(__DIR__.'/.runtime/config.php',"<?php return ".var_export($config,true).';'); chmod(__DIR__.'/.runtime/config.php',0600);
file_put_contents(__DIR__.'/.runtime/admin.env', 'ADMIN_USERNAME=admin'."\n".'ADMIN_PASSWORD_HASH="'.password_hash('AdminTest2026!',PASSWORD_DEFAULT).'"'."\n"); chmod(__DIR__.'/.runtime/admin.env',0600);
define('ROOT',dirname(__DIR__).'/filolux-private'); define('PUBLIC_ROOT',dirname(__DIR__).'/public'); $GLOBALS['config']=$config; require ROOT.'/vendor/autoload.php'; require ROOT.'/app/core.php';
$db=db(); $db->exec('SET FOREIGN_KEY_CHECKS=0'); foreach(['payments','order_items','orders','product_images','products','categories','settings','password_resets','rate_limits','users'] as $table) $db->exec('DROP TABLE IF EXISTS '.$table); $db->exec('SET FOREIGN_KEY_CHECKS=1'); $db->exec(file_get_contents(ROOT.'/database/schema.sql'));
query("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin')",['Admin de prueba','admin@example.test',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
foreach(['brand'=>'FiloLux','hero_title'=>'De una idea a algo extraordinario.','hero_text'=>'Diseño y corte láser a medida. Creamos piezas únicas para regalar, emprender y transformar tus espacios.','about'=>'Cada pieza empieza con una idea. La transformamos con precisión, diseño y dedicación, fabricando especialmente para vos.','pickup'=>'Retiro coordinado en nuestro taller.','shipping_price'=>'350000','mp_mode'=>'test'] as $k=>$v) putSetting($k,$v);
foreach(['Decoración','Regalos','Para tu marca'] as $name) query('INSERT INTO categories (name) VALUES (?)',[$name]);
foreach([['Lámpara geométrica',1,2450000,'buy'],['Tu nombre, tu espacio',2,1250000,'buy'],['Identidad que se siente',3,0,'quote'],['Detalles para celebrar',2,850000,'buy'],['Organizador de escritorio',1,1680000,'buy'],['Un regalo con historia',2,1950000,'buy']] as [$name,$cat,$price,$mode]) query('INSERT INTO products (name,description,category_id,price_cents,lead_time,mode,featured) VALUES (?,?,?,?,?,?,1)',[$name,'Pieza de ejemplo para verificar la tienda. Fabricación por encargo, con detalles cortados a láser. Podés consultar opciones de acabado por WhatsApp.',$cat,$price,'7 a 10 días hábiles',$mode]);
echo "Base de prueba preparada: filolux_test. Configuración aislada en tests/.runtime/config.php.\n";
