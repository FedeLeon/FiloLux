"""Product suggestions, admin measurements and order snapshots on the disposable test DB."""
import pathlib, subprocess, requests, re
from urllib.parse import urlparse
ROOT=pathlib.Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087'
s=requests.Session()
def get(path):
 r=s.get(BASE+path);assert r.status_code==200,(path,r.status_code);return r

def csrf(path):return re.search(r'name="_csrf" value="([^"]+)"',get(path).text).group(1)
def post(path,data):return s.post(BASE+path,data=data,allow_redirects=False)
def fixture(action):subprocess.run(['php','tests/fixtures.php',action],cwd=ROOT,check=True)
def sql(code):
 prefix="define('ROOT',getcwd().'/filolux-private'); $GLOBALS['config']=require 'tests/.runtime/config.php'; require ROOT.'/app/core.php'; if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
 return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT).decode()

# Six existing fixtures: 1,5 share category; 2,4,6 share another; 3 is alone.
t=csrf('/login');r=post('/login',{'_csrf':t,'email':'admin','password':'AdminTest2026!'});assert r.status_code==303
text=get('/producto/1').text
assert 'id="suggested-heading"' in text
related=text.split('id="suggested-heading"',1)[1]
assert '/producto/5' in related and '/producto/1"' not in related and '/producto/2"' not in related
assert 'id="suggested-heading"' not in get('/producto/3').text
sql("query('UPDATE products SET active=0 WHERE id=5');")
assert 'id="suggested-heading"' not in get('/producto/1').text
sql("query('UPDATE products SET active=1 WHERE id=5');")
# Create a product without a category, with escaped dimensions.
t=csrf('/admin/productos/nuevo')
data={'_csrf':t,'name':'Producto de medidas de prueba','description':'Prueba de medidas','category_id':'','lead_time':'5 días','mode':'buy','price':'123.45','active':'1','dimensions':'30 × 20 × 5 cm <script>alert(1)</script>'}
r=post('/admin/productos/nuevo',data);assert r.status_code==303
pid=int(sql("echo query(\"SELECT id FROM products WHERE name='Producto de medidas de prueba' ORDER BY id DESC LIMIT 1\")->fetchColumn();"))
try:
 text=get('/producto/'+str(pid)).text
 assert '30 × 20 × 5 cm &lt;script&gt;' in text and '<script>alert(1)</script>' not in text
 assert 'id="suggested-heading"' not in text
 assert data['dimensions'].replace('<','&lt;').replace('>','&gt;') in get('/admin/productos/'+str(pid)).text
 bad=post('/admin/productos/'+str(pid),{**data,'dimensions':'x'*161})
 assert 'Revisá el campo dimensions' in bad.text
 data['dimensions']='Ø 15 cm';assert post('/admin/productos/'+str(pid),data).status_code==303
 assert 'Ø 15 cm' in get('/producto/'+str(pid)).text
 fixture('enable-payments')
 assert post('/carrito/agregar',{'_csrf':t,'product_id':pid,'quantity':1}).status_code==303
 assert 'Medidas: Ø 15 cm' in get('/carrito').text
 checkout=get('/checkout');assert 'Medidas: Ø 15 cm' in checkout.text
 key=re.search(r'name="checkout_key" value="([^"]+)"',checkout.text).group(1)
 r=post('/checkout',{'_csrf':t,'checkout_key':key,'name':'Prueba','phone':'1112345678','delivery':'pickup'})
 assert r.status_code==303
 order_path=urlparse(r.headers['Location']).path
 data['dimensions']='80 × 60 cm';assert post('/admin/productos/'+str(pid),data).status_code==303
 assert 'Medidas: Ø 15 cm' in get(order_path).text
 assert '80 × 60 cm' in get('/producto/'+str(pid)).text
 data['dimensions']='';assert post('/admin/productos/'+str(pid),data).status_code==303
 assert 'product-measurements' not in get('/producto/'+str(pid)).text
 print('PASS: sugeridos de la misma categoría, exclusión propia/ocultos, categoría vacía, medidas opcionales/escapadas, validación, carrito/checkout y copia inmutable en pedidos.')
finally:
 fixture('preview')
 # Keep the ordered fixture product hidden so the foreign-key history remains intact.
 sql('query(\"UPDATE products SET active=0 WHERE id=?\",['+str(pid)+']);')
