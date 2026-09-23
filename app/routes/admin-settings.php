<?php
if($page==='admin-settings'){
  require_admin();
  layout_start('Settings',true);?>
  <section class="container narrow section">
    <div class="section-head"><div><span class="eyebrow">Configuration</span><h1>Settings</h1></div></div>
    <div class="panel"><form method="post" class="form-grid">
      <?=csrf_field()?><input type="hidden" name="action" value="admin-settings">
      <label>Site name<input name="site_name" value="<?=e(setting('site_name','BounceApp'))?>" required></label>
      <label>Currency symbol<input name="currency_symbol" value="<?=e(setting('currency_symbol','$'))?>" maxlength="5" required></label>
      <label>Support email<input name="support_email" type="email" value="<?=e(setting('support_email'))?>"></label>
      <label>Footer text<textarea name="footer_text" rows="3"><?=e(setting('footer_text'))?></textarea></label>
      <button class="btn" type="submit">Save settings</button>
    </form></div>
  </section>
  <?php layout_end();exit;
}
