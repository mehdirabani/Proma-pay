<?php
require_once dirname(__DIR__).'/src/Services/IranianPhoneNumberNormalizer.php';
require_once dirname(__DIR__).'/src/Services/BaleSafirPayloadBuilder.php';
use Proma\Plugins\SignConnect\Services\IranianPhoneNumberNormalizer as Phone;
use Proma\Plugins\SignConnect\Services\BaleSafirPayloadBuilder;
$errors=[];
foreach(['09123456789','989123456789','+98 912-345-6789','۰۹۱۲۳۴۵۶۷۸۹']as$v)try{if(Phone::normalize($v)!=='989123456789')$errors[]='phone:'.$v;}catch(Throwable$e){$errors[]='phone-throw:'.$v;}
foreach(['123','98912345678a','00999123456789']as$v){try{Phone::normalize($v);$errors[]='invalid-accepted:'.$v;}catch(InvalidArgumentException$e){}}
$builder=new BaleSafirPayloadBuilder();$payload=$builder->build(['type'=>'text','to'=>'09123456789','text'=>'آزمایش'],'11111111-1111-4111-8111-111111111111','123456');
if(($payload['phone_number']??'')!=='989123456789'||($payload['message_data']['message']['text']??'')!=='آزمایش')$errors[]='payload';
try{$builder->build(['type'=>'otp','to'=>'09123456789','otp'=>'12x4'],'11111111-1111-4111-8111-111111111111','123456');$errors[]='otp-invalid-accepted';}catch(InvalidArgumentException$e){}
echo $errors?"FAIL\n".implode("\n",$errors)."\n":"PASS: professional settings services\n";exit($errors?1:0);
