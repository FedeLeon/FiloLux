"""Integration checks using only the isolated filolux_test server on :8087."""
import json
import re
import subprocess
import time
from pathlib import Path
import requests
ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8087'
HEADERS={'Accept':'application/json'}
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def data(code): return json.loads(sql('echo json_encode('+code+');'))
def count(): return int(sql('echo query("SELECT COUNT(*) FROM visit_events")->fetchColumn();'))
def csrf(session,path='/login'):
    response=session.get(BASE+path)
    assert response.status_code==200,response.text
    return re.search(r'name="_csrf" value="([^"]+)"',response.text).group(1)
def login_admin(session):
    token=csrf(session)
    response=session.post(BASE+'/login',data={'_csrf':token,'email':'admin','password':'AdminTest2026!'},allow_redirects=False)
    assert response.status_code==303,response.text
    return token
sql('query("DELETE FROM visit_events");query("DELETE FROM site_visits");query("DELETE FROM visit_locations");query("DELETE FROM rate_limits");')
mobile=requests.Session()
mobile.headers['User-Agent']='Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 Chrome/130.0.0.0 Mobile Safari/537.36'
mobile.headers['X-Forwarded-For']='8.8.8.8'
mobile.headers['CF-Connecting-IP']='1.1.1.1'
assert mobile.get(BASE+'/').status_code==200
assert mobile.get(BASE+'/catalogo?q=private-search&secret=not-saved').status_code==200
assert mobile.get(BASE+'/producto/1').status_code==200
visit=data('one("SELECT * FROM site_visits ORDER BY id LIMIT 1")')
assert visit['ip']=='127.0.0.1' and visit['device']=='Celular' and visit['browser']=='Chrome' and visit['os']=='Android'
visit_id=visit['id']
assert data('query("SELECT path FROM visit_events ORDER BY id")->fetchAll(PDO::FETCH_COLUMN)')==['/','/catalogo','/producto/1']
assert data('one("SELECT * FROM visit_events ORDER BY id DESC LIMIT 1")')['product_name']=='Lámpara geométrica'
assert data('one("SELECT * FROM visit_locations LIMIT 1")')['status']=='private'
token=csrf(mobile,'/producto/1')
before=count()
assert mobile.post(BASE+'/carrito/agregar',data={'product_id':1,'quantity':2,'_csrf':'invalid'},headers=HEADERS).status_code==419
assert mobile.post(BASE+'/carrito/agregar',data={'product_id':999999,'quantity':2,'_csrf':token},headers=HEADERS).status_code==404
assert count()==before
assert mobile.post(BASE+'/carrito/agregar',data={'product_id':1,'quantity':2,'_csrf':token},headers=HEADERS).status_code==200
assert mobile.post(BASE+'/carrito/agregar',data={'product_id':1,'quantity':99,'_csrf':token},headers=HEADERS).status_code==200
last=data('one("SELECT * FROM visit_events ORDER BY id DESC LIMIT 1")')
assert last['type']=='cart_add' and last['quantity']==97
before=count()
assert mobile.post(BASE+'/carrito/agregar',data={'product_id':1,'quantity':1,'_csrf':token},headers=HEADERS).status_code==200
assert count()==before
assert mobile.post(BASE+'/carrito',data={'quantities[1]':3,'_csrf':token},allow_redirects=False).status_code==303
assert data('one("SELECT * FROM visit_events ORDER BY id DESC LIMIT 1")')['quantity']==96
assert mobile.post(BASE+'/carrito/quitar',data={'product_id':1,'_csrf':token},headers=HEADERS).status_code==200
last=data('one("SELECT * FROM visit_events ORDER BY id DESC LIMIT 1")')
assert last['type']=='cart_remove' and last['quantity']==3
before=count()
assert mobile.get(BASE+'/producto/999999').status_code==404
assert mobile.get(BASE+'/login').status_code==200
assert mobile.get(BASE+'/privacidad').status_code==200
assert mobile.get(BASE+'/carrito/resumen',headers=HEADERS).status_code==200
assert count()==before
# Another session sharing the same IP is a distinct visit, not a distinct person claim.
other=requests.Session();assert other.get(BASE+'/contacto').status_code==200
assert int(sql('echo query("SELECT COUNT(*) FROM site_visits")->fetchColumn();'))==2
admin=requests.Session();admin_token=login_admin(admin)
before=count()
assert admin.get(BASE+'/').status_code==200
assert admin.get(BASE+'/producto/1').status_code==200
assert admin.post(BASE+'/carrito/agregar',data={'product_id':1,'_csrf':admin_token},headers=HEADERS).status_code==200
assert count()==before
listing=admin.get(BASE+'/admin/visitas');assert listing.status_code==200 and 'Visita #'+str(visit_id) in listing.text
assert '127.0.0.1' in listing.text and 'Celular' in listing.text
assert 'Agregó al carrito' in listing.text and 'Productos explorados' in listing.text
assert 'Visita #'+str(visit_id) in admin.get(BASE+'/admin/visitas?carrito=1').text
assert 'Visita #'+str(visit_id) not in admin.get(BASE+'/admin/visitas?dispositivo=Escritorio').text
assert admin.get(BASE+'/admin/visitas?desde=2026-02-31').status_code==422
assert admin.get(BASE+'/admin/visitas?ip=bad').status_code==422
assert admin.get(BASE+'/admin/visitas?desde=2026-10-16&hasta=2026-10-01').status_code==422
assert 'No hay visitas' in admin.get(BASE+'/admin/visitas?desde=2000-01-01&hasta=2000-01-01').text
assert admin.get(BASE+'/admin/visitas/9999999').status_code==404
detail=admin.get(BASE+'/admin/visitas/'+str(visit_id));assert detail.status_code==200
assert all(label in detail.text for label in ['Vio un producto','Agregó al carrito','Quitó del carrito','Lámpara geométrica','Android'])
assert admin.post(BASE+'/admin/visitas/ubicaciones',headers=HEADERS).status_code==419
assert admin.post(BASE+'/admin/visitas/ubicaciones',data={'_csrf':admin_token},headers=HEADERS).json()=={'locations':[]}
assert other.get(BASE+'/admin/visitas',allow_redirects=False).status_code==303
assert other.get(BASE+'/admin/visitas/'+str(visit_id),allow_redirects=False).status_code==303
customer=requests.Session();customer_token=csrf(customer,'/registro')
assert customer.post(BASE+'/registro',data={'_csrf':customer_token,'name':'Visitas test','email':str(time.time_ns())+'@example.test','password':'CustomerTest2026!'},allow_redirects=False).status_code==303
assert customer.get(BASE+'/admin/visitas').status_code==403
assert customer.get(BASE+'/admin/visitas/'+str(visit_id)).status_code==403
assert customer.post(BASE+'/admin/visitas/ubicaciones',data={'_csrf':customer_token},headers=HEADERS).status_code==403
# Snapshots are escaped, including untrusted browser headers.
sql('query("UPDATE site_visits SET user_agent=? WHERE id=?",["<script>alert(1)</script>",'+str(visit_id)+']);')
html=admin.get(BASE+'/admin/visitas/'+str(visit_id)).text
assert '&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html
# Repeating migration preserves the history; absence never breaks storefront/cart.
before=count()
sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-15-site-visits.sql'));")
assert count()==before
sql('query("RENAME TABLE visit_events TO visit_events_backup");')
try:
    assert other.get(BASE+'/').status_code==200
    html=admin.get(BASE+'/admin/visitas').text;assert 'importá la migración' in html
    missing_token=csrf(other,'/producto/1')
    assert other.post(BASE+'/carrito/agregar',data={'_csrf':missing_token,'product_id':1},headers=HEADERS).status_code==200
