<?php
function login():void{
 global $db;
 $ip=hash('sha256',$_SERVER['REMOTE_ADDR']);$db->beginTransaction();
 query("DELETE FROM attempts WHERE since < NOW()-INTERVAL 15 MINUTE");
 query("INSERT INTO attempts(ip,since,hits) VALUES(?,NOW(),1) ON DUPLICATE KEY UPDATE hits=hits+1",[$ip]);
 $hits=query('SELECT hits FROM attempts WHERE ip=?',[$ip])->fetchColumn();
 $u=query('SELECT * FROM owners WHERE username=?',[field('username',80)])->fetch();
 if($hits>8 || !$u || !is_string($_POST['password']??null) || !password_verify($_POST['password'],$u['hash'])){
 $db->commit();if($hits>8)http_response_code(429);
 throw new FieldError('password',$hits>8?'Too many attempts. Try again in 15 minutes.':'Check your username and password.');
 }
 query('DELETE FROM attempts WHERE ip=?',[$ip]);$db->commit();session_regenerate_id(true);
 $_SESSION=['owner'=>$u['id'],'version'=>$u['version'],'last'=>time(),'token'=>bin2hex(random_bytes(32))];redirectAdmin('Homepage');
}
function saveSection(string $area,string $action,array $user):string{
 global $db;
 if($action==='logout'){
 $_SESSION=[];$p=session_get_cookie_params();setcookie(session_name(),'',time()-86400,$p['path'],'',$p['secure'],true);session_destroy();redirectAdmin('Homepage');
 }
 $db->beginTransaction();
 $s=query('SELECT * FROM site WHERE id=1 FOR UPDATE')->fetch();
 if(in_array($area,['SEO','Ads & Analytics','Google Reviews'],true) && $action==='save'){
 saveMarketing($area);
 }elseif($area==='Homepage' && $action==='save'){
 $data=[field('headline',160),field('intro',400),field('story',1800),field('book_label',48),linkField('book_target'),field('explore_label',48),linkField('explore_target'),field('hero_alt',240),upload('logo',$s['logo']),upload('hero',$s['hero'])];
 query('UPDATE site SET headline=?,intro=?,story=?,book_label=?,book_target=?,explore_label=?,explore_target=?,hero_alt=?,logo=?,hero=? WHERE id=1',$data);
 }elseif($area==='Contact' && $action==='save'){
 $phone=field('phone',32);$email=field('email',254,false);$address=field('address',350);
 if(!preg_match('/^\+?[0-9 ()-]+$/D',$phone)||!preg_match('/^\d{7,15}$/D',preg_replace('/\D/','',$phone)))throw new FieldError('phone','Enter 7–15 digits, with an optional leading +.');
 if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))throw new FieldError('email','Enter a valid email address.');
 query('UPDATE site SET phone=?,email=?,address=? WHERE id=1',[$phone,$email,$address]);
 }elseif($area==='Account' && $action==='password'){
 $user=query('SELECT * FROM owners WHERE id=? FOR UPDATE',[$user['id']])->fetch();
 $old=$_POST['current']??null;$new=$_POST['new']??null;
 if(!is_string($old)||!password_verify($old,$user['hash']))throw new FieldError('current','The current password is incorrect.');
 if(!is_string($new)||mb_strlen($new)<12||strlen($new)>72||str_contains($new,chr(0))||$old===$new)throw new FieldError('new','Choose a different password with at least 12 characters and at most 72 bytes.');
 if($new!==($_POST['confirm']??null))throw new FieldError('confirm','The new passwords must match.');
 query('UPDATE owners SET hash=?,version=version+1,initial_password=0 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$user['id']]);
 }elseif(in_array($area,['Services','Gallery'],true)){
 $table=strtolower($area);$rows=items($table);$ids=array_map('intval',array_column($rows,'id'));
 $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);$ix=array_search($id,$ids,true);
 if($id!==0 && $ix===false)throw new FieldError('id','This entry has changed. Refresh and try again.');
 if($action==='save'){
 $image=$id?$rows[$ix]['image']:'';
 if($area==='Services'){
 $cols=['name','description','image'];$values=[field('name',100),field('description',400),upload('image',isset($_POST['clear_image'])?'':$image)];
 }else{
 $cols=['alt','caption','image'];$values=[field('alt',240),field('caption',240,false),upload('image',$image)];if(!$values[2])throw new FieldError('image','Choose a photograph.');
 }
 if($id){query("UPDATE $table SET ".implode('=?,',$cols)."=? WHERE id=?",[...$values,$id]);}
 else{query("INSERT INTO $table (".implode(',',$cols).",position) VALUES(?,?,?,?)",[...$values,$rows?max(array_column($rows,'position'))+1:0]);}
 }elseif($id && $action==='remove'){
 if(($_POST['confirmed']??'')!=='yes')throw new FieldError('confirmed','Confirm removal first.');
 query("DELETE FROM $table WHERE id=?",[$id]);
 }elseif($id && in_array($action,['up','down'],true)){
 $j=$ix+($action==='up'?-1:1);if(isset($ids[$j])){
 [$ids[$ix],$ids[$j]]=[$ids[$j],$ids[$ix]];foreach($ids as $i=>$entry)query("UPDATE $table SET position=? WHERE id=?",[$i,$entry]);}
 }else{throw new FieldError('action','Unknown action.');}
 }else{throw new FieldError('action','Choose a valid editor action.');}
 $db->commit();
 if($area==='Account'){$_SESSION['version']=$user['version']+1;session_regenerate_id(true);$_SESSION['token']=bin2hex(random_bytes(32));}
 return $action==='remove'?'Entry removed.':(in_array($action,['up','down'],true)?'Order saved.':'Changes saved.');
}
