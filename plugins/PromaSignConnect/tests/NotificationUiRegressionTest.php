<?php
$root=dirname(__DIR__);$errors=[];$view=file_get_contents($root.'/views/settings-center.php');$css=file_get_contents($root.'/assets/css/sign-connect.css');
foreach(['خدماتی','مالی','محرمانه','امنیتی','حقوقی','psc-event-summary','psc-event-group','psc-rules-table','data-dirty-count']as$needle)if(strpos($view,$needle)===false)$errors[]='view:'.$needle;
foreach(['@media(max-width:900px)','psc-capability-cards','psc-rules-table td:not(:first-child)','psc-event-summary']as$needle)if(strpos($css,$needle)===false)$errors[]='css:'.$needle;
echo $errors?"FAIL\n".implode("\n",$errors)."\n":"PASS: notification UI regression\n";exit($errors?1:0);

