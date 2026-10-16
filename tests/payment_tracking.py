"""Return hints never credit payments; verified data and retry guards on filolux_test."""
from pathlib import Path
from urllib.parse import urlparse
import requests,re,subprocess,json,time
ROOT=Path(__file__).resolve().parents[1];BASE='http://127.0.0.1:8087'
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';require ROOT.'/app/Payments.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
def token(s,path):return re.search(r'name="_csrf" value="([^"]+)"',s.get(BASE+path).text).group(1)
s=requests.Session();other=requests.Session();email=str(time.time_ns())+'@example.test';pw='CustomerTest2026!'
t=token(s,'/registro');assert s.post(BASE+'/registro',data={'_csrf':t,'name':'Prueba pago','email':email,'password':pw},allow_redirects=False).status_code==303
assert s.post(BASE+'/carrito/agregar',data={'_csrf':t,'product_id':1},allow_redirects=False).status_code==303
subprocess.run(['php','tests/fixtures.php','enable-payments'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
try:
    html=s.get(BASE+'/checkout').text;key=re.search(r'name="checkout_key" value="([^"]+)"',html).group(1)
    r=s.post(BASE+'/checkout',data={'_csrf':t,'checkout_key':key,'name':'Prueba pago','phone':'1112345678','delivery':'pickup'},allow_redirects=False);assert r.status_code==303
    path=urlparse(r.headers['Location']).path;oid=int(path.rsplit('/',1)[1]);pid='990777000'
    assert 'Pagar la compra' in s.get(BASE+path).text
    sql("query('UPDATE orders SET preference_id=?,checkout_url=? WHERE id=?',['pref-tracking','https://sandbox.mercadopago.com.ar/checkout/test',"+str(oid)+"]);")
    params={'retorno':1,'payment_id':pid,'status':'approved','external_reference':str(oid),'preference_id':'pref-tracking','payment_type':'account_money','merchant_order_id':'45325055942'}
    assert other.get(BASE+path,params=params,allow_redirects=False).status_code==303
    assert 'Referencia recibida' not in s.get(BASE+path,params={**params,'external_reference':'999999'}).text
    html=s.get(BASE+path,params=params).text
    assert pid in html and 'Pago: Pendiente' in html and 'Pago en verificación' in html and 'todavía sin verificar' in html
    assert sql('echo query("SELECT COUNT(*) FROM payments WHERE order_id=?",['+str(oid)+'])->fetchColumn();')=='0'
    assert 'Pago en verificación' in s.get(BASE+path).text
    assert s.post(BASE+path+'/pagar',data={'_csrf':t},allow_redirects=False).status_code==422
    # New authenticated session also stays blocked; this is not a browser-only guard.
    again=requests.Session();t2=token(again,'/login');assert again.post(BASE+'/login',data={'_csrf':t2,'email':email,'password':pw},allow_redirects=False).status_code==303
    assert 'Pago en verificación' in again.get(BASE+path).text
    order=json.loads(sql('echo json_encode(one("SELECT * FROM orders WHERE id=?",['+str(oid)+']));'))
    payment={'id':pid,'external_reference':str(oid),'transaction_amount':order['total_cents']/100,'currency_id':'ARS','collector_id':555,'live_mode':False,'status':'pending','payment_type_id':'account_money','payment_method_id':'account_money','order':{'id':'45325055942'}}
    def reconcile(p):
        encoded=json.dumps(p)
        return sql('echo (int)Payments::reconcile(json_decode('+json.dumps(encoded)+',true),"test",555);')
    reconcile(payment)
    assert 'Estado confirmado' in s.get(BASE+path).text and 'Pago en verificación' in s.get(BASE+path).text
    reconcile({**payment,'status':'rejected'})
    assert 'Pagar la compra' in s.get(BASE+path).text
    assert s.post(BASE+path+'/pagar',data={'_csrf':t},allow_redirects=False).status_code==303
    # A new return blocks again; reopening the old rejected return cannot unlock it.
    new_pid='990777001'
    s.get(BASE+path,params={**params,'payment_id':new_pid})
    s.get(BASE+path,params=params)
    assert 'Pago en verificación' in s.get(BASE+path).text
    assert s.post(BASE+path+'/pagar',data={'_csrf':t},allow_redirects=False).status_code==422
    assert reconcile({**payment,'id':new_pid,'status':'approved'})=='1'
    assert reconcile({**payment,'id':new_pid,'status':'approved'})=='0'
    html=s.get(BASE+path).text
    assert 'Pago: Aprobado' in html and new_pid in html and 'account_money' in html and '45325055942' in html and 'Pagar la compra' not in html
    assert s.post(BASE+path+'/pagar',data={'_csrf':t},allow_redirects=False).status_code==422
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-16-payment-tracking.sql'));")
    assert new_pid in s.get(BASE+path).text
    print('PASS: retorno no acredita, persistencia/privacidad, bloqueo UI/servidor/otra sesión, pendiente, rechazo/reintento, retorno antiguo, aprobación, datos verificados, duplicados y migración repetible.')
finally:
    subprocess.run(['php','tests/fixtures.php','preview'],cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
