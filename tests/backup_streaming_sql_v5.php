<?php
declare(strict_types=1);

require dirname(__DIR__) . '/helpers/BackupService.php';

function backupStreamingAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

function backupStreamingParse($sql, $maximumBytes = null)
{
    $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
    if (!$stream) throw new RuntimeException('Could not create SQL test stream.');
    try {
        if (fwrite($stream, $sql) !== strlen($sql)) throw new RuntimeException('Could not write SQL test stream.');
        rewind($stream);
        $parser = new ReflectionMethod(BackupService::class, 'streamSqlStatements');
        return iterator_to_array($parser->invoke(null, $stream, $maximumBytes), false);
    } finally { fclose($stream); }
}

class BackupStreamingMockResult
{
    private array $rows;
    public function __construct(array $rows = []) { $this->rows = $rows; }
    public function fetch($mode = null) { return $this->rows[0] ?? false; }
    public function fetchColumn($column = 0) { return $this->rows[0][$column] ?? 1; }
    public function fetchAll($mode = null) { return $this->rows; }
}

class BackupStreamingMockPdo
{
    public array $queries = [];
    public array $execs = [];
    public bool $transaction = false;
    public function query($sql)
    {
        $this->queries[] = $sql;
        if (str_starts_with($sql, 'SHOW FULL TABLES')) return new BackupStreamingMockResult(['retired_table']);
        if (str_contains($sql, 'AS fk')) return new BackupStreamingMockResult([['fk' => 1, 'mode' => 'STRICT_ALL_TABLES']]);
        return new BackupStreamingMockResult();
    }
    public function exec($sql) { $this->execs[] = $sql; return 0; }
    public function quote($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
    public function inTransaction() { return $this->transaction; }
    public function beginTransaction() { $this->transaction = true; return true; }
    public function commit() { $this->transaction = false; return true; }
    public function rollBack() { $this->transaction = false; return true; }
}

class Model
{
    public static $connection;
    public static function db() { return self::$connection; }
}

class SchemaGuard
{
    public static function clearCache() {}
}

$longValue = str_repeat('x', 1024 * 1024 - 16);
$sql = "\xEF\xBB\xBF-- backup header\r\n"
    . "SET @large='" . $longValue . "';\n"
    . "INSERT INTO users (id,note) VALUES (1,'semi; quote \\' and doubled '' quote /* literal */ -- literal');\n"
    . '/*' . str_repeat('ignored;', 140000) . "*/ SELECT 1;\n"
    . "# trailing comment with ; CREATE TABLE fake (id INT)\nSET @end=1;";
$statements = backupStreamingParse($sql);
backupStreamingAssert(count($statements) === 4, 'Streaming parser lost or split SQL statements across 1 MiB boundaries.');
backupStreamingAssert(str_starts_with($statements[0], "SET @large='") && strlen($statements[0]) > 1024 * 1024 - 32, 'Large quoted values must remain intact.');
backupStreamingAssert(str_contains($statements[1], "semi; quote \\' and doubled '' quote /* literal */ -- literal"), 'Quoted semicolons and comment markers must remain data.');
backupStreamingAssert($statements[2] === 'SELECT 1', 'Block comments must be discarded without splitting statements.');
backupStreamingAssert($statements[3] === 'SET @end=1', 'Line comments at EOF must not leak into the next statement.');
$boundaryComment = '/*' . str_repeat('c', 1024 * 1024 - 5) . '*/SELECT 1;';
backupStreamingAssert(backupStreamingParse($boundaryComment) === ['SELECT 1'], 'A block comment closer crossing a 1 MiB stream boundary must be preserved.');

$legacy = "CREATE TABLE users (id INT); CREATE TABLE contracts (id INT); CREATE TABLE installments (id INT); INSERT INTO users VALUES (1);";
$validator = new ReflectionMethod(BackupService::class, 'validateDatabaseDump');
$validator->invoke(null, $legacy);

$mockPdo = new BackupStreamingMockPdo();
Model::$connection = $mockPdo;
$restoreStream = fopen('php://temp', 'w+b');
fwrite($restoreStream, $legacy);
rewind($restoreStream);
(new ReflectionMethod(BackupService::class, 'replaceDatabaseFromStream'))->invoke(null, $restoreStream);
fclose($restoreStream);
backupStreamingAssert(in_array('DROP TABLE `retired_table`', $mockPdo->execs, true), 'Database replacement must remove tables absent from the dump.');
backupStreamingAssert(count(array_filter($mockPdo->queries, static function ($query) { return str_starts_with($query, 'INSERT INTO'); })) === 1, 'Streaming import must execute INSERT statements.');
backupStreamingAssert(!$mockPdo->transaction, 'Streaming import must commit its grouped INSERT transaction.');

$zipPath = tempnam(sys_get_temp_dir(), 'proma-stream-sql-zip-');
if ($zipPath === false) throw new RuntimeException('Could not create SQL ZIP fixture path.');
$extractedPath = null;
try {
    $archiveSql = $legacy . "\nSET @large='" . $longValue . "';\n";
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create SQL ZIP fixture.');
    if (!$zip->addFromString('database.sql', $archiveSql) || !$zip->close()) throw new RuntimeException('Could not write SQL ZIP fixture.');
    $zip->open($zipPath);
    $database = (new ReflectionMethod(BackupService::class, 'databaseDumpFromZip'))->invoke(null, $zip);
    $zip->close();
    $extractedPath = $database['path'];
    (new ReflectionMethod(BackupService::class, 'validateDatabaseDumpFile'))->invoke(null, $extractedPath);
    backupStreamingAssert(hash_file('sha256', $extractedPath) === hash('sha256', $archiveSql), 'SQL extraction from ZIP must preserve every byte.');
} finally {
    if (is_string($extractedPath) && is_file($extractedPath)) unlink($extractedPath);
    if (is_file($zipPath)) unlink($zipPath);
}

$rejected = false;
try { backupStreamingParse('SELECT ' . str_repeat('a', 2048) . ';', 1024); }
catch (RuntimeException $e) { $rejected = true; }
backupStreamingAssert($rejected, 'A single SQL statement must respect the configured memory ceiling.');

echo "BACKUP_STREAMING_SQL_V5_OK\n";
