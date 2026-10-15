<?php $inquiryValues=$values??['name'=>'','phone'=>'','message'=>'']; ?>
<form method="post" action="/consultas" data-inquiry-form>
    <?= csrf() ?><input type="hidden" name="submission_key" value="<?= e(inquiryToken()) ?>">
    <div class="alert" data-inquiry-error role="alert" <?= empty($error)?'hidden':'' ?>><?= e($error??'') ?></div>
    <label for="<?= e($inquiryFormId) ?>-name">Nombre<input id="<?= e($inquiryFormId) ?>-name" name="name" autocomplete="name" maxlength="120" value="<?= e($inquiryValues['name']) ?>" required></label>
    <label for="<?= e($inquiryFormId) ?>-phone">Teléfono<input id="<?= e($inquiryFormId) ?>-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="40" placeholder="Con código de área" value="<?= e($inquiryValues['phone']) ?>" required></label>
    <label for="<?= e($inquiryFormId) ?>-message">Tu mensaje<textarea id="<?= e($inquiryFormId) ?>-message" name="message" rows="5" maxlength="5000" placeholder="Contanos qué imaginás, cuántas piezas necesitás y para cuándo." required><?= e($inquiryValues['message']) ?></textarea></label>
    <p class="microcopy">Usaremos estos datos para responder a tu consulta.</p>
    <button class="btn full" type="submit" data-inquiry-submit>Enviar consulta <span aria-hidden="true">↗</span></button>
</form>
