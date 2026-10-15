"""Inquiry forms and admin queue on disposable filolux_test, never production."""
from pathlib import Path
import json
import re
import subprocess
import time
import requests

ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8087'
HEADERS={'Accept':'application/json'}


def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)


def tokens(session,path='/a-medida'):
    page=session.get(BASE+path)
    assert page.status_code==200
    return {name:re.search('name="'+name+'" value="([^"]+)"',page.text).group(1) for name in ['_csrf','submission_key']}


anon=requests.Session();admin=requests.Session();other=requests.Session()
assert anon.get(BASE+'/admin/mensajes',allow_redirects=False).status_code==303
form=tokens(anon)
count=int(sql('echo query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();'))
values={**form,'name':'Cliente <b>de prueba</b>','phone':'+54 9 11 1234-5678','message':'Quiero un cartel.\n<script>alert(1)</script>'}
assert anon.post(BASE+'/consultas',data={**values,'_csrf':'bad'},headers=HEADERS).status_code==419
for invalid in [{'name':''},{'phone':'12'},{'phone':'abc1234567'},{'message':''},{'message':'x'*5001}]:
    response=anon.post(BASE+'/consultas',data={**values,**invalid},headers=HEADERS)
    assert response.status_code==422,response.text
assert int(sql('echo query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();'))==count
response=anon.post(BASE+'/consultas',data=values,headers=HEADERS)
assert response.status_code==200 and response.json()['next_key']!=form['submission_key']
assert anon.post(BASE+'/consultas',data=values,headers=HEADERS).status_code==200
assert int(sql('echo query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();'))==count+1
row=json.loads(sql('echo json_encode(one("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 1"));'))
assert row['status']=='pendiente' and row['responded_at'] is None
other_form=tokens(other)
assert other.post(BASE+'/consultas',data={**values,'_csrf':other_form['_csrf']},headers=HEADERS).status_code==419
csrf=re.search(r'name="_csrf" value="([^"]+)"',admin.get(BASE+'/login').text).group(1)
assert admin.post(BASE+'/login',data={'_csrf':csrf,'email':'admin','password':'AdminTest2026!'},allow_redirects=False).status_code==303
def badge_count(path='/admin'):
    html=admin.get(BASE+path).text
    badge=re.search(r'class="admin-message-badge"[^>]*>(\d+)</span>',html)
    return int(badge.group(1)) if badge else 0
assert badge_count()==count+1
page=admin.get(BASE+'/admin/mensajes');assert page.status_code==200
assert badge_count('/admin/mensajes')==count+1
assert 'Cliente &lt;b&gt;de prueba&lt;/b&gt;' in page.text and '&lt;script&gt;alert(1)&lt;/script&gt;' in page.text
assert '<script>alert(1)</script>' not in page.text
csrf=re.search(r'name="_csrf" value="([^"]+)"',page.text).group(1)
endpoint=BASE+'/admin/mensajes/'+str(row['id'])+'/estado'
assert admin.post(endpoint,data={'status':'respondido'},headers=HEADERS).status_code==419
assert admin.post(endpoint,data={'_csrf':csrf,'status':'invalid'},headers=HEADERS).status_code==422
assert admin.post(endpoint,data={'_csrf':csrf,'status':'respondido'},allow_redirects=False).status_code==303
assert badge_count()==count
assert 'Cliente &lt;b&gt;' in admin.get(BASE+'/admin/mensajes?estado=respondido').text
assert 'Cliente &lt;b&gt;' not in admin.get(BASE+'/admin/mensajes?estado=pendiente').text
assert sql('echo one("SELECT responded_at FROM contact_messages WHERE id=?",['+str(row['id'])+'])["responded_at"];').strip()
assert admin.post(endpoint,data={'_csrf':csrf,'status':'pendiente'},allow_redirects=False).status_code==303
assert badge_count()==count+1
assert sql('echo one("SELECT status FROM contact_messages WHERE id=?",['+str(row['id'])+'])["status"];')=='pendiente'
# Native HTML submission also stores a pending message and redirects.
native=tokens(anon)
assert anon.post(BASE+'/consultas',data={**native,'name':'Consulta sin JavaScript','phone':'1123456789','message':'Texto de consulta nativa'},allow_redirects=False).status_code==303
native=tokens(anon)
invalid=anon.post(BASE+'/consultas',data={**native,'name':'Nombre conservado','phone':'xx','message':'Mensaje conservado'})
assert invalid.status_code==422 and 'value="Nombre conservado"' in invalid.text and 'Mensaje conservado' in invalid.text
# Customers cannot read or change private messages.
customer=requests.Session();html=customer.get(BASE+'/registro').text
csrf_customer=re.search(r'name="_csrf" value="([^"]+)"',html).group(1)
assert customer.post(BASE+'/registro',data={'_csrf':csrf_customer,'name':'Cliente','email':str(time.time_ns())+'@example.test','password':'CustomerTest2026!'},allow_redirects=False).status_code==303
assert customer.get(BASE+'/admin/mensajes').status_code==403
assert customer.post(endpoint,data={'_csrf':csrf_customer,'status':'respondido'}).status_code==403
for path in ['/','/contacto','/a-medida']:
    assert 'Cliente &lt;b&gt;de prueba&lt;/b&gt;' not in other.get(BASE+path).text
# Exhaust only the inquiries bucket, then verify a new submission is rejected.
for _ in range(3):
    fresh=tokens(anon)
    assert anon.post(BASE+'/consultas',data={**values,**fresh},headers=HEADERS).status_code==200
fresh=tokens(anon)
assert anon.post(BASE+'/consultas',data={**values,**fresh},headers=HEADERS).status_code==422
# Repeated migration preserves records. Missing migration leaves the site usable.
before=int(sql('echo query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();'))
sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-15-contact-messages.sql'));")
assert int(sql('echo query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();'))==before
sql('query("DROP TABLE contact_messages");')
try:
    assert other.get(BASE+'/').status_code==200
    assert 'importá la migración' in admin.get(BASE+'/admin/mensajes').text
    assert badge_count()==0
    fresh=tokens(other)
    assert other.post(BASE+'/consultas',data={**values,**fresh},headers=HEADERS).status_code==503
finally:
    sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-15-contact-messages.sql'));query('DELETE FROM rate_limits');")
print('PASS: formulario anónimo/HTML/JSON, validación, CSRF, sesión, reintentos sin duplicados, privacidad, roles, estados/filtros, límite de envíos y migración repetible/faltante.')
