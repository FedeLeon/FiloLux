"""Apache .htaccess verification on a disposable :8089 instance, no system config changes."""
import pathlib,subprocess,time,requests
ROOT=pathlib.Path(__file__).resolve().parents[1];RUNTIME=ROOT/'tests/.runtime'; BASE='http://127.0.0.1:8089'
config=RUNTIME/'apache.conf'
config.write_text(f'''ServerRoot "/etc/apache2"
DefaultRuntimeDir "{RUNTIME}"
PidFile "{RUNTIME}/apache.pid"
Listen 127.0.0.1:8089
ServerName localhost
LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so
LoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so
LoadModule mime_module /usr/lib/apache2/modules/mod_mime.so
LoadModule dir_module /usr/lib/apache2/modules/mod_dir.so
LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so
LoadModule php_module /usr/lib/apache2/modules/libphp8.2.so
TypesConfig /etc/mime.types
User fede
Group fede
ErrorLog "{RUNTIME}/apache-error.log"
DocumentRoot "{ROOT}/public"
SetEnv FILOLUX_CONFIG_PATH "{RUNTIME}/config.php"
<Directory "{ROOT}">
AllowOverride All
Require all denied
</Directory>
<Directory "{ROOT}/public">
AllowOverride All
Require all granted
</Directory>
<FilesMatch "\\.php$">
SetHandler application/x-httpd-php
</FilesMatch>
'''.replace('SetEnv FILOLUX_CONFIG_PATH', '# Env configured in subprocess; SetEnv FILOLUX_CONFIG_PATH'))
# Environment reaches mod_php getenv without changing system services.
import os
env={**os.environ,'FILOLUX_CONFIG_PATH':str(RUNTIME/'config.php')}
server=subprocess.Popen(['/usr/sbin/apache2','-f',str(config),'-DFOREGROUND'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,env=env,start_new_session=True)
probe=ROOT/'public/uploads/security-probe.php';probe.write_text('<?php echo "EXECUTED";')
try:
 for i in range(30):
  try:r=requests.get(BASE+'/catalogo');break
  except requests.ConnectionError:time.sleep(.1)
 assert r.status_code==200, (r.status_code, r.text)
 assert requests.get(BASE+'/uploads/security-probe.php').status_code==403
 assert requests.get(BASE+'/uploads/.htaccess').status_code==403
 assert requests.get(BASE+'/uploads/').status_code==403
 assert requests.get(BASE+'/config.php').status_code==404
 print('PASS: Apache real, rewrite, bloqueo de ejecución en uploads, archivos ocultos y listado de directorios.')
finally:
 probe.unlink(missing_ok=True);server.terminate();server.wait()
