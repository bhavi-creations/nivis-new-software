<?php
// Product-level receipts; legacy whole-invoice payments are applied in line order.
function ensure_payment_allocations(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS invoice_payment_allocations (
        payment_id BIGINT UNSIGNED NOT NULL, invoice_item_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        PRIMARY KEY(payment_id,invoice_item_id),
        FOREIGN KEY(payment_id) REFERENCES invoice_payments(id) ON DELETE CASCADE,
        FOREIGN KEY(invoice_item_id) REFERENCES invoice_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB');
}
function invoice_payment_details(PDO $pdo, int $id, ?int $excludePaymentId=null): array {
    $q=$pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY line_no');$q->execute([$id]);
    $items=$q->fetchAll();$indices=[];
    foreach($items as $k=>&$item){$indices[$item['id']]=$k;$item['paid_amount']=0;}unset($item);
    $q=$pdo->prepare('SELECT p.*, COALESCE((SELECT SUM(r.amount) FROM payment_refunds r WHERE r.payment_id=p.id),0) AS refunded_amount FROM invoice_payments p WHERE invoice_id=? ORDER BY payment_date,id');$q->execute([$id]);$payments=$q->fetchAll();
    if($excludePaymentId!==null)$payments=array_values(array_filter($payments,fn($p)=>(int)$p['id']!==$excludePaymentId));
    $q=$pdo->prepare('SELECT a.* FROM invoice_payment_allocations a JOIN invoice_payments p ON p.id=a.payment_id WHERE p.invoice_id=?');$q->execute([$id]);$allocations=[];
    foreach($q->fetchAll() as $a)$allocations[$a['payment_id']][]=$a;
    $paid=0;
    foreach($payments as &$payment){
        $net=round(max(0,(float)$payment['amount']-(float)$payment['refunded_amount']),2);$paid+=$net;$payment['products']=[];
        $parts=$allocations[$payment['id']]??[];$payment['credited_items']=[];
        if($parts){
            $remaining=(int)round($net*100);
            foreach($parts as $n=>$part){
                if(!isset($indices[$part['invoice_item_id']]))continue;
                $k=$indices[$part['invoice_item_id']];
                $cents=$n===count($parts)-1?$remaining:min($remaining,(int)round((float)$part['amount']/(float)$payment['amount']*$net*100));$remaining-=$cents;
                $items[$k]['paid_amount']+=$cents/100;
                $payment['credited_items'][]=['id'=>$items[$k]['id'],'amount'=>$cents/100];
                $payment['products'][]=$items[$k]['product_name'].' ('.number_format($cents/100,2,'.','').')';
            }
        }else{
            $remaining=(int)round($net*100);
            foreach($items as &$line){
                $cents=min($remaining,max(0,(int)round(((float)$line['line_total']-$line['paid_amount'])*100)));
                if($cents>0){$line['paid_amount']+=$cents/100;$remaining-=$cents;$payment['products'][]=$line['product_name'].' ('.number_format($cents/100,2,'.','').')';$payment['credited_items'][]=['id'=>$line['id'],'amount'=>$cents/100];}
            }unset($line);
        }
        $payment['products']=implode('; ',$payment['products']);
    }unset($payment);
    foreach($items as &$line){$line['paid_amount']=round($line['paid_amount'],2);$line['balance_due']=round(max(0,(float)$line['line_total']-$line['paid_amount']),2);$line['payment_status']=$line['balance_due']<0.005?'Paid':($line['paid_amount']>0?'Part paid':'Unpaid');}unset($line);
    return ['items'=>$items,'payments'=>$payments,'paid_total'=>round($paid,2)];
}
function store_payment_allocation(PDO $pdo, int $paymentId, array $items, float $amount, ?int $itemId=null): void {
    $remaining=(int)round($amount*100);$parts=[];
    foreach($items as $line){
        if($itemId!==null && (int)$line['id']!==$itemId)continue;
        $cents=min($remaining,max(0,(int)round($line['balance_due']*100)));
        if($cents>0){$parts[]=[$paymentId,$line['id'],$cents/100];$remaining-=$cents;}
    }
    if($remaining>0)throw new InvalidArgumentException('Payment exceeds the selected product balance.');
    $q=$pdo->prepare('INSERT INTO invoice_payment_allocations(payment_id,invoice_item_id,amount) VALUES(?,?,?)');
    foreach($parts as $part)$q->execute($part);
}
