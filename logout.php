<?php
require_once __DIR__.'/config/security.php';
nivis_session();
if ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($_SESSION['csrf'],$_POST['csrf'] ?? '')) {http_response_code(403);exit('Invalid sign-out request.');}
$_SESSION=[];session_destroy();header('Location: dashboard.php');exit;
