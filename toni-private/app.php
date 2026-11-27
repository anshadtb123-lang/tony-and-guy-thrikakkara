<?php
declare(strict_types=1);
ini_set('display_errors','0');
header("Content-Security-Policy: default-src 'self'; img-src 'self' blob:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
set_exception_handler(function(Throwable $e){http_response_code(500); echo 'The salon website is temporarily unavailable. Please try again shortly.';});
require_once __DIR__.'/host.php';
require_once __DIR__.'/marketing.php';
define('HOSTED', getenv('SALON_HOSTED') === '1');
define('BASE', HOSTED ? '/tony-and-guy-thrikkakara/' : '/tony-and-guy/');
if (HOSTED) {
    $db = hostedDb();
} else {
    [$user,$password] = require __DIR__.'/config.php';
    $db=new PDO('mysql:host=127.0.0.1;dbname=toni_kanhangad;charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function query(string $sql,array $args=[]):PDOStatement {global $db;$q=$db->prepare($sql);$q->execute($args);return $q;}
function esc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function localClient():bool{return in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);}
if((HOSTED || !localClient()) && query('SELECT initial_password FROM owners WHERE id=1')->fetchColumn()){http_response_code(503);exit('Salon setup is in progress.');}
function site():array{return query('SELECT * FROM site WHERE id=1')->fetch();}
function items(string $table):array{return query("SELECT * FROM $table ORDER BY position,id")->fetchAll();}
function media(string $path):string{return str_starts_with($path,'assets/')?BASE.$path:BASE.'photo.php?id='.rawurlencode($path);}
function tel(array $s):string{return 'tel:'.preg_replace('/[^0-9+]/','',$s['phone']);}
function destination(string $link,array $s):string{return $link==='booking'?tel($s):($link==='email'?($s['email'] ? 'mailto:'.$s['email'] : '#contact'):$link);}
function csrf():string{return '<input type="hidden" name="token" value="'.esc($_SESSION['token']).'">';}
function startOwnerSession():void{
 header('Cache-Control: no-store');
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 if(HOSTED) session_set_save_handler(new DatabaseSessions(),true);
 session_name('toni_owner');session_set_cookie_params(['lifetime'=>0,'path'=>BASE.'admin/','httponly'=>true,'samesite'=>'Strict','secure'=>HOSTED || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')]);
 session_start();
 if(isset($_SESSION['last']) && time()-$_SESSION['last']>1800){$_SESSION=[];session_regenerate_id(true);}
 $_SESSION['last']=time();$_SESSION['token']??=bin2hex(random_bytes(32));
}
function owner():?array{
 if(empty($_SESSION['owner']))return null;
 $r=query('SELECT * FROM owners WHERE id=? AND version=?',[$_SESSION['owner'],$_SESSION['version']])->fetch();
 return $r?:null;
}
function redirectAdmin(string $area):never{header('Location: '.BASE.'admin/?area='.rawurlencode($area),true,303);exit;}
class FieldError extends Exception {public string $field;function __construct($field,$msg){parent::__construct($msg);$this->field=$field;}}
function field(string $k,int $limit,bool $required=true):string{
 $s=$_POST[$k]??''; if(!is_string($s)||!mb_check_encoding($s,'UTF-8'))throw new FieldError($k,'Enter valid text.');
 $s=trim($s);if($required && $s==='')throw new FieldError($k,'This field is required.');
 if(mb_strlen($s)>$limit)throw new FieldError($k,"Use $limit characters or fewer.");return $s;
}
function linkField(string $k):string{
 $v=field($k,500);if(in_array($v,['booking','email','#services','#salon','#gallery','#contact','#top'],true))return $v;
 if(filter_var($v,FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($v,PHP_URL_SCHEME)?:''),['https','http'],true) && !preg_match('/[\\\\\s]/',$v))return $v;
 throw new FieldError($k,'Use booking, email, a listed section, or a full http(s) URL.');
}
function upload(string $k,string $current):string{
 $f=$_FILES[$k]??null;if(!$f || $f['error']===UPLOAD_ERR_NO_FILE)return $current;
 $fail=fn()=>new FieldError($k,'Choose a valid JPEG, PNG or WebP up to 2 MB and 16 megapixels.');
 if($f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || filesize($f['tmp_name'])>2097152)throw $fail();
 $dims=@getimagesize($f['tmp_name']);
 if(!$dims||!in_array($dims[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)||max($dims[0],$dims[1])>8000||$dims[0]*$dims[1]>16000000)throw $fail();
 $im=@imagecreatefromstring(file_get_contents($f['tmp_name']));if(!$im)throw $fail();
 $scale=min(1,1800/max(imagesx($im),imagesy($im)));
 $out=imagecreatetruecolor(max(1,(int)round(imagesx($im)*$scale)),max(1,(int)round(imagesy($im)*$scale)));
 imagealphablending($out,false);imagesavealpha($out,true);imagecopyresampled($out,$im,0,0,0,0,imagesx($out),imagesy($out),imagesx($im),imagesy($im));
 $name=bin2hex(random_bytes(20)).'.webp';
 if (HOSTED) {
     ob_start();
     $encoded = imagewebp($out,null,85);
     $bytes = ob_get_clean();
     if (!$encoded || !$bytes) throw $fail();
     query('INSERT INTO salon_media(id,bytes) VALUES(?,?)',[$name,$bytes]);
     return $name;
 }
 $path=__DIR__.'/uploads/'.$name;
 $GLOBALS['newFiles'][]=$path;
 if(!@imagewebp($out,$path,85)||!is_file($path)||!filesize($path))throw $fail();
 return $name;
}
