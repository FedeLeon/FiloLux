<?php
declare(strict_types=1);
$error='';
if($post) {
    try {
        rateLimit('auth',30);
        if($path==='/registro') {
            rateLimit('register',5,3600); $name=textInput('name',120); $email=emailInput(); $password=passwordInput();
            if(one('SELECT id FROM users WHERE email=?',[$email])) throw new InvalidArgumentException('Este correo ya está registrado. Podés ingresar o recuperar la contraseña.');
            query('INSERT INTO users (name,email,password) VALUES (?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['user_id']=db()->lastInsertId(); $destination=$_SESSION['intended']??'/cuenta'; unset($_SESSION['intended']); redirect($destination);
        }
        if($path==='/login') {
            rateLimit('login',10); $identifier=textInput('email'); $credentials=adminCredentials();
            $isAdmin=$credentials && hash_equals($credentials['username'],$identifier);
            $u=$isAdmin?one("SELECT * FROM users WHERE role='admin' ORDER BY id LIMIT 1"):one("SELECT * FROM users WHERE email=? AND role='customer'",[mb_strtolower($identifier)]);
            $hash=$isAdmin?$credentials['hash']:($u['password']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
            if(!password_verify((string)($_POST['password']??''),$hash) || !$u) throw new InvalidArgumentException('Usuario, correo o contraseña incorrectos.');
            session_regenerate_id(true); $_SESSION['user_id']=$u['id'];
            unset($_SESSION['admin_stamp']); if($isAdmin) $_SESSION['admin_stamp']=adminStamp($credentials);
            $destination=$_SESSION['intended']??($u['role']==='admin'?'/admin':'/cuenta'); unset($_SESSION['intended']); redirect($destination);
        }
        if($path==='/recuperar') {
            rateLimit('reset',5,3600); $email=emailInput();
            if(setting('smtp_host') && one("SELECT id FROM users WHERE email=? AND role='customer'",[$email])) {
                $token=bin2hex(random_bytes(32)); query('INSERT INTO password_resets (email,token_hash,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at)',[$email,hash('sha256',$token),date('Y-m-d H:i:s',time()+3600)]);
                sendMail($email,'Restablecer tu contraseña',"Para crear una contraseña nueva, abrí este enlace (vence en una hora):\n".url('/restablecer?email='.rawurlencode($email).'&token='.$token));
            }
            flash('Si el correo está registrado y el servicio de correo está habilitado, recibirás un enlace para restablecer tu contraseña.'); redirect('/login');
        }
        if($path==='/restablecer') {
            rateLimit('new-password',10); $email=emailInput(); $token=textInput('token',64); $password=passwordInput();
            db()->beginTransaction();
            try { $r=one('SELECT * FROM password_resets WHERE email=? FOR UPDATE',[$email]); if(!$r || !one("SELECT id FROM users WHERE email=? AND role='customer'",[$email]) || strtotime($r['expires_at'])<time() || !hash_equals($r['token_hash'],hash('sha256',$token))) throw new InvalidArgumentException('El enlace es inválido o venció. Solicitá uno nuevo.');
                query("UPDATE users SET password=? WHERE email=? AND role='customer'",[password_hash($password,PASSWORD_DEFAULT),$email]); query('DELETE FROM password_resets WHERE email=?',[$email]); db()->commit();
            } catch(Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); throw $ex; }
            flash('Contraseña actualizada. Ya podés ingresar.'); redirect('/login');
        }
    } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
}
$titles=['/login'=>'Qué bueno verte.','/registro'=>'Tu próxima idea empieza acá.','/recuperar'=>'Recuperá tu acceso.','/restablecer'=>'Una nueva contraseña.'];
render('auth',['title'=>$titles[$path],'error'=>$error,'authPath'=>$path]);
