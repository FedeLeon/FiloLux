<?php
declare(strict_types=1);
function cartPayload(string $message='',int $addedId=0): array {
    $items=cartItems();
    $count=array_sum(array_column($items,'quantity'));
    $subtotal=cartTotal($items);
    ob_start(); require ROOT.'/views/mini-cart-items.php'; $html=ob_get_clean();
    ob_start(); require ROOT.'/views/cart.php'; $cartHtml=ob_get_clean();
    return ['count'=>$count,'subtotal_cents'=>$subtotal,'html'=>$html,'cartHtml'=>$cartHtml];
}
