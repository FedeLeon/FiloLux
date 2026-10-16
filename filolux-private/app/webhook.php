<?php
declare(strict_types=1);
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo '{"error":"method"}'; return; }
$id=''; foreach(explode('&',$_SERVER['QUERY_STRING']??'') as $piece) { $kv=explode('=',$piece,2); if(rawurldecode($kv[0])==='data.id') $id=rawurldecode($kv[1]??''); }
$modes=[];
foreach(['test','production'] as $candidate) if(Payments::verifySignature($id,$_SERVER['HTTP_X_REQUEST_ID']??'',$_SERVER['HTTP_X_SIGNATURE']??'',secret('mp_'.$candidate.'_webhook'))) $modes[]=$candidate;
if(!$modes || !ctype_digit($id)) { http_response_code(401); echo '{"error":"signature"}'; return; }
$mode=implode('|',$modes);
try {
    // An application may use the same signing secret for both configured environments.
    $result=Payments::syncPayment($id,$modes);
    if($result['changed']) { $o=one('SELECT * FROM orders WHERE id=?',[$result['order_id']]); sendMail($o['email'],'Actualización del pago #'.$o['id'],'El pago de tu pedido está: '.paymentLabel($o['payment_status']).'. Consultá '.url('/pedidos/'.$o['id'])); }
    echo '{"ok":true}';
} catch (InvalidArgumentException $ex) {
    // Reconciliation messages are controlled diagnostics, without tokens or API payloads.
    error_log('FiloLux: webhook rechazado payment_id='.$id.' entorno='.$mode.' motivo='.$ex->getMessage());
    try { notifyLive('webhook-error:'.$mode.':'.$id.':'.$ex->getMessage(),'admin',null,'webhook_error','Webhook de Mercado Pago rechazado','Pago '.$id.': '.$ex->getMessage(),'/admin/configuracion/logs'); }
    catch(Throwable $notificationError) { error_log('FiloLux: no se pudo registrar el aviso del webhook rechazado'); }
    http_response_code(422); echo '{"error":"payment_mismatch"}';
}
catch (Throwable $ex) { error_log('FiloLux: webhook temporalmente no disponible'); http_response_code(503); echo '{"error":"retry"}'; }
