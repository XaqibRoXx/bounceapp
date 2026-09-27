<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if(!installed()){header('Location: install.php');exit;}
$pdo=db();
$page=trim((string)($_GET['page']??'home'));
$statuses=['open','in_progress','need_clarification','done','approved','reopened'];

function page_row(int $id):?array{
    $s=db()->prepare('SELECT pg.*,p.name project_name,p.base_url,p.share_token,p.id project_id FROM pages pg JOIN projects p ON p.id=pg.project_id WHERE pg.id=? LIMIT 1');
    $s->execute([$id]);
    return $s->fetch()?:null;
}
function latest_capture(int $pageId, ?int $captureId=null):?array{
    if($captureId){$s=db()->prepare('SELECT * FROM captures WHERE id=? AND page_id=? LIMIT 1');$s->execute([$captureId,$pageId]);return $s->fetch()?:null;}
    $s=db()->prepare('SELECT * FROM captures WHERE page_id=? ORDER BY id DESC LIMIT 1');$s->execute([$pageId]);return $s->fetch()?:null;
}
function capture_annotations(int $captureId):array{
    $s=db()->prepare('SELECT a.*,(SELECT COUNT(*) FROM comments c WHERE c.annotation_id=a.id) comment_count FROM annotations a WHERE a.capture_id=? ORDER BY a.number');
    $s->execute([$captureId]);
    return $s->fetchAll();
}
function public_project(string $token):?array{
    if($token==='')return null;
    $s=db()->prepare('SELECT * FROM projects WHERE share_token=? AND status="active" LIMIT 1');
    $s->execute([$token]);
    return $s->fetch()?:null;
}
function require_public_project(string $token):array{
    $p=public_project($token);
    if(!$p){http_response_code(404);exit('Project link not found.');}
    return $p;
}
function annotation_context(int $id):?array{
    $s=db()->prepare('SELECT a.*,c.page_id,pg.project_id,pg.title page_title,pg.url page_url,p.name project_name,p.share_token,c.file_path capture_path FROM annotations a JOIN captures c ON c.id=a.capture_id JOIN pages pg ON pg.id=c.page_id JOIN projects p ON p.id=pg.project_id WHERE a.id=? LIMIT 1');
    $s->execute([$id]);
    return $s->fetch()?:null;
}
function token_from_post():string{return post('token',post('share_token',''));}
function assert_page_in_project(int $pageId,array $p):array{
    $pg=page_row($pageId);
    if(!$pg||(int)$pg['project_id']!==(int)$p['id']){http_response_code(404);exit('Page not found.');}
    return $pg;
}
function capture_and_store(array $pg,array $p):array{
    $result=capture_smart((string)$pg['url']);
    if(!$result['ok'])return $result;
    $s=db()->prepare('INSERT INTO captures(page_id,file_path,width,height,source,created_by) VALUES(?,?,?,?,?,NULL)');
    $s->execute([(int)$pg['id'],$result['path'],$result['width'],$result['height'],$result['source']]);
    $captureId=(int)db()->lastInsertId();
    log_activity((int)$p['id'],null,'captured page','capture',$captureId,['page'=>$pg['url'],'source'=>$result['source']]);
    $result['capture_id']=$captureId;
    return $result;
}

