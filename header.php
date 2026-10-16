<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/security.php';
nivis_session();
try { if ($denied=nivis_access(database_connection())) { header('Location: login.php'); exit; } }
catch(Throwable $e) { error_log($e->getMessage()); http_response_code(503); exit('Application unavailable. Check database configuration.'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nivis Labs International</title>
  <link rel="stylesheet" href="./assets/css/style.css">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/ui-theme.css">
    <link rel="icon" type="image/png" href="./assets/img/logo_1.jpeg">

  
</head>

<body>