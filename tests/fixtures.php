<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
define('ROOT',dirname(__DIR__).'/filolux-private'); define('PUBLIC_ROOT',dirname(__DIR__).'/public'); $file=__DIR__.'/.runtime/config.php'; $GLOBALS['config']=require $file; require ROOT.'/vendor/autoload.php'; require ROOT.'/app/core.php';
if($GLOBALS['config']['db']['name']!=='filolux_test') exit('Test database required');
$action=$argv[1]??'';
if($action==='enable-payments') { $GLOBALS['config']['url']='https://tienda.example'; putSetting('mp_test_token',encryptSecret('TEST-local-fixture')); putSetting('mp_test_webhook',encryptSecret('local-fixture-secret')); }
if($action==='preview') { $GLOBALS['config']['url']='http://127.0.0.1:8087'; putSetting('mp_test_token',''); putSetting('mp_test_webhook',''); }
if($action==='smtp') { query('DELETE FROM rate_limits'); putSetting('smtp_host','localhost'); putSetting('smtp_port','14657'); putSetting('smtp_security','ssl'); putSetting('smtp_from','tienda@example.test'); putSetting('smtp_user',''); }
if($action==='smtp-off') { putSetting('smtp_host',''); }
if($action==='approve') { query("UPDATE orders SET payment_status='approved' WHERE id=?",[(int)($argv[2]??0)]); }
file_put_contents($file,'<?php return '.var_export($GLOBALS['config'],true).';'); chmod($file,0600);
