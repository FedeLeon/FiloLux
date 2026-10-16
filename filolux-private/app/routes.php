<?php
declare(strict_types=1);
$post=$_SERVER['REQUEST_METHOD']==='POST';
if($path==='/notificaciones' && !$post) {
    $u=user(); if(!$u) jsonResponse(['error'=>'authentication_required'],401);
    $raw=$_GET['desde']??'0';
    if(!is_string($raw) || !preg_match('/^[0-9]{1,18}$/D',$raw)) jsonResponse(['error'=>'invalid_cursor'],422);
    session_write_close();
    jsonResponse(liveNotificationFeed($u,(int)$raw));
}
require ROOT.'/app/profiles.php';
if($path==='/cuenta/perfil') return;
if(str_starts_with($path,'/carrito')) require ROOT.'/app/cart.php';
if(str_starts_with($path,'/admin')) { requireUser(true); require ROOT.'/app/admin.php'; return; }
if (($path==='/a-medida' && !$post) || ($path==='/consultas' && $post)) { require ROOT.'/app/inquiries.php'; return; }
if ($path==='/logout' && $post) { unset($_SESSION['user_id'],$_SESSION['admin_stamp']); session_regenerate_id(true); redirect('/'); }
if (in_array($path,['/login','/registro','/recuperar','/restablecer'],true)) { require ROOT.'/app/auth.php'; return; }
if ($path==='/' || $path==='/catalogo') {
    $sortOptions=[
        'destacados'=>['label'=>'Destacados','sql'=>'p.featured DESC, p.id DESC'],
        'nuevos'=>['label'=>'Más nuevos','sql'=>'p.created_at DESC, p.id DESC'],
        'antiguos'=>['label'=>'Más antiguos','sql'=>'p.created_at ASC, p.id ASC'],
        'precio_asc'=>['label'=>'Menor precio','sql'=>"CASE WHEN p.mode='buy' THEN 0 ELSE 1 END, p.price_cents ASC, p.id DESC"],
        'precio_desc'=>['label'=>'Mayor precio','sql'=>"CASE WHEN p.mode='buy' THEN 0 ELSE 1 END, p.price_cents DESC, p.id DESC"],
        'nombre_asc'=>['label'=>'Nombre: A–Z','sql'=>'p.name ASC, p.id DESC'],
        'nombre_desc'=>['label'=>'Nombre: Z–A','sql'=>'p.name DESC, p.id DESC'],
    ];
    $filters=['q'=>'','categoria'=>0,'orden'=>'destacados'];
    if($path==='/catalogo') {
        $filters['q']=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
        $filters['categoria']=is_scalar($_GET['categoria']??null)?max(0,(int)$_GET['categoria']):0;
        $requestedSort=is_string($_GET['orden']??null)?$_GET['orden']:'';
        if(isset($sortOptions[$requestedSort])) $filters['orden']=$requestedSort;
    }
    $args=[]; $where='p.active=1';
    if($filters['q']!=='') { $where.=' AND (p.name LIKE ? OR p.description LIKE ?)'; $args[]='%'.$filters['q'].'%'; $args[]='%'.$filters['q'].'%'; }
    if($filters['categoria']) { $where.=' AND EXISTS (SELECT 1 FROM categories c WHERE c.id=? AND '.productCategoryMembershipSql().')'; $args[]=$filters['categoria']; }
    $total=(int)query('SELECT COUNT(*) FROM products p WHERE '.$where,$args)->fetchColumn();
    $limit=$path==='/'?6:12;
    $requestedPage=is_scalar($_GET['pagina']??null)?(int)$_GET['pagina']:1;
    $page=$path==='/'?1:min(max(1,$requestedPage),max(1,(int)ceil($total/$limit)));
    $offset=($page-1)*$limit;
    $orderBy=$sortOptions[$filters['orden']]['sql'];
    $products=query('SELECT p.*,'.productCategoryNamesSql().' AS category,(SELECT path FROM product_images WHERE product_id=p.id ORDER BY id LIMIT 1) AS image FROM products p WHERE '.$where.' ORDER BY '.$orderBy.' LIMIT '.$limit.' OFFSET '.$offset,$args)->fetchAll();
    if ($path==='/catalogo' && $products) {
        // Fetch galleries for this page together, avoiding one query per card.
        $productIds=array_column($products,'id');
        $placeholders=implode(',',array_fill(0,count($productIds),'?'));
        $cardImages=[];
        foreach(query('SELECT product_id,path FROM product_images WHERE product_id IN ('.$placeholders.') ORDER BY id',$productIds)->fetchAll() as $image) {
            $cardImages[$image['product_id']][]=$image['path'];
        }
        foreach($products as &$cardProduct) $cardProduct['images']=$cardImages[$cardProduct['id']]??[];
        unset($cardProduct);
    }
    render($path==='/'?'home':'catalog',['title'=>$path==='/'?'Objetos únicos. Ideas sin límites.':'Catálogo','products'=>$products,'categories'=>query('SELECT * FROM categories ORDER BY name')->fetchAll(),'total'=>$total,'page'=>$page,'filters'=>$filters,'sortOptions'=>$sortOptions]); return;
}
if (preg_match('#^/producto/(\d+)$#D',$path,$m)) {
    $product=one('SELECT p.*,'.productCategoryNamesSql().' AS category FROM products p WHERE p.id=? AND p.active=1',[$m[1]]);
    if(!$product) abortPage(404,'No encontramos ese producto.');
    $product['category_ids']=productCategoryIds((int)$product['id']);
    $suggested=[];
    if($product['category_ids']) {
        $placeholders=implode(',',array_fill(0,count($product['category_ids']),'?'));
        $suggested=query('SELECT p.*,'.productCategoryNamesSql().' AS category,(SELECT path FROM product_images WHERE product_id=p.id ORDER BY id LIMIT 1) AS image FROM products p WHERE p.active=1 AND p.id<>? AND EXISTS (SELECT 1 FROM categories c WHERE c.id IN ('.$placeholders.') AND '.productCategoryMembershipSql().') ORDER BY p.featured DESC,p.id DESC LIMIT 3',array_merge([$product['id']],$product['category_ids']))->fetchAll();
    }
    render('product',['title'=>$product['name'],'product'=>$product,'images'=>query('SELECT * FROM product_images WHERE product_id=? ORDER BY id',[$m[1]])->fetchAll(),'suggested'=>$suggested]); return;
}
if ($path==='/contacto') { render('contact',['title'=>'Hablemos de tu próxima idea']); return; }
if ($path==='/privacidad' && !$post) { render('privacy',['title'=>'Privacidad']); return; }
if ($path==='/carrito/agregar' && $post) {
    $p=one("SELECT * FROM products WHERE id=? AND active=1 AND mode='buy'",[(int)($_POST['product_id']??0)]); if(!$p) abortPage(404,'Este producto no está disponible para comprar.');
    $colors=productColors($p); $color=textInput('color',30,false);
    if(($colors && !in_array($color,$colors,true)) || (!$colors && $color!=='')) throw new InvalidArgumentException('Elegí un color disponible para esta pieza.');
    $cartKey=(string)$p['id'].($color!==''?':'.$color:'');
    $before=(int)($_SESSION['cart'][$cartKey]??0);
    $_SESSION['cart'][$cartKey]=min(99,$before+max(1,min(99,(int)($_POST['quantity']??1))));
    $added=$_SESSION['cart'][$cartKey]-$before;
    if($added>0) recordVisitEvent('cart_add',$path,$p,$added);
    unset($_SESSION['checkout_key']); if(wantsJson()) jsonResponse(cartPayload('Agregaste '.$p['name'].' al carrito.',(int)$p['id'])); flash('Agregamos la pieza a tu carrito.'); redirect('/carrito');
}
if ($path==='/carrito/resumen' && !$post) jsonResponse(cartPayload());
if ($path==='/carrito/quitar' && $post) {
    $key=cartKeyInput(); $id=(int)$key;
    $quantity=(int)($_SESSION['cart'][$key]??0);
    unset($_SESSION['cart'][$key],$_SESSION['checkout_key']);
    if($quantity>0 && ($removed=one('SELECT id,name FROM products WHERE id=?',[(int)$id]))) recordVisitEvent('cart_remove',$path,$removed,$quantity);
    if(wantsJson()) jsonResponse(cartPayload('Producto eliminado del carrito.'));
    flash('Producto eliminado del carrito.'); redirect('/carrito');
}
if ($path==='/carrito') {
    if($post) {
        foreach(($_POST['quantities']??[]) as $id=>$qty) {
            if(!preg_match('/^[1-9][0-9]*(?::[a-z_]+)?$/D',(string)$id) || !is_scalar($qty)) throw new InvalidArgumentException('Cantidad inválida.');
            $before=(int)($_SESSION['cart'][$id]??0);
            if((int)$qty<=0) unset($_SESSION['cart'][$id]); elseif(isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id]=min(99,(int)$qty);
            $after=(int)($_SESSION['cart'][$id]??0);
            if($after!==$before && ($changed=one('SELECT id,name FROM products WHERE id=?',[(int)$id]))) recordVisitEvent($after>$before?'cart_add':'cart_remove',$path,$changed,abs($after-$before));
        }
        unset($_SESSION['checkout_key']); redirect('/carrito');
    }
    render('cart',['title'=>'Tu carrito','items'=>cartItems()]); return;
}
if ($path==='/checkout') {
    $u=requireUser(); $customer=userProfile($u); $items=cartItems(); foreach($items as $item) if($item['invalid_color']) throw new InvalidArgumentException('Un color del carrito ya no está disponible. Quitá esa pieza y volvé a elegirla desde el catálogo.'); if(!$items) { flash('Tu carrito está vacío.'); redirect('/carrito'); }
    if($post) {
        if (!hash_equals($_SESSION['checkout_key']??'',(string)($_POST['checkout_key']??''))) throw new InvalidArgumentException('El carrito cambió. Volvé a revisar la compra.');
        if(!Payments::enabled()) throw new InvalidArgumentException('Los pagos todavía no están habilitados. Consultanos desde Contacto.');
        $name=textInput('name',120); $phone=textInput('phone',40); if(!preg_match('/^[+\d\s().-]{6,40}$/D',$phone)) throw new InvalidArgumentException('Ingresá un teléfono válido.');
        $delivery=textInput('delivery',20); if(!in_array($delivery,['pickup','shipping'],true)) throw new InvalidArgumentException('Elegí una forma de entrega.');
        $shippingData=$delivery==='shipping'?profileInput(true):[];
        $address=$delivery==='shipping'?shippingAddress($shippingData):''; $notes=textInput('notes',2000,false);
        db()->beginTransaction();
        try {
            // Serialize retries from the same account, including double submissions.
            one('SELECT id FROM users WHERE id=? FOR UPDATE',[$u['id']]);
            $existing=one('SELECT id FROM orders WHERE checkout_key=? AND user_id=?',[$_SESSION['checkout_key'],$u['id']]);
            if($existing) { db()->commit(); redirect('/pedidos/'.$existing['id']); }
            $items=cartItems(); if(!$items) throw new InvalidArgumentException('El carrito ya no contiene productos disponibles.');
            foreach($items as $item) if($item['invalid_color']) throw new InvalidArgumentException('Revisá los colores de tu carrito antes de comprar.');
            $subtotal=cartTotal($items); $shipping=$delivery==='shipping'?(int)setting('shipping_price','0'):0;
            query('INSERT INTO orders (user_id,checkout_key,customer_name,email,phone,delivery,address,notes,subtotal_cents,shipping_cents,total_cents,payment_mode) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$u['id'],$_SESSION['checkout_key'],$name,$u['email'],$phone,$delivery,$address,$notes,$subtotal,$shipping,$subtotal+$shipping,setting('mp_mode','test')]);
            $id=db()->lastInsertId();
            foreach($items as $i) {
                $columns='order_id,product_id,name,quantity,price_cents,lead_time,dimensions';
                $values=[$id,$i['id'],$i['name'],$i['quantity'],$i['price_cents'],$i['lead_time'],$i['dimensions']];
                if(hasProductColors()) { $columns.=',color'; $values[]=$i['color']; }
                query('INSERT INTO order_items ('.$columns.') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',$values);
            }
            notifyLive('order:'.$id,'admin',null,'order_created','Nueva compra · Pedido #'.$id,$name.' registró un pedido por '.money($subtotal+$shipping).'. Pago pendiente de confirmación.','/admin/pedidos/'.$id);
            db()->commit();
        } catch(Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); throw $ex; }
        $_SESSION['cart']=[]; unset($_SESSION['checkout_key']);
        sendMail($u['email'],'Recibimos tu pedido #'.$id,'Tu pedido fue registrado. Completá el pago desde '.url('/pedidos/'.$id).'. La fabricación comienza al confirmar el pago.');
        flash('Pedido registrado. Continuá con el pago para confirmar tu compra.'); redirect('/pedidos/'.$id);
    }
    $_SESSION['checkout_key']??=bin2hex(random_bytes(32)); render('checkout',['title'=>'Finalizar compra','items'=>$items,'customer'=>$customer]); return;
}
if($path==='/cuenta') { $u=requireUser(); render('account',['title'=>'Mi cuenta','orders'=>query('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 100',[$u['id']])->fetchAll()]); return; }
if(preg_match('#^/pedidos/(\d+)(/pagar|/verificar)?$#D',$path,$m)) {
    $u=requireUser(); $order=one('SELECT * FROM orders WHERE id=? AND user_id=?',[$m[1],$u['id']]); if(!$order) abortPage(404,'No encontramos ese pedido.');
    if(empty($m[2]) && !$post && isset($_GET['retorno'])) {
        Payments::registerReturn($order,$_GET);
        redirect('/pedidos/'.$order['id']);
    }
    if(($m[2]??'')==='/verificar' && $post) {
        rateLimit('verify-payment:'.$u['id'],6,60);
        $hint=json_decode($order['payment_return_data']??'null',true);
        $paymentId=(string)($hint['payment_id']??'');
        if($paymentId==='') $paymentId=(string)(one('SELECT id FROM payments WHERE order_id=? ORDER BY updated_at DESC LIMIT 1',[$order['id']])['id']??'');
        if($paymentId==='') throw new InvalidArgumentException('Todavía no recibimos el código del pago.');
        try {
            $result=Payments::syncPayment($paymentId,[$order['payment_mode']],(int)$order['id']);
            if($result['changed']) sendMail($order['email'],'Actualización del pago #'.$order['id'],'El estado de tu pago fue actualizado. Consultá '.url('/pedidos/'.$order['id']));
            flash('Consultamos el pago en Mercado Pago. El estado del pedido está actualizado.');
        } catch(Throwable $ex) {
            error_log('FiloLux: verificación de pago payment_id='.$paymentId.' motivo='.($ex instanceof InvalidArgumentException?$ex->getMessage():'No se pudo consultar a Mercado Pago.'));
            flash('No pudimos confirmar el pago todavía. Podés intentar verificarlo de nuevo en unos instantes.');
        }
        redirect('/pedidos/'.$order['id']);
    }
    if(($m[2]??'')==='/pagar' && $post) {
        if($order['status']==='cancelado' || in_array($order['payment_status'],['approved','refunded','charged_back'],true)) throw new InvalidArgumentException('Este pedido ya no admite pagos.');
        if(Payments::waiting($order)) throw new InvalidArgumentException('Estamos verificando un pago de este pedido. Esperá la confirmación antes de volver a pagar.');
        if(!Payments::enabled($order['payment_mode'])) throw new InvalidArgumentException('Los pagos todavía no están habilitados.');
        db()->beginTransaction();
        try { $order=one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$order['id']]); if($order['status']==='cancelado' || in_array($order['payment_status'],['approved','refunded','charged_back'],true)) throw new InvalidArgumentException('Pedido no disponible para pagar'); if(Payments::waiting($order)) throw new InvalidArgumentException('Ya existe un pago pendiente de confirmación para este pedido.'); $link=Payments::preference($order); db()->commit(); } catch(Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); if($ex instanceof InvalidArgumentException) throw $ex; flash('No pudimos abrir Mercado Pago. Tu pedido sigue guardado; podés intentar nuevamente.'); redirect('/pedidos/'.$order['id']); }
        header('Location: '.$link,true,303); exit;
    }
    render('order',['title'=>'Pedido #'.$order['id'],'order'=>$order,'items'=>query('SELECT * FROM order_items WHERE order_id=?',[$order['id']])->fetchAll()]); return;
}
abortPage(404,'La página que buscás no está disponible.');
