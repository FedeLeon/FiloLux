<?php if($message!==''): ?><p class="mini-cart-notice" role="status"><?= e($message) ?></p><?php endif ?>
<?php if(!$items): ?>
<div class="mini-cart-empty"><span class="brand-symbol" aria-hidden="true"></span><h3>Tu carrito está vacío.</h3><p>Encontrá algo especial para crear.</p><a href="/catalogo" class="text-link">Explorar catálogo ↗</a></div>
<?php else: ?>
<div class="mini-cart-list">
<?php foreach($items as $i): ?>
<article class="mini-cart-item<?= (int)$i['id']===$addedId?' just-added':'' ?>">
    <a href="/producto/<?= (int)$i['id'] ?>" class="mini-cart-image"><?php if($i['image']): ?><img src="/<?= e($i['image']) ?>" alt="<?= e($i['name']) ?>"><?php else: ?><span class="brand-symbol" aria-hidden="true"></span><?php endif ?></a>
    <div class="mini-cart-description"><h3><a href="/producto/<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a></h3><small><?= $i['quantity'] ?> × <?= money($i['price_cents']) ?></small><?php if(($i['dimensions']??'')!==''): ?><small><?= e($i['dimensions']) ?></small><?php endif ?></div>
    <div class="mini-cart-actions"><strong><?= money($i['quantity']*$i['price_cents']) ?></strong><form method="post" action="/carrito/quitar"><?= csrf() ?><input type="hidden" name="product_id" value="<?= (int)$i['id'] ?>"><button type="submit" class="cart-remove" data-cart-remove aria-label="Eliminar <?= e($i['name']) ?> del carrito" title="Eliminar producto"><?php require ROOT.'/views/trash-icon.php'; ?></button></form></div>
</article>
<?php endforeach ?>
</div>
<div class="mini-cart-subtotal"><span>Subtotal</span><strong><?= money($subtotal) ?></strong></div><p class="mini-cart-delivery">La entrega se calcula al finalizar la compra.</p>
<div class="mini-cart-footer"><a class="btn full" href="/carrito">Ver carrito ↗</a><button type="button" class="btn secondary full" data-cart-close>Seguir navegando</button></div>
<?php endif ?>
