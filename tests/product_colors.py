"""Color availability, variant carts and immutable order snapshots on filolux_test."""
from pathlib import Path
from urllib.parse import urlparse
import json,re,subprocess,requests
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087'
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def token(s,path):
    r=s.get(BASE+path);assert r.status_code==200,r.text
    return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1)
a=requests.Session();s=requests.Session();t=token(a,'/login')
assert a.post(BASE+'/login',data={'_csrf':t,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
product=json.loads(sql('echo json_encode(one("SELECT * FROM products WHERE id=1"));'))
data={'_csrf':t,'name':product['name'],'description':product['description'],'dimensions':product['dimensions'],'lead_time':product['lead_time'],'price':str(product['price_cents']/100),'mode':'buy','active':'1','colors_present':'1','colors[]':['negro','dorado'],'category_id':product['category_id'],'featured':'1'}
try:
    # Simulate an existing installation: pages remain usable before the migration.
    sql('query("ALTER TABLE products DROP COLUMN available_colors");query("ALTER TABLE order_items DROP COLUMN color");')
    assert s.get(BASE+'/producto/1').status_code==200
    assert 'importá la migración' in a.get(BASE+'/admin/productos/1').text
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-product-colors.sql'));")
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-product-colors.sql'));")
    assert 'name="color"' not in s.get(BASE+'/producto/1').text
    assert a.post(BASE+'/admin/productos/1',data=data,allow_redirects=False).status_code==303
    assert json.loads(sql('echo one("SELECT available_colors FROM products WHERE id=1")["available_colors"];'))==['negro','dorado']
    html=s.get(BASE+'/producto/1').text
    assert 'value="negro"' in html and 'value="dorado"' in html and 'value="blanco"' not in html
    bad=a.post(BASE+'/admin/productos/1',data={**data,'colors[]':['inventado']});assert 'Color inválido' in bad.text
    t2=token(s,'/producto/1')
    def add(color=None,quantity=1):
        d={'_csrf':t2,'product_id':1,'quantity':quantity,'price':1}
        if color is not None:d['color']=color
        return s.post(BASE+'/carrito/agregar',data=d,headers={'Accept':'application/json'})
    assert add().status_code==422 and add('blanco').status_code==422
    assert add('negro',2).status_code==200
    j=add('dorado').json();assert j['count']==3 and j['subtotal_cents']==product['price_cents']*3
    assert 'Color: Negro' in j['html'] and 'Color: Dorado' in j['html']
    assert add('negro').json()['count']==4
    r=s.post(BASE+'/carrito',data={'_csrf':t2,'quantities[1:negro]':5,'quantities[1:dorado]':2},allow_redirects=False);assert r.status_code==303
    assert s.get(BASE+'/carrito/resumen').json()['count']==7
    r=s.post(BASE+'/carrito/quitar',data={'_csrf':t2,'cart_key':'1:negro'},headers={'Accept':'application/json'});assert r.json()['count']==2 and 'Color: Negro' not in r.json()['html']
    sql("query(\"UPDATE products SET available_colors=? WHERE id=1\",['[\"negro\"]']);")
    assert 'ya no está disponible' in s.get(BASE+'/carrito').text
    assert add('dorado').status_code==422
    sql("query(\"UPDATE products SET available_colors=? WHERE id=1\",['[\"negro\",\"dorado\"]']);")
    # Enable only fixture payment configuration; this test never contacts Mercado Pago.
    subprocess.run(['php','tests/fixtures.php','enable-payments'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
    assert s.post(BASE+'/login',data={'_csrf':t2,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
    page=s.get(BASE+'/checkout');assert page.status_code==200 and 'Color: Dorado' in page.text
    key=re.search(r'name="checkout_key" value="([^"]+)"',page.text).group(1)
    r=s.post(BASE+'/checkout',data={'_csrf':t2,'checkout_key':key,'name':'Prueba colores','phone':'1112345678','delivery':'pickup'},allow_redirects=False);assert r.status_code==303,r.text
    order_path=BASE+urlparse(r.headers['Location']).path
    assert 'Color: Dorado' in s.get(order_path).text
    sql("query(\"UPDATE products SET available_colors=NULL WHERE id=1\");")
    assert 'Color: Dorado' in s.get(order_path).text
    assert 'name="color"' not in s.get(BASE+'/producto/1').text
    # Re-importing the migration keeps the selected color in old order items.
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-product-colors.sql'));")
    assert 'Color: Dorado' in s.get(order_path).text
    print('PASS: admin, colores opcionales/permitidos, variantes separadas, cantidades/eliminación, precios del servidor, color retirado, checkout y copia inmutable en pedido, migración repetible.')
finally:
    sql("query('UPDATE products SET available_colors=NULL WHERE id=1');")
    subprocess.run(['php','tests/fixtures.php','preview'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
