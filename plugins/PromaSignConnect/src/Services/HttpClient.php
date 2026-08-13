<?php
namespace Proma\Plugins\SignConnect\Services;
final class HttpClient
{
    private const ALLOWED_HOSTS = [
        'edge.ippanel.com',
        'api.sms.ir',
        'safir.bale.ai',
        'api.telegram.org',
    ];

    public function request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 10): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new \RuntimeException('provider_url_not_allowed');
        }
        $timeout = max(3, min(30, $timeout));
        $ch = curl_init($url);
        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }
        $raw = '';
        $responseHeaders = [];
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    if (in_array($name, ['retry-after', 'content-type', 'content-length'], true)) {
                        $responseHeaders[$name] = trim(substr($line, $separator + 1));
                    }
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$raw, &$tooLarge): int {
                if (strlen($raw) + strlen($chunk) > 1048576) {
                    $tooLarge = true;
                    return 0;
                }
                $raw .= $chunk;
                return strlen($chunk);
            },
        ]);
        $executed = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($tooLarge) {
            throw new \RuntimeException('provider_response_too_large');
        }
        if ($executed === false) {
            throw new \RuntimeException('provider_transport_error:' . $this->redactError($error));
        }
        $decoded = json_decode((string) $raw, true);
        if ($raw !== '' && !is_array($decoded)) {
            throw new \RuntimeException('provider_malformed_json');
        }
        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : [],
            'headers' => $responseHeaders,
        ];
    }

    private function redactError(string $error): string
    {
        $error = preg_replace('~/bot[^/\s]+/~', '/bot[REDACTED]/', $error);
        return substr((string) $error, 0, 180);
    }
}
