<?php
if($page==='profile'){
  $u=require_auth();layout_start('Profile');?><section class="container narrow section"><a class="back" href="<?=e(app_url(['page'=>'dashboard']))?>">← Dashboard</a><div class="panel"><span class="eyebrow">Account settings</span><h1>Your profile</h1><form method="post" class="form-grid"><?=csrf_field()?><input type="hidden" name="action" value="update-profile"><label>Name<input name="name" value="<?=e($u['name'])?>" required></label><label>Email<input value="<?=e($u['email'])?>" disabled></label><button class="btn" type="submit">Save changes</button></form></div></section><?php layout_end();exit;
}
