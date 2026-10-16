"""Live feed roles, transactional events, duplicate delivery and unread orders on filolux_test."""
from pathlib import Path
from urllib.parse import urlparse
import requests,re,subprocess,time,json
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087'
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';require ROOT.'/app/Payments.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def token(s,path):
    r=s.get(BASE+path);assert r.status_code==200
    return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
def feed(s,cursor=0):
    r=s.get(BASE+'/notificaciones',params={'desde':cursor});assert r.status_code==200,r.text
    assert 'no-store' in r.headers['Cache-Control'];return r.json()
guest=requests.Session();admin=requests.Session();buyer=requests.Session();other=requests.Session()
assert guest.get(BASE+'/notificaciones').status_code==401
stamp=str(time.time_ns())
for s,suffix in [(buyer,'buyer'),(other,'other')]:
    t=token(s,'/registro');assert s.post(BASE+'/registro',data={'_csrf':t,'name':'Cliente '+suffix,'email':suffix+stamp+'@example.test','password':'CustomerTest2026!'},allow_redirects=False).status_code==303
csrf=token(buyer,'/cuenta');ta=token(admin,'/login');assert admin.post(BASE+'/login',data={'_csrf':ta,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
initial=feed(admin);cursor=int(sql('echo query("SELECT COALESCE(MAX(id),0) FROM live_notifications")->fetchColumn();'))
assert buyer.get(BASE+'/notificaciones?desde[]=1').status_code==422
assert buyer.post(BASE+'/notificaciones').status_code in [419,404]
assert buyer.post(BASE+'/carrito/agregar',data={'_csrf':csrf,'product_id':1},allow_redirects=False).status_code==303
subprocess.run(['php','tests/fixtures.php','enable-payments'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
try:
    html=buyer.get(BASE+'/checkout').text;key=re.search(r'name="checkout_key" value="([^"]+)"',html).group(1)
    data={'_csrf':csrf,'checkout_key':key,'name':'Compra <script>alert(1)</script>','phone':'1112345678','delivery':'pickup'}
    r=buyer.post(BASE+'/checkout',data=data,allow_redirects=False);assert r.status_code==303,r.text
    path=urlparse(r.headers['Location']).path;oid=int(path.rsplit('/',1)[1])
    result=feed(admin,cursor);new=[n for n in result['events'] if n['type']=='order_created'];assert len(new)==1 and str(oid) in new[0]['href']
    assert result['orders']==initial['orders']+1
    assert not feed(buyer,cursor)['events'] and not feed(other,cursor)['events']
    assert buyer.get(BASE+'/admin/pedidos/'+str(oid)).status_code==403
    assert buyer.get(BASE+path).status_code==200
    assert feed(admin,cursor)['orders']==initial['orders']+1
    # Re-importing a migration must not mark new orders as reviewed.
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-live-notifications.sql'));")
    assert feed(admin,cursor)['orders']==initial['orders']+1
    listing=admin.get(BASE+'/admin/pedidos').text
    assert 'data-order-count' in listing
    assert re.search(r'admin-order-number[^>]*>#'+str(oid)+r'<span class="admin-order-unread"',listing)
    assert 'admin-order-unread' in admin.get(BASE+'/admin').text
    assert 'admin-order-unread' not in buyer.get(BASE+'/cuenta').text
    assert feed(admin,cursor)['orders']==initial['orders']+1
    assert admin.get(BASE+'/admin/pedidos/'+str(oid)).status_code==200
    assert feed(admin,cursor)['orders']==initial['orders']
    listing=admin.get(BASE+'/admin/pedidos').text
    assert re.search(r'admin-order-number[^>]*>#'+str(oid)+r'</span>',listing)
    order=json.loads(sql('echo json_encode(one("SELECT * FROM orders WHERE id=?",['+str(oid)+']));'))
    payment={'id':'990880001','external_reference':str(oid),'transaction_amount':order['total_cents']/100,'currency_id':'ARS','collector_id':555,'live_mode':False,'status':'approved'}
    def reconcile(p):return sql('echo (int)Payments::reconcile(json_decode('+json.dumps(json.dumps(p))+',true),"test",555);')
    assert reconcile({**payment,'status':'pending'})=='0'
    pending_client=feed(buyer,cursor);assert len(pending_client['events'])==1 and 'Pendiente' in pending_client['events'][0]['body']
    pending_admin=feed(admin,result['cursor']);assert len(pending_admin['events'])==1
    pending_count=sql('echo query("SELECT COUNT(*) FROM live_notifications")->fetchColumn();')
    assert reconcile({**payment,'status':'pending'})=='0'
    assert sql('echo query("SELECT COUNT(*) FROM live_notifications")->fetchColumn();')==pending_count
    cursor=pending_client['cursor'];result=pending_admin
    count=sql('echo query("SELECT COUNT(*) FROM live_notifications")->fetchColumn();')
    assert reconcile(payment)=='1'
    a=feed(admin,result['cursor']);c=feed(buyer,cursor)
    assert len(a['events'])==1 and a['events'][0]['type']=='payment_update'
    assert len(c['events'])==1 and c['events'][0]['href']==path and 'Aprobado' in c['events'][0]['body']
    assert not feed(other,cursor)['events']
    assert reconcile(payment)=='0'
    assert sql('echo query("SELECT COUNT(*) FROM live_notifications")->fetchColumn();')==str(int(count)+2)
    assert not feed(admin,a['cursor'])['events']
    assert guest.post(BASE+'/webhook/mercadopago?data.id=990880001',headers={'X-Signature':'bad'}).status_code==401
    assert sql('echo query("SELECT COUNT(*) FROM live_notifications")->fetchColumn();')==str(int(count)+2)
    # Notification inserts participate in rollback: no toast for a failed transaction.
    sql("db()->beginTransaction();notifyLive('rollback-test','admin',null,'order_created','No publicar','Prueba');db()->rollBack();")
    assert sql('echo query("SELECT COUNT(*) FROM live_notifications")->fetchColumn();')==str(int(count)+2)
    # First migration on an older database does not flag historical orders as new.
    sql('query("ALTER TABLE orders DROP INDEX unread_orders, DROP COLUMN admin_reviewed_at");')
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-live-notifications.sql'));")
    assert feed(admin)['orders']==0
    sql('query("DROP TABLE live_notifications");')
    try:
        assert buyer.get(BASE+'/').status_code==200 and admin.get(BASE+'/admin').status_code==200
        assert feed(admin)['available'] is False
    finally:sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-live-notifications.sql'));")
    print('PASS: privacidad por rol/cliente, eventos de compra/pago, burbuja y lectura individual, duplicados, rollback, firma inválida, cursor, migración repetible y compatibilidad sin tabla.')
finally:subprocess.run(['php','tests/fixtures.php','preview'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
