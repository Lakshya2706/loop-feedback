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
function smtp_error_reason(Throwable $error): string {
    $text=strtolower($error->getMessage());
    if(str_contains($text,'certificate') || str_contains($text,'crypto') || str_contains($text,'ssl operation'))return 'tls';
    if(str_contains($text,'authenticate') || str_contains($text,'authentication') || str_contains($text,'535'))return 'authentication';
    if(str_contains($text,'recipient') || str_contains($text,'recipients failed'))return 'recipient';
    if(str_contains($text,'connect') || str_contains($text,'timed out') || str_contains($text,'getaddrinfo'))return 'connection';
    return 'server';
}
function smtp_reason_message(string $reason): string {
    return match($reason){
        'tls'=>'The SMTP server certificate could not be verified. Ask BigRock for the correct SSL/TLS mail hostname.',
        'authentication'=>'The SMTP server rejected the mailbox login. Check the full email username and mailbox password.',
        'recipient'=>'The SMTP server rejected the recipient address.',
        'connection'=>'The hosting server could not connect to the SMTP server on the configured port.',
        'not_configured'=>'Email setup is incomplete. Check smtp-config.php in cPanel.',
        default=>'The mail server rejected the sending attempt. Check cPanel Track Delivery or ask BigRock for the SMTP error.',
    };
}
function smtp_settings(): ?array {
    if(!is_file(__DIR__.'/smtp-config.php'))return null;
    $settings=require __DIR__.'/smtp-config.php';
    if(!is_array($settings) || empty($settings['enabled']) || empty($settings['password']) || $settings['password']==='REPLACE_WITH_MAILBOX_PASSWORD')return null;
    return $settings;
}
function configured_mailer(array $settings): PHPMailer\PHPMailer\PHPMailer {
    require_once __DIR__.'/lib/PHPMailer/Exception.php';
    require_once __DIR__.'/lib/PHPMailer/SMTP.php';
    require_once __DIR__.'/lib/PHPMailer/PHPMailer.php';
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();$mail->Host=$settings['host'];$mail->Port=(int)$settings['port'];
    $mail->SMTPAuth=true;$mail->Username=$settings['username'];$mail->Password=$settings['password'];
    $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Timeout=8;$mail->Timelimit=10;$mail->CharSet='UTF-8';$mail->SMTPDebug=0;
    return $mail;
}
function smtp_connection_check(): array {
    $mail=null;
    try {
        $settings=smtp_settings();
        if(!$settings)return ['ok'=>false,'stage'=>'not_configured','message'=>smtp_reason_message('not_configured')];
        $mail=configured_mailer($settings);
        if(!$mail->smtpConnect())throw new RuntimeException($mail->ErrorInfo?:'SMTP connection failed');
        return ['ok'=>true,'stage'=>'authenticated','message'=>'Encrypted SMTP connection and mailbox authentication succeeded. No email was sent. For missing messages, check cPanel Track Delivery.'];
    } catch(Throwable $e){$reason=smtp_error_reason($e);return ['ok'=>false,'stage'=>$reason,'message'=>smtp_reason_message($reason)];}
    finally {if($mail)$mail->smtpClose();}
}
function deliver_assignment(array $event, array $settings): void {
    $mail=configured_mailer($settings);
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
        $sent=0;$failed=0;$reasons=[];
        foreach($events as $index=>$event){
            // Bound request time if a bulk edit assigns many items at once.
            if($index>=5){$failed++;continue;}
            try {($transport??'deliver_assignment')($event,$settings);$sent++;}
            catch(Throwable $e){$failed++;$reason=smtp_error_reason($e);$reasons[]=$reason;error_log('Loop assignment email failed: '.$reason.'; feedback was saved.');}
        }
        return ['sent'=>$sent,'failed'=>$failed,'status'=>$failed?'failed':'sent','message'=>$failed?smtp_reason_message($reasons[0]??'server'):'Assignment email accepted by the SMTP server. This does not confirm inbox delivery.'];
    } catch(Throwable $e){
        error_log('Loop assignment email configuration failed; feedback was saved.');
        return ['sent'=>0,'failed'=>count($events),'status'=>'failed'];
    }
}
