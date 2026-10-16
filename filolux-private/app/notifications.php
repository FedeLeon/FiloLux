<?php
declare(strict_types=1);
function hasLiveNotifications(): bool {
    static $available;
    return $available ??= (bool)query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='admin_reviewed_at'")->fetchColumn() && (bool)query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='live_notifications'")->fetchColumn();
}
function notifyLive(string $key,string $audience,?int $userId,string $type,string $title,string $body,string $href=''): void {
    if(!hasLiveNotifications()) return;
    query('INSERT INTO live_notifications (event_key,audience,user_id,type,title,body,href) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id',[hash('sha256',$key),$audience,$userId,$type,$title,$body,$href]);
}
function unreadOrderCount(): int {
    return hasLiveNotifications()?(int)query('SELECT COUNT(*) FROM orders WHERE admin_reviewed_at IS NULL'.(hasOrderDeletion()?' AND admin_deleted_at IS NULL':''))->fetchColumn():0;
}
function liveNotificationFeed(array $u,int $after): array {
    if(!hasLiveNotifications()) return ['available'=>false,'events'=>[],'cursor'=>$after,'orders'=>0,'viewer'=>['id'=>(int)$u['id'],'role'=>$u['role']]];
    $where=$u['role']==='admin'?"audience='admin'":"audience='customer' AND user_id=".(int)$u['id'];
    if($u['role']==='admin' && hasOrderDeletion()) $where.=" AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.admin_deleted_at IS NOT NULL AND CONCAT('/admin/pedidos/',o.id)=live_notifications.href)";
    $events=query('SELECT id,type,title,body,href FROM live_notifications WHERE ('.$where.') AND id>? ORDER BY id LIMIT 30',[$after])->fetchAll();
    return ['available'=>true,'events'=>$events,'cursor'=>$events?(int)end($events)['id']:$after,'orders'=>$u['role']==='admin'?unreadOrderCount():0,'viewer'=>['id'=>(int)$u['id'],'role'=>$u['role']]];
}