if(is_post()){
    verify_csrf();
    $action=post('action');

    if($action==='create-public-project'){
        $url=normalize_public_url(post('base_url'));
        if(!$url||!valid_public_url($url)){
            flash('error','Enter a valid public website URL, for example example.com.');
            go();
        }
        $host=(string)parse_url($url,PHP_URL_HOST);
        $name=trim(post('name'));
        if($name===''){$name=ucwords(str_replace(['www.','-','_','.'],['','',' ',' '],$host));}
        $ownerId=public_owner_id();
        $token=random_token(30);
        $s=$pdo->prepare('INSERT INTO projects(owner_id,name,base_url,share_token,share_password_hash,status) VALUES(?,?,?,?,NULL,"active")');
        $s->execute([$ownerId,mb_substr($name,0,160),rtrim($url,'/'),$token]);
        $projectId=(int)$pdo->lastInsertId();

        // Exact-page mode: the URL entered by the user is the only page created automatically.
        // BounceApp does not discover sitemap/homepage links or scan the rest of the website.
        $pageUrl=$url;
        $path=parse_url($pageUrl,PHP_URL_PATH)?:'/';
        $title=$path==='/'?'Home':trim(str_replace(['-','_','/'],' ',basename(rtrim($path,'/'))));
        $title=$title!==''?ucwords($title):'Page';
        $insert=$pdo->prepare('INSERT INTO pages(project_id,title,url) VALUES(?,?,?)');
        $insert->execute([$projectId,mb_substr($title,0,190),$pageUrl]);
        $pageId=(int)$pdo->lastInsertId();

        log_activity($projectId,null,'created exact-page public project','project',$projectId,['url'=>$pageUrl]);
        if($pageId){
            $pg=page_row($pageId);
            if($pg){$cap=capture_and_store($pg,['id'=>$projectId]);if(!$cap['ok'])flash('error','Page saved, but automatic screenshot failed: '.$cap['error']);}
        }
        $_SESSION['recent_public_projects'][$token]=['name'=>$name,'created_at'=>time()];
        if(count($_SESSION['recent_public_projects'])>8)$_SESSION['recent_public_projects']=array_slice($_SESSION['recent_public_projects'],-8,8,true);
        go(['page'=>'workspace','token'=>$token]);
    }

    if(in_array($action,['add-page','capture-page','upload-capture','create-annotation','update-status','comment','review-status'],true)){
        $token=token_from_post();
        $p=require_public_project($token);

        if($action==='add-page'){
            $url=normalize_public_url(post('url'));
            $title=post('title');
            if(!$url||!valid_public_url($url)||!same_host($url,(string)$p['base_url'])){
                flash('error','The page must be a public URL on this website.');
                go(['page'=>'workspace','token'=>$token]);
            }
            if($title===''){$path=parse_url($url,PHP_URL_PATH)?:'/';$title=$path==='/'?'Home':ucwords(trim(str_replace(['-','_','/'],' ',basename(rtrim($path,'/')))));}
            $s=$pdo->prepare('INSERT IGNORE INTO pages(project_id,title,url) VALUES(?,?,?)');$s->execute([(int)$p['id'],mb_substr($title,0,190),$url]);
            flash($s->rowCount()?'success':'error',$s->rowCount()?'Page added.':'That page is already saved.');
            go(['page'=>'workspace','token'=>$token]);
        }

        if($action==='capture-page'){
            $pageId=(int)post('page_id');
            $pg=assert_page_in_project($pageId,$p);
            $result=capture_and_store($pg,$p);
            if(!$result['ok'])flash('error',$result['error']);else flash('success','Fresh screenshot captured automatically.');
            go(['page'=>'visual','token'=>$token,'id'=>$pageId]);
        }

        if($action==='upload-capture'){
            $pageId=(int)post('page_id');
            $pg=assert_page_in_project($pageId,$p);
            $result=save_image_upload('screenshot');
            if(!$result['ok']){flash('error',$result['error']);go(['page'=>'visual','token'=>$token,'id'=>$pageId]);}
            $s=$pdo->prepare('INSERT INTO captures(page_id,file_path,width,height,source,created_by) VALUES(?,?,?,?,"upload",NULL)');
            $s->execute([$pageId,$result['path'],$result['width'],$result['height']]);
            log_activity((int)$p['id'],null,'uploaded screenshot','capture',(int)$pdo->lastInsertId());
            flash('success','Screenshot uploaded.');
            go(['page'=>'visual','token'=>$token,'id'=>$pageId]);
        }

        if($action==='create-annotation'){
            $captureId=(int)post('capture_id');
            $s=$pdo->prepare('SELECT c.*,pg.project_id,pg.id page_id FROM captures c JOIN pages pg ON pg.id=c.page_id WHERE c.id=? LIMIT 1');
            $s->execute([$captureId]);$cap=$s->fetch();
            if(!$cap||(int)$cap['project_id']!==(int)$p['id'])exit('Capture not found.');
            $title=post('title');
            if($title===''){flash('error','Add a short change-request title.');go(['page'=>'visual','token'=>$token,'id'=>$cap['page_id']]);}
            $next=$pdo->prepare('SELECT COALESCE(MAX(number),0)+1 FROM annotations WHERE capture_id=?');$next->execute([$captureId]);$num=(int)$next->fetchColumn();
            $vals=[];foreach(['x_pct','y_pct','w_pct','h_pct'] as $k){$vals[$k]=max(0,min(100,(float)post($k,'0')));}
            $s=$pdo->prepare('INSERT INTO annotations(capture_id,created_by,number,x_pct,y_pct,w_pct,h_pct,title,description,requested_text,target_url) VALUES(?,NULL,?,?,?,?,?,?,?,?,?)');
            $s->execute([$captureId,$num,$vals['x_pct'],$vals['y_pct'],$vals['w_pct'],$vals['h_pct'],$title,post('description'),post('requested_text'),post('target_url')]);
            $annId=(int)$pdo->lastInsertId();
            try{$att=save_attachment('attachment',$annId);if($att){$a=$pdo->prepare('INSERT INTO attachments(annotation_id,original_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?)');$a->execute([$annId,$att['original_name'],$att['file_path'],$att['mime_type'],$att['file_size']]);}}catch(Throwable $ex){flash('error',$ex->getMessage());}
            log_activity((int)$p['id'],null,'created change request','annotation',$annId,['number'=>$num,'title'=>$title]);
            flash('success','Change request #'.$num.' created.');
            go(['page'=>'issue','token'=>$token,'id'=>$annId]);
        }

        if($action==='update-status'||$action==='review-status'){
            $id=(int)post('annotation_id');$ctx=annotation_context($id);
            if(!$ctx||(int)$ctx['project_id']!==(int)$p['id'])exit('Issue not found.');
            $status=post('status');
            $allowed=$action==='review-status'?['approved','reopened']:$statuses;
            if(!in_array($status,$allowed,true))exit('Invalid status.');
            $s=$pdo->prepare('UPDATE annotations SET status=? WHERE id=?');$s->execute([$status,$id]);
            log_activity((int)$p['id'],null,'changed status','annotation',$id,['status'=>$status]);
            go(['page'=>'issue','token'=>$token,'id'=>$id]);
        }

        if($action==='comment'){
            $id=(int)post('annotation_id');$ctx=annotation_context($id);
            if(!$ctx||(int)$ctx['project_id']!==(int)$p['id'])exit('Issue not found.');
            $body=post('body');$guest=mb_substr(post('guest_name','Reviewer'),0,120);
            if($body===''){flash('error','Comment cannot be empty.');go(['page'=>'issue','token'=>$token,'id'=>$id]);}
            $parent=(int)post('parent_id','0');
            $s=$pdo->prepare('INSERT INTO comments(annotation_id,user_id,guest_name,body,parent_id) VALUES(?,NULL,?,?,?)');
            $s->execute([$id,$guest?:'Reviewer',$body,$parent?:null]);
            log_activity((int)$p['id'],null,'commented','annotation',$id,['name'=>$guest]);
            go(['page'=>'issue','token'=>$token,'id'=>$id]);
        }
    }
}

