<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/security.php';
nivis_session(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf'],$_POST['csrf'] ?? '')) $error='Refresh this page and try again.';
    elseif (($_SESSION['login_attempts'] ?? 0)>=10 && time()-($_SESSION['login_time'] ?? 0)<300) $error='Too many attempts. Try again in five minutes.';
    else {
        try {
            $p=database_connection(); $q=$p->prepare('SELECT id,password_hash FROM users WHERE email=? AND is_active=1');$q->execute([trim($_POST['email'] ?? '')]);$u=$q->fetch();
            if ($u && password_verify($_POST['password'] ?? '',$u['password_hash'])) {
                session_regenerate_id(true);$_SESSION['user_id']=$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['login_attempts']=0;
                header('Location: dashboard.php');exit;
            }
            $_SESSION['login_attempts']=(time()-($_SESSION['login_time'] ?? 0)>300 ? 0 : ($_SESSION['login_attempts'] ?? 0))+1;$_SESSION['login_time']=time();$error='Email or password is incorrect.';
        } catch(Throwable $e) {error_log($e->getMessage());$error='Sign-in unavailable. Check the server configuration.';}
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nivis Labs sign in</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>
<body class="bg-light"><main class="container py-5" style="max-width:480px"><h1>Nivis Labs</h1><h2 class="h5">Sign in</h2><form method="post" class="card p-4">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8') ?>">
<label for="email">Email</label><input id="email" name="email" class="form-control mb-3" type="email" autocomplete="username" required>
<label for="password">Password</label><input id="password" name="password" class="form-control mb-3" type="password" autocomplete="current-password" required>
<p role="alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p><button class="btn btn-primary">Sign in</button></form></main></body></html>
