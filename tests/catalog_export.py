"""Download real PDF/XLSX/CSV from the isolated filolux_test server."""
import csv, io, json, os, re, subprocess, tempfile, zipfile
from pathlib import Path
from xml.etree import ElementTree as ET
import requests
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('FILOLUX_TEST_BASE','http://127.0.0.1:8087')
def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');define('PUBLIC_ROOT',getcwd().'/public');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/vendor/autoload.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)
image=ROOT/'public/uploads/catalog-export-test.png'
assert not image.exists()
sql("$img=imagecreatetruecolor(80,60);imagefill($img,0,0,imagecolorallocate($img,190,145,65));imagepng($img,PUBLIC_ROOT.'/uploads/catalog-export-test.png');imagedestroy($img);")
try:
    sql("query('INSERT INTO product_categories (product_id,category_id) VALUES (1,1),(1,2)');query('UPDATE products SET dimensions=?, available_colors=? WHERE id=1',['20 × 30 cm',json_encode(['negro','dorado'])]);query('INSERT INTO product_images (product_id,path) VALUES (1,?),(1,?)',['uploads/catalog-export-test.png','uploads/catalog-export-test.png']);query('UPDATE products SET active=0 WHERE id=5');query('UPDATE products SET deleted_at=CURRENT_TIMESTAMP WHERE id=6');")
    sql("query('INSERT INTO products (name,description,price_cents,lead_time,mode) VALUES (?,?,?,?,?)',['=Prueba segura','Descripción con acentos y símbolos <script>seguro</script>',12345,'5 días hábiles','buy']);$id=(int)db()->lastInsertId();query('INSERT INTO product_categories (product_id,category_id) VALUES (?,2)',[$id]);for($n=0;$n<14;$n++)query('INSERT INTO products (name,description,price_cents,lead_time,mode) VALUES (?,?,?,?,?)',['Extra '.$n,'Detalle '.$n,15000,'3 días hábiles','buy']);")
    sql("query('UPDATE products SET description=? WHERE id=2',[str_repeat('Descripción completa con detalles de fabricación. ',140).'FIN-DE-DESCRIPCIÓN']);")
    expected=json.loads(sql("require ROOT.'/app/catalog-export.php';echo json_encode(catalogExportProducts());"))
    assert len(expected)>12
    s=requests.Session()
    page=s.get(BASE+'/catalogo?q=sin-resultados').text
    for fmt in ['pdf','xlsx','csv']: assert '/catalogo/descargar/'+fmt in page
    results={}
    for fmt in ['pdf','xlsx','csv']:
        r=s.get(BASE+'/catalogo/descargar/'+fmt+'?categoria=999&q=sin-resultados&pagina=100')
        assert r.status_code==200,(fmt,r.status_code,r.text[:200])
        assert 'attachment;' in r.headers['Content-Disposition'] and r.headers['Content-Disposition'].endswith(fmt+'"')
        assert len(r.content)==int(r.headers['Content-Length']) and 'no-store' in r.headers['Cache-Control']
        results[fmt]=r.content
    rows=list(csv.reader(io.StringIO(results['csv'].decode('utf-8-sig')),delimiter=';'))
    assert len(rows)==len(expected)+1
    assert len({r[2] for r in rows[1:]})==len(expected)
    first=next(r for r in rows[1:] if r[2]=='#1')
    assert first[0]=='Decoración' and 'Regalos' in first[1]
    assert first[6]=='24500,00' and first[7]=='20 × 30 cm' and first[9]=='Negro, Dorado'
    assert len(first[12].splitlines())==2
    assert any(r[0]=='Regalos' and r[3]=="'=Prueba segura" for r in rows[1:])
    assert all(r[2] not in ['#5','#6'] for r in rows[1:])
    assert any(r[5]=='A medida / consultar' and r[6]=='A consultar' for r in rows[1:])
    ns={'m':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
    with zipfile.ZipFile(io.BytesIO(results['xlsx'])) as book:
        assert book.testzip() is None
        strings=[''.join(t.itertext()) for t in ET.fromstring(book.read('xl/sharedStrings.xml')).findall('m:si',ns)]
        sheet=ET.fromstring(book.read('xl/worksheets/sheet1.xml'))
        assert not sheet.findall('.//m:f',ns)
        assert '=Prueba segura' in strings and '20 × 30 cm' in strings
        assert any(name.startswith('xl/media/') for name in book.namelist())
        cells={c.attrib['r']:c for c in sheet.findall('.//m:c',ns)}
        codes=[strings[int(c.find('m:v',ns).text)] for addr,c in cells.items() if re.match(r'^C[0-9]+$',addr) and addr!='C1' and c.attrib.get('t')=='s']
        assert len(codes)==len(expected) and len(set(codes))==len(expected)
    with tempfile.TemporaryDirectory(prefix='filolux-pdf-test-') as tmp:
        pdf=Path(tmp)/'catalog.pdf';pdf.write_bytes(results['pdf'])
        assert pdf.read_bytes().startswith(b'%PDF-')
        text=subprocess.check_output(['pdftotext','-layout',str(pdf),'-'],text=True)
        for p in expected: assert len(re.findall(r'#'+str(p['id'])+r'\b',text))==1,(p['id'],text)
        assert '20 × 30 cm' in text and 'Negro, Dorado' in text and 'Sin categoría' in text
        assert re.search(r'FIN-DE-\s*DESCRIPCIÓN',text)
        assert 'Organizador de escritorio' not in text and 'Un regalo con historia' not in text
        assert 'Índice de categorías' in text.split('\f')[0]
        # Resolve actual PDF GoTo actions: index links land on each category's opening page.
        raw=pdf.read_bytes()
        objects=dict(re.findall(rb'(\d+) 0 obj\s*(.*?)\s*endobj',raw,re.S))
        targets=re.findall(rb'/S /GoTo\s*/D (\d+) 0 R',raw)
        groups=list(dict.fromkeys(p['export_category'] for p in expected))
        assert len(targets)==2*len(groups)
        pages=re.findall(rb'(\d+) 0 obj\s*<< /Type /Page\s',raw)
        text_pages=text.split('\f')
        for target,name in zip(targets[:len(groups)],groups):
            page_ref=re.search(rb'\[(\d+) 0 R',objects[target]).group(1)
            assert text_pages[pages.index(page_ref)].strip().splitlines()[0]==name
        for target in targets[len(groups):]:
            assert re.search(rb'\[(\d+) 0 R',objects[target]).group(1)==pages[0]
        assert re.search(r'^\s*\d+\s+\d+\s+image',subprocess.check_output(['pdfimages','-list',str(pdf)],text=True),re.M)
    assert s.get(BASE+'/catalogo/descargar/html').status_code==404
    assert s.get(BASE+'/catalogo/descargar/pdf/extra').status_code==404
    # An empty catalog must still produce usable downloads.
    active=json.loads(sql('echo json_encode(query("SELECT id FROM products WHERE active=1")->fetchAll(PDO::FETCH_COLUMN));'))
    sql('query("UPDATE products SET active=0");')
    for fmt in ['pdf','xlsx','csv']:
        r=s.get(BASE+'/catalogo/descargar/'+fmt);assert r.status_code==200
    sql('query("UPDATE products SET active=1 WHERE id IN ('+','.join(map(str,active))+')");')
    # Bound expensive anonymous exports, without blocking the regular catalog.
    sql("query('UPDATE rate_limits SET attempts=15 WHERE bucket=?',[hash('sha256','catalog-export|127.0.0.1')]);")
    assert s.get(BASE+'/catalogo/descargar/pdf').status_code==422
    assert s.get(BASE+'/catalogo').status_code==200
    print('PASS: descargas PDF/XLSX/CSV reales, catálogo completo, categorías únicas/principal/fallback, privacidad, fotos, colores, medidas, acentos, importes, fórmulas como texto, catálogo vacío y límite de descargas.')
finally:
    image.unlink(missing_ok=True)
    sql("query('DELETE FROM product_images WHERE path=?',['uploads/catalog-export-test.png']);query('DELETE FROM rate_limits WHERE bucket=?',[hash('sha256','catalog-export|127.0.0.1')]);")
