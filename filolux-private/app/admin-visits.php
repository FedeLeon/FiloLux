<?php
declare(strict_types=1);
$available=hasVisitAnalytics();
if(!$available) { render('admin-visits',['title'=>'Visitas','available'=>false]); return; }
if($path==='/admin/visitas/ubicaciones' && $post) {
    $labels=enrichVisitLocations();
    if(wantsJson()) jsonResponse(['locations'=>$labels]);
    flash('Ubicaciones actualizadas. Las IP locales o privadas no se consultan.'); redirect('/admin/visitas');
}
if($post) abortPage(405,'Acción no disponible.');
// Bounded cleanup also cascades to the corresponding events. Never touch business data.
query('DELETE FROM visit_events WHERE created_at<DATE_SUB(NOW(),INTERVAL 90 DAY) LIMIT 1000');
query('DELETE FROM site_visits WHERE last_seen_at<DATE_SUB(NOW(),INTERVAL 90 DAY) LIMIT 1000');
query('DELETE FROM visit_locations WHERE NOT EXISTS (SELECT 1 FROM site_visits v WHERE v.ip=visit_locations.ip) LIMIT 1000');
$locationJoin=' LEFT JOIN visit_locations l ON l.ip=v.ip ';
$locationColumns='l.country,l.region,l.city,l.status AS location_status';
if(preg_match('#^/admin/visitas/(\d+)$#D',$path,$match)) {
    $visit=one('SELECT v.*,'.$locationColumns.' FROM site_visits v'.$locationJoin.'WHERE v.id=?',[$match[1]]);
    if(!$visit) abortPage(404,'Visita no encontrada.');
    $events=query('SELECT * FROM visit_events WHERE visit_id=? ORDER BY id DESC LIMIT 200',[$visit['id']])->fetchAll();
    render('admin-visit',['title'=>'Detalle de visita','visit'=>$visit,'events'=>array_reverse($events)]); return;
}
if($path!=='/admin/visitas') abortPage(404,'Página no encontrada.');
$dateInput=static function(string $key,string $default): string {
    $value=is_string($_GET[$key]??null)?$_GET[$key]:$default;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$date || $date->format('Y-m-d')!==$value || $value<'1000-01-01' || $value>'9998-12-31') throw new InvalidArgumentException('Ingresá una fecha válida.');
    return $value;
};
$from=$dateInput('desde',date('Y-m-d',strtotime('-6 days')));
$until=$dateInput('hasta',date('Y-m-d'));
if($from>$until) throw new InvalidArgumentException('La fecha inicial debe ser anterior a la fecha final.');
$device=is_string($_GET['dispositivo']??null)?$_GET['dispositivo']:'';
if(!in_array($device,['','Celular','Tablet','Escritorio','Bot'],true)) $device='';
$withCart=($_GET['carrito']??'')==='1';
$withProducts=($_GET['productos']??'1')!=='0';
$ip=is_string($_GET['ip']??null)?trim($_GET['ip']):'';
if($ip!=='' && !filter_var($ip,FILTER_VALIDATE_IP)) throw new InvalidArgumentException('Ingresá una IP válida.');
$conditions=['v.started_at>=?','v.started_at<?']; $args=[$from.' 00:00:00',(new DateTimeImmutable($until))->modify('+1 day')->format('Y-m-d').' 00:00:00'];
if($device!=='') { $conditions[]='v.device=?'; $args[]=$device; }
if($ip!=='') { $conditions[]='v.ip=?'; $args[]=$ip; }
if($withCart) $conditions[]="EXISTS (SELECT 1 FROM visit_events ce WHERE ce.visit_id=v.id AND ce.type='cart_add')";
if($withProducts) $conditions[]="EXISTS (SELECT 1 FROM visit_events pe WHERE pe.visit_id=v.id AND pe.type='product_view')";
$where=implode(' AND ',$conditions);
$total=(int)query('SELECT COUNT(*) FROM site_visits v WHERE '.$where,$args)->fetchColumn();
$page=min(max(1,is_scalar($_GET['pagina']??null)?(int)$_GET['pagina']:1),max(1,(int)ceil($total/20)));
$summary=one("SELECT COUNT(*) AS events,COALESCE(SUM(e.type IN ('page_view','product_view')),0) AS pages,COUNT(DISTINCT CASE WHEN e.type='cart_add' THEN v.id END) AS carts FROM visit_events e JOIN site_visits v ON v.id=e.visit_id WHERE ".$where,$args);
$visits=query("SELECT v.*,".$locationColumns.", (SELECT COUNT(*) FROM visit_events e WHERE e.visit_id=v.id AND e.type IN ('page_view','product_view')) AS pages,(SELECT COUNT(*) FROM visit_events e WHERE e.visit_id=v.id AND e.type='product_view') AS products,EXISTS (SELECT 1 FROM visit_events e WHERE e.visit_id=v.id AND e.type='cart_add') AS has_cart FROM site_visits v".$locationJoin.'WHERE '.$where.' ORDER BY v.started_at DESC,v.id DESC LIMIT 20 OFFSET '.(($page-1)*20),$args)->fetchAll();
$topProducts=query("SELECT e.product_id,MAX(e.product_name) AS name,SUM(e.type='product_view') AS views,SUM(CASE WHEN e.type='cart_add' THEN e.quantity ELSE 0 END) AS added FROM visit_events e JOIN site_visits v ON v.id=e.visit_id WHERE ".$where." AND e.product_id IS NOT NULL GROUP BY e.product_id ORDER BY views DESC,added DESC LIMIT 10",$args)->fetchAll();
$filters=['desde'=>$from,'hasta'=>$until,'dispositivo'=>$device,'carrito'=>$withCart?'1':'','productos'=>$withProducts?'1':'0','ip'=>$ip];
render('admin-visits',compact('available','visits','total','page','summary','topProducts','filters')+['title'=>'Visitas']);
