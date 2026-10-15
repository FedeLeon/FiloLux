<?php
declare(strict_types=1);
$values=['name'=>'','phone'=>'','message'=>''];
$error='';
if($post) {
    foreach($values as $field=>$_) $values[$field]=is_string($_POST[$field]??null)?trim($_POST[$field]):'';
    try {
        if(!hasContactMessages()) abortPage(503,'Todavía no podemos recibir consultas. Podés usar los datos de nuestra sección de contacto.');
        $token=is_string($_POST['submission_key']??null)?$_POST['submission_key']:'';
        if(!isset($_SESSION['inquiry_tokens'][$token])) abortPage(419,'El formulario venció. Recargá la página e intentá nuevamente.');
        // A repeated submission from this session receives the same confirmation.
        $existing=one('SELECT id FROM contact_messages WHERE submission_key=?',[$token]);
        if(!$existing) {
            if($values['name']==='' || mb_strlen($values['name'])>120) throw new InvalidArgumentException('Ingresá tu nombre (hasta 120 caracteres).');
            $digits=preg_replace('/\D/','',$values['phone']);
            if(!preg_match('/^\+?[0-9\s().-]+$/D',$values['phone']) || mb_strlen($values['phone'])>40 || strlen($digits)<7 || strlen($digits)>15) throw new InvalidArgumentException('Ingresá un teléfono válido, con código de área.');
            if($values['message']==='' || mb_strlen($values['message'])>5000) throw new InvalidArgumentException('Escribí tu mensaje (hasta 5000 caracteres).');
            rateLimit('inquiries',5,900);
            query('INSERT INTO contact_messages (name,phone,message,submission_key) VALUES (?,?,?,?)',[$values['name'],$values['phone'],$values['message'],$token]);
        }
        if(wantsJson()) jsonResponse(['message'=>'Recibimos tu idea. Te vamos a contactar al teléfono que nos dejaste.','next_key'=>inquiryToken()]);
        flash('Recibimos tu idea. Te vamos a contactar al teléfono que nos dejaste.');
        redirect('/a-medida');
    } catch(InvalidArgumentException $exception) {
        if(wantsJson()) jsonResponse(['error'=>$exception->getMessage()],422);
        $error=$exception->getMessage(); http_response_code(422);
    }
}
render('inquiry-page',['title'=>'Contanos tu idea','values'=>$values,'error'=>$error]);
