<?php

/** Standards-based Web Push delivery using VAPID and aes128gcm. */
final class WebPushService extends Model
{
    private const PRIVATE_KEY_FILE = 'storage/secure_uploads/.proma-webpush-vapid-private.pem';
    private const MAX_SUBSCRIPTIONS_PER_USER = 20;
    private const BATCH_SIZE = 25;

    public static function enabled(): bool
    {
        return (string) Settings::get('web_push_enabled', '0') === '1';
    }

    public static function enabledForType(string $type): bool
    {
        if (!self::enabled()) return false;
        $types = json_decode((string) Settings::get('web_push_event_types_json', ''), true);
        if (!is_array($types)) $types = ['payment', 'payment_receipt', 'legal', 'identity', 'profile', 'overdue', 'calendar', 'message', 'bot', 'repair', 'installment', 'due_soon', 'due_today', 'followup', 'system'];
        return in_array($type, $types, true);
    }

    public static function keysReady(): bool
    {
        try { return self::publicKey() !== ''; } catch (Throwable $e) { return false; }
    }

    public static function publicKey(): string
    {
        $private = self::loadPrivateKey();
        if (!$private) return '';
        $details = openssl_pkey_get_details($private);
        $ec = $details['ec'] ?? [];
        $x = $ec['x'] ?? '';
        $y = $ec['y'] ?? '';
        if (strlen($x) !== 32 || strlen($y) !== 32) throw new RuntimeException('کلید VAPID سامانه از نوع P-256 نیست.');
        return self::base64UrlEncode("\x04" . $x . $y);
    }

