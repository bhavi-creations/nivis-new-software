<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../config/database.php';
echo "Admin name: ";$name=trim(fgets(STDIN));echo "Admin email: ";$email=trim(fgets(STDIN));echo "Password (input is visible in this console; use 12+ characters): ";$password=trim(fgets(STDIN));
if(!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12){fwrite(STDERR,"Invalid name, email or password.\n");exit(1);}
$q=database_connection()->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,'admin')");
$q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);echo "Account created. Sign in at login.php.\n";
