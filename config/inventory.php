<?php
// Product quantity is total stock received. Issued, non-cancelled invoices consume it.
function inventory_quantity(float $quantity, string $from, string $to): ?float {
    $from = strtoupper(trim($from)); $to = strtoupper(trim($to));
    if ($from === $to) return $quantity;
    $units = ['KG'=>['mass',1000], 'GRAM'=>['mass',1], 'LITER'=>['volume',1000], 'ML'=>['volume',1]];
    if (!isset($units[$from],$units[$to]) || $units[$from][0] !== $units[$to][0]) return null;
    return $quantity * $units[$from][1] / $units[$to][1];
}
function inventory_sales(PDO $pdo, int $productId): array {
    $query=$pdo->prepare("SELECT i.id AS invoice_id,i.invoice_number,i.buyer_name AS customer,i.customer_id,i.issue_date,i.issued_at,ii.quantity,ii.unit
        FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id
        WHERE ii.product_id=? AND i.status='issued' ORDER BY i.issued_at DESC,i.id DESC,ii.line_no");
    $query->execute([$productId]); return $query->fetchAll();
}
function inventory_balance(PDO $pdo, array $product): array {
    $sold=0; $unmatched=false;
    foreach(inventory_sales($pdo,(int)$product['id']) as $sale){
        $quantity=inventory_quantity((float)$sale['quantity'],$sale['unit'],$product['unit']);
        if($quantity===null) $unmatched=true; else $sold+=$quantity;
    }
    $total=(float)($product['quantity']??$product['default_quantity']);
    return ['sold_quantity'=>round($sold,3),'available_quantity'=>max(0,round($total-$sold,3)),
        'stock_error'=>$unmatched?'Some issued sales use a different unit. Review the stock unit.':($sold>$total+0.00001?'Issued sales exceed total stock. Update total stock received.':null)];
}
