<?php
declare(strict_types=1);
$config=require __DIR__.'/config.php';
date_default_timezone_set((string)($config['timezone']??'Asia/Karachi'));
if(session_status()!==PHP_SESSION_ACTIVE){
 $secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
 session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
 session_start();
}
function cfg(string $key,mixed $default=null):mixed{global $config;$v=$config;foreach(explode('.',$key) as $p){if(!is_array($v)||!array_key_exists($p,$v))return $default;$v=$v[$p];}return $v;}
function installed():bool{return is_file(__DIR__.'/storage/installed.lock');}
function db():PDO{static $pdo=null;if($pdo instanceof PDO)return $pdo;$name=(string)cfg('db.name');if($name==='')throw new RuntimeException('Database not configured. Run install.php.');$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',cfg('db.host'),cfg('db.port'),$name,cfg('db.charset'));$pdo=new PDO($dsn,(string)cfg('db.user'),(string)cfg('db.pass'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $pdo;}
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function app_url(array $q=[]):string{$base=(string)cfg('app_url','');$query=http_build_query(array_filter($q,fn($v)=>$v!==null));return $base.'/index.php'.($query?'?'.$query:'');}
function asset(string $p):string{return (string)cfg('app_url','').'/assets/'.ltrim($p,'/');}
function public_file(string $p):string{return (string)cfg('app_url','').'/'.ltrim($p,'/');}
function go(array $q=[]):never{header('Location: '.app_url($q));exit;}
function flash(string $type,string $msg):void{$_SESSION['flash'][]=['type'=>$type,'message'=>$msg];}
function pull_flashes():array{$x=$_SESSION['flash']??[];unset($_SESSION['flash']);return $x;}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function csrf_field():string{return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';}
function verify_csrf():void{$t=(string)($_POST['_token']??'');if($t===''||!hash_equals(csrf_token(),$t)){http_response_code(419);exit('Session expired. Please go back and try again.');}}
function post(string $k,string $d=''):string{return trim((string)($_POST[$k]??$d));}
function is_post():bool{return strtoupper($_SERVER['REQUEST_METHOD']??'GET')==='POST';}
function user():?array{if(empty($_SESSION['user_id']))return null;$s=db()->prepare('SELECT id,name,email,role,status,created_at FROM users WHERE id=? LIMIT 1');$s->execute([(int)$_SESSION['user_id']]);$u=$s->fetch()?:null;if($u&&$u['status']!=='active'){unset($_SESSION['user_id']);return null;}return $u;}
function require_auth():array{$u=user();if(!$u){flash('error','Please sign in to continue.');go(['page'=>'login']);}return $u;}
function require_admin():array{$u=require_auth();if($u['role']!=='admin'){http_response_code(403);exit('Access denied.');}return $u;}
function random_token(int $bytes=24):string{return rtrim(strtr(base64_encode(random_bytes($bytes)),'+/','-_'),'=');}
function normalize_public_url(string $url):?string{
 $url=trim($url);if($url==='')return null;if(!preg_match('~^https?://~i',$url))$url='https://'.$url;
 $p=parse_url($url);if(!$p||empty($p['host']))return null;$scheme=strtolower((string)($p['scheme']??'https'));if(!in_array($scheme,['http','https'],true))return null;
 $host=strtolower((string)$p['host']);$port=isset($p['port'])?':'.$p['port']:'';$path=$p['path']??'';$query=isset($p['query'])?'?'.$p['query']:'';
 return $scheme.'://'.$host.$port.($path?:'').$query;
}
function public_owner_id():int{
 $email='public@bounceapp.local';$s=db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$s->execute([$email]);$id=$s->fetchColumn();if($id)return (int)$id;
 $s=db()->prepare('INSERT INTO users(name,email,password,role,status) VALUES(?,?,?,?,?)');$s->execute(['Public Workspace',$email,password_hash(random_token(32),PASSWORD_DEFAULT),'user','active']);return (int)db()->lastInsertId();
}
function ensure_public_schema():void{
 static $done=false;if($done)return;$done=true;
 try{db()->exec("ALTER TABLE captures MODIFY source ENUM('pagespeed','thumio','upload') NOT NULL DEFAULT 'upload'");}catch(Throwable){}
}
function slugify(string $s):string{$s=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$s),'-'));return $s!==''?$s:'project';}
function project_for_user(int $id,array $u):?array{$s=db()->prepare('SELECT p.* FROM projects p LEFT JOIN project_members pm ON pm.project_id=p.id AND pm.user_id=? WHERE p.id=? AND (p.owner_id=? OR pm.user_id IS NOT NULL OR ?="admin") LIMIT 1');$s->execute([(int)$u['id'],$id,(int)$u['id'],$u['role']]);return $s->fetch()?:null;}
function require_project(int $id):array{$u=require_auth();$p=project_for_user($id,$u);if(!$p){http_response_code(404);exit('Project not found.');}return $p;}
function project_role(array $p,array $u):string{if($u['role']==='admin'||(int)$p['owner_id']===(int)$u['id'])return 'owner';$s=db()->prepare('SELECT role FROM project_members WHERE project_id=? AND user_id=?');$s->execute([$p['id'],$u['id']]);return (string)($s->fetchColumn()?:'viewer');}
function can_edit_project(array $p,array $u):bool{return in_array(project_role($p,$u),['owner','developer'],true);}
function status_label(string $s):string{return ucwords(str_replace('_',' ',$s));}
function log_activity(int $projectId,?int $actorId,string $action,string $entityType,?int $entityId=null,array $meta=[]):void{$s=db()->prepare('INSERT INTO activity(project_id,actor_user_id,action,entity_type,entity_id,meta_json) VALUES(?,?,?,?,?,?)');$s->execute([$projectId,$actorId,$action,$entityType,$entityId,$meta?json_encode($meta,JSON_UNESCAPED_SLASHES):null]);}
function valid_public_url(string $url):bool{
 if(!filter_var($url,FILTER_VALIDATE_URL))return false;$p=parse_url($url);if(!$p||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host']))return false;
 $host=$p['host'];if(in_array(strtolower($host),['localhost','localhost.localdomain'],true))return false;
 if(filter_var($host,FILTER_VALIDATE_IP))$ips=[$host];else{$ips=gethostbynamel($host)?:[];if(function_exists('dns_get_record')){foreach(dns_get_record($host,DNS_A|DNS_AAAA)?:[] as $r){if(!empty($r['ip']))$ips[]=$r['ip'];if(!empty($r['ipv6']))$ips[]=$r['ipv6'];}}}
 if(!$ips)return false;foreach(array_unique($ips) as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;}return true;
}
function same_host(string $a,string $b):bool{return strtolower((string)parse_url($a,PHP_URL_HOST))===strtolower((string)parse_url($b,PHP_URL_HOST));}
function absolutize_url(string $href,string $base):?string{
 $href=trim($href);if($href===''||str_starts_with($href,'#')||preg_match('~^(mailto:|tel:|javascript:)~i',$href))return null;if(preg_match('~^https?://~i',$href))return $href;
 $p=parse_url($base);if(!$p||empty($p['scheme'])||empty($p['host']))return null;$origin=$p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:'');
 if(str_starts_with($href,'//'))return $p['scheme'].':'.$href;if(str_starts_with($href,'/'))return $origin.$href;$path=$p['path']??'/';$dir=rtrim(str_replace('\\','/',dirname($path)),'/');return $origin.($dir?'/'.ltrim($dir,'/'):'').'/'.$href;
}
function safe_fetch(string $url,int $timeout=12,int $maxBytes=1500000):?string{
 if(!valid_public_url($url)||!function_exists('curl_init'))return null;$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'BounceApp/1.0 (+visual feedback scanner)',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xml,text/xml;q=0.9,*/*;q=0.5'],CURLOPT_RANGE=>'0-'.($maxBytes-1)]);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return is_string($body)&&$code>=200&&$code<300?substr($body,0,$maxBytes):null;
}
function save_capture_bytes(string $bytes,string $source):array{
 if($bytes==='')return ['ok'=>false,'error'=>'Screenshot provider returned an empty image.'];
 $dir=__DIR__.'/public/uploads/captures';if(!is_dir($dir))mkdir($dir,0755,true);
 $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->buffer($bytes);$ext=match($mime){'image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif',default=>null};
 if(!$ext)return ['ok'=>false,'error'=>'Screenshot provider returned an unsupported response.'];
 $name='capture-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.'.$ext;$path=$dir.'/'.$name;
 if(file_put_contents($path,$bytes,LOCK_EX)===false)return ['ok'=>false,'error'=>'Could not save screenshot on the server.'];
 $size=@getimagesize($path);return ['ok'=>true,'path'=>'public/uploads/captures/'.$name,'width'=>$size[0]??0,'height'=>$size[1]??0,'source'=>$source];
}
function capture_pagespeed(string $url):array{
 if(!valid_public_url($url))return ['ok'=>false,'error'=>'The page URL must be a public http/https URL.'];
 $key=(string)cfg('capture.pagespeed_key','');if($key==='')return ['ok'=>false,'error'=>'PageSpeed API key is not configured.'];
 if(!function_exists('curl_init'))return ['ok'=>false,'error'=>'cURL is not enabled on this server.'];
 $api='https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=desktop&category=performance&url='.rawurlencode($url).'&key='.rawurlencode($key);
 $attempt=0;$raw=false;$code=0;$err='';
 do{
  if($attempt>0)usleep(450000*$attempt);
  $ch=curl_init($api);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>90,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'BounceApp/2.0']);$raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$attempt++;
 }while($code===429&&$attempt<2);
 if(!is_string($raw)||$code<200||$code>=300)return ['ok'=>false,'error'=>$err?:'PageSpeed returned HTTP '.$code];
 $json=json_decode($raw,true);$data=$json['lighthouseResult']['audits']['final-screenshot']['details']['data']??'';
 if(!is_string($data)||!str_starts_with($data,'data:image/'))return ['ok'=>false,'error'=>'PageSpeed did not return a screenshot.'];
 [, $b64]=explode(',',$data,2);$bytes=base64_decode($b64,true);if($bytes===false)return ['ok'=>false,'error'=>'Invalid PageSpeed screenshot data.'];
 return save_capture_bytes($bytes,'pagespeed');
}
function capture_thumio(string $url):array{
 if(!valid_public_url($url))return ['ok'=>false,'error'=>'The page URL must be a public http/https URL.'];
 if(!function_exists('curl_init'))return ['ok'=>false,'error'=>'cURL is not enabled on this server.'];
 $api='https://image.thum.io/get/noanimate/png/width/1200/crop/1200/?url='.rawurlencode($url);
 $ch=curl_init($api);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>75,CURLOPT_HTTPHEADER=>['Accept: image/*'],CURLOPT_USERAGENT=>'BounceApp/2.0']);$bytes=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
 if(!is_string($bytes)||$code<200||$code>=300)return ['ok'=>false,'error'=>$err?:'Fallback screenshot service returned HTTP '.$code];
 if($type!==''&&!str_starts_with(strtolower($type),'image/'))return ['ok'=>false,'error'=>'Fallback screenshot service did not return an image.'];
 return save_capture_bytes($bytes,'thumio');
}
function capture_smart(string $url):array{
 ensure_public_schema();$errors=[];
 if((string)cfg('capture.pagespeed_key','')!==''){$r=capture_pagespeed($url);if($r['ok'])return $r;$errors[]=$r['error'];}
 $r=capture_thumio($url);if($r['ok'])return $r;$errors[]=$r['error'];
 return ['ok'=>false,'error'=>'Automatic capture failed. '.implode(' · ',$errors).' Manual upload remains available as a last fallback.'];
}
function save_image_upload(string $field,string $folder='captures'):array{
 if(empty($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return ['ok'=>false,'error'=>'Choose an image file.'];$f=$_FILES[$field];if(($f['size']??0)>12*1024*1024)return ['ok'=>false,'error'=>'Image must be 12 MB or smaller.'];$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($map[$mime]))return ['ok'=>false,'error'=>'Only JPG, PNG or WEBP images are allowed.'];$dir=__DIR__.'/public/uploads/'.$folder;if(!is_dir($dir))mkdir($dir,0755,true);$name=$folder.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.'.$map[$mime];$dest=$dir.'/'.$name;if(!move_uploaded_file($f['tmp_name'],$dest))return ['ok'=>false,'error'=>'Upload failed.'];$size=@getimagesize($dest);return ['ok'=>true,'path'=>'public/uploads/'.$folder.'/'.$name,'width'=>$size[0]??0,'height'=>$size[1]??0,'source'=>'upload'];
}
function save_attachment(string $field,int $annotationId):?array{
 if(empty($_FILES[$field])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return null;$f=$_FILES[$field];if(($f['size']??0)>10*1024*1024)throw new RuntimeException('Attachment must be 10 MB or smaller.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];if(!isset($map[$mime]))throw new RuntimeException('Attachment must be JPG, PNG, WEBP or PDF.');$dir=__DIR__.'/public/uploads/attachments';if(!is_dir($dir))mkdir($dir,0755,true);$name='attachment-'.bin2hex(random_bytes(8)).'.'.$map[$mime];$dest=$dir.'/'.$name;if(!move_uploaded_file($f['tmp_name'],$dest))throw new RuntimeException('Attachment upload failed.');return ['annotation_id'=>$annotationId,'original_name'=>mb_substr((string)$f['name'],0,190),'file_path'=>'public/uploads/attachments/'.$name,'mime_type'=>$mime,'file_size'=>(int)$f['size']];
}
function layout_start(string $title,bool $workspace=false):void{$flashes=pull_flashes();?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0c0d0d"><title><?=e($title)?> · <?=e((string)cfg('app_name','BounceApp'))?></title><link rel="stylesheet" href="<?=e(asset('app.css'))?>"></head><body class="<?=$workspace?'workspace-body':''?>"><header class="topbar"><div class="container navwrap"><a class="brand" href="<?=e(app_url())?>"><span class="brandmark">B</span><span>BounceApp</span></a><button class="navtoggle" data-nav-toggle>☰</button><nav class="nav" data-nav><a href="<?=e(app_url())?>">New scan</a><span class="navuser">No login required</span></nav></div></header><main><?php foreach($flashes as $f):?><div class="container"><div class="alert alert-<?=e($f['type'])?>"><?=e($f['message'])?></div></div><?php endforeach;?><?php }
function layout_end():void{?><footer class="footer"><div class="container footerinner"><div><strong>BounceApp</strong><p>Visual website feedback without the screenshot chaos.</p></div><small>© <?=date('Y')?> BounceApp</small></div></footer></main><script src="<?=e(asset('app.js'))?>"></script></body></html><?php }
