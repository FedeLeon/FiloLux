<dialog id="inquiry-modal" class="inquiry-modal" aria-labelledby="inquiry-heading" aria-describedby="inquiry-description">
    <div class="inquiry-modal-header"><p class="eyebrow">TU IDEA EMPIEZA ACÁ</p><button class="inquiry-close" type="button" data-inquiry-close aria-label="Cerrar formulario">×</button></div>
    <h2 id="inquiry-heading">Contanos tu idea.</h2><p id="inquiry-description">Dejanos tus datos y te contactamos para darle forma.</p>
    <?php $inquiryFormId='inquiry-modal'; $values=['name'=>'','phone'=>'','message'=>'']; $error=''; require ROOT.'/views/inquiry-form.php'; ?>
    <div data-inquiry-success hidden role="status" tabindex="-1"><span class="brand-symbol" aria-hidden="true"></span><h3>¡Gracias por escribirnos!</h3><p>Recibimos tu idea. Te vamos a contactar al teléfono que nos dejaste.</p><button class="btn full" type="button" data-inquiry-close>Seguir navegando</button></div>
</dialog>
