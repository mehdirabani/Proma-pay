<?php
namespace Proma\Plugins\SignConnect\Providers;

final class IPPanelProvider extends AbstractProvider
{
    public function getKey(): string { return 'ippanel'; }
    public function getCapabilities(): array { return ['sms', 'pattern', 'otp', 'delivery']; }

    public function send(array $message): array
    {
        $pattern = trim((string) ($message['pattern'] ?? ''));
        $recipient = $this->normalizeIranMobile((string) ($message['to'] ?? ''));
        $fromNumber = $this->required('from_number');
        if ($pattern !== '') {
            $body = [
                'sending_type' => 'pattern',
                'from_number' => $fromNumber,
                'code' => $pattern,
                'recipients' => [$recipient],
                'params' => (new \Proma\Plugins\SignConnect\Services\TemplateVariableRegistry())
                    ->mapValues('ippanel', $message['variables'] ?? []),
            ];
        } else {
            $text = trim((string) ($message['text'] ?? ''));
            if ($text === '') throw new \InvalidArgumentException('ippanel_message_empty');
            $body = [
                'sending_type' => 'webservice',
                'from_number' => $fromNumber,
                'message' => $text,
                // The Edge Webservice API expects recipients inside params.
                'params' => ['recipients' => [$recipient]],
            ];
        }

        $response = $this->http->request(
            'POST',
            rtrim($this->config['base_url'] ?? 'https://edge.ippanel.com/v1', '/') . '/api/send',
            [
                'Authorization' => $this->required('api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $body,
            max(3, min(30, (int) ($this->config['timeout'] ?? 10))),
            max(1, min(10, (int) ($this->config['connect_timeout'] ?? 5)))
        );

        $data = $response['body']['data'] ?? [];
        $id = is_array($data) ? ($data['message_outbox_ids'][0] ?? $data['message_outbox_id'] ?? null) : null;
        $metaStatus = $response['body']['meta']['status'] ?? null;
        $httpOk = $response['status'] >= 200 && $response['status'] < 300;
        $providerOk = $metaStatus === null || $metaStatus === true || $metaStatus === 1 || $metaStatus === 'true';
        $hasId = is_scalar($id) && (string) $id !== '' && (string) $id !== '0';

        return [
            'ok' => $httpOk && $providerOk && $hasId,
            'external_id' => $hasId ? (string) $id : null,
            'status' => $response['status'],
            'response' => $response['body'],
        ];
    }

    public function healthCheck(): array
    {
        $configured = trim((string) ($this->config['api_key'] ?? '')) !== ''
            && trim((string) ($this->config['from_number'] ?? '')) !== '';
        return ['ok' => $configured, 'passive' => true];
    }
}