if($page==='home'){
    layout_start('Scan a website');?>
    <section class="hero public-hero"><div class="container hero-grid"><div><span class="eyebrow">No account. Paste one exact page URL.</span><h1>Scan it. Mark it.<br><span>Share the link.</span></h1><p>Paste any public page URL. BounceApp saves and captures only that exact URL — it does not crawl or scan the rest of the website — then gives you one public workspace link to share.</p><form method="post" class="scan-card"><?=csrf_field()?><input type="hidden" name="action" value="create-public-project"><label><span>Website URL</span><input name="base_url" placeholder="example.com" autocomplete="url" required></label><button class="btn">Scan website →</button></form><div class="trust"><span>✓ No login or signup</span><span>✓ Saved in database</span><span>✓ Public share link</span><span>✓ Visual annotations</span></div></div><div class="mock"><div class="browserbar"><i></i><i></i><i></i><span>yourwebsite.com</span></div><div class="mockpage"><div class="mockhero"></div><div class="annotation-demo"><b>3</b><span>Change this section</span></div><div class="mockcards"><i></i><i></i><i></i></div></div></div></div></section>
    <section class="container section"><div class="feature-grid"><article><b>01</b><h3>Paste the exact URL</h3><p>Bounce saves and captures only the page URL you enter. No full-site crawl.</p></article><article><b>02</b><h3>Draw on the screenshot</h3><p>Drag over the exact area, then add the requested change, replacement text or reference.</p></article><article><b>03</b><h3>Send one link</h3><p>Anyone with the unique workspace link can open it, comment and update work without an account.</p></article></div></section>
    <?php layout_end();exit;
}

