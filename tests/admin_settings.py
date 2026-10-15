"""Settings/CRUD integration against the disposable fixture DB and :8087."""
import requests,re,html,subprocess,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087';s=requests.Session()
def page(path):
 r=s.get(BASE+path);assert r.status_code==200
 return r.text

def csrf(text):return re.search('name="_csrf" value="([^"]+)"',text).group(1)
def post(path,data):return s.post(BASE+path,data=data,allow_redirects=False)
t=csrf(page('/login'));r=post('/login',{'_csrf':t,'email':'admin','password':'AdminTest2026!'});assert r.status_code==303
def settings():
 text=page('/admin/configuracion');data={}
 for tag in re.findall(r'<input\b[^>]*>',text):
  attrs=dict(re.findall(r'(\w+)="([^"]*)"',tag));name=attrs.get('name');kind=attrs.get('type','text')
  if name and kind not in ['checkbox','file','password']:data[name]=html.unescape(attrs.get('value',''))
 for name,body in re.findall(r'<textarea name="([^"]+)"[^>]*>(.*?)</textarea>',text,re.S):data[name]=html.unescape(body)
 data.update(mp_mode='test',smtp_security='tls')
 return data,text
saved,text=settings();original=saved.copy()
try:
 saved.update(whatsapp='5491112345678',instagram='https://instagram.com/filolux.test',contact_email='hola@example.test',shipping_price='1234.56',mp_test_token='TEST-hidden-token-abc',mp_test_webhook='hidden-webhook-abc')
 r=post('/admin/configuracion',saved);assert r.status_code==303,'save settings'
 next_data,text=settings();assert 'TEST-hidden-token-abc' not in text and 'hidden-webhook-abc' not in text,'secrets masked'
 assert next_data['shipping_price']=='1234.56','price cents conversion'
 assert '5491112345678' in text,'contact stored'
 next_data['hero_title']='Texto de prueba temporal';r=post('/admin/configuracion',next_data);assert r.status_code==303,'blank secrets preserve'
 # Verify decrypted values through isolated CLI, without printing secrets.
 subprocess.run(['php','-r',"define('ROOT',getcwd().'/filolux-private'); $GLOBALS['config']=require 'tests/.runtime/config.php'; require ROOT.'/app/core.php'; if(secret('mp_test_token')!=='TEST-hidden-token-abc') exit(1);"],cwd=ROOT,check=True)
 r=post('/admin/configuracion',{**next_data,'instagram':'javascript:alert(1)'});assert 'Las redes deben tener una URL HTTPS' in r.text,'unsafe external URLs rejected'
 t=csrf(page('/admin/categorias'));r=post('/admin/categorias',{'_csrf':t,'name':'Categoría de integración'});assert r.status_code==303
 text=page('/admin/categorias');match=re.search(r'name="id" value="(\d+)">.*?value="Categoría de integración"',text)
 # Match the containing form rather than preceding categories.
 form=next(f for f in re.findall('<form.*?</form>',text,re.S) if 'Categoría de integración' in f);cid=re.search(r'name="id" value="(\d+)"',form).group(1)
 r=post('/admin/categorias',{'_csrf':t,'id':cid,'name':'Categoría editada'});assert r.status_code==303
 r=post('/admin/categorias',{'_csrf':t,'id':cid,'delete':1});assert r.status_code==303
 assert 'Categoría editada' not in page('/admin/categorias')
 # Test pickup totals, duplicate checkout and cart update using the admin's own customer account.
 subprocess.run(['php','tests/fixtures.php','enable-payments'],cwd=ROOT,check=True)
 t=csrf(page('/producto/1'));post('/carrito/agregar',{'_csrf':t,'product_id':1,'quantity':3})
 post('/carrito',{'_csrf':t,'quantities[1]':1})
 text=page('/checkout');key=re.search('name="checkout_key" value="([^"]+)"',text).group(1)
 data={'_csrf':t,'checkout_key':key,'name':'Pickup Test','phone':'1112345678','delivery':'pickup'}
 r=post('/checkout',data);assert r.status_code==303, (r.status_code, re.sub('<[^>]+>',' ',r.text)[-2000:]);from urllib.parse import urlparse
 order=urlparse(r.headers['Location']).path;text=page(order)
 assert '$ 24.500,00' in text and 'Retiro en el taller' in text,'free pickup total'
 r=post('/checkout',data);assert r.status_code==303 and r.headers['Location'].endswith('/carrito'),'repeat submission creates no second order'
 print('PASS: configuración, secretos ocultos y conservados, tarifa en centavos, URLs seguras, categorías CRUD, carrito, retiro y doble envío del checkout.')
finally:
 original['clear_mp_test_token']=1;original['clear_mp_test_webhook']=1
 post('/admin/configuracion',original)
 subprocess.run(['php','tests/fixtures.php','preview'],cwd=ROOT,check=True)
