<?php
$root=dirname(__DIR__);$errors=[];
$manifest=json_decode(file_get_contents($root.'/plugin.json'),true);
$migration=file_get_contents($root.'/migrations/2026_07_30_security_signature_policies.sql');
$view=file_get_contents($root.'/views/settings-center.php');
foreach(['proma_connect_module_policies','proma_sign_signer_policies','proma_sign_integrity_runs','proma_connect_legal_holds','proma_connect_otp_security_events']as$table)if(strpos($migration,$table)===false)$errors[]='table:'.$table;
foreach(['mfa','otp-security','signers','hashes','document-versions','evidence-retention']as$section){if(strpos($view,"section==='".$section."'")===false)$errors[]='section:'.$section;if(strpos($view,'module/'.$section.'/save')===false&&$section!=='signers')$errors[]='save-route:'.$section;}
foreach(['نسخه RC حاضر','backend غیرفعال','هیچ موفقیتی صوری ثبت نمی‌شود','زیرساخت این بخش فعال است']as$forbidden)if(strpos($view,$forbidden)!==false)$errors[]='placeholder:'.$forbidden;
if(!in_array('migrations/2026_07_30_security_signature_policies.sql',$manifest['migrations']??[],true))$errors[]='manifest';
echo $errors?"FAIL\n".implode("\n",$errors)."\n":"PASS: security/signature policy structure\n";exit($errors?1:0);

