"""Local TLS-only mail sink, never sends messages to the Internet. Run on :14657."""
import socket, ssl, threading, pathlib, json
ROOT=pathlib.Path(__file__).resolve().parent/'.runtime'
ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
ctx.load_cert_chain(ROOT/'tls/cert.pem',ROOT/'tls/key.pem')
server=socket.socket(); server.setsockopt(socket.SOL_SOCKET,socket.SO_REUSEADDR,1); server.bind(('127.0.0.1',14657)); server.listen()
def handle(raw):
    try:
        with ctx.wrap_socket(raw,server_side=True) as conn:
            conn.settimeout(15); f=conn.makefile('rwb'); f.write(b'220 localhost SMTP test\r\n');f.flush()
            while True:
                line=f.readline()
                if not line: break
                cmd=line.decode(errors='replace').strip().upper()
                if cmd.startswith('EHLO'): reply=b'250-localhost\r\n250 SIZE 1000000\r\n'
                elif cmd.startswith('HELO') or cmd.startswith('MAIL') or cmd.startswith('RCPT') or cmd.startswith('RSET'): reply=b'250 OK\r\n'
                elif cmd.startswith('DATA'):
                    f.write(b'354 End with dot\r\n'); f.flush(); parts=[]
                    while True:
                        data=f.readline()
                        if data in (b'.\r\n',b''): break
                        parts.append(data.decode(errors='replace'))
                    with (ROOT/'mail.jsonl').open('a') as out: out.write(json.dumps(''.join(parts))+'\n')
                    reply=b'250 Accepted\r\n'
                elif cmd.startswith('QUIT'): f.write(b'221 Bye\r\n'); f.flush(); break
                else: reply=b'250 OK\r\n'
                f.write(reply);f.flush()
    except Exception: raw.close()
print('TLS SMTP sink listening on localhost:14657',flush=True)
while True:
    raw,_=server.accept();threading.Thread(target=handle,args=(raw,),daemon=True).start()
