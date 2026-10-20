"""Bulk price changes against filolux_test only: roles, exact rounding, atomicity, retries."""
import json,os,re,subprocess,time
from decimal import Decimal,ROUND_CEILING
from pathlib import Path
import requests
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('FILOLUX_TEST_BASE','http://127.0.0.1:8087')
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';require ROOT.'/app/product-prices.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def data(code):return json.loads(sql('echo json_encode('+code+');'))
def csrf(session,path='/login'):
    r=session.get(BASE+path);assert r.status_code==200
    return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
def form(session):
    r=session.get(BASE+'/admin/productos?categoria=999&q=sin-resultados');assert r.status_code==200
    return {'_csrf':re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1),'adjustment_token':re.search(r'name="adjustment_token" value="([^"]+)"',r.text).group(1)}
def prices():return data('query("SELECT id,price_cents FROM products ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR)')
endpoint=BASE+'/admin/productos/ajustar-precios'
admin=requests.Session();t=csrf(admin)
assert admin.post(BASE+'/login',data={'_csrf':t,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
sql("query('UPDATE products SET price_cents=123000 WHERE id=1');query('UPDATE products SET price_cents=125000 WHERE id=2');query('UPDATE products SET price_cents=50000 WHERE id=4');query('UPDATE products SET price_cents=100000,active=0 WHERE id=5');query('UPDATE products SET deleted_at=CURRENT_TIMESTAMP WHERE id=6');")
sql("query(\"INSERT INTO orders (user_id,checkout_key,customer_name,email,phone,delivery,address,notes,subtotal_cents,shipping_cents,total_cents,payment_mode) VALUES (1,?, 'Cliente de prueba','price@example.test','1112345678','pickup','','',123000,0,123000,'test')\",[hash('sha256','price-test')]);$id=(int)db()->lastInsertId();query(\"INSERT INTO order_items (order_id,product_id,name,quantity,price_cents,lead_time) VALUES (?,1,'Snapshot de precio',1,123000,'7 días')\",[$id]);")
snapshot=data('query("SELECT * FROM order_items")->fetchAll()');orders=data('query("SELECT * FROM orders")->fetchAll()')
original=prices();f=form(admin)
assert admin.get(endpoint).status_code==404
assert admin.post(endpoint,data={**f,'_csrf':'wrong','direction':'increase','percentage':'10'}).status_code==419
assert prices()==original
# Invalid values never mutate any row.
for value,direction in [('0','increase'),('-10','increase'),('1e2','increase'),('10.001','increase'),('100','decrease'),('1000.01','increase'),('5','other'),('1.2.3','increase')]:
    assert admin.post(endpoint,data={**f,'direction':direction,'percentage':value}).status_code==422
    assert prices()==original
assert admin.post(endpoint,data={**f,'direction':'increase','percentage[]':'10'}).status_code==422
assert admin.post(endpoint,data={**f,'adjustment_token':'fake','direction':'increase','percentage':'10'}).status_code==422
customer=requests.Session();tc=csrf(customer,'/registro')
assert customer.post(BASE+'/registro',data={'_csrf':tc,'name':'Cliente','email':str(time.time_ns())+'@example.test','password':'CustomerTest2026!'},allow_redirects=False).status_code==303
assert customer.post(endpoint,data={**f,'_csrf':tc,'direction':'increase','percentage':'10'}).status_code==403
assert prices()==original
r=admin.post(endpoint,data={**f,'direction':'increase','percentage':'10'},allow_redirects=False);assert r.status_code==303
raised=prices();assert raised['1']==140000 and raised['2']==140000 and raised['4']==55000 and raised['5']==110000
assert raised['3']==original['3'] and raised['6']==original['6']
assert admin.post(endpoint,data={**f,'direction':'increase','percentage':'10'}).status_code==422
assert prices()==raised
f=form(admin);assert admin.post(endpoint,data={**f,'direction':'decrease','percentage':'12,5'},allow_redirects=False).status_code==303
lowered=prices();assert lowered['1']==125000 and lowered['2']==125000 and lowered['4']==50000 and lowered['5']==100000
assert data('query("SELECT * FROM order_items")->fetchAll()')==snapshot
assert data('query("SELECT * FROM orders")->fetchAll()')==orders
# Validate a late overflow before writing earlier candidates; the operation is all-or-nothing.
sql('query("UPDATE products SET price_cents=9999999999 WHERE id=5");');before=prices();f=form(admin)
assert admin.post(endpoint,data={**f,'direction':'increase','percentage':'1000'}).status_code==422
assert prices()==before
# Check centavo boundaries, exact multiples and decimal percentages without float rounding.
cases=[]
for price in [1,4999,5000,5001,123456,999999999]:
    for bp in [1,1000,1250,9999]:
        for direction in ['increase','decrease']:
            cases.append([price,bp,direction])
actual=data('(function(){ $cases=json_decode('+json.dumps(json.dumps(cases))+',true);return array_map(fn($v)=>adjustedProductPrice(...$v),$cases);})()')
for (price,bp,direction),got in zip(cases,actual):
    factor=10000+(bp if direction=='increase' else -bp)
    expected=int((Decimal(price)*factor/Decimal(50000000)).to_integral_value(rounding=ROUND_CEILING))*5000
    assert got==expected,(price,bp,direction,got,expected)
sql('query("UPDATE products SET price_cents=100000,active=1 WHERE id=5");query("UPDATE products SET deleted_at=NULL WHERE id=6");')
print('PASS: aumentos/bajas para todo el catálogo, ocultos incluidos, eliminados/a consultar excluidos, redondeo exacto a $50, decimales, CSRF/rol, reenvío sin duplicar, errores atómicos y pedidos conservados.')
