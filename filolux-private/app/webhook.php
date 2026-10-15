<?php
declare(strict_types=1);
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo '{"error":"method"}'; return; }
$id=''; foreach(explode('&',$_SERVER['QUERY_STRING']??'') as $piece) { $kv=explode('=',$piece,2); if(rawurldecode($kv[0])==='data.id') $id=rawurldecode($kv[1]??''); }
$mode=null;
foreach (['test','production'] as $candidate) if(Payments::verifySignature($id,$_SERVER['HTTP_X_REQUEST_ID']??'',$_SERVER['HTTP_X_SIGNATURE']??'',secret('mp_'.$candidate.'_webhook'))) { $mode=$candidate; break; }
if (!$mode || !ctype_digit($id)) { http_response_code(401); echo '{"error":"signature"}'; return; }
try {
    // Serialize each payment lookup through reconciliation so a concurrent older lookup cannot overwrite a newer one.
    $lock='flx_'.substr(hash('sha256',$id),0,50); $locked=one('SELECT GET_LOCK(?, 5) AS acquired',[$lock]);
    if ((int)$locked['acquired']!==1) throw new RuntimeException('Busy');
    try {
        $p=Payments::api('GET','/v1/payments/'.rawurlencode($id),$mode);
        if ((string)($p['id']??'')!==$id) throw new InvalidArgumentException('Pago inválido');
        $me=Payments::api('GET','/users/me',$mode);
        if(empty($me['id'])) throw new RuntimeException('Cuenta no disponible');
        $changed=Payments::reconcile($p,$mode,(int)$me['id']);
    } finally { query('SELECT RELEASE_LOCK(?)',[$lock]); }
    if($changed) { $o=one('SELECT * FROM orders WHERE id=?',[$p['external_reference']]); sendMail($o['email'],'Actualización del pago #'.$o['id'],'El pago de tu pedido está: '.paymentLabel($o['payment_status']).'. Consultá '.url('/pedidos/'.$o['id'])); }
    echo '{"ok":true}';
} catch (InvalidArgumentException $ex) { http_response_code(422); echo '{"error":"payment_mismatch"}'; }
catch (Throwable $ex) { error_log('FiloLux: webhook temporalmente no disponible'); http_response_code(503); echo '{"error":"retry"}'; }
