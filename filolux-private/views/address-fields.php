<div class="form-grid">
<?php foreach(['street'=>['Calle','address-line1',160],'street_number'=>['Número','off',20],'apartment'=>['Piso / departamento (opcional)','address-line2',100],'city'=>['Localidad','address-level2',100],'province'=>['Provincia','address-level1',100],'postal_code'=>['Código postal','postal-code',12]] as $key=>[$label,$autocomplete,$max]): ?>
<label><?= e($label) ?><input name="<?= e($key) ?>" value="<?= e($details[$key]??'') ?>" autocomplete="<?= e($autocomplete) ?>" maxlength="<?= $max ?>" <?= $key!=='apartment'?'data-shipping-required':'' ?>></label>
<?php endforeach ?>
</div>
