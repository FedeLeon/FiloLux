"""Multi-category CRUD, migration and filtering using only filolux_test on :8087."""
from pathlib import Path
import json
import re
import subprocess
import requests

ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8087'
s=requests.Session()


def sql(code):
    prefix="define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);"
    return subprocess.check_output(['php','-r',prefix+code],cwd=ROOT,text=True)


def page(path):
    response=s.get(BASE+path)
    assert response.status_code==200,(path,response.status_code)
    return response.text


def token(path):
    return re.search(r'name="_csrf" value="([^"]+)"',page(path)).group(1)


def post(path,data):
    return s.post(BASE+path,data=data,allow_redirects=False)


def edit_data(categories):
    return {'_csrf':token('/admin/productos/1'),'categories_present':'1','category_ids[]':categories,
            'name':'Lámpara geométrica','description':'Pieza para pruebas de categorías','dimensions':'20 × 30 cm',
            'lead_time':'7 a 10 días hábiles','mode':'buy','price':'24500.00','active':'1','featured':'1'}


def selected(path):
    html=page(path)
    return [int(value) for value in re.findall(r'name="category_ids\[\]" value="(\d+)" checked',html)]


assert post('/login',{'_csrf':token('/login'),'email':'admin','password':'AdminTest2026!'}).status_code==303
assert selected('/admin/productos/1')==[1]  # Original single category remains selected.
assert post('/admin/productos/1',edit_data(['1','2','2'])).status_code==303
assert selected('/admin/productos/1')==[1,2]
assert json.loads(sql("echo json_encode(query('SELECT category_id FROM product_categories WHERE product_id=1 ORDER BY category_id')->fetchAll(PDO::FETCH_COLUMN));"))==[1,2]
for category in [1,2]:
    html=page('/catalogo?categoria='+str(category))
    assert html.count('class="product-image" href="/producto/1"')==1
    assert '/admin/productos/1' in page('/admin/productos?categoria='+str(category))
assert 'Decoración, Regalos' in page('/producto/1')
assert '/admin/productos/1' not in page('/admin/productos?categoria=sin_categoria')
invalid=post('/admin/productos/1',edit_data(['1','999999999']))
assert 'Categoría inválida' in invalid.text and selected('/admin/productos/1')==[1,2]
# A validation error preserves selected checkboxes for the user to correct the form.
invalid=post('/admin/productos/1',{**edit_data(['1','2']),'name':''})
assert invalid.status_code==200
assert len(re.findall(r'name="category_ids\[\]" value="[12]" checked',invalid.text))==2
# A product in the secondary category alone must also find related products.
sql('query("INSERT INTO product_categories (product_id,category_id) VALUES (3,1)");')
assert 'suggested-heading' in page('/producto/3')
sql('query("DELETE FROM product_categories WHERE product_id=3");')
assert post('/admin/productos/1',edit_data([])).status_code==303
assert selected('/admin/productos/1')==[]
assert '/admin/productos/1' in page('/admin/productos?categoria=sin_categoria')
assert 'suggested-heading' not in page('/producto/1')
# Existing databases can keep serving catalog pages before importing the new table.
assert post('/admin/productos/1',edit_data(['1'])).status_code==303
sql('query("DROP TABLE product_categories");')
assert '/producto/1' in page('/catalogo?categoria=1')
missing=post('/admin/productos/1',edit_data(['1','2']))
assert 'importá primero la migración' in missing.text
assert selected('/admin/productos/1')==[1]
sql("db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-15-product-categories.sql'));db()->exec(file_get_contents(ROOT.'/database/migrations/2026-10-15-product-categories.sql'));")
assert selected('/admin/productos/1')==[1]
# Deleting one category must leave the other assigned category intact.
ids=json.loads(sql("query(\"INSERT INTO categories (name) VALUES ('Temporal A')\");$a=(int)db()->lastInsertId();query(\"INSERT INTO categories (name) VALUES ('Temporal B')\");echo json_encode([$a,(int)db()->lastInsertId()]);"))
created=None
try:
    data=edit_data([str(value) for value in ids]);data['name']='Pieza temporal multicategoría'
    assert post('/admin/productos/nuevo',data).status_code==303
    created=int(sql("echo one(\"SELECT id FROM products WHERE name='Pieza temporal multicategoría'\")[\"id\"];").strip())
    assert post('/admin/categorias',{'_csrf':token('/admin/categorias'),'id':ids[0],'delete':'1'}).status_code==303
    assert selected('/admin/productos/'+str(created))==[ids[1]]
    assert 'Temporal B' in page('/producto/'+str(created))
    assert '/admin/productos/'+str(created) in page('/admin/productos?categoria='+str(ids[1]))
finally:
    if created:sql('query("DELETE FROM products WHERE id=?",['+str(created)+']);')
    sql('query("DELETE FROM categories WHERE id IN (?,?)",'+json.dumps(ids)+');')
print('PASS: casillas múltiples, duplicados/IDs inválidos, conservación ante errores, filtros público/admin, sugeridos secundarios, sin categoría, migración repetida, compatibilidad anterior y eliminación parcial de categorías.')
