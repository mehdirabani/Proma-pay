<?php

use App\Actions\Contracts\TransitionContract;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $contractId, $requestId, $actorId, $barrierDirectory, $workerId] = $argv;
$readyPath = $barrierDirectory.DIRECTORY_SEPARATOR.'ready-'.$workerId;
$goPath = $barrierDirectory.DIRECTORY_SEPARATOR.'go';
file_put_contents($readyPath, 'ready');

$deadline = microtime(true) + 15;
while (! is_file($goPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Timed out waiting for the concurrency barrier.\n");
        exit(2);
    }

    usleep(10_000);
}

try {
    $contract = $app->make(TransitionContract::class)->handle(
        (int) $contractId,
        'submit',
        $requestId,
        'همزمانی رزرو اعتبار در آزمون',
        User::query()->findOrFail((int) $actorId),
    );

    echo json_encode(['outcome' => 'accepted', 'status' => $contract->status], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (ValidationException $exception) {
    echo json_encode(['outcome' => 'rejected', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());
    exit(1);
}