if($page==='workspace'){
    $token=(string)($_GET['token']??'');$p=require_public_project($token);
    $s=$pdo->prepare('SELECT pg.*,(SELECT file_path FROM captures c WHERE c.page_id=pg.id ORDER BY c.id DESC LIMIT 1) capture_path,(SELECT COUNT(*) FROM captures c WHERE c.page_id=pg.id) capture_count,(SELECT COUNT(*) FROM annotations a JOIN captures c2 ON c2.id=a.capture_id WHERE c2.page_id=pg.id) issue_count,(SELECT COUNT(*) FROM annotations a JOIN captures c3 ON c3.id=a.capture_id WHERE c3.page_id=pg.id AND a.status IN ("approved")) approved_count FROM pages pg WHERE pg.project_id=? ORDER BY pg.id');
    $s->execute([$p['id']]);$pages=$s->fetchAll();
    $stats=$pdo->prepare('SELECT COUNT(DISTINCT pg.id) pages,COUNT(DISTINCT a.id) issues,SUM(a.status="done") done_count,SUM(a.status="approved") approved_count FROM pages pg LEFT JOIN captures c ON c.page_id=pg.id LEFT JOIN annotations a ON a.capture_id=c.id WHERE pg.project_id=?');$stats->execute([$p['id']]);$st=$stats->fetch()?:[];
    $share=app_url(['page'=>'workspace','token'=>$token]);
    layout_start($p['name'],true);?>
    <section class="container section"><div class="sectionhead"><div><span class="eyebrow">Public workspace · no login required</span><h1><?=e($p['name'])?></h1><p><?=e($p['base_url'])?></p></div><div class="actions"><button class="btn btn-light" type="button" data-copy="<?=e($share)?>">Copy share link</button></div></div>
    <div class="share-banner"><div><strong>Anyone with this link can use this workspace.</strong><p>No account or password is required.</p></div><code><?=e($share)?></code></div>
    <div class="stats"><div><small>Pages</small><strong><?=e($st['pages']??count($pages))?></strong></div><div><small>Requests</small><strong><?=e($st['issues']??0)?></strong></div><div><small>Done</small><strong><?=e($st['done_count']??0)?></strong></div><div><small>Approved</small><strong><?=e($st['approved_count']??0)?></strong></div></div>
    <div class="sectionhead compact"><div><h2>Workspace pages</h2><p>The pasted URL is the only page added automatically. Add another page manually only when you need it.</p></div><button class="btn btn-light" type="button" data-toggle="#addPage">+ Add page</button></div>
    <div id="addPage" class="panel" hidden><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="add-page"><input type="hidden" name="token" value="<?=e($token)?>"><div class="two"><label>Page URL<input name="url" placeholder="<?=e(rtrim($p['base_url'],'/').'/about')?>" required></label><label>Title <span class="muted">optional</span><input name="title" placeholder="About"></label></div><button class="btn btn-small">Add page</button></form></div>
    <?php if(!$pages):?><div class="empty"><h3>No page found.</h3><p>Add a page manually to continue.</p></div><?php else:?><div class="page-list"><?php foreach($pages as $pg):?><a class="page-row" href="<?=e(app_url(['page'=>'visual','token'=>$token,'id'=>$pg['id']]))?>"><div class="thumb"><?php if($pg['capture_path']):?><img src="<?=e(public_file($pg['capture_path']))?>" alt="Page capture"><?php else:?><span>No capture yet</span><?php endif;?></div><div class="page-meta"><h3><?=e($pg['title']?:'Page')?></h3><p><?=e($pg['url'])?></p><span><?=$pg['capture_count']?> captures · <?=$pg['issue_count']?> requests · <?=$pg['approved_count']?> approved</span></div><span class="arrow">→</span></a><?php endforeach;?></div><?php endif;?></section>
    <?php layout_end();exit;
}

