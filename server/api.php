<?php
declare(strict_types=1);
require_once __DIR__.'/notifications.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(array $body, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
function text_value(mixed $v, int $max, bool $required = false): bool {
    return is_string($v) && strlen($v) <= $max * 4 && (!$required || trim($v) !== '');
}
function valid_board(mixed $d): bool {
    if (!is_array($d)) return false;
    foreach (['projects'=>300, 'developers'=>500, 'items'=>5000] as $key=>$max) {
        if (!isset($d[$key]) || !is_array($d[$key]) || !array_is_list($d[$key]) || count($d[$key]) > $max) return false;
    }
    $projects = []; $devs = []; $ids = [];
    foreach ($d['projects'] as $p) {
        if (!is_array($p) || !text_value($p['id']??null,80,true) || !text_value($p['name']??null,120,true) || !text_value($p['url']??null,2048) || ($p['url'] !== '' && !preg_match('~^https?://~i',$p['url'])) || isset($projects[$p['id']])) return false;
        $projects[$p['id']] = true;
    }
    $platforms = [];
    if (isset($d['platforms'])) {
        if (!is_array($d['platforms']) || !array_is_list($d['platforms']) || count($d['platforms']) > 1500) return false;
        foreach ($d['platforms'] as $p) {
            if (!is_array($p) || !text_value($p['id']??null,80,true) || !text_value($p['name']??null,120,true) || !is_string($p['product']??null) || !isset($projects[$p['product']]) || isset($platforms[$p['id']])) return false;
            $platforms[$p['id']] = $p['product'];
        }
    }
    foreach ($d['developers'] as $p) {
        if (!is_array($p) || !text_value($p['id']??null,80,true) || !text_value($p['name']??null,100,true) || !text_value($p['email']??null,254,true) || !filter_var($p['email'],FILTER_VALIDATE_EMAIL) || isset($devs[$p['id']])) return false;
        $devs[$p['id']] = true;
    }
    foreach ($d['items'] as $i) {
        if (!is_array($i) || !text_value($i['id']??null,80,true) || isset($ids[$i['id']]) || !is_string($i['project']??null) || !isset($projects[$i['project']]) || !text_value($i['title']??null,160,true) || !text_value($i['comment']??null,10000) || !is_string($i['developer']??null) || ($i['developer'] !== '' && !isset($devs[$i['developer']])) || !in_array($i['status']??null,['Open','Ready for review','Approved'],true) || !isset($i['thread']) || !is_array($i['thread']) || !array_is_list($i['thread']) || count($i['thread']) >= 2000) return false;
        if (isset($i['priority']) && !in_array($i['priority'],['P1','P2','P3'],true)) return false;
        if (isset($i['platform']) && (!is_string($i['platform']) || ($i['platform'] !== '' && ($platforms[$i['platform']]??null) !== $i['project']))) return false;
        if (!empty($i['image']) && (!is_string($i['image']) || !preg_match('~^api\.php\?route=images/[a-f0-9-]{36}$~D',$i['image']))) return false;
        foreach ($i['thread'] as $t) {
            if (!is_array($t) || !in_array($t['role']??null,['Reviewer','Developer','Activity'],true) || !text_value($t['text']??null,10000) || !text_value($t['at']??null,100,true)) return false;
        }
        $ids[$i['id']] = true;
    }
    return true;
}
function current_board(PDO $db): array {
    $row=$db->query('SELECT document, revision FROM loop_board WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) respond(['error'=>'Import schema.sql into the database before using the app.'],503);
    return ['data'=>json_decode($row['document'],true,512,JSON_THROW_ON_ERROR),'revision'=>$row['revision']];
}
try {
    $method=$_SERVER['REQUEST_METHOD'];
    $route=$_GET['route']??'';
    if (!is_string($route)) respond(['error'=>'Invalid route.'],400);
    if (in_array($method,['PUT','POST','DELETE'],true)) {
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
        if (($origin!=='' && $origin!==$scheme.'://'.($_SERVER['HTTP_HOST']??'')) || ($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') respond(['error'=>'Invalid request origin.'],403);
    }
    if ($route==='email-check' && $method==='GET') respond(smtp_connection_check());
    if (!is_file(__DIR__.'/config.php')) respond(['error'=>'Server setup required: create config.php with your database details.'],503);
    $c=require __DIR__.'/config.php';
    if (!is_array($c) || ($c['password']??'')==='REPLACE_WITH_YOUR_DATABASE_USER_PASSWORD') respond(['error'=>'Enter your database password in config.php.'],503);
    $db=new PDO('mysql:host='.$c['host'].';dbname='.$c['database'].';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($route==='board' && $method==='GET') respond(current_board($db));
    if ($route==='board' && $method==='PUT') {
        $raw=file_get_contents('php://input',false,null,0,8*1024*1024+1);
        if (strlen($raw)>8*1024*1024) respond(['error'=>'Workspace is too large.'],413);
        try {$body=json_decode($raw,true,512,JSON_THROW_ON_ERROR);} catch (JsonException $e) {respond(['error'=>'Invalid JSON.'],400);}
        if (!is_array($body) || !is_string($body['revision']??null) || !valid_board($body['data']??null)) respond(['error'=>'Please check the feedback fields and try again.'],400);
        $before=current_board($db);
        if ($before['revision']!==$body['revision']) respond(['error'=>'Someone updated this board. The latest version is loaded; please retry your change.']+$before,409);
        $document=json_encode($body['data'],JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $revision=bin2hex(random_bytes(32));
        $stmt=$db->prepare('UPDATE loop_board SET document=?, revision=? WHERE id=1 AND revision=?');
        $stmt->execute([$document,$revision,$body['revision']]);
        if ($stmt->rowCount()!==1) respond(['error'=>'Someone updated this board. The latest version is loaded; please retry your change.']+current_board($db),409);
        $notifications=notify_assignments($before['data'],$body['data']);
        respond(['revision'=>$revision,'notifications'=>$notifications]);
    }
    if ($route==='upload' && $method==='POST') {
        $bytes=file_get_contents('php://input',false,null,0,8*1024*1024+1);
        if (strlen($bytes)>8*1024*1024 || strlen($bytes)<12) respond(['error'=>'Choose a non-empty image under 8 MB.'],400);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $declared=trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]);
        if (!in_array($mime,['image/png','image/jpeg','image/webp','image/gif'],true) || $declared!==$mime || @getimagesizefromstring($bytes)===false) respond(['error'=>'The file must be a valid PNG, JPG, GIF or WebP image.'],400);
        $id=vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex(random_bytes(16)),4));
        $stmt=$db->prepare('INSERT INTO loop_images (id,mime,content) VALUES (?,?,?)');
        $stmt->bindValue(1,$id); $stmt->bindValue(2,$mime); $stmt->bindValue(3,$bytes,PDO::PARAM_LOB); $stmt->execute();
        respond(['url'=>'api.php?route=images/'.$id]);
    }
    if (preg_match('~^images/([a-f0-9-]{36})$~D',$route,$m) && $method==='GET') {
        $stmt=$db->prepare('SELECT mime,content FROM loop_images WHERE id=?'); $stmt->execute([$m[1]]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) respond(['error'=>'Image not found.'],404);
        header('Content-Type: '.$row['mime']); header('Cache-Control: private, max-age=3600');
        echo $row['content']; exit;
    }
    respond(['error'=>'Not found.'],404);
} catch (Throwable $e) {
    // Never expose connection details, passwords or SQL to visitors.
    error_log('Loop API: '.$e->getMessage());
    respond(['error'=>'Storage unavailable. Check config.php, the database schema and PHP extensions in cPanel.'],503);
}
