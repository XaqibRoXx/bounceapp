<?php
declare(strict_types=1);
$lock=__DIR__.'/storage/installed.lock';
if(is_file($lock)){http_response_code(403);exit('BounceApp is already installed.');}
$error='';$success=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 $host=trim((string)($_POST['db_host']??'localhost'));$port=trim((string)($_POST['db_port']??'3306'));$name=trim((string)($_POST['db_name']??''));$dbuser=trim((string)($_POST['db_user']??''));$dbpass=(string)($_POST['db_pass']??'');$url=rtrim(trim((string)($_POST['app_url']??'')),'/');
 if($name===''||$dbuser===''||$url==='')$error='Enter the app URL and valid MySQL database details.';else try{
  $pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$dbuser,$dbpass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $sql=file_get_contents(__DIR__.'/database.sql');if($sql===false)throw new RuntimeException('database.sql is missing.');$pdo->exec($sql);
  $email='public@bounceapp.local';$s=$pdo->prepare('SELECT id FROM users WHERE email=?');$s->execute([$email]);if(!$s->fetch()){$s=$pdo->prepare('INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,?,?)');$s->execute(['Public Workspace',$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'user','active']);}
  $safe=fn(string $v)=>str_replace(["\r","\n",'"'],'',$v);
  $env='APP_NAME="BounceApp"'."\n".'APP_URL="'.$safe($url).'"'."\nAPP_TIMEZONE=Asia/Karachi\n".'DB_HOST="'.$safe($host).'"'."\n".'DB_PORT="'.$safe($port).'"'."\n".'DB_DATABASE="'.$safe($name).'"'."\n".'DB_USERNAME="'.$safe($dbuser).'"'."\n".'DB_PASSWORD="'.$safe($dbpass).'"'."\nPAGESPEED_API_KEY=\nSCAN_MAX_PAGES=30\n";
  if(file_put_contents(__DIR__.'/.env',$env,LOCK_EX)===false)throw new RuntimeException('Could not write .env. Check folder permissions.');
  if(!is_dir(__DIR__.'/storage'))mkdir(__DIR__.'/storage',0755,true);file_put_contents($lock,date(DATE_ATOM));$success=true;
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BounceApp Installer</title><link rel="stylesheet" href="assets/app.css"></head><body class="installer"><div class="install-card"><div class="brand"><span class="brandmark">B</span><span>BounceApp</span></div><span class="eyebrow">Production setup</span><h1>Install BounceApp</h1><p>Connect MySQL. BounceApp does not require user accounts or login.</p><?php if($error):?><div class="alert alert-error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($success):?><div class="alert alert-success">Installation complete. The installer is locked.</div><a class="btn btn-block" href="index.php">Open BounceApp</a><?php else:?><form method="post" class="form-grid"><label>App URL<input name="app_url" placeholder="https://bounce.example.com" required></label><div class="two"><label>DB host<input name="db_host" value="localhost" required></label><label>DB port<input name="db_port" value="3306" required></label></div><label>Database name<input name="db_name" required></label><label>Database user<input name="db_user" required></label><label>Database password<input type="password" name="db_pass"></label><button class="btn btn-block">Install BounceApp</button></form><?php endif;?></div></body></html>