<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$package = '';
$confirmed = false;
foreach (array_slice($argv, 1) as $argument) {
    if (strpos($argument, '--package=') === 0) {
        $package = substr($argument, strlen('--package='));
    } elseif ($argument === '--confirm=RETIRE-CORE-FILES') {
        $confirmed = true;
    }
}

if ($package === '' || !$confirmed) {
    fwrite(STDERR, "Usage: php retire-core-files.php --package=proma-update-YYYY-MM-DD-HH-MM-SS-name.zip --confirm=RETIRE-CORE-FILES\n");
    exit(2);
}

try {
    require __DIR__ . '/bootstrap.php';
    $result = ScriptUpdateService::finalizeRetiredFilesFromInstalledPackage(basename($package));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'RETIRE_CORE_FILES_FAILED: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