    public static function ensureKeys(): string
    {
        $path = self::privateKeyPath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('ساخت پوشه امن کلید اعلان مرورگر انجام نشد.');
        }
        $handle = @fopen($path, 'c+b');
        if ($handle === false) throw new RuntimeException('فایل امن کلید اعلان مرورگر قابل نوشتن نیست.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('قفل کلید اعلان مرورگر برقرار نشد.');
            $pem = stream_get_contents($handle);
            if (is_string($pem) && trim($pem) !== '') {
                $key = openssl_pkey_get_private($pem);
                if ($key) return self::publicKeyFromPrivate($key);
            }
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            if (!$key || !openssl_pkey_export($key, $pem)) throw new RuntimeException('ساخت کلید VAPID اعلان مرورگر ناموفق بود.');
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $pem) !== strlen($pem) || !fflush($handle)) {
                throw new RuntimeException('ذخیره امن کلید اعلان مرورگر کامل نشد.');
            }
            @chmod($path, 0600);
            return self::publicKeyFromPrivate($key);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
            @chmod($path, 0600);
        }
    }

    public static function hasSubscription(int $userId): bool
    {
        return (bool) self::fetch('SELECT id FROM web_push_subscriptions WHERE user_id=? AND is_active=1 LIMIT 1', [$userId]);
    }

    public static function subscriberCount(): int
    {
        $row = self::fetch('SELECT COUNT(DISTINCT user_id) AS total FROM web_push_subscriptions WHERE is_active=1');
        return (int) ($row['total'] ?? 0);
    }

    public static function saveSubscription(int $userId, string $endpoint, string $p256dh, string $auth, string $userAgent = ''): void
    {
        if ($userId <= 0 || !self::validEndpoint($endpoint)) throw new InvalidArgumentException('نشانی سرویس اعلان مرورگر معتبر نیست.');
        $publicKey = self::base64UrlDecode($p256dh);
        $authSecret = self::base64UrlDecode($auth);
        if (strlen($publicKey) !== 65 || $publicKey[0] !== "\x04" || strlen($authSecret) < 16 || strlen($authSecret) > 64) {
            throw new InvalidArgumentException('کلیدهای اشتراک اعلان مرورگر معتبر نیستند.');
        }
        $user = User::find($userId);
        if (!$user || ($user['status'] ?? '') !== 'active') throw new RuntimeException('حساب کاربری فعال نیست.');
        $existing = self::fetch('SELECT COUNT(*) AS total FROM web_push_subscriptions WHERE user_id=? AND is_active=1', [$userId]);
        $endpointHash = hash('sha256', $endpoint);
        $same = self::fetch('SELECT id,user_id FROM web_push_subscriptions WHERE endpoint_hash=? LIMIT 1', [$endpointHash]);
        if ($same && (int) $same['user_id'] !== $userId) throw new InvalidArgumentException('این دستگاه قبلاً به حساب دیگری متصل شده است.');
        if (!$same && (int) ($existing['total'] ?? 0) >= self::MAX_SUBSCRIPTIONS_PER_USER) {
            throw new RuntimeException('برای این حساب حداکثر ۲۰ دستگاه اعلان فعال مجاز است.');
        }
        self::execute(
            'INSERT INTO web_push_subscriptions (user_id,endpoint,endpoint_hash,p256dh,auth_secret,user_agent,is_active,created_at,updated_at)
             VALUES (?,?,?,?,?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE endpoint=VALUES(endpoint),p256dh=VALUES(p256dh),auth_secret=VALUES(auth_secret),user_agent=VALUES(user_agent),is_active=1,updated_at=NOW()',
            [$userId, $endpoint, $endpointHash, $p256dh, $auth, mb_substr($userAgent, 0, 500, 'UTF-8')]
        );
    }

    public static function removeSubscription(int $userId, string $endpoint): int
    {
        if ($userId <= 0 || $endpoint === '' || strlen($endpoint) > 2048) return 0;
        return self::execute('UPDATE web_push_subscriptions SET is_active=0,updated_at=NOW() WHERE user_id=? AND endpoint_hash=?', [$userId, hash('sha256', $endpoint)]);
    }

    public static function enqueueNotification(int $notificationId): void
    {
        if (!self::enabled() || $notificationId <= 0) return;
        self::safePushEnqueue('web_push.notification', ['notification_id' => $notificationId], 'notification', $notificationId);
    }

    public static function enqueueDirectNotification(int $userId, string $title, string $body, string $type, $url = null, $dedupeKey = null): void
    {
        if ($userId <= 0 || !self::enabledForType($type)) return;
        $dedupeKey = trim((string) $dedupeKey);
        $eventKey = $dedupeKey !== ''
            ? 'direct:' . hash('sha256', $dedupeKey)
            : 'direct:' . bin2hex(random_bytes(16));
        self::safePushEnqueue('web_push.direct_notification', [
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'url' => $url,
            'event_key' => $eventKey,
        ], 'push_notification', $userId);
    }

    public static function deliverDirectNotification(array $payload): void
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $type = (string) ($payload['type'] ?? 'system');
        $eventKey = (string) ($payload['event_key'] ?? '');
        if ($userId <= 0 || $eventKey === '' || !self::enabledForType($type)) return;
        self::deliverUser($userId, $eventKey, [
            'title' => (string) ($payload['title'] ?? 'اعلان سامانه'),
            'body' => (string) ($payload['body'] ?? ''),
            'url' => (string) ($payload['url'] ?? url('notifications')),
            'tag' => 'proma-' . substr(hash('sha256', $eventKey), 0, 32),
        ]);
    }

    public static function enqueueAnnouncement(int $announcementId): void
    {
        if (!self::enabled() || $announcementId <= 0) return;
        self::safePushEnqueue('web_push.announcement_batch', ['announcement_id' => $announcementId, 'after_user_id' => 0], 'announcement', $announcementId);
    }

    public static function deliverNotification(int $notificationId): void
    {
        if (!self::enabled()) return;
        $row = self::fetch('SELECT id,user_id,title,body,type,url FROM notifications WHERE id=? LIMIT 1', [$notificationId]);
        if (!$row || !self::enabledForType((string) $row['type'])) return;
        self::deliverUser((int) $row['user_id'], 'notification:' . $notificationId, [
            'title' => (string) $row['title'], 'body' => (string) $row['body'], 'url' => (string) ($row['url'] ?? url('notifications')),
            'tag' => 'proma-notification-' . $notificationId,
        ]);
    }

    public static function processAnnouncementBatch(int $announcementId, int $afterUserId = 0): void
    {
        if (!self::enabled() || $announcementId <= 0) return;
        $announcement = self::fetch('SELECT id,title,body,local_url,expires_at FROM system_announcements WHERE id=? LIMIT 1', [$announcementId]);
        if (!$announcement || (!empty($announcement['expires_at']) && strtotime((string) $announcement['expires_at']) <= time())) return;
        $recipients = self::fetchAll(
            'SELECT user_id FROM system_announcement_recipients WHERE announcement_id=? AND user_id>? ORDER BY user_id ASC LIMIT ' . self::BATCH_SIZE,
            [$announcementId, $afterUserId]
        );
        if (!$recipients) return;
        $payload = [
            'title' => (string) $announcement['title'], 'body' => (string) $announcement['body'],
            'url' => !empty($announcement['local_url']) ? url((string) $announcement['local_url']) : url('notifications'),
            'tag' => 'proma-announcement-' . $announcementId,
        ];
        $retry = false;
        foreach ($recipients as $recipient) {
            try {
                self::deliverUser((int) $recipient['user_id'], 'announcement:' . $announcementId, $payload);
            } catch (Throwable $e) {
                $retry = true;
                if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('web_push.announcement_delivery', $e);
            }
        }
        if (count($recipients) === self::BATCH_SIZE) {
            self::safePushEnqueue('web_push.announcement_batch', [
                'announcement_id' => $announcementId, 'after_user_id' => (int) end($recipients)['user_id'],
            ], 'announcement', $announcementId);
        }
        if ($retry) throw new RuntimeException('ارسال اعلان همگانی به برخی دستگاه‌ها موقتاً ناموفق بود؛ تلاش مجدد در صف انجام می‌شود.');
    }

    private static function deliverUser(int $userId, string $eventKey, array $payload): void
    {
        $subscriptions = self::fetchAll('SELECT * FROM web_push_subscriptions WHERE user_id=? AND is_active=1 ORDER BY id ASC LIMIT ' . self::MAX_SUBSCRIPTIONS_PER_USER, [$userId]);
        $retry = false;
        foreach ($subscriptions as $subscription) {
            $subscriptionId = (int) $subscription['id'];
            $delivery = self::fetch('SELECT status FROM web_push_deliveries WHERE subscription_id=? AND event_key=? LIMIT 1', [$subscriptionId, $eventKey]);
            if (($delivery['status'] ?? '') === 'sent') continue;
            self::execute(
                "INSERT INTO web_push_deliveries (subscription_id,event_key,status,attempts,created_at,updated_at)
                 VALUES (?,?, 'pending',0,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE status=IF(status='sent','sent','pending'),updated_at=NOW()",
                [$subscriptionId, $eventKey]
            );
            try {
                $response = self::send($subscription, $payload);
                $status = (int) $response['status'];
                if ($status >= 200 && $status < 300) {
                    self::execute("UPDATE web_push_deliveries SET status='sent',http_status=?,attempts=attempts+1,delivered_at=NOW(),updated_at=NOW(),error_code=NULL WHERE subscription_id=? AND event_key=?", [$status, $subscriptionId, $eventKey]);
                    self::execute('UPDATE web_push_subscriptions SET last_success_at=NOW(),last_error_code=NULL,updated_at=NOW() WHERE id=?', [$subscriptionId]);
                } elseif ($status === 404 || $status === 410 || $status === 400) {
                    self::expireSubscription($subscriptionId, $eventKey, $status);
                } else {
                    self::recordFailure($subscriptionId, $eventKey, $status, 'http_' . $status);
                    $retry = true;
                }
            } catch (Throwable $e) {
                self::recordFailure($subscriptionId, $eventKey, 0, 'transport_error');
                $retry = true;
                if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('web_push.delivery', $e);
            }
        }
        if ($retry) throw new RuntimeException('ارسال اعلان مرورگر به‌طور موقت ناموفق بود؛ تلاش مجدد در صف انجام می‌شود.');
    }

    private static function send(array $subscription, array $payload): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('افزونه cURL روی سرور فعال نیست.');
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        if (!self::validEndpoint($endpoint)) throw new RuntimeException('نشانی سرویس اعلان نامعتبر است.');
        $private = self::loadPrivateKey();
        if (!$private) throw new RuntimeException('کلید VAPID اعلان مرورگر آماده نیست.');
        $clientPublic = self::base64UrlDecode((string) ($subscription['p256dh'] ?? ''));
        $authSecret = self::base64UrlDecode((string) ($subscription['auth_secret'] ?? ''));
        if (strlen($clientPublic) !== 65 || strlen($authSecret) < 16) throw new RuntimeException('کلید دستگاه اعلان معتبر نیست.');
        $peer = openssl_pkey_get_public(self::publicPemFromRaw($clientPublic));
        $ephemeral = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if (!$peer || !$ephemeral) throw new RuntimeException('ساخت کلید موقت رمزنگاری اعلان ناموفق بود.');
        $shared = openssl_pkey_derive($peer, $ephemeral, 32);
        $ephemeralDetails = openssl_pkey_get_details($ephemeral);
        $ephemeralEc = $ephemeralDetails['ec'] ?? [];
        $serverPublic = "\x04" . ($ephemeralEc['x'] ?? '') . ($ephemeralEc['y'] ?? '');
        if (!is_string($shared) || strlen($serverPublic) !== 65) throw new RuntimeException('مذاکره کلید اعلان مرورگر ناموفق بود.');

        $keyPrk = hash_hmac('sha256', $shared, $authSecret, true);
        $inputKey = self::hkdfExpand($keyPrk, "WebPush: info\0" . $clientPublic . $serverPublic, 32);
        $salt = random_bytes(16);
        $contentPrk = hash_hmac('sha256', $inputKey, $salt, true);
        $contentKey = self::hkdfExpand($contentPrk, "Content-Encoding: aes128gcm\0", 16);
        $nonce = self::hkdfExpand($contentPrk, "Content-Encoding: nonce\0", 12);
        $json = json_encode([
            'title' => mb_substr(trim((string) ($payload['title'] ?? 'اعلان سامانه')), 0, 120, 'UTF-8'),
            'body' => mb_substr(trim((string) ($payload['body'] ?? '')), 0, 1500, 'UTF-8'),
            'url' => self::safeLocalUrl((string) ($payload['url'] ?? '')),
            'tag' => preg_replace('/[^A-Za-z0-9._:-]/', '', (string) ($payload['tag'] ?? 'proma-notification')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) throw new RuntimeException('ساخت متن اعلان مرورگر ناموفق بود.');
        $tag = '';
        $ciphertext = openssl_encrypt($json . "\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if (!is_string($ciphertext) || strlen($tag) !== 16) throw new RuntimeException('رمزنگاری اعلان مرورگر ناموفق بود.');
        $body = $salt . pack('N', 4096) . chr(strlen($serverPublic)) . $serverPublic . $ciphertext . $tag;
        $jwt = self::vapidToken($endpoint, $private);
        $headers = [
            'TTL: 86400', 'Urgency: normal', 'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream',
            'Authorization: vapid t=' . $jwt . ', k=' . self::publicKeyFromPrivate($private),
        ];
        $handle = curl_init($endpoint);
        curl_setopt_array($handle, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk): int { return strlen($chunk); },
        ]);
        $sent = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($sent === false) throw new RuntimeException('اتصال امن به سرویس اعلان مرورگر برقرار نشد: ' . substr($error, 0, 120));
        return ['status' => $status];
    }

    private static function vapidToken(string $endpoint, $private): string
    {
        $parts = parse_url($endpoint);
        $origin = 'https://' . strtolower((string) ($parts['host'] ?? ''));
        if (!empty($parts['port']) && (int) $parts['port'] !== 443) $origin .= ':' . (int) $parts['port'];
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64UrlEncode(json_encode(['aud' => $origin, 'exp' => time() + 43200, 'sub' => app_base_url()]));
        $unsigned = $header . '.' . $claims;
        $der = '';
        if (!openssl_sign($unsigned, $der, $private, OPENSSL_ALGO_SHA256)) throw new RuntimeException('امضای درخواست VAPID ناموفق بود.');
        return $unsigned . '.' . self::base64UrlEncode(self::derSignatureToRaw($der));
    }

    private static function publicPemFromRaw(string $raw): string
    {
        $der = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200') . $raw;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function publicKeyFromPrivate($private): string
    {
        $details = openssl_pkey_get_details($private);
        $ec = $details['ec'] ?? [];
        $x = $ec['x'] ?? '';
        $y = $ec['y'] ?? '';
        if (strlen($x) !== 32 || strlen($y) !== 32) throw new RuntimeException('کلید VAPID سامانه از نوع P-256 نیست.');
        return self::base64UrlEncode("\x04" . $x . $y);
    }

    private static function loadPrivateKey()
    {
        $path = self::privateKeyPath();
        if (!is_file($path)) return null;
        $pem = @file_get_contents($path);
        return is_string($pem) && trim($pem) !== '' ? openssl_pkey_get_private($pem) : null;
    }

    private static function privateKeyPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::PRIVATE_KEY_FILE);
    }

    private static function validEndpoint(string $endpoint): bool
    {
        if (strlen($endpoint) > 2048) return false;
        $parts = parse_url($endpoint);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) return false;
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        foreach (['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com'] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) return true;
        }
        return false;
    }

    private static function safeLocalUrl(string $candidate): string
    {
        $fallback = url('notifications');
        $candidate = trim($candidate);
        if ($candidate === '') return $fallback;
        $base = parse_url(app_base_url());
        $target = parse_url($candidate);
        if (is_array($target) && !empty($target['host'])) {
            if (strtolower((string) ($target['host'] ?? '')) !== strtolower((string) ($base['host'] ?? '')) || ($target['scheme'] ?? '') !== ($base['scheme'] ?? 'https')) return $fallback;
            return $candidate;
        }
        if (strpos($candidate, '/') === 0 && strpos($candidate, '//') !== 0) return rtrim(app_base_url(), '/') . $candidate;
        return $fallback;
    }

    private static function recordFailure(int $subscriptionId, string $eventKey, int $httpStatus, string $errorCode): void
    {
        self::execute("UPDATE web_push_deliveries SET status='failed',http_status=?,attempts=attempts+1,error_code=?,updated_at=NOW() WHERE subscription_id=? AND event_key=?", [$httpStatus ?: null, $errorCode, $subscriptionId, $eventKey]);
        self::execute('UPDATE web_push_subscriptions SET last_error_code=?,updated_at=NOW() WHERE id=?', [$errorCode, $subscriptionId]);
    }

    private static function expireSubscription(int $subscriptionId, string $eventKey, int $status): void
    {
        self::execute("UPDATE web_push_deliveries SET status='expired',http_status=?,attempts=attempts+1,updated_at=NOW() WHERE subscription_id=? AND event_key=?", [$status, $subscriptionId, $eventKey]);
        self::execute("UPDATE web_push_subscriptions SET is_active=0,last_error_code='subscription_expired',updated_at=NOW() WHERE id=?", [$subscriptionId]);
    }

    private static function safePushEnqueue(string $event, array $payload, string $aggregateType, int $aggregateId): void
    {
        try { SystemOutbox::safeEnqueue($event, $payload, $aggregateType, $aggregateId); }
        catch (Throwable $e) { if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('web_push.enqueue', $e); }
    }

    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        $output = '';
        $block = '';
        for ($counter = 1; strlen($output) < $length; $counter++) {
            $block = hash_hmac('sha256', $block . $info . chr($counter), $prk, true);
            $output .= $block;
        }
        return substr($output, 0, $length);
    }

    private static function derSignatureToRaw(string $signature): string
    {
        $offset = 0;
        if (ord($signature[$offset++] ?? "\0") !== 0x30) throw new RuntimeException('ساختار امضای VAPID نامعتبر است.');
        $sequenceLength = ord($signature[$offset++] ?? "\0");
        if (($sequenceLength & 0x80) !== 0) {
            $bytes = $sequenceLength & 0x7f;
            $sequenceLength = 0;
            for ($i = 0; $i < $bytes; $i++) $sequenceLength = ($sequenceLength << 8) | ord($signature[$offset++] ?? "\0");
        }
        if (ord($signature[$offset++] ?? "\0") !== 0x02) throw new RuntimeException('مولفه اول امضای VAPID نامعتبر است.');
        $rLength = ord($signature[$offset++] ?? "\0");
        $r = substr($signature, $offset, $rLength); $offset += $rLength;
        if (ord($signature[$offset++] ?? "\0") !== 0x02) throw new RuntimeException('مولفه دوم امضای VAPID نامعتبر است.');
        $sLength = ord($signature[$offset++] ?? "\0");
        $s = substr($signature, $offset, $sLength);
        $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
        if (strlen($r) !== 32 || strlen($s) !== 32) throw new RuntimeException('اندازه امضای VAPID معتبر نیست.');
        return $r . $s;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        return is_string($decoded) ? $decoded : '';
    }
}
