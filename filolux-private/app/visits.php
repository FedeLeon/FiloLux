<?php
declare(strict_types=1);

function hasVisitAnalytics(): bool {
    static $available;
    return $available ??= (int)query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('site_visits','visit_events','visit_locations')")->fetchColumn()===3;
}

function visitDevice(string $ua): array {
    $device=preg_match('/bot|crawl|spider|slurp|headless|preview|facebookexternalhit/i',$ua)?'Bot':(preg_match('/ipad|tablet|android(?!.*mobile)/i',$ua)?'Tablet':(preg_match('/mobile|iphone|ipod/i',$ua)?'Celular':'Escritorio'));
    $os='Desconocido';
    foreach(['Android'=>'/android/i','iOS'=>'/iphone|ipad|ipod/i','Windows'=>'/windows/i','macOS'=>'/macintosh|mac os/i','Linux'=>'/linux/i'] as $label=>$pattern) if(preg_match($pattern,$ua)) { $os=$label; break; }
    $browser='Desconocido';
    foreach(['Edge'=>'/edg(?:e|a|ios)?\//i','Opera'=>'/opr\/|opera/i','Firefox'=>'/firefox|fxios/i','Chrome'=>'/chrome|crios/i','Safari'=>'/safari/i'] as $label=>$pattern) if(preg_match($pattern,$ua)) { $browser=$label; break; }
    return [$device,$browser,$os];
}

function publicVisitIp(string $ip): bool {
    return (bool)filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
}

function recordVisitEvent(string $type,string $path,?array $product=null,int $quantity=0): void {
    // Analytics failures must never prevent browsing or a successful cart mutation.
    try {
        if((user()['role']??'')==='admin' || !hasVisitAnalytics()) return;
        $ip=$_SERVER['REMOTE_ADDR']??'';
        if(!filter_var($ip,FILTER_VALIDATE_IP)) return;
        $ua=mb_substr(mb_convert_encoding($_SERVER['HTTP_USER_AGENT']??'','UTF-8','UTF-8'),0,512);
        $now=time(); $current=$_SESSION['analytics_visit']??[];
        if(($current['last']??0)<$now-1800 || ($current['ip']??'')!==$ip || ($current['ua']??'')!==$ua) $current=['token'=>bin2hex(random_bytes(32)),'ip'=>$ip,'ua'=>$ua];
        [$device,$browser,$os]=visitDevice($ua);
        query('INSERT IGNORE INTO visit_locations (ip,status) VALUES (?,?)',[$ip,publicVisitIp($ip)?'pending':'private']);
        query('INSERT INTO site_visits (token,ip,device,browser,os,user_agent,started_at,last_seen_at) VALUES (?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW()',[$current['token'],$ip,$device,$browser,$os,$ua]);
        $visitId=(int)one('SELECT id FROM site_visits WHERE token=?',[$current['token']])['id'];
        query('INSERT INTO visit_events (visit_id,type,path,product_id,product_name,quantity,created_at) VALUES (?,?,?,?,?,?,NOW())',[$visitId,$type,$path,$product['id']??null,$product['name']??'',max(0,min(99,$quantity))]);
        $current['last']=$now; $_SESSION['analytics_visit']=$current;
    } catch(Throwable $ex) { error_log('FiloLux: no se pudo registrar actividad de visitas ('.$ex::class.').'); }
}

function recordPageVisit(string $view,array $data): void {
    global $path;
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET' || http_response_code()>=400) return;
    if(!in_array($view,['home','catalog','product','contact','cart','checkout','inquiry-page'],true)) return;
    recordVisitEvent($view==='product'?'product_view':'page_view',$path,$view==='product'?($data['product']??null):null);
}

function visitLocationLabel(array $row): string {
    if(($row['location_status']??$row['status']??'')==='private') return 'IP local o privada';
    $parts=array_values(array_unique(array_filter([$row['city']??'',$row['region']??'',$row['country']??''])));
    return $parts?implode(', ',$parts):((($row['location_status']??$row['status']??'')==='pending')?'Ubicación pendiente':'Ubicación no disponible');
}

function lookupVisitLocations(array $ips): array {
    $ips=array_values(array_unique(array_filter($ips,'publicVisitIp')));
    if(!$ips) return [];
    $ips=array_slice($ips,0,10);
    $curl=curl_init('https://get.geojs.io/v1/ip/geo.json?ip='.rawurlencode(implode(',',$ips)));
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'FiloLux/1.0']);
    $body=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
    if($status!==200 || !is_string($body) || strlen($body)>100000) return [];
    $rows=json_decode($body,true);
    if(!is_array($rows)) return [];
    if(isset($rows['ip'])) $rows=[$rows];
    $out=[];
    foreach($rows as $row) {
        if(!is_array($row) || !in_array($row['ip']??null,$ips,true)) continue;
        $entry=['country'=>'','region'=>'','city'=>''];
        foreach($entry as $key=>$_) if(is_string($row[$key]??null)) $entry[$key]=mb_substr($row[$key],0,100);
        $out[$row['ip']]=$entry;
    }
    return $out;
}

function enrichVisitLocations(): array {
    $ips=query("SELECT ip FROM visit_locations WHERE status='pending' OR (status='unavailable' AND checked_at<DATE_SUB(NOW(),INTERVAL 6 HOUR)) ORDER BY checked_at,ip LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    $locations=lookupVisitLocations($ips); $labels=[];
    foreach($ips as $ip) {
        $entry=$locations[$ip]??['country'=>'','region'=>'','city'=>''];
        $status=implode('',$entry)!==''?'resolved':'unavailable';
        query('UPDATE visit_locations SET country=?,region=?,city=?,status=?,checked_at=NOW() WHERE ip=?',[$entry['country'],$entry['region'],$entry['city'],$status,$ip]);
        $labels[$ip]=visitLocationLabel($entry+['status'=>$status]);
    }
    return $labels;
}
