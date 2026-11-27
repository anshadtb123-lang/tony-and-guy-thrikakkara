<?php
require dirname(__DIR__,3).'/toni-private/app.php';
require dirname(__DIR__,3).'/toni-private/actions.php';
header('X-Robots-Tag: noindex, nofollow');
startOwnerSession();$user=owner();$areas=['Homepage','Services','Gallery','Contact','SEO','Ads & Analytics','Google Reviews','Account'];
$area=$_GET['area']??'Homepage';if(!in_array($area,$areas,true))$area='Homepage';
$err=null;$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 $action=$_POST['action']??'';
 if(!$user && $action!=='login'){http_response_code(403);exit('Sign in to make changes.');}
 if(!is_string($_POST['token']??null)||!hash_equals($_SESSION['token'],$_POST['token'])){http_response_code(403);exit('The form expired. Return to the editor and try again.');}
 try{
 if(!$user){login();}else{$_SESSION['flash']=saveSection($area,$action,$user);redirectAdmin($area);}
 }catch(Throwable $e){
 if($db->inTransaction())$db->rollBack();foreach($GLOBALS['newFiles']??[] as $file){if(is_file($file))unlink($file);}
 $err=$e instanceof FieldError?$e:new FieldError('form','Could not save. Your previous content is intact.');
 if(http_response_code()!==429)http_response_code(422);
 }
}
$s=site();$active=false;
function msg($k){global $err,$active;return $active && $err && $err->field===$k?'<small class="error" role="alert">'.esc($err->getMessage()).'</small>':'';}
function inputBox($k,$label,$val='',$type='text',$required=true,$hint=''){
 global $active,$err;if($active && $err && $type!=='password' && is_string($_POST[$k]??null))$val=$_POST[$k];
 echo '<label>'.esc($label);
 $attr=($required?' required':'').(msg($k)?' aria-invalid="true"':'');
 if($type==='textarea')echo '<textarea name="'.$k.'"'.$attr.'>'.esc($val).'</textarea>';
 else echo '<input name="'.$k.'" type="'.$type.'" value="'.esc($type==='password'?'':$val).'"'.$attr.($type==='password'?' autocomplete="'.(in_array($k,['new','confirm'])?'new-password':'current-password').'"':'').'>';
 if($hint)echo '<small>'.esc($hint).'</small>';echo msg($k).'</label>';
}
function photoBox($k,$label,$file){
 echo '<fieldset><legend>'.esc($label).'</legend><div class="previews">';
 if($file)echo '<figure><img src="'.esc(media($file)).'" alt="Current '.esc($label).'"><figcaption>Current image</figcaption></figure>';
 echo '<figure hidden><img alt="Selected replacement"><figcaption>Selected image</figcaption></figure></div><input type="file" name="'.$k.'" aria-label="'.esc($label).'" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG or WebP · up to 2 MB and 16 megapixels</small>'.msg($k).'</fieldset>';
}
function beginForm($id=null){global $err,$active;$active=$err && (string)($_POST['id']??'')===(string)$id;echo '<form method="post" enctype="multipart/form-data">'.csrf();if($id!==null)echo '<input type="hidden" name="id" value="'.(int)$id.'">';}
function saveBtn($text='Save changes',$action='save'){echo '<button name="action" value="'.$action.'">'.esc($text).'</button></form>';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=esc($user?$area:'Sign in')?> | Salon editor</title><link rel="stylesheet" href="../assets/site.css"><link rel="stylesheet" href="../assets/editor.css"><script src="../assets/editor.js" defer></script></head><body><header class="editor-head"><a href="../"><img src="<?=esc(media($s['logo']))?>" alt="TONI&amp;GUY home"></a><a href="../">View website ↗</a></header>
<main class="editor"><h1><?= $user?'Salon editor':'Owner sign in'?></h1>
<?php if($err):?><p class="notice error" role="alert"><?=esc($err->getMessage())?></p><?php endif;if(!$user): beginForm();inputBox('username','Username');inputBox('password','Password','','password');saveBtn('Sign in','login');else:?>
<nav aria-label="Editor sections"><?php foreach($areas as $a):?><a href="?area=<?=rawurlencode($a)?>" <?=$a===$area?'aria-current="page"':''?>><?=esc($a)?></a><?php endforeach?></nav>
<?php if($flash):?><p class="notice" role="status"><?=esc($flash)?></p><?php endif?><h2><?=esc($area)?></h2>
<?php if($area==='Homepage'):beginForm();inputBox('headline','Headline',$s['headline']);inputBox('intro','Introduction',$s['intro'],'textarea');inputBox('story','Salon description',$s['story'],'textarea');foreach(['book'=>'Booking button','explore'=>'Explore link'] as $k=>$label){inputBox($k.'_label',$label.' label',$s[$k.'_label']);inputBox($k.'_target',$label.' destination',$s[$k.'_target'],'text',true,'Choose booking, email, #services, #salon, #gallery, #contact, #top or an https:// URL.');}photoBox('logo','Logo',$s['logo']);photoBox('hero','Hero photograph',$s['hero']);inputBox('hero_alt','Hero image description',$s['hero_alt']);saveBtn();
elseif(in_array($area,['SEO','Ads & Analytics','Google Reviews'],true)):marketingForm($area,marketing());
elseif($area==='Contact'):beginForm();inputBox('phone','Booking phone number',$s['phone'],'tel');inputBox('email','Email address (optional)',$s['email'],'email',false);inputBox('address','Street address',$s['address'],'textarea');saveBtn();
elseif($area==='Account'):if($user['initial_password']):?><p class="notice">Change the initial password before public deployment. Local access is required until then.</p><?php endif;beginForm();inputBox('current','Current password','','password');inputBox('new','New password','','password',true,'Use at least 12 characters.');inputBox('confirm','Confirm new password','','password');saveBtn('Change password','password');
else:$rows=items(strtolower($area));$count=count($rows);$rows[]=['id'=>0];foreach($rows as $i=>$row):$id=$row['id'];$title=$area==='Services'?'service':'photograph';?>
<article><h3><?=esc($id?($i+1).'. '.($row['name']??($row['caption']?:$row['alt'])):'Add '.$title)?></h3><?php beginForm($id);
if($area==='Services'){inputBox('name','Service name',$row['name']??'');inputBox('description','Description',$row['description']??'','textarea');}else{inputBox('alt','Image description (alt text)',$row['alt']??'');inputBox('caption','Caption (optional)',$row['caption']??'','text',false);}
photoBox('image',$area==='Services'?'Service image (optional)':'Photograph',$row['image']??'');
if($area==='Services' && !empty($row['image']))echo '<label class="check"><input type="checkbox" name="clear_image"> Remove current service image</label>';
saveBtn($id?'Save changes':'Add '.$title);if($id):?>
<div class="manage"><form method="post"><?=csrf()?><input name="id" type="hidden" value="<?=$id?>"><button class="minor" name="action" value="up" <?=$i===0?'disabled':''?>>↑ Move up</button> <button class="minor" name="action" value="down" <?=$i===$count-1?'disabled':''?>>↓ Move down</button></form><details><summary>Remove</summary><form method="post"><?=csrf()?><input type="hidden" name="id" value="<?=$id?>"><label class="check"><input type="checkbox" required name="confirmed" value="yes"> Confirm removal</label><button name="action" value="remove">Remove <?=esc($title)?></button></form></details></div><?php endif?></article><?php endforeach;endif;?>
<form class="logout" method="post"><?=csrf()?><button class="minor" name="action" value="logout">Log out</button></form><?php endif?></main></body></html>
