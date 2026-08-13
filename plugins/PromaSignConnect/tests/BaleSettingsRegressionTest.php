<?php
$root=dirname(__DIR__);$errors=[];
require_once $root.'/src/Services/BaleCapabilityCatalog.php';
$catalog=\Proma\Plugins\SignConnect\Services\BaleCapabilityCatalog::all();
$keys=[];foreach($catalog as$group){if(empty($group['title']))$errors[]='group-title';foreach($group['items'] as$key=>$item){$keys[]=$key;if(empty($item[0])||empty($item[1]))$errors[]='label:'.$key;}}
foreach(['text_message','otp_message','contract_signature_request','installment_overdue','security_otp']as$key)if(!in_array($key,$keys,true))$errors[]='missing:'.$key;
$service=file_get_contents($root.'/src/Services/BaleAccountService.php');
foreach(["configured_not_verified","\\Model::begin()","SecretCipher::encrypt","شناسه عددی بازو معتبر نیست"]as$needle)if(strpos($service,$needle)===false)$errors[]='service:'.$needle;
if(strpos($service,'BaleSafirProvider::send')!==false)$errors[]='provider-called-during-save';
$view=file_get_contents($root.'/views/settings-center.php');
foreach(['عنوان داخلی بازو','شناسه عددی بازو','توکن وب‌سرویس بله','ارسال پیام آزمایشی','فعال‌سازی بازو']as$needle)if(strpos($view,$needle)===false)$errors[]='view:'.$needle;
echo $errors?"FAIL\n".implode("\n",$errors)."\n":"PASS: Bale settings regression\n";exit($errors?1:0);

