<?php
declare(strict_types=1);
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function request_api(string $route,string $method='GET',mixed $body=null,string $mime='application/json',string $extra=''): array {
    $options=['http'=>['method'=>$method,'header'=>"Content-Type: $mime\r\n".$extra,'ignore_errors'=>true,'timeout'=>10]];
    if($body!==null)$options['http']['content']=is_string($body)?$body:json_encode($body,JSON_THROW_ON_ERROR);
    $raw=@file_get_contents('http://127.0.0.1:8000/api.php?route='.$route,false,stream_context_create($options));
    if($raw===false)return [0,''];
    preg_match('/ (\d{3}) /',$http_response_header[0],$match);
    return [(int)$match[1],$raw];
}
for($attempt=0;$attempt<20;$attempt++){[$code,$raw]=request_api('board');if($code===200)break;usleep(200000);}
check($code===200,'Database GET failed: '.$raw);
$initial=json_decode($raw,true,512,JSON_THROW_ON_ERROR);check(count($initial['data']['items'])===0,'Run integration tests only against an empty test database.');
$doc=['projects'=>[['id'=>'qa-project','name'=>'Test platform','url'=>'https://example.com']],'developers'=>[['id'=>'qa-dev','name'=>'Example Developer','email'=>'developer@example.com']],'items'=>[]];
$revision=$initial['revision'];
[$code]=request_api('board','PUT',['data'=>$doc,'revision'=>$revision],'application/json',"Origin: https://other.test\r\n");check($code===403,'Origin rejection');
[$code]=request_api('upload','POST','not a PNG image','image/png');check($code===400,'Invalid image rejection');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF1sAAAAASUVORK5CYII=');
[$code,$raw]=request_api('upload','POST',$png,'image/png');check($code===200,'Image upload: '.$raw);$image=json_decode($raw,true)['url'];
[$code,$bytes]=request_api(explode('route=',$image)[1]);check($code===200&&$bytes===$png,'Image bytes preserved');
$doc['items'][]=['id'=>'qa-item','project'=>'qa-project','title'=>'Fix button spacing','comment'=>'Add space','developer'=>'qa-dev','image'=>$image,'status'=>'Open','thread'=>[]];
foreach(['Open','Ready for review','Open','Ready for review','Approved'] as $status){
    $doc['items'][0]['status']=$status;
    $doc['items'][0]['thread'][]=['role'=>'Developer','authorId'=>'qa-dev','authorName'=>'Example Developer','text'=>'Review round','at'=>date('c')];
    $previous=$revision;
    [$code,$raw]=request_api('board','PUT',['data'=>$doc,'revision'=>$revision]);check($code===200,'Save '.$status.': '.$raw);$revision=json_decode($raw,true)['revision'];
    [$code]=request_api('board','PUT',['data'=>$doc,'revision'=>$previous]);check($code===409,'Stale write rejected');
}
[$code,$raw]=request_api('board');$saved=json_decode($raw,true);check($saved['data']['items'][0]['status']==='Approved','Saved approval');check(count($saved['data']['items'][0]['thread'])===5,'Saved discussion history');check($saved['data']['items'][0]['thread'][0]['authorName']==='Example Developer','Comment author metadata preserved');
$invalid=$doc;$invalid['items'][0]['image']='https://other.test/image.png';[$code]=request_api('board','PUT',['data'=>$invalid,'revision'=>$revision]);check($code===400,'Invalid image URL rejected');
echo "PHP/MySQL integration passed: persistence, PNG upload, exact bytes, review/reopen history, stale writes, validation and origins.\n";
