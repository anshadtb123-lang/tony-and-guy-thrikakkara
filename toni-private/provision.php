<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/host.php';
try {
    $db=hostedDb();
    try {
        if ((int)$db->query('SELECT COUNT(*) FROM owners WHERE initial_password=0')->fetchColumn()>0) {
            // Schema upgrades are explicitly versioned here; avoid DDL on ordinary cold starts.
            $db->query('SELECT 1 FROM marketing,web_sessions,salon_media,ad_keywords,featured_reviews LIMIT 0');
            exit(0);
        }
    } catch(PDOException $e) {
        if ($e->getCode()!=='42S02') throw $e;
    }
    if ((int)$db->query("SELECT GET_LOCK('salon_provision_v1',30)")->fetchColumn()!==1) throw new RuntimeException();
    $db->exec(file_get_contents(__DIR__.'/schema.sql'));
    $db->beginTransaction();
    if (!$db->query('SELECT COUNT(*) FROM site')->fetchColumn()) {
        $content=json_decode(file_get_contents(__DIR__.'/launch.json'),true,512,JSON_THROW_ON_ERROR);
        foreach (['site','services','gallery','marketing','ad_keywords','featured_reviews'] as $table) {
            foreach ($content[$table] as $row) {
                if(in_array($table,['marketing','ad_keywords'],true)) $db->exec('DELETE FROM '.$table.' WHERE id=1');
                $names=implode(',',array_map(fn($k)=>'`'.$k.'`',array_keys($row)));
                $marks=implode(',',array_fill(0,count($row),'?'));
                $db->prepare("INSERT INTO $table ($names) VALUES ($marks)")->execute(array_values($row));
            }
        }
        foreach ($content['uploads'] as $name=>$encoded) {
            $db->prepare('INSERT INTO salon_media(id,bytes) VALUES(?,?)')->execute([$name,base64_decode($encoded,true)]);
        }
    }
    if (!$db->query('SELECT COUNT(*) FROM owners')->fetchColumn()) {
        $password=getenv('SALON_ADMIN_PASSWORD') ?: '';
        if (strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Choose a production password of 12–72 bytes.');
        $db->prepare("INSERT INTO owners(username,hash,initial_password) VALUES('admin',?,0)")->execute([password_hash($password,PASSWORD_DEFAULT)]);
    }
    if ($db->query('SELECT COUNT(*) FROM owners WHERE initial_password=1')->fetchColumn()) throw new RuntimeException('Use a dedicated production database with a production password.');
    $db->commit();
} catch(Throwable $e) {
    if(isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR,"Salon setup needs a TLS-enabled MySQL database, schema permissions, and a production password for first launch. Check the Vercel environment settings.\n");
    exit(1);
}

