<?php
declare(strict_types=1);
ini_set('display_errors','0');
date_default_timezone_set('America/Argentina/Buenos_Aires');
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');
session_name('filolux'); session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),'samesite'=>'Lax','path'=>'/']); session_start();
$_SESSION['csrf']??=bin2hex(random_bytes(32));
require ROOT.'/vendor/autoload.php'; require ROOT.'/app/core.php'; require ROOT.'/app/Payments.php';
$configFile=getenv('FILOLUX_CONFIG_PATH')?:ROOT.'/config.php';
if (!is_file($configFile)) {
    error_log('FiloLux: falta config.php privado. Completar la configuración manual indicada en README.md.');
    http_response_code(503); header('Content-Type: text/plain; charset=UTF-8');
    exit('El sitio todavía no está configurado.');
}
try {
    $config=require $configFile;
    if (!is_array($config) || !is_array($config['db']??null) || !is_string($config['url']??null) || !filter_var($config['url'],FILTER_VALIDATE_URL) || !is_string($config['key']??null) || strlen(base64_decode($config['key'],true)?:'')!==32) throw new RuntimeException('Configuración inválida');
    $GLOBALS['config']=$config;
} catch (Throwable $ex) {
    error_log('FiloLux: configuración privada inválida. Revisar config.php según README.md.');
    http_response_code(503); header('Content-Type: text/plain; charset=UTF-8');
    exit('El sitio todavía no está configurado.');
}
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
try {
    if ($path==='/webhook/mercadopago') { require ROOT.'/app/webhook.php'; exit; }
    if ($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf'],(string)($_POST['_csrf']??''))) abortPage(419,'La sesión del formulario venció. Recargá la página e intentá otra vez.');
    require ROOT.'/app/routes.php';
} catch (InvalidArgumentException $ex) { if(wantsJson()) jsonResponse(['error'=>$ex->getMessage()],422); http_response_code(422); render('message',['title'=>'Revisá los datos','message'=>$ex->getMessage()]); }
catch (Throwable $ex) { $ref=bin2hex(random_bytes(5)); error_log('FiloLux '.$ref.' '.$ex::class.' '.$ex->getCode()); if(wantsJson()) jsonResponse(['error'=>'No pudimos completar la operación. Referencia: '.$ref],500); http_response_code(500); render('message',['title'=>'No pudimos completar la operación','message'=>'Intentá nuevamente. Referencia: '.$ref]); }
