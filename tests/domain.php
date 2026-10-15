<?php
declare(strict_types=1);
define('ROOT',dirname(__DIR__).'/filolux-private'); define('PUBLIC_ROOT',dirname(__DIR__).'/public');
$GLOBALS['config']=require __DIR__.'/.runtime/config.php'; require ROOT.'/vendor/autoload.php'; require ROOT.'/app/core.php'; require ROOT.'/app/Payments.php';
$tests=0;
function check(bool $value,string $name): void { global $tests; if(!$value) throw new RuntimeException('FAIL '.$name); $tests++; echo 'PASS '.$name."\n"; }
function rejects(callable $fn,string $name): void { try{$fn();}catch(InvalidArgumentException){check(true,$name);return;} throw new RuntimeException('FAIL '.$name); }
check(cents('1234,56')===123456 && cents('1.5')===150 && cents('0')===0,'importes exactos en centavos');
rejects(fn()=>cents('1.234,00'),'rechazar formato ambiguo'); rejects(fn()=>cents('-1'),'rechazar importes negativos');
$encrypted=encryptSecret('token-prueba'); check($encrypted!=='token-prueba' && decryptSecret($encrypted)==='token-prueba','cifrado autenticado de credenciales');
$now=time(); $ts=(string)($now*1000); $manifest='id:123;request-id:request-123;ts:'.$ts.';'; $header='ts='.$ts.',v1='.hash_hmac('sha256',$manifest,'test-key');
check(Payments::verifySignature('123','request-123',$header,'test-key',$now),'firma de Mercado Pago con timestamp en milisegundos');
check(!Payments::verifySignature('124','request-123',$header,'test-key',$now),'firma con id alterado');
check(!Payments::verifySignature('123','request-123',$header,'wrong',$now),'firma con clave inválida');
check(!Payments::verifySignature('123','request-123',$header,'test-key',$now+601),'firma vencida');
query("INSERT INTO orders (user_id,checkout_key,customer_name,email,phone,delivery,address,notes,subtotal_cents,shipping_cents,total_cents,payment_mode) VALUES (1,?,'Test','test@example.test','1234567','pickup','','',15000,0,15000,'test')",[bin2hex(random_bytes(32))]); $id=(int)db()->lastInsertId();
$p=['id'=>99001,'external_reference'=>(string)$id,'transaction_amount'=>150,'currency_id'=>'ARS','collector_id'=>555,'live_mode'=>false,'status'=>'approved'];
check(Payments::reconcile($p,'test',555),'aprobar pago coincidente');
check(!Payments::reconcile($p,'test',555),'webhook duplicado no repite cambio de estado');
check((int)query('SELECT COUNT(*) FROM payments WHERE order_id=?',[$id])->fetchColumn()===1,'pago duplicado no duplica registros');
$p['id']=99002; $p['status']='rejected'; check(!Payments::reconcile($p,'test',555),'intento rechazado no pisa un pago aprobado');
foreach(['transaction_amount'=>151,'currency_id'=>'USD','collector_id'=>556,'live_mode'=>true,'external_reference'=>'999999999','status'=>'unknown'] as $key=>$value) { $bad=$p; $bad[$key]=$value; rejects(fn()=>Payments::reconcile($bad,'test',555),'rechazar pago con '.$key.' inválido'); }
$p['id']=99001; $p['status']='refunded'; check(Payments::reconcile($p,'test',555),'registrar reintegro verificado');
check(one('SELECT payment_status FROM orders WHERE id=?',[$id])['payment_status']==='refunded','estado reintegrado persistido');
$p['id']=99002; $p['status']='rejected'; check(!Payments::reconcile($p,'test',555),'notificación de rechazo antigua no reemplaza un reintegro');
query('INSERT INTO order_items (order_id,product_id,name,quantity,price_cents,lead_time) VALUES (?,1,?,?,?,?)',[$id,'Pieza de prueba',1,15000,'7 días']);
$o=one('SELECT * FROM orders WHERE id=?',[$id]);
try { Payments::preference($o,fn()=>['sandbox_init_point'=>'https://sandbox.mercadopago.com.ar/checkout/test']); throw new RuntimeException('No rechazó preferencia incompleta'); } catch(RuntimeException $ex) { check(one('SELECT preference_id FROM orders WHERE id=?',[$id])['preference_id']===null,'respuesta sin ID no persiste preferencia'); }
$link=Payments::preference($o,function($method,$path,$mode,$payload)use($id){
    check($method==='POST' && $path==='/checkout/preferences' && $mode==='test','endpoint y entorno de preferencia');
    check($payload['external_reference']===(string)$id && $payload['items'][0]['unit_price']===150 && $payload['items'][0]['currency_id']==='ARS','preferencia usa referencia y precio guardados');
    return ['id'=>'pref-fixture','sandbox_init_point'=>'https://sandbox.mercadopago.com.ar/checkout/test','init_point'=>'https://www.mercadopago.com.ar/checkout/live'];
});
check($link==='https://sandbox.mercadopago.com.ar/checkout/test','redirección sandbox en pruebas');
$o=one('SELECT * FROM orders WHERE id=?',[$id]);
check(Payments::preference($o,fn()=>throw new RuntimeException('No debería consultar API'))===$link,'reintentos reutilizan preferencia existente');
query('DELETE FROM order_items WHERE order_id=?',[$id]);
query('DELETE FROM payments WHERE order_id=?',[$id]); query('DELETE FROM orders WHERE id=?',[$id]);
echo "Total: $tests comprobaciones.\n";