if($page==='visual'){
    $token=(string)($_GET['token']??'');$p=require_public_project($token);$pageId=(int)($_GET['id']??0);$pg=assert_page_in_project($pageId,$p);$selected=(int)($_GET['capture_id']??0);$cap=latest_capture($pageId,$selected?:null);$anns=$cap?capture_annotations((int)$cap['id']):[];
    $history=$pdo->prepare('SELECT * FROM captures WHERE page_id=? ORDER BY id DESC');$history->execute([$pageId]);$captures=$history->fetchAll();
    layout_start($pg['title']?:'Page',true);?>
    <section class="container wide section"><a class="back" href="<?=e(app_url(['page'=>'workspace','token'=>$token]))?>">← Workspace</a><div class="sectionhead"><div><span class="eyebrow">Visual feedback</span><h1><?=e($pg['title']?:'Page')?></h1><p><?=e($pg['url'])?></p></div><div class="actions"><a class="btn btn-light" href="<?=e($pg['url'])?>" target="_blank" rel="noopener">Open website ↗</a><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="capture-page"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="page_id" value="<?=$pageId?>"><button class="btn">↻ Recapture</button></form></div></div>
    <?php if(!$cap):?><div class="empty"><h2>No screenshot yet</h2><p>Automatic capture can be retried. If both screenshot providers are unavailable, upload a screenshot below.</p><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="capture-page"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="page_id" value="<?=$pageId?>"><button class="btn">Capture automatically</button></form><form method="post" enctype="multipart/form-data" class="upload-inline"><?=csrf_field()?><input type="hidden" name="action" value="upload-capture"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="page_id" value="<?=$pageId?>"><input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" required><button class="btn btn-light">Upload screenshot</button></form></div><?php else:?>
    <div class="capture-toolbar"><div><strong>Capture #<?=$cap['id']?></strong><span><?=e(ucfirst($cap['source']))?> · <?=date('M j, Y · g:i A',strtotime($cap['created_at']))?></span></div><?php if(count($captures)>1):?><div class="capture-history"><span>History:</span><?php foreach($captures as $c):?><a class="<?=$c['id']===$cap['id']?'active':''?>" href="<?=e(app_url(['page'=>'visual','token'=>$token,'id'=>$pageId,'capture_id'=>$c['id']]))?>">#<?=$c['id']?></a><?php endforeach;?></div><?php endif;?></div>
    <div class="annotate-layout"><div><div class="capture-stage" id="captureStage" data-editable="1"><img src="<?=e(public_file($cap['file_path']))?>" alt="Website screenshot"><?php foreach($anns as $a):?><a class="ann-box status-<?=e($a['status'])?>" href="<?=e(app_url(['page'=>'issue','token'=>$token,'id'=>$a['id']]))?>" style="left:<?=$a['x_pct']?>%;top:<?=$a['y_pct']?>%;width:<?=$a['w_pct']?>%;height:<?=$a['h_pct']?>%"><b><?=$a['number']?></b></a><?php endforeach;?><span id="draftBox" class="ann-box draft" hidden><b>+</b></span></div><p class="canvas-help">Drag over any area of the screenshot to create a numbered change request.</p></div><aside class="issue-sidebar"><span class="eyebrow">Change requests</span><?php if(!$anns):?><p class="muted">No requests yet. Draw on the screenshot to add one.</p><?php else:?><div class="issue-list"><?php foreach($anns as $a):?><a href="<?=e(app_url(['page'=>'issue','token'=>$token,'id'=>$a['id']]))?>"><span class="issue-num">#<?=$a['number']?></span><div><strong><?=e($a['title'])?></strong><p><?=e(status_label($a['status']))?> · <?=$a['comment_count']?> comments</p></div></a><?php endforeach;?></div><?php endif;?></aside></div>
    <dialog id="annotationDialog"><form method="post" enctype="multipart/form-data" class="dialog-card form-grid"><?=csrf_field()?><input type="hidden" name="action" value="create-annotation"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="capture_id" value="<?=$cap['id']?>"><input type="hidden" name="x_pct" id="xPct"><input type="hidden" name="y_pct" id="yPct"><input type="hidden" name="w_pct" id="wPct"><input type="hidden" name="h_pct" id="hPct"><div class="dialog-head"><div><span class="eyebrow">New change request</span><h2>What should change here?</h2></div><button class="iconbtn" type="button" data-close-dialog>×</button></div><label>Short title<input name="title" placeholder="Make this heading smaller" required autofocus></label><label>Details<textarea name="description" rows="4" placeholder="Explain exactly what should change"></textarea></label><label>Replacement text <span class="muted">optional</span><textarea name="requested_text" rows="3" placeholder="Paste the exact replacement copy"></textarea></label><label>Target URL <span class="muted">optional</span><input name="target_url" placeholder="https://..."></label><label>Reference attachment <span class="muted">optional</span><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></label><div class="actions"><button class="btn">Create request</button><button class="btn btn-light" type="button" data-close-dialog>Cancel</button></div></form></dialog>
    <?php endif;?></section>
    <?php layout_end();exit;
}

