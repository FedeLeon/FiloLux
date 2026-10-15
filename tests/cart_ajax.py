"""Session cart JSON and native-form integration against the isolated fixture DB."""
import requests,re
BASE='http://127.0.0.1:8087';s=requests.Session();json_headers={'Accept':'application/json'}
def token():return re.search(r'name="_csrf" value="([^"]+)"',s.get(BASE+'/producto/1').text).group(1)
t=token()
def post(path,data):return s.post(BASE+path,data={'_csrf':t,**data},headers=json_headers)
r=s.get(BASE+'/carrito/resumen',headers=json_headers);assert r.status_code==200 and r.json()['count']==0
r=post('/carrito/agregar',{'product_id':1,'quantity':2,'price':1});assert r.status_code==200
j=r.json();assert j['count']==2 and j['subtotal_cents']==4900000 and 'Agregaste Lámpara' in j['html'] and 'cart-remove' in j['html']
r=post('/carrito/agregar',{'product_id':5,'quantity':1});j=r.json();assert j['count']==3 and j['subtotal_cents']==6580000
other=requests.Session();assert other.get(BASE+'/carrito/resumen').json()['count']==0
r=post('/carrito/agregar',{'product_id':3,'quantity':1});assert r.status_code==404 and 'error' in r.json()
r=s.post(BASE+'/carrito/quitar',data={'product_id':1},headers=json_headers);assert r.status_code==419 and 'error' in r.json()
r=post('/carrito/quitar',{'product_id':1});j=r.json();assert j['count']==1 and j['subtotal_cents']==1680000 and 'Lámpara' not in j['html']
r=post('/carrito/quitar',{'product_id':1});assert r.json()['count']==1
r=post('/carrito/quitar',{'product_id':5});j=r.json();assert j['count']==0 and j['subtotal_cents']==0 and 'Tu carrito está vacío' in j['html']
r=s.post(BASE+'/carrito/agregar',data={'_csrf':t,'product_id':1},allow_redirects=False);assert r.status_code==303
r=s.post(BASE+'/carrito/quitar',data={'_csrf':t,'product_id':1},allow_redirects=False);assert r.status_code==303
assert s.get(BASE+'/carrito/resumen').json()['count']==0
print('PASS: agregar/quitar JSON y nativo, importes del servidor, sesión aislada, CSRF, consultas no comprables, eliminación repetida y carrito vacío.')
