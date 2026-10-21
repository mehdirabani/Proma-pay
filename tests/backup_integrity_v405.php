<?php
declare(strict_types=1);
require dirname(__DIR__) . '/helpers/RestoreIntegrityService.php';
class IntegrityResult {
    private int $position=0;
    public function __construct(private array $rows) {}
    public function fetchAll($mode=null) { return $this->rows; }
    public function fetch($mode=null) { return $this->rows[$this->position++] ?? false; }
    public function closeCursor() {}
}
class IntegrityDb {
    public array $columns=['id','value','amount'];
    public array $rows=[[1,"فارسی\0binary",'12.00'],[2,null,'0'],[2,null,'0']];
    public function query($sql) {
        if(str_starts_with($sql,'SHOW FULL')) return new IntegrityResult(['legacy']);
        if(str_starts_with($sql,'SHOW COLUMNS')) return new IntegrityResult($this->columns);
        if(str_starts_with($sql,'SELECT')) return new IntegrityResult($this->rows);
        throw new RuntimeException('Unexpected query');
    }
}
class Model { public static IntegrityDb $connection; public static function db() {return self::$connection;} }
Model::$connection=new IntegrityDb(); $db=Model::$connection;
$before=RestoreIntegrityService::capture();
$db->rows[]=[3,'new','100.00']; RestoreIntegrityService::assertPreserved($before);
foreach(['value-change','null-change','duplicate-loss'] as $case) {
    $db->rows=[[1,"فارسی\0binary",'12.00'],[2,null,'0'],[2,null,'0']];
    if($case==='value-change') $db->rows[0][2]='12.01';
    if($case==='null-change') $db->rows[1][1]='';
    if($case==='duplicate-loss') array_pop($db->rows);
    $rejected=false; try{RestoreIntegrityService::assertPreserved($before);}catch(RuntimeException $e){$rejected=true;}
    if(!$rejected) throw new RuntimeException('Data loss accepted: '.$case);
}
require dirname(__DIR__).'/helpers/BackupService.php';
$split=new ReflectionMethod(BackupService::class,'splitSqlStatements');
$statements=iterator_to_array($split->invoke(null,"INSERT INTO users (id,note) VALUES (1,'CREATE TABLE fake (id INT);'); CREATE TABLE real (id INT);"));
$names=[]; foreach($statements as $statement) if(preg_match('/^\s*CREATE\s+TABLE\s+(\w+)/i',$statement,$m)) $names[]=$m[1];
if($names!==['real']) throw new RuntimeException('SQL in a data literal declared a table');
echo "BACKUP_FULL_VALUE_MULTISET_INTEGRITY_V405_OK\n";
