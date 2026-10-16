<?php
if (empty($_GET['id'])) {
    include_once __DIR__.'/header.php';
    include __DIR__.'/sidebar.php';
    $simpleMode = 'invoice';
    include __DIR__.'/simple-quotation.php';
    include __DIR__.'/footer.php';
    return;
}
$directInvoice = true;
require __DIR__ . '/create-quotation.php';
