<?php
declare(strict_types=1);
define('ROOT',dirname(__DIR__).'/filolux-private');
$GLOBALS['config']=require __DIR__.'/.runtime/config.php';
if($GLOBALS['config']['db']['name']!=='filolux_test') exit(1);
require ROOT.'/app/core.php';require ROOT.'/app/Payments.php';
$count=0;$orders=[];
function checkSync(bool $ok,string $label): void { global $count;if(!$ok) throw new RuntimeException($label);$count++; }
function rejectSync(callable $fn,string $label): void { try{$fn();}catch(InvalidArgumentException){checkSync(true,$label);return;}throw new RuntimeException($label); }
function makeSyncOrder(string $mode): int { global $orders;query("INSERT INTO orders (user_id,checkout_key,customer_name,email,phone,delivery,address,notes,subtotal_cents,shipping_cents,total_cents,payment_mode) VALUES (1,?,'Test','test@example.test','1112345678','pickup','','',15000,0,15000,?)",[bin2hex(random_bytes(32)),$mode]);$id=(int)db()->lastInsertId();$orders[]=$id;return $id; }
try {
    $test=makeSyncOrder('test');$prod=makeSyncOrder('production');
    $payment=['id'=>'991100001','external_reference'=>(string)$test,'transaction_amount'=>150,'currency_id'=>'ARS','collector_id'=>555,'payer'=>['id'=>777],'live_mode'=>true,'status'=>'approved'];
    $transport=function(array $p,array $seller,array $buyer=[]): callable {
        return function($method,$path,$mode)use($p,$seller,$buyer){
            checkSync($method==='GET','La verificación no crea pagos.');
            if(str_starts_with($path,'/v1/payments/'))return $p;
            if($path==='/users/me')return $seller;
            if($path==='/users/777')return $buyer;
            throw new RuntimeException('Unexpected API path');
        };
    };
    rejectSync(fn()=>Payments::reconcile($payment,'test',555),'No aceptar live_mode=true sin verificar cuenta test.');
    rejectSync(fn()=>Payments::syncPayment($payment['id'],['test'],$test,$transport($payment,['id'=>555,'tags'=>['normal']],['id'=>777,'tags'=>['normal']])),'No aceptar cuentas reales como pruebas.');
    rejectSync(fn()=>Payments::syncPayment($payment['id'],['test'],$test,$transport($payment,['id'=>555],['id'=>778,'tags'=>['test_user']])),'El ID de la cuenta test debe coincidir con el comprador.');
    rejectSync(fn()=>Payments::syncPayment($payment['id'],['test'],$prod,$transport($payment,['id'=>555,'tags'=>['test_user']])),'No verificar pagos de otro pedido.');
    foreach(['transaction_amount'=>151,'currency_id'=>'USD','collector_id'=>556] as $field=>$value) {
        rejectSync(fn()=>Payments::syncPayment($payment['id'],['test'],$test,$transport(array_replace($payment,[$field=>$value]),['id'=>555,'tags'=>['test_user']])),'Conservar validación de '.$field);
    }
    $result=Payments::syncPayment($payment['id'],['test'],$test,$transport($payment,['id'=>555,'tags'=>['test_user']]));
    checkSync($result['changed'] && $result['order_id']===$test,'Admitir vendedor test verificado con live_mode=true.');
    checkSync(!Payments::syncPayment($payment['id'],['test'],$test,$transport($payment,['id'=>555],['id'=>777,'tags'=>['test_user']]))['changed'],'Admitir comprador test verificado, sin duplicar acreditación.');
    $live=array_replace($payment,['id'=>'991100002','external_reference'=>(string)$prod]);
    rejectSync(fn()=>Payments::syncPayment($live['id'],['production'],$prod,$transport($live,['id'=>555,'tags'=>['test_user']])),'No acreditar vendedor de prueba en producción.');
    $result=Payments::syncPayment($live['id'],['test','production'],null,$transport($live,['id'=>555,'tags'=>['normal']]));
    checkSync($result['mode']==='production' && $result['changed'],'Una firma compartida no fuerza el entorno test.');
    checkSync(one('SELECT payment_status FROM orders WHERE id=?',[$prod])['payment_status']==='approved','Estado confirmado persistido.');
    echo "PASS: $count controles de consulta, cuentas de prueba, modo compartido, permisos y conciliación.\n";
} finally {
    foreach($orders as $id) { query('DELETE FROM payments WHERE order_id=?',[$id]);query('DELETE FROM orders WHERE id=?',[$id]); }
}
