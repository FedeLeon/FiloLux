"""Verify the project folders manual deployment using only filolux_manual_test on disposable MySQL.
Imports SQL explicitly; the application must never create its own tables or admin.
"""
import subprocess, pathlib, shutil, re, requests, time, os, json
ROOT=pathlib.Path(__file__).resolve().parents[1]; TARGET=ROOT/'tests/.runtime/manual-hostinger'; BASE='http://127.0.0.1:8088'
subprocess.run(['php','-r',"$p=new PDO('mysql:unix_socket=/tmp/filolux-mysql/mysql.sock','root',''); $p->exec('DROP DATABASE IF EXISTS filolux_manual_test'); $p->exec('CREATE DATABASE filolux_manual_test CHARACTER SET utf8mb4'); $p->exec(\"GRANT ALL ON filolux_manual_test.* TO 'filolux_test'@'127.0.0.1'\");"],check=True)
cfg=json.loads(subprocess.check_output(['php','-r',"echo json_encode(require 'tests/.runtime/config.php');"],cwd=ROOT))
if TARGET.exists(): shutil.rmtree(TARGET)
TARGET.mkdir()
shutil.copytree(ROOT/'filolux-private', TARGET/'filolux-private', ignore=shutil.ignore_patterns('storage'))
(TARGET/'filolux-private/storage').mkdir()
shutil.copytree(ROOT/'public', TARGET/'public_html', ignore=shutil.ignore_patterns('uploads', 'router.php'))
(TARGET/'public_html/uploads').mkdir()
shutil.copyfile(ROOT/'public/uploads/.htaccess', TARGET/'public_html/uploads/.htaccess')
assert (TARGET/'filolux-private/config.php').is_file()
assert (TARGET/'filolux-private/database/filolux.sql').is_file()
assert (TARGET/'filolux-private/.env').read_bytes()==(ROOT/'filolux-private/.env').read_bytes()
assert not (TARGET/'filolux-private/app/install.php').exists()
private=TARGET/'filolux-private';config=private/'config.php'
# Only test credentials are used to exercise login. Project .env was verified above.
shutil.copyfile(ROOT/'tests/.runtime/admin.env',private/'.env')
router=TARGET/'router.php';router.write_text("<?php $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); if($p!=='/' && is_file(__DIR__.'/public_html'.$p)) return false; require __DIR__.'/public_html/index.php';")
log=(TARGET/'server.log').open('w');env=os.environ.copy();env.pop('FILOLUX_CONFIG_PATH',None);env.pop('FILOLUX_ENV_PATH',None)
server=subprocess.Popen(['php','-d','opcache.enable=0','-S','127.0.0.1:8088','-t',str(TARGET/'public_html'),str(router)],stdout=log,stderr=log,env=env)
try:
    config.rename(private/'config.php.pending');s=requests.Session()
    for i in range(30):
        try:r=s.get(BASE);break
        except requests.ConnectionError:time.sleep(.1)
    assert r.status_code==503 and 'todavía no está configurado' in r.text and '<form' not in r.text
    assert s.post(BASE,data={'name':'Ignored'}).status_code==503
    (private/'config.php.pending').rename(config)
    subprocess.run(['php','-r',"$f=$argv[1];$c=require $f;$v=json_decode(stream_get_contents(STDIN),true);$c['url']=$v['url'];$c['db']=$v['db'];file_put_contents($f,'<?php return '.var_export($c,true).';');",str(config)],input=json.dumps({'url':BASE,'db':{**cfg['db'],'name':'filolux_manual_test'}}),text=True,check=True)
    # With configuration but no SQL import the app must fail, not create tables.
    assert s.get(BASE).status_code==500
    sql=private/'database/filolux.sql';sql.write_text(sql.read_text().replace('admin@filolux.invalid','owner@example.test'))
    php="define('ROOT',$argv[1]);$GLOBALS['config']=require ROOT.'/config.php';require ROOT.'/app/core.php';db()->exec(file_get_contents(ROOT.'/database/filolux.sql'));"
    subprocess.run(['php','-r',php,str(private)],check=True)
    r=s.get(BASE);assert r.status_code==200 and 'Estamos preparando algo único.' in r.text
    t=re.search('name="_csrf" value="([^"]+)"',s.get(BASE+'/login').text).group(1)
    r=s.post(BASE+'/login',data={'_csrf':t,'email':'admin','password':'AdminTest2026!'},allow_redirects=False);assert r.status_code==303 and r.headers['Location'].endswith('/admin')
    assert 'Tu taller' in s.get(BASE+'/admin').text
    for path in ['/install','/config.php','/.env','/filolux-private/config.php']:assert s.get(BASE+path).status_code==404
    subprocess.run(['php','-r',"define('ROOT',$argv[1]);$GLOBALS['config']=require ROOT.'/config.php';require ROOT.'/app/core.php'; if((int)query(\"SELECT COUNT(*) FROM users WHERE role='admin'\")->fetchColumn()!==1 || (int)query('SELECT COUNT(*) FROM products')->fetchColumn()!==0)exit(1); putSetting('shipping_price','12345');db()->exec(file_get_contents(ROOT.'/database/filolux.sql'));if(setting('shipping_price')!=='12345' || (int)query(\"SELECT COUNT(*) FROM users WHERE role='admin'\")->fetchColumn()!==1)exit(1);",str(private)],check=True)
    print('PASS: carpetas sin ZIP ni instalador, config editable con clave, SQL importado manualmente, inicio vacío, login admin, archivos privados protegidos y semillas sin sobreescritura.')
finally:server.terminate();server.wait();log.close()
