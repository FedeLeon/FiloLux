"""Profile isolation, admin users, structured shipping and address snapshots on filolux_test."""
from pathlib import Path
from urllib.parse import urlparse
import requests,re,subprocess,time
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087'
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def token(s,path):
    r=s.get(BASE+path);assert r.status_code==200,(r.status_code,r.text)
    return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
guest=requests.Session();a=requests.Session();b=requests.Session();admin=requests.Session()
assert guest.get(BASE+'/cuenta/perfil',allow_redirects=False).status_code==303
stamp=str(time.time_ns())
for s,suffix in [(a,'a'),(b,'b')]:
    t=token(s,'/registro');r=s.post(BASE+'/registro',data={'_csrf':t,'name':'Cliente '+suffix,'email':suffix+stamp+'@example.test','password':'CustomerTest2026!'},allow_redirects=False);assert r.status_code==303
csrf=token(a,'/cuenta/perfil');data={'_csrf':csrf,'name':'Cliente de perfil','phone':'+54 9 11 1234-5678','street':'Calle Prueba','street_number':'123','apartment':'2 B','city':'Córdoba','province':'Córdoba','postal_code':'X5000ABC'}
assert a.post(BASE+'/cuenta/perfil',data={**data,'_csrf':'bad'}).status_code==419
assert 'teléfono válido' in a.post(BASE+'/cuenta/perfil',data={**data,'phone':'abc'}).text
assert 'Revisá el campo postal code' in a.post(BASE+'/cuenta/perfil',data={**data,'postal_code':'x'*13}).text
assert a.post(BASE+'/cuenta/perfil',data={**data,'user_id':1,'role':'admin','email':'attacker@example.test'},allow_redirects=False).status_code==303
html=a.get(BASE+'/cuenta/perfil').text
assert 'value="Calle Prueba"' in html and 'value="X5000ABC"' in html and 'attacker@example.test' not in html
assert 'Calle Prueba' not in b.get(BASE+'/cuenta/perfil').text
assert a.get(BASE+'/admin/usuarios').status_code==403
csrf_admin=token(admin,'/login');assert admin.post(BASE+'/login',data={'_csrf':csrf_admin,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
html=admin.get(BASE+'/admin/usuarios?q=Cliente+de+perfil').text
assert 'Calle Prueba' in html and 'X5000ABC' in html and 'CustomerTest2026!' not in html and 'password' not in html
assert 'Calle Prueba' not in guest.get(BASE+'/').text
assert a.post(BASE+'/carrito/agregar',data={'_csrf':csrf,'product_id':1},allow_redirects=False).status_code==303
subprocess.run(['php','tests/fixtures.php','enable-payments'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
try:
    html=a.get(BASE+'/checkout').text
    assert 'value="Calle Prueba"' in html and 'value="X5000ABC"' in html and 'value="+54 9 11 1234-5678"' in html
    key=re.search(r'name="checkout_key" value="([^"]+)"',html).group(1)
    order={**data,'checkout_key':key,'delivery':'shipping'}
    for field in ['street','street_number','city','province','postal_code']:
        r=a.post(BASE+'/checkout',data={**order,field:''},allow_redirects=False);assert r.status_code==422,(field,r.status_code)
    r=a.post(BASE+'/checkout',data=order,allow_redirects=False);assert r.status_code==303,r.text
    path=urlparse(r.headers['Location']).path
    assert 'Calle Prueba 123' in a.get(BASE+path).text and 'Código postal: X5000ABC' in a.get(BASE+path).text
    assert b.get(BASE+path).status_code==404
    assert a.post(BASE+'/cuenta/perfil',data={**data,'street':'Otra dirección'},allow_redirects=False).status_code==303
    assert 'Calle Prueba 123' in a.get(BASE+path).text
    # Pickup requires contact information but no shipping address.
    assert a.post(BASE+'/carrito/agregar',data={'_csrf':csrf,'product_id':1},allow_redirects=False).status_code==303
    html=a.get(BASE+'/checkout').text;key=re.search(r'name="checkout_key" value="([^"]+)"',html).group(1)
    assert a.post(BASE+'/checkout',data={'_csrf':csrf,'checkout_key':key,'name':data['name'],'phone':data['phone'],'delivery':'pickup'},allow_redirects=False).status_code==303
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-user-profiles.sql'));")
    assert 'Otra dirección' in a.get(BASE+'/cuenta/perfil').text
    assert a.post(BASE+'/logout',data={'_csrf':csrf},allow_redirects=False).status_code==303
    assert a.get(BASE+'/cuenta/perfil',allow_redirects=False).status_code==303
finally:
    subprocess.run(['php','tests/fixtures.php','preview'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
print('PASS: perfil propio/CSRF/validación, permisos, usuarios del admin, autocompletado, envío obligatorio, retiro, dirección inmutable, cierre de sesión y migración repetible.')
