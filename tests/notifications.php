<?php
declare(strict_types=1);
require __DIR__.'/../server/notifications.php';
function expect($ok,$message){if(!$ok)throw new RuntimeException($message);}
$before=['projects'=>[['id'=>'p','name'=>'Portal']],'developers'=>[['id'=>'a','name'=>'Alice','email'=>'alice@example.com'],['id'=>'b','name'=>'Bob','email'=>'bob@example.com']],'items'=>[]];
$item=['id'=>'feedback 1','project'=>'p','title'=>'Fix button','comment'=>'Make it bigger','developer'=>'a'];
$after=$before;$after['items']=[$item];
expect(count(assignment_notifications($before,$after))===1,'New assigned feedback');
expect(assignment_notifications($after,$after)===[],'No duplicate on unchanged save');
$reply=$after;$reply['items'][0]['thread']=[['role'=>'Developer','text'=>'Done']];
expect(assignment_notifications($after,$reply)===[],'Reply is not another assignment');
$changed=$after;$changed['items'][0]['developer']='b';$events=assignment_notifications($after,$changed);
expect(count($events)===1&&$events[0]['developer']['email']==='bob@example.com','Reassignment goes only to new developer');
$unassigned=$after;$unassigned['items'][0]['developer']='';expect(assignment_notifications($after,$unassigned)===[],'Unassign does not send');
$message=assignment_message($events[0],'https://feedback.example.com/');expect(str_contains($message['body'],'/?feedback=feedback%201'),'Deep link is encoded');expect(str_contains($message['body'],'Make it bigger'),'Contains reviewer comment');
$settings=['enabled'=>true,'password'=>'test-only'];$sent=[];
$r=notify_assignments($before,$after,$settings,function($e,$s)use(&$sent){$sent[]=$e;});expect($r['sent']===1&&count($sent)===1,'Success status');
$r=notify_assignments($before,$after,$settings,function(){throw new RuntimeException('Test connection failure');});expect($r['failed']===1,'SMTP failure remains a notification result');
$r=notify_assignments($before,$after,['enabled'=>false]);expect($r['status']==='not_configured','Disabled SMTP');
expect(notify_assignments($after,$after,$settings)['status']==='not_needed','No changes require no SMTP connection');
echo "Assignment email tests passed (mock transport; no real mail sent).\n";
