<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/RequestFloodGuard.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$directory = sys_get_temp_dir() . '/proma-rate-guard-' . bin2hex(random_bytes(6));
putenv('PROMA_REQUEST_RATE_DIRECTORY=' . $directory);
putenv('PROMA_REQUEST_RATE_CAPACITY=2');
putenv('PROMA_REQUEST_RATE_REFILL_PER_SECOND=1');
putenv('PROMA_CHAT_POLL_RATE_CAPACITY=1');
putenv('PROMA_CHAT_POLL_RATE_REFILL_PER_SECOND=1');
putenv('PROMA_CONTRACT_PREVIEW_RATE_CAPACITY=1');
putenv('PROMA_CONTRACT_PREVIEW_RATE_REFILL_PER_SECOND=1');

try {
    $first = RequestFloodGuard::consume('198.51.100.148', 'dashboard', 1000.0);
    $second = RequestFloodGuard::consume('198.51.100.148', 'dashboard', 1000.0);
    $third = RequestFloodGuard::consume('198.51.100.148', 'dashboard', 1000.0);
    $recovered = RequestFloodGuard::consume('198.51.100.148', 'dashboard', 1001.0);
    $assert(!empty($first['allowed']) && !empty($second['allowed']), 'Global request burst budget did not allow its configured capacity.');
    $assert(empty($third['allowed']) && (int) ($third['retry_after'] ?? 0) >= 1, 'Global request burst was not rejected with a retry interval.');
    $assert(!empty($recovered['allowed']), 'Global request budget did not refill over time.');

    $chatFirst = RequestFloodGuard::consume('198.51.100.149', 'chat/fetch', 2000.0);
    $chatSecond = RequestFloodGuard::consume('198.51.100.149', 'chat/fetch', 2000.0);
    $chatRecovered = RequestFloodGuard::consume('198.51.100.149', 'chat/fetch', 2001.0);
    $assert(!empty($chatFirst['allowed']), 'Chat poll budget rejected its first request.');
    $assert(empty($chatSecond['allowed']) && ($chatSecond['policy'] ?? '') === 'chat-poll', 'Chat polling is not independently rate-limited.');
    $assert(!empty($chatRecovered['allowed']), 'Chat poll budget did not refill.');

    $previewFirst = RequestFloodGuard::consume('198.51.100.150', 'contracts/preview', 3000.0);
    $previewSecond = RequestFloodGuard::consume('198.51.100.150', 'contracts/preview', 3000.0);
    $previewRecovered = RequestFloodGuard::consume('198.51.100.150', 'contracts/preview', 3001.0);
    $assert(!empty($previewFirst['allowed']), 'Contract preview budget rejected its first request.');
    $assert(empty($previewSecond['allowed']) && ($previewSecond['policy'] ?? '') === 'contract-preview', 'Contract preview is not independently rate-limited.');
    $assert(!empty($previewRecovered['allowed']), 'Contract preview budget did not refill.');

    echo "REQUEST_FLOOD_GUARD_V149_OK\n";
} finally {
    putenv('PROMA_REQUEST_RATE_DIRECTORY');
    putenv('PROMA_REQUEST_RATE_CAPACITY');
    putenv('PROMA_REQUEST_RATE_REFILL_PER_SECOND');
    putenv('PROMA_CHAT_POLL_RATE_CAPACITY');
    putenv('PROMA_CHAT_POLL_RATE_REFILL_PER_SECOND');
    putenv('PROMA_CONTRACT_PREVIEW_RATE_CAPACITY');
    putenv('PROMA_CONTRACT_PREVIEW_RATE_REFILL_PER_SECOND');
    foreach (glob($directory . '/*.json') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($directory);
}
