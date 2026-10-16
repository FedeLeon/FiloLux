<?php
declare(strict_types=1);
require_once ROOT.'/app/notifications.php';
final class Payments {
    public static function hasTracking(): bool {
        static $available;
        return $available ??= (int)query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='orders' AND COLUMN_NAME='payment_return_data') OR (TABLE_NAME='payments' AND COLUMN_NAME='details'))")->fetchColumn()===2;
    }
    public static function registerReturn(array $order,array $query): void {
        if(!self::hasTracking() || in_array($order['payment_status'],['approved','refunded','charged_back'],true)) return;
        $id=$query['payment_id']??$query['collection_id']??'';
        if(!is_string($id) || !preg_match('/^[0-9]{1,100}$/D',$id)) return;
        if(isset($query['external_reference']) && $query['external_reference']!==(string)$order['id']) return;
        if(isset($query['preference_id']) && !empty($order['preference_id']) && $query['preference_id']!==$order['preference_id']) return;
        $previous=json_decode($order['payment_return_data']??'null',true);
        if(!empty($previous['payment_id']) && $previous['payment_id']!==$id && self::waiting($order)) return;
        $data=['payment_id'=>$id,'received_at'=>date('c')];
        foreach(['collection_id','collection_status','status','external_reference','payment_type','merchant_order_id','preference_id','site_id','processing_mode'] as $key) {
            if(isset($query[$key]) && is_string($query[$key]) && strlen($query[$key])<=150) $data[$key]=$query[$key];
        }
        // Browser return values are only hints: they never confirm a payment.
        query('UPDATE orders SET payment_return_data=? WHERE id=? AND user_id=?',[json_encode($data,JSON_THROW_ON_ERROR),$order['id'],$order['user_id']]);
    }
    public static function waiting(array $order): bool {
        if(in_array($order['payment_status'],['approved','refunded','charged_back'],true)) return false;
        if(in_array($order['payment_status'],['in_process','authorized'],true)) return true;
        if(one("SELECT id FROM payments WHERE order_id=? AND status IN ('pending','in_process','authorized') LIMIT 1",[$order['id']])) return true;
        $hint=json_decode($order['payment_return_data']??'null',true);
        if(!empty($hint['payment_id'])) {
            $payment=one('SELECT status FROM payments WHERE id=? AND order_id=?',[$hint['payment_id'],$order['id']]);
            return !$payment || !in_array($payment['status'],['rejected','cancelled','approved','refunded','charged_back'],true);
        }
        return false;
    }
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
            if(!$o) throw new InvalidArgumentException('La referencia del pago no corresponde a un pedido existente.');
            if($o['payment_mode']!==$mode) throw new InvalidArgumentException('El entorno del pedido no coincide con el entorno del webhook (pruebas/producción).');
            if(($payment['currency_id']??'')!=='ARS') throw new InvalidArgumentException('La moneda del pago no es ARS.');
            if($amount!==(int)$o['total_cents']) throw new InvalidArgumentException('El importe del pago no coincide con el total del pedido.');
            if((int)($payment['collector_id']??0)!==$collector) throw new InvalidArgumentException('La cuenta receptora del pago no coincide con la cuenta del Access Token.');
            if(!array_key_exists('live_mode',$payment)) throw new InvalidArgumentException('Mercado Pago no informó el entorno del pago (live_mode).');
            if((bool)$payment['live_mode']!==($mode==='production')) throw new InvalidArgumentException('El pago consultado pertenece a un entorno diferente al webhook (live_mode).');
            $existing=one('SELECT order_id FROM payments WHERE id=?',[$pid]);
            if($existing && (int)$existing['order_id']!==(int)$o['id']) throw new InvalidArgumentException('Pago ya asociado a otro pedido');
            $status=(string)($payment['status']??'');
            if (!in_array($status,['approved','pending','in_process','authorized','rejected','cancelled','refunded','charged_back'],true)) throw new InvalidArgumentException('Estado de pago desconocido');
            query('INSERT INTO payments (id,order_id,status,amount_cents) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), amount_cents=VALUES(amount_cents)',[$pid,$o['id'],$status,$amount]);
            if(self::hasTracking()) {
                $details=[];
                foreach(['payment_type_id','payment_method_id','date_created','date_approved','status_detail'] as $field) if(isset($payment[$field]) && is_scalar($payment[$field])) $details[$field]=(string)$payment[$field];
                if(isset($payment['order']['id']) && is_scalar($payment['order']['id'])) $details['merchant_order_id']=(string)$payment['order']['id'];
                query('UPDATE payments SET details=? WHERE id=?',[json_encode($details,JSON_THROW_ON_ERROR),$pid]);
            }
            $latest=one("SELECT status FROM payments WHERE order_id=? ORDER BY FIELD(status,'approved','charged_back','refunded','authorized','in_process','pending','rejected','cancelled'), id DESC LIMIT 1",[$o['id']]);
            $new=$latest['status'];
            query('UPDATE orders SET payment_status=? WHERE id=?',[$new,$o['id']]);
            notifyLive('payment:'.$mode.':'.$pid.':'.$status.':admin','admin',null,'payment_update','Pago actualizado · Pedido #'.$o['id'],'Mercado Pago: '.paymentLabel($status).'. Importe: '.money($amount).'.','/admin/pedidos/'.$o['id']);
            if($new!==$o['payment_status'] || (!$existing && $status===$new)) notifyLive('payment:'.$mode.':'.$pid.':'.$status.':customer','customer',(int)$o['user_id'],'payment_update','Tu pago del pedido #'.$o['id'],'Estado del pago: '.paymentLabel($new).'.','/pedidos/'.$o['id']);
            db()->commit();
            return $new!==$o['payment_status'];
        } catch (Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); throw $ex; }
    }
}
