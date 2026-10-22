<?php
declare(strict_types=1);
// Dedicated databases only. Never import into the active installation.
$mode = $argv[1] ?? 'inspect';
$root = dirname(__DIR__);
$app = 'C:/laragon/www/proma-qa-backup-v405-20261008';
$source = 'C:/Users/pgmme/Downloads/proma-pay-backup-2026-10-07-21-31-06-0ddc3955.zip';
$rawName = 'proma_qa_backup_raw_v405_20261008';
$targetName = 'proma_qa_backup_v405_20261008';
$converted = 'C:/Users/pgmme/Downloads/proma-pay-backup-2026-10-08-09-42-v405-converted-0ddc3955.zip';
$second = ($argv[2] ?? '') === 'second';
if ($second) { $app = 'C:/laragon/www/proma-qa-backup-roundtrip-v405-20261008'; $targetName = 'proma_qa_backup_roundtrip_v405_20261008'; }
$config = require 'C:/laragon/www/proma/config/database.php';
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_STRINGIFY_FETCHES=>true];
$server = new PDO('mysql:host='.$config['host'].';charset=utf8mb4', $config['username'], $config['password'], $options);
if ($mode === 'init') {
    foreach ($second ? [$targetName] : [$rawName,$targetName] as $name) {
        $s=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?'); $s->execute([$name]);
        if ($s->fetchColumn()) throw new RuntimeException('Owned database already exists: '.$name);
    }
    if (is_dir($app)) throw new RuntimeException('QA app already exists');
    $z=new ZipArchive(); if ($z->open($root.'/dist/candidates/archive/v4/v4.0.4/core/PromaPay-Full-v4.0.4.zip')!==true) throw new RuntimeException('Missing verified archive');
    $z->extractTo($app); $z->close();
    foreach ($second ? [$targetName] : [$rawName,$targetName] as $name) $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['helpers/BackupService.php','helpers/RestoreIntegrityService.php','core/PluginManager.php','controllers/BackupController.php','views/backup/data.php','views/settings/index.php','config/version.php','config/settings.php','manifest.json','package.json','service-worker.js'] as $file) {
        if (!is_dir(dirname($app.'/'.$file))) mkdir(dirname($app.'/'.$file),0755,true);
        copy($root.'/'.$file,$app.'/'.$file);
    }
    $config['database']=$targetName;
    file_put_contents($app.'/config/database.php', "<?php\nreturn ".var_export($config,true).";\n");
    file_put_contents($app.'/installed.lock','qa=1');
}
$_SERVER['REQUEST_METHOD']='CLI'; $_SERVER['REMOTE_ADDR']='198.51.100.45';
require $app.'/bootstrap.php';
if (Model::db()->query('SELECT DATABASE()')->fetchColumn() !== $targetName) throw new RuntimeException('Wrong database');
$raw=new PDO('mysql:host='.$config['host'].';dbname='.$rawName.';charset=utf8mb4',$config['username'],$config['password'],$options);
$target=Model::db(); $target->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,true);
$z=new ZipArchive(); if ($z->open($source)!==true) throw new RuntimeException('Missing requested backup');
$sql=$z->getFromName('database.sql');
$run=new ReflectionMethod(BackupService::class,'runSql');
if ($mode === 'native-reference') {
    $sqlPath=$root.'/storage/qa-v405-source-native.sql'; file_put_contents($sqlPath,$sql);
    $host=explode(';',$config['host'])[0]; $port=preg_match('/;port=(\d+)/',$config['host'],$m) ? (int)$m[1] : 3306;
    $command=['C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe','--default-character-set=utf8mb4','--host='.$host,'--port='.$port,'--user='.$config['username'],$rawName];
    $environment=getenv(); $environment['MYSQL_PWD']=$config['password'];
    $process=proc_open($command,[0=>['file',$sqlPath,'r'],1=>['file',$root.'/storage/qa-v405-native-output.log','w'],2=>['file',$root.'/storage/qa-v405-native-error.log','w']],$pipes,null,$environment);
    if(!is_resource($process) || proc_close($process)!==0) throw new RuntimeException('Independent native reference import failed; inspect private log');
    unlink($sqlPath); echo "INDEPENDENT_NATIVE_MYSQL_REFERENCE_IMPORT_OK\n";
}
if ($mode==='init') {
    $prop=new ReflectionProperty(Model::class,'pdo');
    if (!$second) { $prop->setValue(null,$raw); $run->invoke(null,$sql); $prop->setValue(null,$target); }
    $run->invoke(null,$sql);
    echo "REQUESTED_BACKUP_IMPORTED_IN_TWO_OWNED_DATABASES\n";
}
if ($mode==='restore') {
    $restoreSource = $second ? $converted : $source;
    (new ReflectionMethod(BackupService::class,'restoreZipBackup'))->invoke(null,$restoreSource,basename($restoreSource));
    echo "REQUESTED_BACKUP_RESTORED_WITH_COMPATIBILITY_REPAIR\n";
}
if (in_array($mode,['inspect','init','restore','compare'],true)) {
    $report=['source_sha256'=>hash_file('sha256',$source),'source_version'=>json_decode($z->getFromName('backup-meta.json'),true)['version']??null,'tables'=>[],'failures'=>[]];
    foreach ($raw->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $q='`'.str_replace('`','``',$table).'`';
        $columns=$raw->query('SHOW COLUMNS FROM '.$q)->fetchAll(); $names=array_column($columns,'Field');
        $targetNames=$target->query('SHOW COLUMNS FROM '.$q)->fetchAll(PDO::FETCH_COLUMN);
        $missing=array_values(array_diff($names,$targetNames));
        $projection=implode(',',array_map(static fn($n)=>'`'.str_replace('`','``',$n).'`',$names));
        // A sorted multiset of row digests handles tables without primary keys and preserves duplicates.
        $fingerprint=static function(PDO $db) use ($q,$projection) { $hashes=[]; $stmt=$db->query('SELECT '.$projection.' FROM '.$q); while($row=$stmt->fetch(PDO::FETCH_NUM)) $hashes[]=hash('sha256',serialize($row)); sort($hashes,SORT_STRING); return [count($hashes),hash('sha256',implode('\n',$hashes)),array_count_values($hashes)]; };
        [$count,$hash,$oldRows]=$fingerprint($raw); [$newCount,$newHash,$newRows]=$missing ? [null,null,[]] : $fingerprint($target);
        $same=$missing===[];
        foreach ($oldRows as $rowHash=>$copies) if (($newRows[$rowHash]??0)<$copies) $same=false;
        $indexSignature=static function(PDO $db) use($q) { $signatures=[]; foreach($db->query('SHOW INDEX FROM '.$q)->fetchAll() as $index) { $v=[]; foreach(['Key_name','Non_unique','Seq_in_index','Column_name','Sub_part','Collation','Index_type','Expression','Visible'] as $field) $v[$field]=$index[$field]??null; $signatures[]=json_encode($v); } return $signatures; };
        $indexesPreserved=array_diff($indexSignature($raw),$indexSignature($target))===[];
        $same=$same&&$indexesPreserved;
        $report['tables'][$table]=['rows'=>$count,'columns'=>count($names),'target_rows'=>$newCount,'added_columns'=>array_values(array_diff($targetNames,$names)),'indexes_preserved'=>$indexesPreserved,'preserved'=>$same,'source_hash'=>$hash,'target_hash'=>$newHash];
        if(!$same) $report['failures'][]=$table;
    }
    $report['source_tables']=count($report['tables']); $report['source_rows']=array_sum(array_column($report['tables'],'rows'));
    $report['source_columns']=array_sum(array_column($report['tables'],'columns'));
    $report['target_tables']=count($target->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll());
    $foreignKeys=static function(PDO $db):array {
        $rows=$db->query("SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.ORDINAL_POSITION,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn($row)=>json_encode($row),$rows);
    };
    $sourceKeys=$foreignKeys($raw); $report['foreign_key_columns']=count($sourceKeys); $report['foreign_keys_preserved']=array_diff($sourceKeys,$foreignKeys($target))===[];
    if(!$report['foreign_keys_preserved']) $report['failures'][]='foreign-keys';
    $report['non_table_objects']=(int)$raw->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE<>'BASE TABLE'")->fetchColumn()+(int)$raw->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn()+(int)$raw->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()')->fetchColumn();
    $report['files']=[];
    for ($i=0;$i<$z->numFiles;$i++) {
        $name=$z->getNameIndex($i);
        if (str_ends_with($name,'/') || !str_starts_with($name,'storage/')) continue;
        $expected=hash('sha256',$z->getFromIndex($i)); $actual=is_file($app.'/'.$name) ? hash_file('sha256',$app.'/'.$name) : null;
        $report['files'][$name]=['bytes'=>$z->statIndex($i)['size'],'sha256'=>$expected,'restored'=>$actual!==null && hash_equals($expected,$actual)];
        if ($mode!=='init' && $mode!=='inspect' && !$report['files'][$name]['restored']) $report['failures'][]='file:'.$name;
    }
    $snapshot=json_decode($z->getFromName('config-snapshot.json'),true); $effective=Settings::allKeyed(); $report['snapshot_keys']=count($snapshot); $report['snapshot_mismatches']=[];
    foreach ($snapshot as $key=>$value) if (!array_key_exists($key,$effective) || (string)$effective[$key] !== (string)$value) $report['snapshot_mismatches'][]=$key;
    $report['credentials']=[];
    foreach ([['admin','5249911439','Hh13671367'],['operator','09159771019','1019'],['lawyer','5240249490','Mm13791379'],['technician','09158840771','0771']] as [$role,$identifier,$password]) {
        $s=$target->prepare('SELECT role,status,password_hash FROM users WHERE username=? OR mobile=? OR national_id=?'); $s->execute([$identifier,$identifier,$identifier]); $user=$s->fetch();
        $report['credentials'][$role]=['found'=>(bool)$user,'hash_matches'=>$user ? password_verify($password,$user['password_hash']) : false,'role'=>$user['role']??null,'status'=>$user['status']??null];
    }
    file_put_contents($root.'/storage/qa-backup-v405-'.$mode.($second?'-roundtrip':'').'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo json_encode(array_diff_key($report,['tables'=>1,'files'=>1]),JSON_UNESCAPED_UNICODE)."\n";
}
if ($mode==='convert') {
    if (is_file($converted)) throw new RuntimeException('Converted artifact already exists; refusing overwrite');
    $evidence=json_decode(file_get_contents($root.'/storage/qa-backup-v405-compare.json'),true);
    if ($evidence['failures'] || $evidence['snapshot_mismatches']) throw new RuntimeException('Source preservation gate failed');
    $out=new ZipArchive(); if ($out->open($converted,ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('Cannot create converted backup');
    for($i=0;$i<$z->numFiles;$i++) {
        $name=$z->getNameIndex($i); if(in_array($name,['database.sql','backup-meta.json'],true)) continue;
        if(str_ends_with($name,'/')) $out->addEmptyDir(rtrim($name,'/')); else $out->addFromString($name,$z->getFromIndex($i));
    }
    $out->addFromString('database.sql',(new ReflectionMethod(BackupService::class,'databaseDump'))->invoke(null));
    $out->addFromString('backup-meta.json',json_encode(['app'=>'Proma Pay','version'=>'4.0.5','database'=>'mysql','created_at'=>date('c'),'source_sha256'=>hash_file('sha256',$source),'conversion'=>'additive-schema-and-snapshot-defaults'],JSON_PRETTY_PRINT));
    $prefix='storage/restore-archives/'.hash_file('sha256',$source).'/';
    $out->addFile($source,$prefix.'original-backup.zip');
    $out->addFromString($prefix.'original-backup-meta.json',$z->getFromName('backup-meta.json'));
    $out->addFromString($prefix.'preservation-report.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    if(!$out->close()) throw new RuntimeException('ZIP finalization failed');
    echo 'CONVERTED_BACKUP_CREATED_SHA256='.hash_file('sha256',$converted)."\n";
}
$z->close();
