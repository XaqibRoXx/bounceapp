<?php
declare(strict_types=1);
$lock=__DIR__.'/storage/installed.lock';
if(is_file($lock)){http_response_code(403);exit('BounceApp is already installed.');}
$error='';$success=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  $host=trim((string)($_POST['db_host']??'localhost'));$port=trim((string)($_POST['db_port']??'3306'));
  $name=trim((string)($_POST['db_name']??''));$user=trim((string)($_POST['db_user']??''));$pass=(string)($_POST['db_pass']??'');
  $url=rtrim(trim((string)($_POST['app_url']??'')),'/');$site=trim((string)($_POST['site_name']??'BounceApp'));
  $adminName=trim((string)($_POST['admin_name']??'Administrator'));$adminEmail=strtolower(trim((string)($_POST['admin_email']??'')));$adminPass=(string)($_POST['admin_password']??'');
  if($name===''||$user===''||!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||strlen($adminPass)<10){$error='Enter valid database details, admin email, and a password of at least 10 characters.';}
  else{
    try{
      $pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
      $sql=file_get_contents(__DIR__.'/database.sql');if($sql===false)throw new RuntimeException('database.sql is missing.');$pdo->exec($sql);
      $stmt=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$stmt->execute([$adminEmail]);
      if(!$stmt->fetch()){$stmt=$pdo->prepare('INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,?,?)');$stmt->execute([$adminName,$adminEmail,password_hash($adminPass,PASSWORD_DEFAULT),'admin','active']);}
      $stmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
      foreach(['site_name'=>$site,'currency_symbol'=>'$','support_email'=>$adminEmail,'footer_text'=>'Simple, secure luggage storage booking.'] as $k=>$v)$stmt->execute([$k,$v]);
      $safe=fn(string $v)=>str_replace(["\r","\n",'"'],'',$v);
      $env='APP_NAME="'.$safe($site)."\"\nAPP_URL=\"".$safe($url)."\"\nAPP_ENV=production\nAPP_DEBUG=false\nAPP_TIMEZONE=Asia/Karachi\nDB_HOST=\"".$safe($host)."\"\nDB_PORT=\"".$safe($port)."\"\nDB_DATABASE=\"".$safe($name)."\"\nDB_USERNAME=\"".$safe($user)."\"\nDB_PASSWORD=\"".$safe($pass)."\"\n";
      if(file_put_contents(__DIR__.'/.env',$env,LOCK_EX)===false)throw new RuntimeException('Could not write .env. Check folder permissions.');
      if(!is_dir(__DIR__.'/storage'))mkdir(__DIR__.'/storage',0755,true);file_put_contents($lock,date(DATE_ATOM));$success=true;
    }catch(Throwable $e){$error=$e->getMessage();}
  }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BounceApp Installer</title><link rel="stylesheet" href="assets/app.css"></head><body class="installer"><div class="install-card"><div class="brand brand-center"><span class="brand-mark">B</span><span>BounceApp</span></div><span class="eyebrow">Production setup</span><h1>Server installer</h1><p>Connect MySQL and create the first administrator account.</p><?php if($error):?><div class="alert alert-error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($success):?><div class="alert alert-success">Installation complete. The installer is now locked.</div><a class="btn btn-block" href="index.php?page=login">Open BounceApp</a><?php else:?><form method="post" class="form-grid"><label>Site name<input name="site_name" value="BounceApp" required></label><label>App URL<input name="app_url" placeholder="https://example.com" required></label><div class="two"><label>DB host<input name="db_host" value="localhost" required></label><label>DB port<input name="db_port" value="3306" required></label></div><label>Database name<input name="db_name" required></label><label>Database user<input name="db_user" required></label><label>Database password<input name="db_pass" type="password"></label><hr><label>Admin name<input name="admin_name" value="Administrator" required></label><label>Admin email<input name="admin_email" type="email" required></label><label>Admin password<input name="admin_password" type="password" minlength="10" required></label><button class="btn btn-block" type="submit">Install BounceApp</button></form><?php endif;?></div></body></html>
