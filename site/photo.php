<?php
require dirname(__DIR__,2).'/toni-private/app.php';
$id=$_GET['id']??'';
if(HOSTED) {
    if(!is_string($id)||!preg_match('/^[a-f0-9]{40}\.webp$/D',$id)){http_response_code(404);exit;}
    $bytes=query('SELECT bytes FROM salon_media WHERE id=?',[$id])->fetchColumn();
    if ($bytes===false){http_response_code(404);exit;}
    header('Content-Type: image/webp');header('Cache-Control: public,max-age=31536000,immutable');
    echo $bytes;exit;
}
if(!is_string($id)||!preg_match('/^[a-f0-9]{40}\.webp$/D',$id)||!is_file($p=dirname(__DIR__,2).'/toni-private/uploads/'.$id)){http_response_code(404);exit;}
header('Content-Type: image/webp');header('Cache-Control: public,max-age=31536000,immutable');header('Content-Length: '.filesize($p));readfile($p);
