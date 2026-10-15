"""Run smtp_sink.py first. PHP :8087 must trust .runtime/tls/cert.pem via openssl.cafile.
Generate a local certificate with subjectAltName=DNS:localhost before starting both.
This exercises actual SMTPS delivery into a local sink; no provider account is used.
"""
import requests, subprocess, re, pathlib, json, email, time
from urllib.parse import urlparse, parse_qs
ROOT=pathlib.Path(__file__).resolve().parents[1]; BASE='http://127.0.0.1:8087'; MAIL=ROOT/'tests/.runtime/mail.jsonl'
s=requests.Session()
def token(path):
    r=s.get(BASE+path); assert r.status_code==200
    return re.search('name="_csrf" value="([^"]+)"',r.text).group(1)
def post(path,data,t): return s.post(BASE+path,data={**data,'_csrf':t},allow_redirects=False)
def fixture(action): subprocess.run(['php','tests/fixtures.php',action],cwd=ROOT,check=True)
fixture('smtp')
try:
    before=len(MAIL.read_text().splitlines()) if MAIL.exists() else 0
    address='reset'+str(time.time_ns())+'@example.test'
    r=post('/registro',{'name':'Mail Test','email':address,'password':'OriginalTest2026!'},token('/registro')); assert r.status_code==303
    post('/logout',{},token('/cuenta'))
    r=post('/recuperar',{'email':address},token('/recuperar')); assert r.status_code==303
    lines=MAIL.read_text().splitlines(); assert len(lines)==before+1,'SMTP delivery with verified TLS'
    message=email.message_from_bytes(json.loads(lines[-1]).encode('utf-8')); body=message.get_payload(decode=True).decode(message.get_content_charset() or 'utf-8')
    link=re.search(r'https?://[^\s]+/restablecer\?[^\s]+',body).group(0); parsed=urlparse(link); qs=parse_qs(parsed.query)
    t=token(parsed.path+'?'+parsed.query)
    data={'email':qs['email'][0],'token':qs['token'][0],'password':'Replacement2026!'}
    r=post('/restablecer',data,t); assert r.status_code==303,'password reset'
    r=post('/restablecer',data,t); assert r.status_code==200 and 'inválido o venció' in r.text,'single-use token'
    r=post('/login',{'email':address,'password':'Replacement2026!'},token('/login')); assert r.status_code==303,'new password login'
    t=token('/cuenta'); post('/logout',{},t)
    r=post('/recuperar',{'email':'nonexistent@example.test'},token('/recuperar')); assert r.status_code==303
    assert len(MAIL.read_text().splitlines())==before+1,'unknown address sends no mail'
    print('PASS: entrega SMTPS con certificado verificado, recuperación, token de un uso, ingreso con nueva contraseña y respuesta genérica.')
finally: fixture('smtp-off')
