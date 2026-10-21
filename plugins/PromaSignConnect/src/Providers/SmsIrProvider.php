<?php
namespace Proma\Plugins\SignConnect\Providers;

final class SmsIrProvider extends AbstractProvider
{
    public function getKey(): string { return 'smsir'; }
    public function getCapabilities(): array { return ['sms', 'verify', 'otp', 'delivery']; }

    public function send(array $message): array
    {
        $pattern = trim((string) ($message['pattern'] ?? ''));
        $headers = [
            'X-API-KEY' => $this->required('api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'text/plain',
        ];
        $base = rtrim($this->config['base_url'] ?? 'https://api.sms.ir/v1', '/');
        $canonical = $this->normalizeIranMobile((string) ($message['to'] ?? ''));
        // Verify examples use 912...; the bulk API's examples use 0912....
        $verifyMobile = substr($canonical, 2);
        $bulkMobile = '0' . substr($canonical, 2);
        $timeout = max(3, min(30, (int) ($this->config['timeout'] ?? 10)));
        $connectTimeout = max(1, min($timeout, (int) ($this->config['connect_timeout'] ?? 5)));
        if ($pattern !== '') {
            $variables = [];
            foreach ((new \Proma\Plugins\SignConnect\Services\TemplateVariableRegistry())
                ->mapValues('smsir', $message['variables'] ?? []) as $name => $value) {
                $variables[] = ['name' => (string) $name, 'value' => (string) $value];
            }
            $response = $this->http->request(
                'POST',
                $base . '/send/verify',
                $headers,
                ['mobile' => $verifyMobile, 'templateId' => (int) $pattern, 'parameters' => $variables],
                $timeout,
                $connectTimeout
            );
            $data = $response['body']['data'] ?? [];
            $id = is_array($data) ? ($data['messageId'] ?? $data['message_id'] ?? null) : null;
        } else {
            $text = trim((string) ($message['text'] ?? ''));
            if ($text === '') throw new \InvalidArgumentException('smsir_message_empty');
            $lineNumber = $this->required('sender_line');
            if (!ctype_digit($lineNumber)) throw new \InvalidArgumentException('smsir_sender_line_invalid');
            $response = $this->http->request(
                'POST',
                $base . '/send/bulk',
                $headers,
                [
                    'lineNumber' => (int) $lineNumber,
                    'messageText' => $text,
                    'mobiles' => [$bulkMobile],
                ],
                $timeout,
                $connectTimeout
            );
            $data = $response['body']['data'] ?? [];
            if (is_array($data)) {
                $messageIds = $data['messageIds'] ?? null;
                if (is_array($messageIds)) {
                    // A zero or null item means this recipient was not accepted,
                    // even if the API also returned an aggregate pack ID.
                    $id = $messageIds[0] ?? null;
                } else {
                    $id = $data['messageId'] ?? $data['message_id'] ?? null;
                }
                // Preserve compatibility with older API response envelopes.
                if ($id === null && array_is_list($data) && isset($data[0]) && is_array($data[0])) {
                    $id = $data[0]['messageId'] ?? $data[0]['message_id'] ?? null;
                }
            } else {
                $id = null;
            }
        }

        $apiStatus = $response['body']['status'] ?? null;
        $apiOk = $apiStatus === null || $apiStatus === 1 || $apiStatus === '1';
        $hasId = is_scalar($id) && (string) $id !== '' && (string) $id !== '0';
        return [
            'ok' => $response['status'] >= 200 && $response['status'] < 300 && $apiOk && $hasId,
            'external_id' => $hasId ? (string) $id : null,
            'status' => $response['status'],
            'response' => $response['body'],
        ];
    }

    public function healthCheck(): array
    {
        $line = trim((string) ($this->config['sender_line'] ?? ''));
        return [
            'ok' => trim((string) ($this->config['api_key'] ?? '')) !== '' && $line !== '' && ctype_digit($line),
            'passive' => true,
        ];
    }
}
