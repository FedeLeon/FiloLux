<?php
declare(strict_types=1);
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO {
    static $pdo;
    if (!$pdo) { $c=$GLOBALS['config']['db']; $pdo=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); $pdo->exec("SET time_zone = '-03:00'"); }
    return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function one(string $sql, array $args=[]): ?array { return query($sql,$args)->fetch() ?: null; }
function setting(string $key, string $default=''): string {
    if (!isset($GLOBALS['settings'])) $GLOBALS['settings']=query('SELECT `key`,`value` FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return (string)($GLOBALS['settings'][$key] ?? $default);
}
function putSetting(string $key,string $value): void { query('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)',[$key,$value]); unset($GLOBALS['settings']); }
function encryptSecret(string $value): string { $iv=random_bytes(12); $out=openssl_encrypt($value,'aes-256-gcm',base64_decode($GLOBALS['config']['key'],true),OPENSSL_RAW_DATA,$iv,$tag); return base64_encode($iv.$tag.$out); }
function decryptSecret(string $value): string { if (!$value) return ''; $raw=base64_decode($value,true); if ($raw===false || strlen($raw)<28) throw new RuntimeException('Secreto inválido'); $out=openssl_decrypt(substr($raw,28),'aes-256-gcm',base64_decode($GLOBALS['config']['key'],true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16)); if ($out===false) throw new RuntimeException('No se pudo descifrar'); return $out; }
function secret(string $key): string { return decryptSecret(setting($key)); }
function url(string $path='/'): string { return rtrim($GLOBALS['config']['url'],'/').$path; }
function redirect(string $path): never { header('Location: '.url($path),true,303); exit; }
function flash(string $text): void { $_SESSION['flash']=$text; }
function csrf(): string { return '<input type="hidden" name="_csrf" value="'.e($_SESSION['csrf']).'">'; }
function adminCredentials(): ?array {
    static $loaded=false, $credentials=null;
    if (!$loaded) {
        $loaded=true;
        $file=getenv('FILOLUX_ENV_PATH')?:ROOT.'/.env';
        $env=is_readable($file)?@parse_ini_file($file,false,INI_SCANNER_RAW):false;
        if (is_array($env)) {
            $username=trim((string)($env['ADMIN_USERNAME']??''));
            $hash=(string)($env['ADMIN_PASSWORD_HASH']??'');
            if ($username!=='' && (password_get_info($hash)['algo']??null)!==null) {
                $credentials=['username'=>$username,'hash'=>$hash];
            }
        }
    }
    return $credentials;
}
function adminStamp(array $credentials): string { return hash('sha256',$credentials['username']."\0".$credentials['hash']); }
function user(): ?array {
    static $loaded=false,$u;
    if (!$loaded) {
        $u=isset($_SESSION['user_id'])?one('SELECT id,name,email,role FROM users WHERE id=?',[$_SESSION['user_id']]):null;
        if ($u && $u['role']==='admin') {
            $credentials=adminCredentials();
            if (!$credentials || !hash_equals(adminStamp($credentials),(string)($_SESSION['admin_stamp']??''))) {
                unset($_SESSION['user_id'],$_SESSION['admin_stamp']);
                $u=null;
            }
        }
        $loaded=true;
    }
    return $u;
}
function requireUser(bool $admin=false): array { $u=user(); if (!$u) { $intended=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH); if(!$admin && preg_match('#^/(checkout|cuenta|pedidos/\d+)$#D',$intended)) $_SESSION['intended']=$intended; flash('Ingresá para continuar.'); redirect('/login'); } if ($admin && $u['role']!=='admin') abortPage(403,'No tenés acceso a esta sección.'); return $u; }
function wantsJson(): bool { return str_contains($_SERVER['HTTP_ACCEPT']??'', 'application/json'); }
function jsonResponse(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); exit; }
function abortPage(int $status,string $message): never { if(wantsJson()) jsonResponse(['error'=>$message],$status); http_response_code($status); render('message',['title'=>'FiloLux','message'=>$message]); exit; }
function render(string $view,array $data=[]): void { global $path; extract($data); ob_start(); require ROOT.'/views/'.$view.'.php'; $content=ob_get_clean(); require ROOT.'/views/layout.php'; }
function money(int|string $cents): string { return '$ '.number_format((int)$cents/100,2,',','.'); }
function cents(string $input): int { $input=trim($input); if (!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D',$input)) throw new InvalidArgumentException('Ingresá un importe válido, sin separadores de miles.'); $parts=explode('.',str_replace(',','.',$input)); return ((int)$parts[0])*100+(int)str_pad($parts[1]??'',2,'0'); }
function textInput(string $key,int $max=190,bool $required=true): string { $v=trim((string)($_POST[$key]??'')); if (($required && $v==='') || mb_strlen($v)>$max) throw new InvalidArgumentException('Revisá el campo '.str_replace('_',' ',$key).'.'); return $v; }
function emailInput(): string { $v=mb_strtolower(textInput('email')); if (!filter_var($v,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Ingresá un correo válido.'); return $v; }
function passwordInput(): string { $v=(string)($_POST['password']??''); if (strlen($v)<10 || strlen($v)>72) throw new InvalidArgumentException('La contraseña debe tener entre 10 y 72 caracteres.'); return $v; }
function rateLimit(string $action,int $max=10,int $seconds=900): void { $key=hash('sha256',$action.'|'.($_SERVER['REMOTE_ADDR']??'cli')); query('INSERT INTO rate_limits (bucket,attempts,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(expires_at < UNIX_TIMESTAMP(),1,attempts+1),expires_at=IF(expires_at < UNIX_TIMESTAMP(),VALUES(expires_at),expires_at)',[$key,time()+$seconds]); $r=one('SELECT attempts FROM rate_limits WHERE bucket=?',[$key]); if ($r['attempts']>$max) throw new InvalidArgumentException('Demasiados intentos. Volvé a intentar en unos minutos.'); }
function whatsapp(string $message='Hola, quiero consultar por un trabajo personalizado.'): string { $phone=preg_replace('/\D/','',setting('whatsapp')); return $phone?'https://wa.me/'.$phone.'?text='.rawurlencode($message):url('/contacto'); }
function cartItems(): array { $out=[]; foreach (($_SESSION['cart']??[]) as $id=>$qty) { $p=one("SELECT p.*,(SELECT path FROM product_images WHERE product_id=p.id ORDER BY id LIMIT 1) AS image FROM products p WHERE p.id=? AND active=1 AND mode='buy'",[$id]); if ($p) { $p['quantity']=min(99,max(1,(int)$qty)); $out[]=$p; } } return $out; }
function cartTotal(array $items): int { return array_sum(array_map(fn($p)=>(int)$p['price_cents']*$p['quantity'],$items)); }
function orderStatuses(): array { return ['pendiente','en fabricación','listo','enviado','entregado','cancelado']; }
function paymentLabel(string $s): string { return ['pending'=>'Pendiente','approved'=>'Aprobado','in_process'=>'En proceso','rejected'=>'Rechazado','cancelled'=>'Cancelado','refunded'=>'Reintegrado','charged_back'=>'Contracargo','authorized'=>'Autorizado'][$s]??$s; }
function uploadImage(array $file): string {
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('No se pudo subir la imagen. Máximo 5 MB por archivo.');
    if ($file['size']>5*1024*1024) throw new InvalidArgumentException('La imagen supera 5 MB.');
    $info=@getimagesize($file['tmp_name']); $type=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!$info || !in_array($type,['image/jpeg','image/png','image/webp'],true) || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>16000000) throw new InvalidArgumentException('Usá JPG, PNG o WebP de hasta 16 megapíxeles y 6000 px por lado.');
    $bytes=file_get_contents($file['tmp_name']); $img=@imagecreatefromstring($bytes); if (!$img) throw new InvalidArgumentException('Imagen inválida.');
    $path='uploads/'.bin2hex(random_bytes(20)).'.webp'; imagepalettetotruecolor($img); imagealphablending($img,false); imagesavealpha($img,true);
    $ok=imagewebp($img,PUBLIC_ROOT.'/'.$path,85); imagedestroy($img); if (!$ok) throw new RuntimeException('No se pudo guardar la imagen.'); return $path;
}
function sendMail(string $to,string $subject,string $body): bool {
    if (!setting('smtp_host')) return false;
    try { $m=new PHPMailer\PHPMailer\PHPMailer(true); $m->isSMTP(); $m->Host=setting('smtp_host'); $m->Port=(int)setting('smtp_port','587'); $m->SMTPAuth=setting('smtp_user')!==''; $m->Username=setting('smtp_user'); $m->Password=secret('smtp_password'); $m->SMTPSecure=setting('smtp_security','tls'); $m->Timeout=10; $m->CharSet='UTF-8'; $m->setFrom(setting('smtp_from'),setting('brand','FiloLux')); $m->addAddress($to); $m->Subject=$subject; $m->Body=$body; $m->send(); return true; } catch (Throwable $ex) { error_log('FiloLux: fallo de envío SMTP (sin datos sensibles).'); return false; }
}
