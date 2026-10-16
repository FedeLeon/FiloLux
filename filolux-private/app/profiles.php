<?php
declare(strict_types=1);
function profileFields(): array {
    return ['phone'=>40,'street'=>160,'street_number'=>20,'apartment'=>100,'city'=>100,'province'=>100,'postal_code'=>12];
}
function hasUserProfiles(): bool {
    static $available;
    return $available ??= (int)query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME IN ('phone','street','street_number','apartment','city','province','postal_code')")->fetchColumn()===7;
}
function userProfile(array $u): array {
    if(!hasUserProfiles()) return $u+array_fill_keys(array_keys(profileFields()),'');
    return one('SELECT id,name,email,phone,street,street_number,apartment,city,province,postal_code FROM users WHERE id=?',[$u['id']]);
}
function profileInput(bool $shipping=false): array {
    $data=[];
    foreach(profileFields() as $field=>$max) {
        if(isset($_POST[$field]) && !is_string($_POST[$field])) throw new InvalidArgumentException('Revisá tus datos de contacto y dirección.');
        $data[$field]=textInput($field,$max,$shipping && !in_array($field,['phone','apartment'],true));
    }
    if($data['phone']!=='' && (!preg_match('/^[+\d\s().-]+$/D',$data['phone']) || strlen(preg_replace('/\D/','',$data['phone']))<7 || strlen(preg_replace('/\D/','',$data['phone']))>15)) throw new InvalidArgumentException('Ingresá un teléfono válido, con código de área.');
    return $data;
}
function shippingAddress(array $data): string {
    return $data['street'].' '.$data['street_number'].($data['apartment']!==''?' · '.$data['apartment']:'')."\n".$data['city'].', '.$data['province']."\nCódigo postal: ".$data['postal_code'];
}
if($path==='/cuenta/perfil') {
    $u=requireUser(); $profile=userProfile($u); $error='';
    if($post) {
        try {
            if(!hasUserProfiles()) throw new InvalidArgumentException('El perfil todavía no está habilitado. Falta importar la migración de perfiles.');
            $name=textInput('name',120); $data=profileInput();
            query('UPDATE users SET name=?,phone=?,street=?,street_number=?,apartment=?,city=?,province=?,postal_code=? WHERE id=?',[$name,...array_values($data),$u['id']]);
            flash('Tus datos se guardaron. Los usaremos para completar tu próxima compra.'); redirect('/cuenta/perfil');
        } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); $profile=array_replace($profile,array_intersect_key(array_filter($_POST,'is_string'),array_flip(['name',...array_keys(profileFields())]))); }
    }
    render('profile',['title'=>'Mi perfil','profile'=>$profile,'error'=>$error]); return;
}
