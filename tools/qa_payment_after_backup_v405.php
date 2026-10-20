<?php
declare(strict_types=1);
$_SERVER['REQUEST_METHOD']='CLI'; $_SERVER['REMOTE_ADDR']='198.51.100.47';
$app='C:/laragon/www/proma-qa-backup-roundtrip-v405-20261008';
require $app.'/bootstrap.php';
$config=require $app.'/config/database.php';
if($config['database']!=='proma_qa_backup_roundtrip_v405_20261008'||Model::db()->query('SELECT DATABASE()')->fetchColumn()!==$config['database']) throw new RuntimeException('Owned payment QA database required');
$before=RestoreIntegrityService::capture(); $settings=Model::fetchAll('SELECT * FROM settings');
$lockName='proma_outbox_'.substr(hash('sha256',$config['database']),0,32);
if((int)Model::fetch('SELECT GET_LOCK(?,0) AS acquired',[$lockName])['acquired']!==1) throw new RuntimeException('Could not isolate the financial tests from background jobs');
$environment=getenv();
$environment['PROMA_TEST_DB_DSN']='mysql:host='.$config['host'].';dbname='.$config['database'].';charset=utf8mb4';
$environment['PROMA_TEST_DB_USER']=$config['username']; $environment['PROMA_TEST_DB_PASSWORD']=$config['password'];
try {
    foreach(['tests/integration_payment_retry_v404.php','tests/integration_payment_race_v404.php'] as $test) {
        $process=proc_open([PHP_BINARY,dirname(__DIR__).'/'.$test],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$environment);
        fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0) throw new RuntimeException('Payment test failed: '.$test.' '.$err);
        echo $out;
    }
} finally {
    Model::fetch('SELECT RELEASE_LOCK(?) AS released',[$lockName]);
    foreach($settings as $row) {
        $fields=array_keys($row); $update=implode(',',array_map(static fn($name)=>'`'.$name.'`=?',$fields));
        Model::execute('UPDATE settings SET '.$update.' WHERE id=?',array_merge(array_values($row),[$row['id']]));
    }
}
RestoreIntegrityService::assertPreserved($before);
echo "PAYMENT_AFTER_CONVERTED_BACKUP_V405_OK_ALL_PREEXISTING_ROWS_PRESERVED\n";
