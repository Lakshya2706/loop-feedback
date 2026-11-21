<?php
declare(strict_types=1);
/** Only a new assignment or a change of developer produces a notification. */
function assignment_notifications(array $before, array $after): array {
    $old=array_column($before['items'],'developer','id');
    $developers=array_column($after['developers'],null,'id');
    $projects=array_column($after['projects'],null,'id');
    $events=[];
    foreach($after['items'] as $item){
        $id=$item['developer']??'';
        if($id==='' || (isset($old[$item['id']]) && $old[$item['id']]===$id) || !isset($developers[$id]))continue;
        $dev=$developers[$id];
        if(!filter_var($dev['email'],FILTER_VALIDATE_EMAIL))continue;
        $events[]=['item'=>$item,'developer'=>$dev,'project'=>$projects[$item['project']]['name']??'Project'];
    }
    return $events;
}
function assignment_message(array $event, string $appUrl): array {
    $item=$event['item'];$dev=$event['developer'];
    $url=rtrim($appUrl,'/').'/?feedback='.rawurlencode($item['id']);
    return [
        'subject'=>'[Loop] Assigned to you: '.str_replace(["\r","\n"],' ',$item['title']),
        'body'=>"Hi ".$dev['name'].",\n\nYou have been assigned feedback in ".$event['project'].".\n\n".$item['title']."\n\n".$item['comment']."\n\nOpen the feedback and screenshot:\n".$url."\n\nPlease add your reply and mark it done when it is ready for review.\n\nLoop Feedback\n",
    ];
}
function deliver_assignment(array $event, array $settings): void {
    require_once __DIR__.'/lib/PHPMailer/Exception.php';
    require_once __DIR__.'/lib/PHPMailer/SMTP.php';
    require_once __DIR__.'/lib/PHPMailer/PHPMailer.php';
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();$mail->Host=$settings['host'];$mail->Port=(int)$settings['port'];
    $mail->SMTPAuth=true;$mail->Username=$settings['username'];$mail->Password=$settings['password'];
    $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Timeout=8;$mail->Timelimit=10;$mail->CharSet='UTF-8';$mail->SMTPDebug=0;
    // PHPMailer retains certificate/hostname verification. No plaintext fallback.
    $mail->setFrom($settings['from_email'],$settings['from_name']);
    $mail->addAddress($event['developer']['email'],$event['developer']['name']);
    $message=assignment_message($event,$settings['app_url']);
    $mail->Subject=$message['subject'];$mail->Body=$message['body'];$mail->send();
}
/** Email runs only after the board write succeeds. Failure must never undo saved feedback. */
function notify_assignments(array $before, array $after, ?array $settings=null, ?callable $transport=null): array {
    $events=assignment_notifications($before,$after);
    if(!$events)return ['sent'=>0,'failed'=>0,'status'=>'not_needed'];
    try {
        if($settings===null){
            if(!is_file(__DIR__.'/smtp-config.php'))return ['sent'=>0,'failed'=>count($events),'status'=>'not_configured'];
            $settings=require __DIR__.'/smtp-config.php';
        }
        if(!is_array($settings) || empty($settings['enabled']) || empty($settings['password']) || $settings['password']==='REPLACE_WITH_MAILBOX_PASSWORD')return ['sent'=>0,'failed'=>count($events),'status'=>'not_configured'];
        $sent=0;$failed=0;
        foreach($events as $index=>$event){
            // Bound request time if a bulk edit assigns many items at once.
            if($index>=5){$failed++;continue;}
            try {($transport??'deliver_assignment')($event,$settings);$sent++;}
            catch(Throwable $e){$failed++;error_log('Loop assignment email delivery failed; feedback was saved.');}
        }
        return ['sent'=>$sent,'failed'=>$failed,'status'=>$failed?'failed':'sent'];
    } catch(Throwable $e){
        error_log('Loop assignment email configuration failed; feedback was saved.');
        return ['sent'=>0,'failed'=>count($events),'status'=>'failed'];
    }
}
