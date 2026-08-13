<?php
$root=dirname(__DIR__);$errors=[];$read=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
$manifest=json_decode($read('plugin.json'),true);$view=$read('views/settings-center.php');$controller=$read('src/Controllers/BaleController.php');$service=$read('src/Services/ProviderTestService.php');$testController=$read('src/Controllers/ProviderTestController.php');$migration=$read('migrations/2026_08_01_provider_tests_1_2_1.sql');$css=$read('assets/css/sign-connect.css');
if(strpos($view,"settings/bale?edit")!==false||strpos($controller,"settings/bale?edit")!==false)$errors[]='bale-query-embedded-in-route';
foreach(["['edit'=>",'ویرایش تنظیمات'] as $needle)if(strpos($view.$controller,$needle)===false)$errors[]='bale-route:'.$needle;
$route=false;foreach($manifest['routes']??[] as $item)if(($item['path']??'')==='plugin/sign-connect/provider/{provider}/test'&&($item['method']??'')==='POST')$route=true;
if(!$route)$errors[]='provider-test-route';
foreach(['ippanel','smsir','telegram','recipient_hash','provider_test_sent'] as $needle)if(strpos($service,$needle)===false)$errors[]='provider-test-service:'.$needle;
foreach(['onlyPost','ErrorHandler::requestId','settings/'] as $needle)if(strpos($testController,$needle)===false)$errors[]='provider-test-controller:'.$needle;
foreach(['proma_connect_provider_test_deliveries','recipient_hash','request_id'] as $needle)if(strpos($migration,$needle)===false)$errors[]='provider-test-migration:'.$needle;
foreach(['IPPanel','SMS.ir','تست ارسال تلگرام','ارسال پیام آزمایشی'] as $needle)if(strpos($view,$needle)===false)$errors[]='provider-test-ui:'.$needle;
foreach(['--proma-brand-700','--proma-radius','psc-test-panel'] as $needle)if(strpos($css,$needle)===false)$errors[]='core-ui-alignment:'.$needle;
echo $errors?"FAIL\n".implode("\n",$errors)."\n":"PASS: provider tests, Bale routing and core UI alignment\n";exit($errors?1:0);
