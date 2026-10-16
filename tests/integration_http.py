"""Integration tests against the isolated fixture database and PHP server on :8087."""
import re, subprocess, pathlib, struct, zlib, requests
from urllib.parse import urlparse
BASE='http://127.0.0.1:8087'
ROOT=pathlib.Path(__file__).resolve().parents[1]
checks=0

def check(condition, name):
    global checks
    assert condition, name
    checks+=1
    print('PASS',name)

def get(s,path):
    r=s.get(BASE+path); check(r.status_code==200,'GET '+path); return r

def csrf(s,path):
    r=get(s,path)
    return re.search(r'name="_csrf" value="([^"]+)"',r.text).group(1), r

def post(s,path,data,token=None,files=None):
    if token is not None: data['_csrf']=token
    return s.post(BASE+path,data=data,files=files,allow_redirects=False)

def fixture(action,*args): subprocess.run(['php','tests/fixtures.php',action,*map(str,args)],cwd=ROOT,check=True)

def png():
    def chunk(k,v): return struct.pack('>I',len(v))+k+v+struct.pack('>I',zlib.crc32(k+v)&0xffffffff)
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>2I5B',2,2,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\0\xff\x99\x33\xff\x99\x33'*2))+chunk(b'IEND',b'')

admin=requests.Session(); guest=requests.Session(); a=requests.Session(); b=requests.Session()
get(guest,'/'); get(guest,'/catalogo'); get(guest,'/contacto')
check(guest.get(BASE+'/admin',allow_redirects=False).status_code==303,'admin requiere ingreso')
check(post(guest,'/carrito/agregar',{'product_id':1}).status_code==419,'CSRF obligatorio')
t,_=csrf(guest,'/producto/1'); r=post(guest,'/carrito/agregar',{'product_id':1,'quantity':2,'price':1},t); check(r.status_code==303,'carrito anónimo')
r=get(guest,'/carrito'); check('$ 49.000,00' in r.text,'precio calculado desde base de datos')
check(guest.get(BASE+'/checkout',allow_redirects=False).status_code==303,'checkout requiere usuario')
# Register two independent customers.
import time
stamp=str(time.time_ns())
for s,suffix in [(a,'a'),(b,'b')]:
    t,_=csrf(s,'/registro'); r=post(s,'/registro',{'name':'Cliente '+suffix,'email':suffix+stamp+'@example.test','password':'PasswordTest2026!'},t)
    check(r.status_code==303,'registro '+suffix)
check(a.get(BASE+'/admin').status_code==403,'cliente no accede al administrador')
t,_=csrf(a,'/producto/1'); post(a,'/carrito/agregar',{'product_id':1,'quantity':2},t)
get(a,'/producto/3'); check(post(a,'/carrito/agregar',{'product_id':3,'quantity':1},t).status_code==404,'personalizado no se compra directamente')
t,r=csrf(a,'/checkout'); check('Los pagos todavía no están habilitados' in r.text,'checkout sin credenciales informa indisponibilidad')
fixture('enable-payments')
try:
    t,r=csrf(a,'/checkout'); key=re.search(r'name="checkout_key" value="([^"]+)"',r.text).group(1)
    data={'checkout_key':key,'name':'Cliente A','phone':'5491112345678','delivery':'shipping','street':'Calle de Prueba','street_number':'123','city':'Córdoba','province':'Córdoba','postal_code':'5000','notes':'Prueba de integración','total':1,'shipping_price':0}
    bad=post(a,'/checkout',{**data,'postal_code':''},t); check(bad.status_code==422,'dirección incompleta rechazada')
    r=post(a,'/checkout',data,t); check(r.status_code==303,'crear pedido con envío')
    path=urlparse(r.headers['Location']).path; order_id=int(path.rsplit('/',1)[1]); r=get(a,path)
    check('$ 52.500,00' in r.text,'envío y total autoritativos')
    check(b.get(BASE+path).status_code==404,'aislamiento de pedidos entre clientes')
    r=a.get(BASE+path+'?retorno=1&status=approved',allow_redirects=False); check(r.status_code==303 and urlparse(r.headers['Location']).path==path,'retorno limpia parámetros técnicos')
    r=get(a,path); check('Pago: Pendiente' in r.text,'retorno manipulado no acredita pago')
    # Admin CRUD and upload.
    t,_=csrf(admin,'/login'); r=post(admin,'/login',{'email':'admin','password':'AdminTest2026!'},t); check(r.status_code==303,'ingreso administrador')
    get(admin,'/admin'); get(admin,'/admin/configuracion'); get(admin,'/admin/pedidos')
    t,r=csrf(admin,'/admin/productos/1')
    data_product={'name':'Lámpara geométrica','description':'Descripción editada después de comprar','category_id':1,'lead_time':'15 días hábiles','mode':'buy','price':'100.00','active':1,'featured':1}
    r=post(admin,'/admin/productos/1',data_product,t,files={'images[]':('photo.png',png(),'image/png')}); check(r.status_code==303,'guardar producto y foto real')
    r=get(a,path); check('$ 52.500,00' in r.text and '7 a 10 días hábiles' in r.text,'pedido conserva precios y plazos originales')
    r=get(guest,'/producto/1'); check(re.search(r'src="/uploads/[a-f0-9]+\.webp"',r.text) is not None,'foto convertida a WebP y publicada')
    r=post(admin,'/admin/productos/1',data_product,t,files={'images[]':('malicious.php',b'<?php echo 1;','image/jpeg')}); check(r.status_code==200 and 'Usá JPG' in r.text,'rechazar archivo ejecutable disfrazado de imagen')
    r=post(admin,'/admin/pedidos/'+str(order_id),{'status':'en fabricación'},t); check(r.status_code==422,'no fabricar pedidos impagos')
    fixture('approve',order_id)
    r=post(admin,'/admin/pedidos/'+str(order_id),{'status':'en fabricación'},t); check(r.status_code==303,'avanzar fabricación de pedido pagado')
    check('en fabricación' in get(a,path).text,'cliente ve avance del pedido')
    check(guest.post(BASE+'/webhook/mercadopago?data.id=123',headers={'X-Signature':'ts=1,v1=bad'}).status_code==401,'webhook rechaza firma inválida')
    # Restore seed product for preview and subsequent runs.
    data_product.update(price='24500.00',lead_time='7 a 10 días hábiles')
    post(admin,'/admin/productos/1',data_product,t)
finally: fixture('preview')
print('Total:',checks,'comprobaciones HTTP')
