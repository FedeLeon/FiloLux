<?php
declare(strict_types=1);
if($path==='/admin/visitas' || str_starts_with($path,'/admin/visitas/')) { require ROOT.'/app/admin-visits.php'; return; }
if($path==='/admin/usuarios') {
    $search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
    $where="u.role='customer'"; $args=[];
    if($search!=='') { $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%'; $where.=" AND (u.name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')"; $args=[$pattern,$pattern]; }
    $total=(int)query('SELECT COUNT(*) FROM users u WHERE '.$where,$args)->fetchColumn();
    $page=min(max(1,is_scalar($_GET['pagina']??null)?(int)$_GET['pagina']:1),max(1,(int)ceil($total/25)));
    $fields=hasUserProfiles()?',u.phone,u.street,u.street_number,u.apartment,u.city,u.province,u.postal_code':'';
    $customers=query('SELECT u.id,u.name,u.email,u.created_at'.$fields.',(SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) AS order_count FROM users u WHERE '.$where.' ORDER BY u.created_at DESC,u.id DESC LIMIT 25 OFFSET '.(($page-1)*25),$args)->fetchAll();
    render('admin-users',['title'=>'Usuarios registrados','customers'=>$customers,'search'=>$search,'total'=>$total,'page'=>$page]); return;
}
if($path==='/admin') {
    render('admin-dashboard',['title'=>'Tu taller, en un solo lugar','counts'=>['products'=>(int)query('SELECT COUNT(*) FROM products')->fetchColumn(),'orders'=>(int)query('SELECT COUNT(*) FROM orders')->fetchColumn(),'pending'=>(int)query("SELECT COUNT(*) FROM orders WHERE payment_status='approved' AND status IN ('pendiente','en fabricación')")->fetchColumn()],'orders'=>query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll()]); return;
}
if($path==='/admin/productos') {
    $rawCategory=is_string($_GET['categoria']??null)?$_GET['categoria']:'';
    $selectedCategory=$rawCategory==='sin_categoria'?'sin_categoria':(ctype_digit($rawCategory)?max(0,(int)$rawCategory):0);
    $search=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,100):'';
    $conditions=[]; $args=[];
    if($selectedCategory==='sin_categoria') $conditions[]='NOT EXISTS (SELECT 1 FROM categories c WHERE '.productCategoryMembershipSql().')';
    elseif($selectedCategory>0) { $conditions[]='EXISTS (SELECT 1 FROM categories c WHERE c.id=? AND '.productCategoryMembershipSql().')'; $args[]=$selectedCategory; }
    if($search!=='') {
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
        $conditions[]="(p.name LIKE ? ESCAPE '!' OR p.description LIKE ? ESCAPE '!' OR p.dimensions LIKE ? ESCAPE '!')";
        array_push($args,$pattern,$pattern,$pattern);
    }
    $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
    render('admin-products',[
        'title'=>'Productos',
        'products'=>query('SELECT p.*,'.productCategoryNamesSql().' AS category,(SELECT path FROM product_images WHERE product_id=p.id ORDER BY id LIMIT 1) AS image FROM products p'.$where.' ORDER BY p.id DESC LIMIT 500',$args)->fetchAll(),
        'categories'=>query('SELECT * FROM categories ORDER BY name')->fetchAll(),
        'selectedCategory'=>$selectedCategory,
        'search'=>$search,
    ]); return;
}
if(preg_match('#^/admin/productos/(nuevo|\d+)$#D',$path,$m)) {
    $id=$m[1]==='nuevo'?0:(int)$m[1]; $product=$id?one('SELECT * FROM products WHERE id=?',[$id]):['name'=>'','description'=>'','dimensions'=>'','category_id'=>'','price_cents'=>0,'lead_time'=>'7 a 10 días hábiles','mode'=>'buy','active'=>1,'featured'=>0]; if(!$product) abortPage(404,'Producto no encontrado.');
    $selectedCategories=$id?productCategoryIds($id):[];
    $selectedColors=productColors($product);
    $error='';
    if($post) {
        $uploaded=[];
        try {
            if(isset($_POST['colors_present'])) {
                $rawColors=$_POST['colors']??[];
                if(!is_array($rawColors) || count($rawColors)>6) throw new InvalidArgumentException('Colores inválidos.');
                foreach($rawColors as $color) if(!is_string($color) || !isset(productColorOptions()[$color])) throw new InvalidArgumentException('Color inválido.');
                $selectedColors=array_values(array_unique($rawColors));
                if(!hasProductColors()) throw new InvalidArgumentException('Importá primero la migración de colores.');
            }
            $rawCategories=$_POST['category_ids']??[];
            // Accept older clients until their forms are refreshed after the deploy.
            if(!isset($_POST['categories_present']) && !array_key_exists('category_ids',$_POST) && !empty($_POST['category_id'])) $rawCategories=[$_POST['category_id']];
            if(!is_array($rawCategories) || count($rawCategories)>1000) throw new InvalidArgumentException('Categorías inválidas.');
            $selectedCategories=[];
            foreach($rawCategories as $value) {
                if(!is_scalar($value) || !ctype_digit((string)$value) || (int)$value<=0) throw new InvalidArgumentException('Categoría inválida.');
                $selectedCategories[]=(int)$value;
            }
            $selectedCategories=array_values(array_unique($selectedCategories)); sort($selectedCategories,SORT_NUMERIC);
            if($selectedCategories) {
                $placeholders=implode(',',array_fill(0,count($selectedCategories),'?'));
                if((int)query('SELECT COUNT(*) FROM categories WHERE id IN ('.$placeholders.')',$selectedCategories)->fetchColumn()!==count($selectedCategories)) throw new InvalidArgumentException('Categoría inválida.');
            }
            if(count($selectedCategories)>1 && !hasProductCategories()) throw new InvalidArgumentException('Para guardar varias categorías, importá primero la migración 2026-10-15-product-categories.sql en esta base.');
            $cat=$selectedCategories[0]??0;
            $name=textInput('name',160); $description=textInput('description',20000); $dimensions=textInput('dimensions',160,false); $lead=textInput('lead_time',160); $mode=textInput('mode',20);
            if(!in_array($mode,['buy','quote'],true)) throw new InvalidArgumentException('Modalidad inválida.');
            $price=$mode==='buy'?cents((string)($_POST['price']??'')):0; if($mode==='buy' && $price<=0) throw new InvalidArgumentException('El precio debe ser mayor a cero.');
            $existing=$id?query('SELECT * FROM product_images WHERE product_id=? ORDER BY id',[$id])->fetchAll():[];
            $delete=array_map('intval',(array)($_POST['delete_images']??[]));
            $files=$_FILES['images']??null; $count=0;
            if($files && is_array($files['name'])) foreach($files['name'] as $i=>$fn) if($files['error'][$i]!==UPLOAD_ERR_NO_FILE) $count++;
            $remain=count(array_filter($existing,fn($i)=>!in_array((int)$i['id'],$delete,true)));
            if($count+$remain>8) throw new InvalidArgumentException('Podés guardar hasta 8 fotos por producto.');
            if($files && is_array($files['name'])) foreach($files['name'] as $i=>$fn) if($files['error'][$i]!==UPLOAD_ERR_NO_FILE) $uploaded[]=uploadImage(['name'=>$fn,'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]]);
            db()->beginTransaction(); $args=[$cat?:null,$name,$description,$dimensions,$price,$lead,$mode,isset($_POST['active'])?1:0,isset($_POST['featured'])?1:0];
            if($id) { $args[]=$id; query('UPDATE products SET category_id=?,name=?,description=?,dimensions=?,price_cents=?,lead_time=?,mode=?,active=?,featured=? WHERE id=?',$args); }
            else { query('INSERT INTO products (category_id,name,description,dimensions,price_cents,lead_time,mode,active,featured) VALUES (?,?,?,?,?,?,?,?,?)',$args); $id=(int)db()->lastInsertId(); }
            if(hasProductColors()) query('UPDATE products SET available_colors=? WHERE id=?',[json_encode($selectedColors),$id]);
            if(hasProductCategories()) {
                query('DELETE FROM product_categories WHERE product_id=?',[$id]);
                foreach($selectedCategories as $categoryId) query('INSERT INTO product_categories (product_id,category_id) VALUES (?,?)',[$id,$categoryId]);
            }
            foreach($uploaded as $image) query('INSERT INTO product_images (product_id,path) VALUES (?,?)',[$id,$image]);
            foreach($existing as $image) if(in_array((int)$image['id'],$delete,true)) query('DELETE FROM product_images WHERE id=? AND product_id=?',[$image['id'],$id]);
            db()->commit();
            foreach($existing as $image) if(in_array((int)$image['id'],$delete,true)) @unlink(PUBLIC_ROOT.'/'.$image['path']);
            flash('Producto guardado.'); redirect('/admin/productos');
        } catch(Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); foreach($uploaded as $image) @unlink(PUBLIC_ROOT.'/'.$image); if(!$ex instanceof InvalidArgumentException) throw $ex; $error=$ex->getMessage(); $product=array_merge($product,array_intersect_key($_POST,$product)); if(isset($_POST['price'])) { try { $product['price_cents']=cents($_POST['price']); } catch(Throwable) {} } $product['active']=isset($_POST['active']); $product['featured']=isset($_POST['featured']); }
    }
    render('admin-product',['title'=>$id?'Editar producto':'Nueva pieza','product'=>$product,'selectedCategories'=>$selectedCategories,'selectedColors'=>$selectedColors,'categories'=>query('SELECT * FROM categories ORDER BY name')->fetchAll(),'images'=>$id?query('SELECT * FROM product_images WHERE product_id=? ORDER BY id',[$id])->fetchAll():[],'error'=>$error]); return;
}
if($path==='/admin/categorias') {
    $error=''; if($post) { try { $id=(int)($_POST['id']??0); if(isset($_POST['delete'])) query('DELETE FROM categories WHERE id=?',[$id]); else { $name=textInput('name',100); if(one('SELECT id FROM categories WHERE name=? AND id<>?',[$name,$id])) throw new InvalidArgumentException('Ya existe esa categoría.'); if($id) query('UPDATE categories SET name=? WHERE id=?',[$name,$id]); else query('INSERT INTO categories (name) VALUES (?)',[$name]); } flash('Categorías actualizadas.'); redirect('/admin/categorias'); } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); } }
    render('admin-categories',['title'=>'Categorías','categories'=>query('SELECT * FROM categories ORDER BY name')->fetchAll(),'error'=>$error]); return;
}
if($path==='/admin/pedidos') { $where=''; $args=[]; $status=(string)($_GET['status']??''); if(in_array($status,orderStatuses(),true)) { $where=' WHERE status=?'; $args[]=$status; } render('admin-orders',['title'=>'Pedidos','orders'=>query('SELECT * FROM orders'.$where.' ORDER BY id DESC LIMIT 200',$args)->fetchAll()]); return; }
if(preg_match('#^/admin/pedidos/(\d+)$#D',$path,$m)) {
    $order=one('SELECT * FROM orders WHERE id=?',[$m[1]]); if(!$order) abortPage(404,'Pedido no encontrado.');
    if($post) { $status=textInput('status',30); if(!in_array($status,orderStatuses(),true)) throw new InvalidArgumentException('Estado inválido.'); if(!in_array($status,['pendiente','cancelado'],true) && $order['payment_status']!=='approved') throw new InvalidArgumentException('Confirmá el pago antes de avanzar con la fabricación.'); if($status==='enviado' && $order['delivery']!=='shipping') throw new InvalidArgumentException('Este pedido se retira en el taller.');
        query('UPDATE orders SET status=? WHERE id=?',[$status,$order['id']]); if($status!==$order['status']) sendMail($order['email'],'Tu pedido #'.$order['id'].' está '.$status,'El estado de tu pedido ahora es: '.$status.'. Más información en '.url('/pedidos/'.$order['id'])); flash('Estado actualizado. Cancelar un pedido no reintegra el pago; los reintegros se gestionan en Mercado Pago.'); redirect('/admin/pedidos/'.$order['id']); }
    render('order',['title'=>'Pedido #'.$order['id'],'order'=>$order,'items'=>query('SELECT * FROM order_items WHERE order_id=?',[$order['id']])->fetchAll(),'admin'=>true]); return;
}
if($path==='/admin/mensajes') {
    $available=hasContactMessages();
    $filter=is_string($_GET['estado']??null)?$_GET['estado']:'';
    if(!in_array($filter,['pendiente','respondido'],true)) $filter='';
    $counts=['pendiente'=>0,'respondido'=>0]; $messages=[]; $page=1; $total=0;
    if($available) {
        foreach(query('SELECT status,COUNT(*) AS total FROM contact_messages GROUP BY status')->fetchAll() as $row) $counts[$row['status']]=(int)$row['total'];
        $total=$filter!==''?$counts[$filter]:array_sum($counts);
        $requestedPage=is_scalar($_GET['pagina']??null)?(int)$_GET['pagina']:1;
        $page=min(max(1,$requestedPage),max(1,(int)ceil($total/20)));
        $where=$filter!==''?' WHERE status=?':'';
        $messages=query('SELECT * FROM contact_messages'.$where.' ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET '.(($page-1)*20),$filter!==''?[$filter]:[])->fetchAll();
    }
    render('admin-messages',['title'=>'Mensajes','available'=>$available,'filter'=>$filter,'counts'=>$counts,'messages'=>$messages,'page'=>$page,'total'=>$total]); return;
}
if(preg_match('#^/admin/mensajes/(\d+)/estado$#D',$path,$m) && $post) {
    if(!hasContactMessages()) abortPage(503,'Importá primero la migración de mensajes.');
    $status=textInput('status',20);
    if(!in_array($status,['pendiente','respondido'],true)) throw new InvalidArgumentException('Estado de mensaje inválido.');
    if(!one('SELECT id FROM contact_messages WHERE id=?',[$m[1]])) abortPage(404,'Mensaje no encontrado.');
    // Choose the timestamp expression here instead of comparing bound text in SQL.
    $respondedAt=$status==='respondido'?'COALESCE(responded_at,CURRENT_TIMESTAMP)':'NULL';
    query('UPDATE contact_messages SET status=?,responded_at='.$respondedAt.' WHERE id=?',[$status,$m[1]]);
    flash('Estado del mensaje actualizado.'); redirect('/admin/mensajes');
}
if($path==='/admin/configuracion/logs' && !$post) {
    require ROOT.'/app/php-errors.php';
    $log=phpErrorLogTail(ROOT.'/storage/php-error.log');
    render('admin-php-errors',['title'=>'Errores de PHP','log'=>$log]); return;
}
if($path==='/admin/configuracion') {
    $error='';
    if($post) {
        try {
            if(isset($_POST['test_mail'])) { if(!sendMail(user()['email'],'Prueba SMTP de FiloLux','La configuración de correo funciona.')) throw new InvalidArgumentException('No se pudo enviar el correo. Revisá la configuración SMTP y los registros del servidor.'); flash('Correo de prueba enviado a tu cuenta administradora.'); redirect('/admin/configuracion'); }
            $save=[];
            foreach(['brand'=>120,'hero_title'=>200,'hero_text'=>1000,'about'=>3000,'pickup'=>1000,'contact_address'=>500,'hours'=>300,'whatsapp'=>30,'contact_email'=>190,'instagram'=>300,'facebook'=>300,'smtp_host'=>190,'smtp_user'=>190,'smtp_from'=>190] as $key=>$max) $save[$key]=textInput($key,$max,in_array($key,['brand','hero_title','hero_text','pickup'],true));
            foreach(['contact_email','smtp_from'] as $key) if($save[$key]!=='' && !filter_var($save[$key],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Revisá el correo en '.$key.'.');
            foreach(['instagram','facebook'] as $key) if($save[$key]!=='' && (!filter_var($save[$key],FILTER_VALIDATE_URL) || parse_url($save[$key],PHP_URL_SCHEME)!=='https' || parse_url($save[$key],PHP_URL_USER))) throw new InvalidArgumentException('Las redes deben tener una URL HTTPS completa.');
            if($save['whatsapp']!=='' && !preg_match('/^\+?[\d\s()-]{8,30}$/D',$save['whatsapp'])) throw new InvalidArgumentException('Revisá el número de WhatsApp.');
            $save['shipping_price']=(string)cents((string)($_POST['shipping_price']??'0'));
            $save['mp_mode']=textInput('mp_mode',20); if(!in_array($save['mp_mode'],['test','production'],true)) throw new InvalidArgumentException('Modo de Mercado Pago inválido.');
            $save['smtp_security']=textInput('smtp_security',10); if(!in_array($save['smtp_security'],['tls','ssl'],true)) throw new InvalidArgumentException('Seguridad SMTP inválida.');
            $save['smtp_port']=(string)(int)($_POST['smtp_port']??587); if((int)$save['smtp_port']<1 || (int)$save['smtp_port']>65535) throw new InvalidArgumentException('Puerto SMTP inválido.');
            if($save['smtp_host']!=='' && (!preg_match('/^[a-zA-Z0-9.-]+$/D',$save['smtp_host']) || !$save['smtp_from'])) throw new InvalidArgumentException('Configurá host SMTP y correo remitente válidos.');
            foreach(['mp_test_token','mp_test_webhook','mp_production_token','mp_production_webhook','smtp_password'] as $key) { $val=trim((string)($_POST[$key]??'')); if(strlen($val)>2048 || str_contains($val,"\n") || str_contains($val,"\r")) throw new InvalidArgumentException('Credencial inválida.'); if(isset($_POST['clear_'.$key])) $save[$key]=''; elseif($val!=='') $save[$key]=encryptSecret($val); }
            $logo=null; if(($_FILES['logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) { $logo=uploadImage($_FILES['logo']); $save['logo']=$logo; } elseif(isset($_POST['clear_logo'])) $save['logo']='';
            db()->beginTransaction(); try { foreach($save as $key=>$val) putSetting($key,$val); db()->commit(); } catch(Throwable $ex) { db()->rollBack(); if($logo) @unlink(PUBLIC_ROOT.'/'.$logo); throw $ex; }
            flash('Configuración guardada.'); redirect('/admin/configuracion');
        } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
    }
    render('admin-settings',['title'=>'Configuración','error'=>$error]); return;
}
abortPage(404,'Página de administración no encontrada.');