finally: sql('query("RENAME TABLE visit_events_backup TO visit_events");')
# Retention cascades only the old visit and its events, leaving shop data untouched.
sql('query("UPDATE site_visits SET last_seen_at=DATE_SUB(NOW(),INTERVAL 91 DAY) WHERE id=?",['+str(visit_id)+']);')
assert admin.get(BASE+'/admin/visitas').status_code==200
assert not data('one("SELECT id FROM site_visits WHERE id=?",['+str(visit_id)+'])')
assert int(sql('echo query("SELECT COUNT(*) FROM visit_events WHERE visit_id=?",['+str(visit_id)+'])->fetchColumn();'))==0
sql('query("DELETE FROM rate_limits");')
# Session continuity and expiration are independent from the PHP session ID.
result=data("(function(){require ROOT.'/app/visits.php';$_SESSION=[];$_SERVER['REMOTE_ADDR']='127.0.0.2';$_SERVER['HTTP_USER_AGENT']='Visit session test';recordVisitEvent('page_view','/');$first=$_SESSION['analytics_visit']['token'];recordVisitEvent('page_view','/catalogo');$same=$_SESSION['analytics_visit']['token'];$_SESSION['analytics_visit']['last']=time()-1801;recordVisitEvent('page_view','/contacto');$expired=$_SESSION['analytics_visit']['token'];query(\"DELETE FROM site_visits WHERE user_agent='Visit session test'\");return [$first,$same,$expired];})()")
assert result[0]==result[1] and result[0]!=result[2]
print('PASS: visitas/sesiones, IP sin cabeceras falsificadas, dispositivo, productos, carrito real/limites, filtros, detalle, CSRF/roles, escape, migración repetible/faltante y limpieza con cascada.')
