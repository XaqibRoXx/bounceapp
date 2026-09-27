<?php
declare(strict_types=1);
function bounce_env(string $path): array {
    if(!is_file($path)) return [];
    $out=[];
    foreach(file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line){
        $line=trim($line);
        if($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$k,$v]=explode('=',$line,2); $k=trim($k); $v=trim($v);
        if(strlen($v)>=2 && (($v[0]==='"' && str_ends_with($v,'"')) || ($v[0]==="'" && str_ends_with($v,"'")))) $v=substr($v,1,-1);
        $out[$k]=$v;
    }
    return $out;
}
$env=bounce_env(__DIR__.'/.env');
return [
 'app_name'=>$env['APP_NAME']??'BounceApp',
 'app_url'=>rtrim($env['APP_URL']??'','/'),
 'timezone'=>$env['APP_TIMEZONE']??'Asia/Karachi',
 'db'=>[
  'host'=>$env['DB_HOST']??'localhost','port'=>$env['DB_PORT']??'3306','name'=>$env['DB_DATABASE']??'',
  'user'=>$env['DB_USERNAME']??'','pass'=>$env['DB_PASSWORD']??'','charset'=>'utf8mb4'
 ],
 'capture'=>[
   'pagespeed_key'=>$env['PAGESPEED_API_KEY']??'',
 ]
];
