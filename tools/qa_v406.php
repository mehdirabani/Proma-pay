<?php
declare(strict_types=1);
$mode=$argv[1]??'inspect';$root=dirname(__DIR__);$app='C:/laragon/www/proma-qa-v406-20261008';$name='proma_qa_v406_20261008';
$config=require 'C:/laragon/www/proma/config/database.php';
if($mode==='init') {
    $server=new PDO('mysql:host='.$config['host'].';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $exists=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$exists->execute([$name]);if($exists->fetchColumn()||is_dir($app)) throw new RuntimeException('QA target already exists');
    $z=new ZipArchive();if($z->open($root.'/dist/candidates/archive/v4/v4.0.4/core/PromaPay-Full-v4.0.4.zip')!==true) throw new RuntimeException('Baseline absent');$z->extractTo($app);$z->close();
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$config['database']=$name;
    file_put_contents($app.'/config/database.php',"<?php\nreturn ".var_export($config,true).";\n");file_put_contents($app.'/installed.lock','qa=1');
}
$_SERVER['REQUEST_METHOD']='CLI';$_SERVER['REMOTE_ADDR']='198.51.100.46';require $app.'/bootstrap.php';
if(Model::db()->query('SELECT DATABASE()')->fetchColumn()!==$name) throw new RuntimeException('Wrong owned QA database');
if($mode==='init') {
    $z=new ZipArchive();$z->open('C:/Users/pgmme/Downloads/proma-pay-backup-2026-10-07-21-31-06-0ddc3955.zip');
    (new ReflectionMethod(BackupService::class,'runSql'))->invoke(null,$z->getFromName('database.sql'));$z->close();
    RestoreSchemaService::repair(static fn($sql)=>(new ReflectionMethod(BackupService::class,'runSql'))->invoke(null,$sql));
    echo "QA_V406_FROM_V404_AND_REAL_BACKUP_CREATED\n";
}
if($mode==='overlay') {
    foreach(['helpers/BackupService.php','helpers/FilesystemPathGuard.php','helpers/ScriptUpdateService.php','controllers/UpdatesController.php','views/settings/index.php','assets/js/app.js'] as $file) copy($root.'/'.$file,$app.'/'.$file);
    echo "QA_V406_MAINTENANCE_FIX_OVERLAY_OK\n";
}
