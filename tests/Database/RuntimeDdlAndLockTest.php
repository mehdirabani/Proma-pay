<?php

$root = dirname(__DIR__, 2);
$runtimeDirectories = ['controllers', 'core', 'models', 'helpers', 'plugins/PromaAccounting/src'];
$violations = [];
foreach ($runtimeDirectories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (preg_match('#(?:Update|Install|Backup|SchemaGuard|PluginManager|SystemReset)#i', $relative)) continue;
        $source = file_get_contents($file->getPathname());
        if (preg_match('/["\']\s*(?:CREATE|ALTER|DROP|TRUNCATE)\s+(?:TABLE|INDEX)/i', $source)) $violations[] = $relative;
    }
}
$auth = file_get_contents($root . '/core/Auth.php');
$controller = file_get_contents($root . '/plugins/PromaAccounting/src/Controllers/AccountingController.php');
if (strpos($auth, 'session_write_close()') === false || strpos($controller, 'Auth::releaseSessionLock()') === false) $violations[] = 'session-lock-release';
$cron = file_get_contents($root . '/controllers/CronController.php');
if (substr_count($cron, 'CronLock::acquire') < 2 || strpos($cron, 'CronLock::release') === false) $violations[] = 'cron-overlap-lock';
if ($violations) { fwrite(STDERR, implode(PHP_EOL, $violations) . PHP_EOL); exit(1); }
echo "PASSED RuntimeDdlAndLockTest\n";
