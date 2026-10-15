<?php
declare(strict_types=1);
final class Payments {
    public static function enabled(?string $mode=null): bool { $mode??=setting('mp_mode','test'); return secret('mp_'.$mode.'_token')!=='' && secret('mp_'.$mode.'_webhook')!=='' && str_starts_with(url(),'https://'); }
    public static function api(string $method,string $path,string $mode,?array $body=null): array {
        $token=secret('mp_'.$mode.'_token'); if (!$token) throw new InvalidArgumentException('Los pagos todavía no están habilitados.');
        $curl=curl_init('https://api.mercadopago.com'.$path);
        $headers=['Authorization: Bearer '.$token,'Content-Type: application/json'];
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
        if ($body!==null) curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        $raw=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        if ($raw===false || $status<200 || $status>=300) throw new RuntimeException('Mercado Pago no disponible: HTTP '.$status);
        $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR); if (!is_array($data)) throw new RuntimeException('Respuesta de pago inválida'); return $data;
    }
    public static function preference(array $order, ?callable $transport=null): string {
        if ($order['checkout_url']) return $order['checkout_url'];
        $items=query('SELECT * FROM order_items WHERE order_id=?',[$order['id']])->fetchAll();
        $payload=['external_reference'=>(string)$order['id'],'items'=>array_map(fn($i)=>['id'=>(string)$i['product_id'],'title'=>$i['name'],'quantity'=>(int)$i['quantity'],'currency_id'=>'ARS','unit_price'=>(int)$i['price_cents']/100],$items),
          'payer'=>['email'=>$order['email']], 'back_urls'=>['success'=>url('/pedidos/'.$order['id'].'?retorno=1'),'pending'=>url('/pedidos/'.$order['id'].'?retorno=1'),'failure'=>url('/pedidos/'.$order['id'].'?retorno=1')],
          'notification_url'=>url('/webhook/mercadopago'),'auto_return'=>'approved'];
        if ((int)$order['shipping_cents']>0) $payload['items'][]=['id'=>'shipping','title'=>'Envío','quantity'=>1,'currency_id'=>'ARS','unit_price'=>(int)$order['shipping_cents']/100];
        $p=($transport??[self::class,'api'])('POST','/checkout/preferences',$order['payment_mode'],$payload);
        $link=$p[$order['payment_mode']==='test'?'sandbox_init_point':'init_point']??'';
        $host=parse_url($link,PHP_URL_HOST)?:'';
        if (empty($p['id']) || parse_url($link,PHP_URL_SCHEME)!=='https' || !preg_match('/(^|\.)mercadopago\.(com|com\.ar)$/D',$host)) throw new RuntimeException('Preferencia inválida');
        query('UPDATE orders SET preference_id=?, checkout_url=? WHERE id=?',[$p['id'],$link,$order['id']]); return $link;
    }
    public static function verifySignature(string $id,string $requestId,string $header,string $key,?int $now=null): bool {
        if (!$key || !$id || !$requestId || strlen($header)>512 || strlen($requestId)>200) return false;
        $parts=[]; foreach (explode(',',$header) as $v) { $p=explode('=',trim($v),2); if(count($p)===2) $parts[$p[0]]=$p[1]; }
        $ts=$parts['ts']??''; if (!ctype_digit($ts) || abs(($now??time())-((strlen($ts)>10)?(int)floor((int)$ts/1000):(int)$ts))>600) return false;
        $manifest='id:'.strtolower($id).';request-id:'.$requestId.';ts:'.$ts.';';
        return hash_equals(hash_hmac('sha256',$manifest,$key),$parts['v1']??'');
    }
    public static function reconcile(array $payment,string $mode,int $collector): bool {
        $ref=(string)($payment['external_reference']??''); $pid=(string)($payment['id']??'');
        if (!ctype_digit($ref) || !ctype_digit($pid)) throw new InvalidArgumentException('Referencia inválida');
        db()->beginTransaction();
        try {
            $o=one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$ref]);
            $amount=(int)round(((float)($payment['transaction_amount']??-1))*100);
            if (!$o || $o['payment_mode']!==$mode || ($payment['currency_id']??'')!=='ARS' || $amount!==(int)$o['total_cents'] || (int)($payment['collector_id']??0)!==$collector || !array_key_exists('live_mode',$payment) || (bool)$payment['live_mode']!==($mode==='production')) throw new InvalidArgumentException('Pago no corresponde al pedido');
            $existing=one('SELECT order_id FROM payments WHERE id=?',[$pid]);
            if($existing && (int)$existing['order_id']!==(int)$o['id']) throw new InvalidArgumentException('Pago ya asociado a otro pedido');
            $status=(string)($payment['status']??'');
            if (!in_array($status,['approved','pending','in_process','authorized','rejected','cancelled','refunded','charged_back'],true)) throw new InvalidArgumentException('Estado de pago desconocido');
            query('INSERT INTO payments (id,order_id,status,amount_cents) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), amount_cents=VALUES(amount_cents)',[$pid,$o['id'],$status,$amount]);
            $latest=one("SELECT status FROM payments WHERE order_id=? ORDER BY FIELD(status,'approved','charged_back','refunded','authorized','in_process','pending','rejected','cancelled'), id DESC LIMIT 1",[$o['id']]);
            $new=$latest['status'];
            query('UPDATE orders SET payment_status=? WHERE id=?',[$new,$o['id']]);
            db()->commit();
            return $new!==$o['payment_status'];
        } catch (Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); throw $ex; }
    }
}
