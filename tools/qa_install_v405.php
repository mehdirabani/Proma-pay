<?php
declare(strict_types=1);
$mode=$argv[1]??'qa'; $root=dirname(__DIR__);
$qaApp='C:/laragon/www/proma-qa-update-v405-20261008'; $qaDb='proma_qa_update_v405_20261008';
$app=$mode==='live' ? 'C:/laragon/www/proma' : $qaApp;
$_SERVER['REQUEST_METHOD']='CLI'; $_SERVER['REMOTE_ADDR']='198.51.100.48';
if($mode==='init') {
    if(is_dir($qaApp)) throw new RuntimeException('QA installation already exists');
    $config=require 'C:/laragon/www/proma/config/database.php';
    $server=new PDO('mysql:host='.$config['host'].';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $exists=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$exists->execute([$qaDb]);
    if($exists->fetchColumn()) throw new RuntimeException('QA database already exists');
    $zip=new ZipArchive(); if($zip->open($root.'/dist/candidates/v4.0.4/core/PromaPay-Full-v4.0.4.zip')!==true) throw new RuntimeException('Missing verified baseline');
    $zip->extractTo($qaApp);$zip->close();
    require 'C:/laragon/www/proma/bootstrap.php';
    if(Model::db()->query('SELECT DATABASE()')->fetchColumn()!=='proma_pay_restore_20261007') throw new RuntimeException('Unexpected source DB');
    $dump=(new ReflectionMethod(BackupService::class,'databaseDump'))->invoke(null);
    $server->exec("CREATE DATABASE `$qaDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $config['database']=$qaDb;
    file_put_contents($qaApp.'/config/database.php',"<?php\nreturn ".var_export($config,true).";\n");file_put_contents($qaApp.'/installed.lock','qa=1');
    $pdo=new PDO('mysql:host='.$config['host'].';dbname='.$qaDb.';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    (new ReflectionProperty(Model::class,'pdo'))->setValue(null,$pdo);
    (new ReflectionMethod(BackupService::class,'runSql'))->invoke(null,$dump);
    echo "FRESH_V404_UPDATE_CLONE_CREATED_WITH_CURRENT_DATA\n";exit;
}
if(!in_array($mode,['qa','live'],true)) throw new RuntimeException('Unknown mode');
if($mode==='live') {
    $proof=$root.'/storage/qa-v405-update-result.json'; if(!is_file($proof)||!json_decode(file_get_contents($proof),true)['all_preexisting_values_preserved']) throw new RuntimeException('QA update proof required');
}
require $app.'/bootstrap.php';
$db=Model::db()->query('SELECT DATABASE()')->fetchColumn();
if($db!==($mode==='live'?'proma_pay_restore_20261007':$qaDb)) throw new RuntimeException('Wrong update database');
$version=require $app.'/config/version.php';if($version['application']!=='4.0.4') throw new RuntimeException('Exact V4.0.4 required');
require_once $root.'/helpers/RestoreIntegrityService.php';$before=RestoreIntegrityService::capture();
RequestMaintenanceLock::exclusive();
$name='proma-update-2026-10-08-10-00-00-v405.zip';
if(!is_dir($app.'/storage/updates')) mkdir($app.'/storage/updates',0755,true);
copy($root.'/dist/candidates/v4.0.5/updates/PromaPay-Update-v4.0.5.zip',$app.'/storage/updates/'.$name);
$result=ScriptUpdateService::install($name);
RestoreIntegrityService::assertPreserved($before);
$version=require $app.'/config/version.php';if($version['application']!=='4.0.5') throw new RuntimeException('New version missing');
file_put_contents($root.'/storage/qa-v405-'.($mode==='live'?'local-install':'update').'-result.json',json_encode(['version'=>'4.0.5','all_preexisting_values_preserved'=>true,'files'=>count($result['installed_files']??[]),'migrations'=>$result['migrations_count']??null,'safety_backup'=>$result['backup_name']??$result['backup']??null,'update_sha256'=>hash_file('sha256',$root.'/dist/candidates/v4.0.5/updates/PromaPay-Update-v4.0.5.zip')],JSON_PRETTY_PRINT));
echo strtoupper($mode)."_PACKAGED_404_TO_405_OK_ALL_PREEXISTING_VALUES_PRESERVED\n";
RequestMaintenanceLock::shared();
