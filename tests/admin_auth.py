"""Environment-based administrator auth against isolated fixtures on :8087.
Uses only fixture passwords; never stores the delivery password in test code.
"""
import requests, pathlib, subprocess, re, os
ROOT=pathlib.Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087';ENV=ROOT/'tests/.runtime/admin.env'
original=ENV.read_bytes()
def token(s,path='/login'):
 r=s.get(BASE+path);assert r.status_code==200
 return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
def login(s,username,password):
 return s.post(BASE+'/login',data={'_csrf':token(s),'email':username,'password':password},allow_redirects=False)
def clear_limits():
 subprocess.run(['php','-r',"define('ROOT',getcwd().'/filolux-private'); $GLOBALS['config']=require 'tests/.runtime/config.php'; require ROOT.'/app/core.php'; if($GLOBALS['config']['db']['name']!=='filolux_test') exit(1); query('DELETE FROM rate_limits');"],cwd=ROOT,check=True)
clear_limits()
try:
 s=requests.Session();r=login(s,'admin','AdminTest2026!');assert r.status_code==303 and r.headers['Location'].endswith('/admin')
 assert s.get(BASE+'/admin').status_code==200
 for username,password in [('admin','wrong'),('admin@example.test','AdminTest2026!')]:
  r=login(requests.Session(),username,password);assert r.status_code==200 and 'incorrectos' in r.text
 # A changed hash revokes existing admin sessions immediately.
 newhash=subprocess.check_output(['php','-r',"echo password_hash('RotatedTest2026!',PASSWORD_DEFAULT);"]).decode()
 ENV.write_text('ADMIN_USERNAME=admin\nADMIN_PASSWORD_HASH="'+newhash+'"\n')
 assert s.get(BASE+'/admin',allow_redirects=False).status_code==303
 assert 'incorrectos' in login(requests.Session(),'admin','AdminTest2026!').text
 rotated=requests.Session();assert login(rotated,'admin','RotatedTest2026!').status_code==303
 # Missing environment fails closed, even with an existing authenticated admin session.
 ENV.unlink();assert rotated.get(BASE+'/admin',allow_redirects=False).status_code==303
 assert 'incorrectos' in login(requests.Session(),'admin','RotatedTest2026!').text
 ENV.write_bytes(original)
 # An old database reset token cannot overwrite or bypass the env-based credential.
 reset_token='a'*64
 subprocess.run(['php','-r',"define('ROOT',getcwd().'/filolux-private'); $GLOBALS['config']=require 'tests/.runtime/config.php'; require ROOT.'/app/core.php'; query('INSERT INTO password_resets (email,token_hash,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at)',['admin@example.test',hash('sha256',str_repeat('a',64)),date('Y-m-d H:i:s',time()+3600)]);"],cwd=ROOT,check=True)
 anon=requests.Session();t=token(anon,'/restablecer?email=admin@example.test&token='+reset_token)
 r=anon.post(BASE+'/restablecer',data={'_csrf':t,'email':'admin@example.test','token':reset_token,'password':'Replacement2026!'})
 assert 'inválido o venció' in r.text
 assert login(requests.Session(),'admin','AdminTest2026!').status_code==303
 print('PASS: usuario admin, hash de entorno, rechazo de contraseña incorrecta/acceso por correo, revocación de sesiones, bloqueo sin .env y rechazo de recuperación admin por base de datos.')
finally:
 ENV.write_bytes(original);os.chmod(ENV,0o600);clear_limits()
