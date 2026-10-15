<?php // Receives $brandName and $brandLogo; shared by the header, footer and installer. ?>
<?php if ($brandLogo!==''): ?>
<img class="brand-upload" src="/<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>">
<?php elseif ($brandName==='FiloLux'): ?>
<span class="brand-wordmark"><img class="brand-logo logo-on-dark" src="/assets/brand/logo-white.svg" alt="FiloLux" width="190" height="80"><img class="brand-logo logo-on-light" src="/assets/brand/logo-black.svg" alt="FiloLux" width="190" height="80"></span>
<?php else: ?>
<?= e($brandName) ?><span class="brand-symbol" aria-hidden="true"></span>
<?php endif ?>
