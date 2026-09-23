<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if(!installed()){header('Location: install.php');exit;}
$page=trim((string)($_GET['page']??'home'));
$pdo=db();
require __DIR__.'/app/actions.php';
if($page==='logout'){$_SESSION=[];session_destroy();header('Location: '.app_url());exit;}
foreach(['auth','home','location','dashboard','booking','profile','admin','admin-locations','admin-bookings','admin-users','admin-settings'] as $route){require __DIR__.'/app/routes/'.$route.'.php';}
http_response_code(404);
layout_start('Not found');?>
<section class="container narrow section"><div class="empty"><h1>Page not found</h1><p>The page you requested does not exist.</p><a class="btn" href="<?=e(app_url())?>">Back home</a></div></section>
<?php layout_end();
