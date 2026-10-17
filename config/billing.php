<?php
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/payment_allocation.php';
function ensure_document_columns(PDO $pdo): void {
    foreach (['quotations','invoices','company_settings'] as $table) {
        $columns = $pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_COLUMN);
        $definitions = $table === 'company_settings' ? ['document_defaults' => 'TEXT NULL'] : ['party_snapshot' => 'LONGTEXT NULL', 'document_details' => 'TEXT NULL', 'additional_charges' => 'DECIMAL(15,2) NOT NULL DEFAULT 0', 'cancellation_reason' => 'VARCHAR(500) NULL'];
        if ($table === 'quotations') $definitions += ['source_type' => "ENUM('quotation','order') NOT NULL DEFAULT 'quotation'", 'order_number' => 'VARCHAR(40) NULL', 'deleted_at' => 'DATETIME NULL', 'advance_amount'=>'DECIMAL(15,2) NOT NULL DEFAULT 0', 'advance_date'=>'DATE NULL', 'advance_method'=>"VARCHAR(20) NOT NULL DEFAULT 'cash'"];
        foreach ($definitions as $column => $definition) if (!in_array($column,$columns,true)) {
            try { $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition"); }
            catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) != 1060) throw $e; }
        }
    }
    ensure_payment_allocations($pdo);
    ensure_deletion_columns($pdo);
}
function billing_details(array $data): array {
    $result = [];
    foreach (['terms','payment_terms','delivery_timeline','bank_details','payment_instructions','signatory'] as $key) {
        $result[$key] = trim((string)($data[$key] ?? ''));
        if (strlen($result[$key]) > 5000) respond(['error' => 'Document terms are too long.'],422);
    }
    return $result;
}
function billing_parties(PDO $pdo, int $customerId): array {
    $q=$pdo->prepare('SELECT name, mobile, email, gstin FROM customers WHERE id=?'); $q->execute([$customerId]);
    $buyer=$q->fetch() ?: [];
    $q=$pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id=? ORDER BY is_default, id'); $q->execute([$customerId]);
    foreach ($q->fetchAll() as $a) $buyer[$a['address_type'].'_address']=implode(', ',array_filter([$a['address_line1'],$a['address_line2'],$a['city'],$a['state_name'],$a['pincode']]));
    $buyer['shipping_address'] = $buyer['shipping_address'] ?? $buyer['billing_address'] ?? '';
    $company=$pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch() ?: [];
    return ['supplier'=>$company,'buyer'=>$buyer];
}
function billing_store(PDO $pdo, string $table, int $id, array $parties, array $details, float $charges=0): void {
    if (!in_array($table,['quotations','invoices'],true)) throw new InvalidArgumentException();
    $pdo->prepare("UPDATE $table SET party_snapshot=?, document_details=?, additional_charges=? WHERE id=?")
        ->execute([json_encode($parties),json_encode($details),$charges,$id]);
}
function billing_actions(PDO $pdo, string $resource, string $method, array $data): void {
    if ($method !== 'POST') return;
    if ($resource === 'quotations' && ($data['action'] ?? '') === 'status') {
        $id=filter_var($data['id'] ?? null,FILTER_VALIDATE_INT);
        $next=$data['status'] ?? ''; if ($next === 'accepted') $next='approved';
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT * FROM quotations WHERE id=? AND deleted_at IS NULL FOR UPDATE'); $q->execute([$id]); $quote=$q->fetch();
        $allowed=['draft'=>['sent','approved','rejected','expired'],'sent'=>['approved','rejected','expired']];
        if (!$quote || !in_array($next,$allowed[$quote['status']] ?? [],true)) { $pdo->rollBack(); respond(['error'=>'This status transition is not allowed.'],409); }
        if ($next==='expired' && $quote['valid_until']>=date('Y-m-d')) { $pdo->rollBack(); respond(['error'=>'The quotation validity date has not passed.'],422); }
        if (in_array($next,['sent','approved'],true) && $quote['valid_until']<date('Y-m-d')) { $pdo->rollBack(); respond(['error'=>'This quotation has expired. Create a new draft copy.'],422); }
        $pdo->prepare("UPDATE quotations SET status=?, approved_at=IF(?='approved',NOW(),approved_at) WHERE id=?")->execute([$next,$next,$id]);
        $pdo->prepare('INSERT INTO quotation_status_history(quotation_id,from_status,to_status,notes) VALUES(?,?,?,?)')->execute([$id,$quote['status'],$next,'Changed from quotation list']);
        $pdo->commit(); respond(['message'=>'Quotation status updated.']);
    }
    if ($resource === 'invoices' && ($data['action'] ?? '') === 'cancel') {
        $id=filter_var($data['invoice_id'] ?? null,FILTER_VALIDATE_INT); $reason=required_text($data,'reason',500);
        $pdo->beginTransaction(); $q=$pdo->prepare('SELECT status FROM v_active_invoices WHERE id=? FOR UPDATE');$q->execute([$id]);$status=$q->fetchColumn();
        $q=$pdo->prepare('SELECT COUNT(*) FROM invoice_payments WHERE invoice_id=?');$q->execute([$id]);
        if ($status!=='issued' || $q->fetchColumn()>0) {$pdo->rollBack();respond(['error'=>'Only issued invoices without payments can be cancelled. Paid invoices require a reviewed credit/refund process.'],409);}
        $pdo->prepare("UPDATE invoices SET status='cancelled',cancellation_reason=? WHERE id=?")->execute([$reason,$id]);
        $pdo->prepare("INSERT INTO audit_logs(entity_type,entity_id,action,after_data) VALUES('invoice',?,'cancel',?)")->execute([$id,json_encode(['reason'=>$reason])]);
        $pdo->commit();respond(['message'=>'Invoice cancelled. Its number and financial history are preserved.']);
    }
}
