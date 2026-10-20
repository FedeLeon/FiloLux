<?php
declare(strict_types=1);

function productPriceAdjustmentPercent(mixed $raw,string $direction): int {
    if(!in_array($direction,['increase','decrease'],true) || !is_string($raw) || !preg_match('/^\d{1,4}(?:[.,]\d{1,2})?$/D',trim($raw))) throw new InvalidArgumentException('Elegí subir o bajar e ingresá un porcentaje con hasta dos decimales.');
    $percent=cents(trim($raw)); // Hundredths of one percent, without floating-point arithmetic.
    if($percent<=0 || ($direction==='increase' && $percent>100000) || ($direction==='decrease' && $percent>=10000)) throw new InvalidArgumentException('El aumento debe ser mayor a 0 y hasta 1000%. La baja debe ser mayor a 0 y menor a 100%.');
    return $percent;
}
function adjustedProductPrice(int $price,int $percent,string $direction): int {
    // The product editor allows up to 8 whole-peso digits. Guard before multiplying.
    if($price<=0 || $price>9999999999) throw new InvalidArgumentException('Hay un precio fuera del rango permitido. Revisalo antes de ajustar.');
    $factor=10000+($direction==='increase'?$percent:-$percent);
    $step=5000*10000; // ARS 50 expressed in cents, including the percent denominator.
    $newPrice=intdiv($price*$factor+$step-1,$step)*5000;
    if($newPrice<=0 || $newPrice>9999999999) throw new InvalidArgumentException('El ajuste supera el precio máximo permitido. No se modificó ningún producto.');
    return $newPrice;
}
function adjustAllProductPrices(int $percent,string $direction): array {
    db()->beginTransaction();
    try {
        $products=query("SELECT id,price_cents FROM products WHERE mode='buy' AND price_cents>0".(hasProductDeletion()?' AND deleted_at IS NULL':'').' ORDER BY id FOR UPDATE')->fetchAll();
        $updates=[];
        foreach($products as $product) {
            $price=adjustedProductPrice((int)$product['price_cents'],$percent,$direction);
            if($price!==(int)$product['price_cents']) $updates[]=[$price,$product['id']];
        }
        foreach($updates as $values) query('UPDATE products SET price_cents=? WHERE id=?',$values);
        db()->commit();
        return ['changed'=>count($updates),'total'=>count($products)];
    } catch(Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); throw $ex; }
}
