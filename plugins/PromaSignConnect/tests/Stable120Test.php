<?php
$root=dirname(__DIR__);$errors=[];$read=static fn($file)=>(string)file_get_contents($root.'/'.$file);
$manifest=json_decode($read('plugin.json'),true);
if(($manifest['version']??'')!=='1.2.1')$errors[]='version';
if(!in_array('migrations/2026_08_01_stable_1_2_0.sql',$manifest['migrations']??[],true))$errors[]='migration-registration';
$migration=$read('migrations/2026_08_01_stable_1_2_0.sql');
foreach(['proma_connect_template_variables','proma_connect_variable_provider_mappings','proma_connect_broadcast_campaigns','uq_connect_broadcast_delivery']as$needle)if(strpos($migration,$needle)===false)$errors[]='migration:'.$needle;
$service=$read('src/Services/BaleAccountService.php');$routing=$read('src/Services/ProviderRegistry.php');$mapping=$read('src/Services/ProviderEventMappingService.php');$capabilities=$read('src/Services/ProviderCapabilityCatalog.php');$variables=$read('src/Services/TemplateVariableRegistry.php');$view=$read('views/settings-center.php');
foreach(['حداقل یک رویداد قابل ارسال','connectionChanged',"action==='archive'",'remove_token']as$needle)if(strpos($service,$needle)===false)$errors[]='bale-service:'.$needle;
foreach(['bale_event_not_permitted','bale_capability_not_permitted','provider_account_id=c.id']as$needle)if(strpos($routing,$needle)===false)$errors[]='routing:'.$needle;
foreach(['saveForProviderAccount','requiredCapability','حداقل یک رویداد']as$needle)if(strpos($mapping,$needle)===false)$errors[]='mapping:'.$needle;
foreach(['resolve','signature_requested','contract_signature_request']as$needle)if(strpos($capabilities,$needle)===false)$errors[]='capability-resolution:'.$needle;
foreach(['mapValues','source_resolver','provider_parameter']as$needle)if(strpos($variables,$needle)===false)$errors[]='variables:'.$needle;
foreach(['ویرایش تنظیمات','دسترسی‌های ارسال رویداد','رجیستری متغیرها','جایگزینی توکن وب‌سرویس']as$needle)if(strpos($view,$needle)===false)$errors[]='view:'.$needle;
echo $errors?"FAIL\n".implode("\n",$errors)."\n":"PASS: stable 1.2.1 management and registry\n";exit($errors?1:0);
