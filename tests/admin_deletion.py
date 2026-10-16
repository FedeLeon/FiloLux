"""Admin-only removal preserves order snapshots, payments and customer history."""
from pathlib import Path
from urllib.parse import urlparse
import subprocess,json,re,time,requests
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087'
def sql(code):
 prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';require ROOT.'/app/Payments.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
 return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def token(s,path):
 r=s.get(BASE+path);assert r.status_code==200,(path,r.status_code,r.text)
 return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
a=requests.Session();c=requests.Session();guest=requests.Session()
ta=token(a,'/login');assert a.post(BASE+'/login',data={'_csrf':ta,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
tc=token(c,'/registro');assert c.post(BASE+'/registro',data={'_csrf':tc,'name':'Cliente eliminar','email':str(time.time_ns())+'@example.test','password':'CustomerTest2026!'},allow_redirects=False).status_code==303
assert c.post(BASE+'/carrito/agregar',data={'_csrf':tc,'product_id':1},allow_redirects=False).status_code==303
subprocess.run(['php','tests/fixtures.php','enable-payments'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
try:
 html=c.get(BASE+'/checkout').text;key=re.search(r'name="checkout_key" value="([^"]+)"',html).group(1)
 r=c.post(BASE+'/checkout',data={'_csrf':tc,'checkout_key':key,'name':'Cliente eliminar','phone':'1112345678','delivery':'pickup'},allow_redirects=False);assert r.status_code==303
 path=urlparse(r.headers['Location']).path;oid=int(path.rsplit('/',1)[1]);order=json.loads(sql('echo json_encode(one("SELECT * FROM orders WHERE id=?",['+str(oid)+']));'))
 payment={'id':'991220001','external_reference':str(oid),'transaction_amount':order['total_cents']/100,'currency_id':'ARS','collector_id':555,'live_mode':False,'status':'approved'}
 sql('Payments::reconcile(json_decode('+json.dumps(json.dumps(payment))+',true),"test",555);')
 assert c.post(BASE+'/carrito/agregar',data={'_csrf':tc,'product_id':1},allow_redirects=False).status_code==303
 for endpoint in ['/admin/productos/1/eliminar','/admin/pedidos/'+str(oid)+'/eliminar']:
  assert guest.post(BASE+endpoint,data={'_csrf':token(guest,'/login')},allow_redirects=False).status_code==303
  assert c.post(BASE+endpoint,data={'_csrf':tc}).status_code==403
  assert a.post(BASE+endpoint,data={}).status_code==419
  assert a.get(BASE+endpoint).status_code==404
 assert 'aria-label="Eliminar producto"' in a.get(BASE+'/admin/productos/1').text
 assert 'aria-label="Eliminar producto"' not in a.get(BASE+'/admin/productos/nuevo').text
 assert a.post(BASE+'/admin/productos/1/eliminar',data={'_csrf':ta},allow_redirects=False).status_code==303
 assert a.post(BASE+'/admin/productos/1/eliminar',data={'_csrf':ta},allow_redirects=False).status_code==303
 assert c.get(BASE+'/producto/1').status_code==404
 assert a.get(BASE+'/admin/productos/1').status_code==404
 assert '/admin/productos/1"' not in a.get(BASE+'/admin/productos').text
 assert 'data-cart-count>0</span>' in c.get(BASE+'/catalogo').text
 assert c.get(BASE+'/carrito/resumen').json()['count']==0
 assert c.post(BASE+'/carrito/agregar',data={'_csrf':tc,'product_id':1}).status_code==404
 html=c.get(BASE+path).text;assert 'Lámpara geométrica' in html and 'Pago: Aprobado' in html
 assert 'admin-delete-button' not in html
 assert a.post(BASE+'/admin/pedidos/'+str(oid)+'/eliminar',data={'_csrf':ta},allow_redirects=False).status_code==303
 assert a.get(BASE+'/admin/pedidos/'+str(oid)).status_code==404
 for admin_path in ['/admin','/admin/pedidos','/admin/pedidos?status=pendiente']:
  assert '/admin/pedidos/'+str(oid)+'"' not in a.get(BASE+admin_path).text
 assert c.get(BASE+path).status_code==200 and path in c.get(BASE+'/cuenta').text
 assert sql('echo query("SELECT COUNT(*) FROM payments WHERE order_id=?",['+str(oid)+'])->fetchColumn();')=='1'
 assert sql('echo query("SELECT COUNT(*) FROM order_items WHERE order_id=?",['+str(oid)+'])->fetchColumn();')=='1'
 feed=a.get(BASE+'/notificaciones?desde=0').json();assert feed['orders']==0 and all(n['href']!='/admin/pedidos/'+str(oid) for n in feed['events'])
 # Refund/other payment updates still reconcile after removal from admin.
 payment['status']='refunded'
 sql('Payments::reconcile(json_decode('+json.dumps(json.dumps(payment))+',true),"test",555);')
 assert 'Pago: Reintegrado' in c.get(BASE+path).text
 assert all(n['href']!='/admin/pedidos/'+str(oid) for n in a.get(BASE+'/notificaciones?desde=0').json()['events'])
 sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-admin-deletion.sql'));")
 assert a.get(BASE+'/admin/pedidos/'+str(oid)).status_code==404
 # Removing an unpaid order blocks new charges; an already initiated payment can still settle.
 new_id=int(sql("query(\"INSERT INTO orders (user_id,checkout_key,customer_name,email,phone,delivery,address,notes,subtotal_cents,shipping_cents,total_cents,payment_mode) SELECT user_id,?,customer_name,email,phone,delivery,address,notes,subtotal_cents,shipping_cents,total_cents,payment_mode FROM orders WHERE id=?\",[bin2hex(random_bytes(32)),"+str(oid)+"]);echo db()->lastInsertId();"))
 assert a.post(BASE+'/admin/pedidos/'+str(new_id)+'/eliminar',data={'_csrf':ta},allow_redirects=False).status_code==303
 assert c.post(BASE+'/pedidos/'+str(new_id)+'/pagar',data={'_csrf':tc},allow_redirects=False).status_code==422
 assert 'Pagar la compra' not in c.get(BASE+'/pedidos/'+str(new_id)).text
 payment.update(id='991220002',external_reference=str(new_id),status='approved')
 sql('Payments::reconcile(json_decode('+json.dumps(json.dumps(payment))+',true),"test",555);')
 assert a.get(BASE+'/notificaciones?desde=0').json()['orders']==1
 assert a.get(BASE+'/admin/pedidos/'+str(new_id)).status_code==200
 print('PASS: rol/CSRF/POST, botones, retiro de catálogo y carrito, pedidos/admin/contadores, historial del cliente, pagos/reintegros, duplicados y migración repetible.')
finally:
 sql('query("UPDATE products SET active=1,deleted_at=NULL WHERE id=1");')
 subprocess.run(['php','tests/fixtures.php','preview'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
