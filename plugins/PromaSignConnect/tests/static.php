<?php
$root=dirname(__DIR__);$errors=[];$manifest=json_decode(file_get_contents($root.'/plugin.json'),true);
foreach(['id','version','provider','migrations','permissions','routes']as$key)if(empty($manifest[$key]))$errors[]="manifest:$key";
if(!preg_match('/^\d+\.\d+\.\d+$/',(string)($manifest['version']??'')))$errors[]='stable-semver-version-required';
$all='';$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));foreach($it as$f)if($f->isFile()&&$f->getExtension()==='php'){$all.=file_get_contents($f->getPathname());$out=[];$code=0;exec(PHP_BINARY.' -l '.escapeshellarg($f->getPathname()),$out,$code);if($code)$errors[]='syntax:'.$f->getFilename();}
foreach(['random_int','password_hash','hash_equals','FOR UPDATE','internal_electronic_acceptance']as$needle)if(strpos($all,$needle)===false)$errors[]='missing:'.$needle;
foreach(['api-access-key','PROMA_SIGNATURE_HMAC_KEY','TelegramLinkTokenService','BaleSafirPayloadBuilder','NotificationRuleResolver']as$needle)if(strpos($all,$needle)===false)$errors[]='advanced-missing:'.$needle;
foreach(['error_log($code','password_hash($code .']as$needle)if(strpos($all,$needle)!==false)$errors[]='secret-risk:'.$needle;
if(preg_match('/api-access-key[\'"]?\s*[:=]\s*[\'"][A-Za-z0-9_-]{20,}/',$all))$errors[]='real-bale-key-detected';
$authController=dirname($root,2).'/controllers/AuthController.php';
if(is_file($authController)&&!preg_match('/substr\\(\\$mobile,\\s*-4\\)/',(string)file_get_contents($authController)))$errors[]='existing-password-login-not-found';
echo $errors?("FAIL\n".implode("\n",$errors)."\n"):"PASS: static release checks\n";exit($errors?1:0);
