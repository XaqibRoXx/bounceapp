<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
date_default_timezone_set((string)($config['timezone'] ?? 'Asia/Karachi'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
}

function config(?string $key = null, mixed $default = null): mixed {
    global $config;
    if ($key === null) return $config;
    $v = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}
function installed(): bool { return is_file(__DIR__ . '/storage/installed.lock'); }
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if ((string)config('db.name') === '') throw new RuntimeException('Database is not configured. Run install.php first.');
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', config('db.host'), config('db.port'), config('db.name'), config('db.charset'));
    $pdo = new PDO($dsn, (string)config('db.user'), (string)config('db.pass'), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function app_url(array $params=[]): string {
    $base = (string)(config('app_url') ?: '');
    $q = http_build_query(array_filter($params, fn($v)=>$v!==null));
    return $base . '/index.php' . ($q ? '?' . $q : '');
}
function asset(string $path): string { return (string)(config('app_url') ?: '') . '/assets/' . ltrim($path, '/'); }
function go(array $params=[]): never { header('Location: ' . app_url($params)); exit; }
function flash(string $type,string $msg): void { $_SESSION['flash'][]=['type'=>$type,'message'=>$msg]; }
function flashes(): array { $v=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $v; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { $t=(string)($_POST['_token']??''); if($t===''||!hash_equals(csrf_token(),$t)){http_response_code(419);exit('Session expired. Please go back and try again.');} }
function post(string $key,string $default=''): string { return trim((string)($_POST[$key]??$default)); }
function is_post(): bool { return strtoupper($_SERVER['REQUEST_METHOD']??'GET')==='POST'; }
function current_user(): ?array {
    if(empty($_SESSION['user_id'])) return null;
    $s=db()->prepare('SELECT id,name,email,role,status,created_at FROM users WHERE id=? LIMIT 1');
    $s->execute([(int)$_SESSION['user_id']]); $u=$s->fetch()?:null;
    if($u && $u['status']!=='active'){unset($_SESSION['user_id']);return null;}
    return $u;
}
function require_auth(): array { $u=current_user(); if(!$u){flash('error','Please sign in to continue.');go(['page'=>'login']);} return $u; }
function require_admin(): array { $u=require_auth(); if($u['role']!=='admin'){http_response_code(403);exit('Access denied.');} return $u; }
function setting(string $key,string $default=''): string {
    try { $s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v; }
    catch(Throwable){ return $default; }
}
function money(float $v): string { return setting('currency_symbol','$').number_format($v,2); }
function booking_code(): string { return 'BNC-'.strtoupper(bin2hex(random_bytes(3))).'-'.date('ymd'); }
function booking_days(string $dropoff,string $pickup): int { $a=strtotime($dropoff);$b=strtotime($pickup);if(!$a||!$b||$b<=$a)return 0;return max(1,(int)ceil(($b-$a)/86400)); }
function layout_start(string $title,bool $admin=false): void {
    $u=current_user(); $items=flashes(); $app=setting('site_name',(string)config('app_name'));
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111111"><title><?=e($title)?> · <?=e($app)?></title><link rel="stylesheet" href="<?=e(asset('app.css'))?>"></head><body class="<?=$admin?'admin-mode':''?>"><header class="topbar"><div class="container nav-wrap"><a class="brand" href="<?=e(app_url())?>"><span class="brand-mark">B</span><span><?=e($app)?></span></a><button class="nav-toggle" data-nav-toggle aria-label="Toggle navigation">☰</button><nav class="nav" data-nav><?php if($admin):?><a href="<?=e(app_url(['page'=>'admin']))?>">Overview</a><a href="<?=e(app_url(['page'=>'admin-locations']))?>">Locations</a><a href="<?=e(app_url(['page'=>'admin-bookings']))?>">Bookings</a><a href="<?=e(app_url(['page'=>'admin-users']))?>">Users</a><a href="<?=e(app_url(['page'=>'admin-settings']))?>">Settings</a><a href="<?=e(app_url())?>">View site</a><?php else:?><a href="<?=e(app_url())?>">Find storage</a><?php if($u):?><a href="<?=e(app_url(['page'=>'dashboard']))?>">My bookings</a><?php if($u['role']==='admin'):?><a href="<?=e(app_url(['page'=>'admin']))?>">Admin</a><?php endif;?><a href="<?=e(app_url(['page'=>'logout']))?>">Sign out</a><?php else:?><a href="<?=e(app_url(['page'=>'login']))?>">Sign in</a><a class="btn btn-small" href="<?=e(app_url(['page'=>'register']))?>">Create account</a><?php endif;?><?php endif;?></nav></div></header><main><?php foreach($items as $f):?><div class="container"><div class="alert alert-<?=e($f['type'])?>"><?=e($f['message'])?></div></div><?php endforeach;?><?php
}
function layout_end(): void { ?><footer class="footer"><div class="container footer-grid"><div><strong><?=e(setting('site_name',(string)config('app_name')))?></strong><p><?=e(setting('footer_text','Simple, secure luggage storage booking.'))?></p></div><div><p>© <?=date('Y')?> <?=e(setting('site_name',(string)config('app_name')))?>. All rights reserved.</p></div></div></footer><script src="<?=e(asset('app.js'))?>"></script></body></html><?php }
