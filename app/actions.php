<?php
if(is_post()){
  verify_csrf();$action=post('action');
  if($action==='register'){
    $name=post('name');$email=strtolower(post('email'));$password=(string)($_POST['password']??'');
    if(strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8){flash('error','Use a valid name/email and a password of at least 8 characters.');go(['page'=>'register']);}
    $s=$pdo->prepare('SELECT id FROM users WHERE email=?');$s->execute([$email]);if($s->fetch()){flash('error','An account with this email already exists.');go(['page'=>'login']);}
    $s=$pdo->prepare('INSERT INTO users(name,email,password) VALUES(?,?,?)');$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$_SESSION['user_id']=(int)$pdo->lastInsertId();session_regenerate_id(true);flash('success','Welcome to BounceApp.');go(['page'=>'dashboard']);
  }
  if($action==='login'){
    $email=strtolower(post('email'));$password=(string)($_POST['password']??'');$s=$pdo->prepare('SELECT * FROM users WHERE email=? LIMIT 1');$s->execute([$email]);$u=$s->fetch();
    if(!$u||$u['status']!=='active'||!password_verify($password,$u['password'])){flash('error','Invalid email or password.');go(['page'=>'login']);}
    $_SESSION['user_id']=(int)$u['id'];session_regenerate_id(true);flash('success','Signed in successfully.');go(['page'=>$u['role']==='admin'?'admin':'dashboard']);
  }
  if($action==='create-booking'){
    $u=require_auth();$locationId=(int)post('location_id','0');$dropoff=post('dropoff_at');$pickup=post('pickup_at');$bags=max(1,min(20,(int)post('bags','1')));$notes=mb_substr(post('notes'),0,500);$days=booking_days($dropoff,$pickup);
    $s=$pdo->prepare("SELECT * FROM locations WHERE id=? AND status='active' LIMIT 1");$s->execute([$locationId]);$loc=$s->fetch();
    if(!$loc||$days<1||strtotime($dropoff)<time()-300){flash('error','Please choose a valid location and future drop-off/pick-up time.');go(['page'=>'location','id'=>$locationId]);}
    $c=$pdo->prepare("SELECT COALESCE(SUM(bags),0) FROM bookings WHERE location_id=? AND status IN ('confirmed','checked_in') AND NOT (pickup_at<=? OR dropoff_at>=?)");$c->execute([$locationId,$dropoff,$pickup]);$reserved=(int)$c->fetchColumn();
    if($reserved+$bags>(int)$loc['capacity']){flash('error','Not enough storage capacity for this time window. Try fewer bags or another location.');go(['page'=>'location','id'=>$locationId]);}
    $price=(float)$loc['price_per_bag'];$total=$price*$bags*$days;$s=$pdo->prepare('INSERT INTO bookings(code,user_id,location_id,dropoff_at,pickup_at,bags,days,price_per_bag,total_amount,notes) VALUES(?,?,?,?,?,?,?,?,?,?)');$s->execute([booking_code(),$u['id'],$locationId,$dropoff,$pickup,$bags,$days,$price,$total,$notes]);$id=(int)$pdo->lastInsertId();flash('success','Booking confirmed. Your booking code is ready.');go(['page'=>'booking','id'=>$id]);
  }
  if($action==='cancel-booking'){
    $u=require_auth();$id=(int)post('booking_id','0');$s=$pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND user_id=? AND status='confirmed'");$s->execute([$id,$u['id']]);flash($s->rowCount()?'success':'error',$s->rowCount()?'Booking cancelled.':'This booking can no longer be cancelled.');go(['page'=>'booking','id'=>$id]);
  }
  if($action==='add-review'){
    $u=require_auth();$id=(int)post('booking_id','0');$rating=max(1,min(5,(int)post('rating','5')));$comment=mb_substr(post('comment'),0,2000);$s=$pdo->prepare("SELECT * FROM bookings WHERE id=? AND user_id=? AND status='completed' LIMIT 1");$s->execute([$id,$u['id']]);$b=$s->fetch();if(!$b){flash('error','Only completed bookings can be reviewed.');go(['page'=>'dashboard']);}
    try{$s=$pdo->prepare('INSERT INTO reviews(user_id,location_id,booking_id,rating,comment) VALUES(?,?,?,?,?)');$s->execute([$u['id'],$b['location_id'],$id,$rating,$comment]);flash('success','Thanks for your review.');}catch(Throwable){flash('error','This booking has already been reviewed.');}go(['page'=>'booking','id'=>$id]);
  }
  if($action==='update-profile'){
    $u=require_auth();$name=post('name');if(strlen($name)<2)flash('error','Enter your name.');else{$s=$pdo->prepare('UPDATE users SET name=? WHERE id=?');$s->execute([$name,$u['id']]);flash('success','Profile updated.');}go(['page'=>'profile']);
  }
  if(str_starts_with($action,'admin-')){
    require_admin();
    if($action==='admin-save-location'){
      $id=(int)post('id','0');$name=post('name');$baseSlug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'));$slug=$baseSlug?:'location';
      if(!$id){$test=$pdo->prepare('SELECT COUNT(*) FROM locations WHERE slug=?');$test->execute([$slug]);if((int)$test->fetchColumn()>0)$slug.='-'.substr(bin2hex(random_bytes(3)),0,6);}
      $data=[$name,$slug,post('address'),post('city'),post('country'),post('description'),post('opening_hours','Daily · 8:00 AM – 10:00 PM'),max(0,(float)post('price_per_bag','0')),max(1,(int)post('capacity','1')),post('image_url'),in_array(post('status'),['active','inactive'],true)?post('status'):'active'];
      if($name===''||$data[2]===''||$data[3]===''||$data[4]===''){flash('error','Name, address, city and country are required.');go(['page'=>'admin-locations','edit'=>$id?:'new']);}
      if($id){$data[]=$id;$s=$pdo->prepare('UPDATE locations SET name=?,slug=?,address=?,city=?,country=?,description=?,opening_hours=?,price_per_bag=?,capacity=?,image_url=?,status=? WHERE id=?');$s->execute($data);}else{$s=$pdo->prepare('INSERT INTO locations(name,slug,address,city,country,description,opening_hours,price_per_bag,capacity,image_url,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$s->execute($data);}flash('success','Location saved.');go(['page'=>'admin-locations']);
    }
    if($action==='admin-delete-location'){
      $id=(int)post('id','0');try{$s=$pdo->prepare('DELETE FROM locations WHERE id=?');$s->execute([$id]);flash('success','Location deleted.');}catch(Throwable){flash('error','This location has booking history. Set it to inactive instead.');}go(['page'=>'admin-locations']);
    }
    if($action==='admin-booking-status'){
      $id=(int)post('id','0');$status=post('status');$payment=post('payment_status');if(!in_array($status,['confirmed','checked_in','completed','cancelled'],true)||!in_array($payment,['unpaid','paid','refunded'],true))flash('error','Invalid booking status.');else{$s=$pdo->prepare('UPDATE bookings SET status=?,payment_status=? WHERE id=?');$s->execute([$status,$payment,$id]);flash('success','Booking updated.');}go(['page'=>'admin-bookings']);
    }
    if($action==='admin-user-status'){
      $id=(int)post('id','0');$status=post('status');$me=current_user();if($id===(int)$me['id'])flash('error','You cannot disable your own admin account.');elseif(in_array($status,['active','disabled'],true)){$s=$pdo->prepare("UPDATE users SET status=? WHERE id=? AND role='customer'");$s->execute([$status,$id]);flash('success','User status updated.');}go(['page'=>'admin-users']);
    }
    if($action==='admin-settings'){
      $s=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach(['site_name','currency_symbol','support_email','footer_text'] as $k)$s->execute([$k,mb_substr(post($k),0,500)]);flash('success','Settings saved.');go(['page'=>'admin-settings']);
    }
  }
}