if($page==='issue'){
    $token=(string)($_GET['token']??'');$p=require_public_project($token);$id=(int)($_GET['id']??0);$ctx=annotation_context($id);if(!$ctx||(int)$ctx['project_id']!==(int)$p['id']){http_response_code(404);exit('Issue not found.');}
    $comments=$pdo->prepare('SELECT * FROM comments WHERE annotation_id=? ORDER BY id');$comments->execute([$id]);$comments=$comments->fetchAll();
    $atts=$pdo->prepare('SELECT * FROM attachments WHERE annotation_id=? ORDER BY id');$atts->execute([$id]);$atts=$atts->fetchAll();
    layout_start('Request #'.$ctx['number'],true);?>
    <section class="container section issue-detail"><a class="back" href="<?=e(app_url(['page'=>'visual','token'=>$token,'id'=>$ctx['page_id']]))?>">← <?=e($ctx['page_title']?:'Page')?></a><div class="issue-head"><div class="issue-title-line"><span class="issue-num big">#<?=$ctx['number']?></span><div><span class="status status-<?=e($ctx['status'])?>"><?=e(status_label($ctx['status']))?></span><h1><?=e($ctx['title'])?></h1><p><?=e($ctx['page_url'])?></p></div></div><div class="review-actions"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="review-status"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="annotation_id" value="<?=$id?>"><input type="hidden" name="status" value="approved"><button class="btn">Approve</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="review-status"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="annotation_id" value="<?=$id?>"><input type="hidden" name="status" value="reopened"><button class="btn btn-light">Reopen</button></form></div></div>
    <div class="issue-grid"><div><div class="issue-crop"><img src="<?=e(public_file($ctx['capture_path']))?>" alt="Screenshot"><span class="ann-box focus" style="left:<?=$ctx['x_pct']?>%;top:<?=$ctx['y_pct']?>%;width:<?=$ctx['w_pct']?>%;height:<?=$ctx['h_pct']?>%"><b><?=$ctx['number']?></b></span></div><div class="panel"><h3>Requested change</h3><p><?=nl2br(e($ctx['description']?:'No additional description.'))?></p><?php if($ctx['requested_text']):?><div class="spec"><small>Replacement text</small><pre><?=e($ctx['requested_text'])?></pre></div><?php endif;?><?php if($ctx['target_url']):?><p><strong>Target URL:</strong> <a href="<?=e($ctx['target_url'])?>" target="_blank" rel="noopener"><?=e($ctx['target_url'])?></a></p><?php endif;?><?php if($atts):?><div class="attachment-list"><?php foreach($atts as $a):?><a href="<?=e(public_file($a['file_path']))?>" target="_blank" rel="noopener"><?=e($a['original_name'])?></a><?php endforeach;?></div><?php endif;?></div></div>
    <aside><div class="panel"><h3>Status</h3><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="update-status"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="annotation_id" value="<?=$id?>"><select name="status"><?php foreach($statuses as $st):?><option value="<?=$st?>" <?=$ctx['status']===$st?'selected':''?>><?=e(status_label($st))?></option><?php endforeach;?></select><button class="btn btn-small">Update status</button></form></div><div class="panel"><h3>Discussion</h3><div class="comments"><?php foreach($comments as $c):?><div class="comment <?=$c['parent_id']?'reply':''?>"><div class="avatar"><?=e(strtoupper(substr($c['guest_name']?:'R',0,1)))?></div><div><strong><?=e($c['guest_name']?:'Reviewer')?></strong><small><?=date('M j · g:i A',strtotime($c['created_at']))?></small><p><?=nl2br(e($c['body']))?></p><button type="button" class="textbtn" data-reply="<?=$c['id']?>" data-reply-name="<?=e($c['guest_name']?:'Reviewer')?>">Reply</button></div></div><?php endforeach;?></div><form method="post" class="comment-form"><?=csrf_field()?><input type="hidden" name="action" value="comment"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="annotation_id" value="<?=$id?>"><input type="hidden" name="parent_id" value="0" data-parent-id><input name="guest_name" placeholder="Your name" required><textarea name="body" rows="3" placeholder="Add a comment…" data-comment-box required></textarea><button class="btn btn-small">Comment</button></form></div></aside></div></section>
    <?php layout_end();exit;
}

http_response_code(404);layout_start('Not found');?><section class="container narrow section"><div class="empty"><h1>Page not found</h1><a class="btn" href="<?=e(app_url())?>">Start a new scan</a></div></section><?php layout_end();
