<?php
namespace Proma\Plugins\SignConnect\Providers;
final class IPPanelProvider extends AbstractProvider
{
    public function getKey(): string { return 'ippanel'; }
    public function getCapabilities(): array { return ['sms','pattern','otp','delivery']; }
    public function send(array $message): array
    {
        $pattern = trim((string) ($message['pattern'] ?? ''));
        $body = $pattern !== '' ? [
            'sending_type'=>'pattern','from_number'=>$this->required('from_number'),'code'=>$pattern,
            'recipients'=>[$this->normalizeIranMobile((string) $message['to'])],'params'=>(new \Proma\Plugins\SignConnect\Services\TemplateVariableRegistry())->mapValues('ippanel',$message['variables'] ?? [])
        ] : [
            'sending_type'=>'webservice','from_number'=>$this->required('from_number'),
            'recipients'=>[$this->normalizeIranMobile((string) $message['to'])],'message'=>(string) ($message['text'] ?? '')
        ];
        $response = $this->http->request('POST', rtrim($this->config['base_url'] ?? 'https://edge.ippanel.com/v1', '/') . '/api/send', [
            'Authorization'=>$this->required('api_key'),'Content-Type'=>'application/json'
        ], $body);
        $id = $response['body']['data']['message_outbox_ids'][0] ?? null;
        return ['ok'=>$response['status'] >= 200 && $response['status'] < 300 && $id !== null,'external_id'=>(string) $id,'status'=>$response['status'],'response'=>$response['body']];
    }
    public function healthCheck(): array { return ['ok'=>trim((string)($this->config['api_key'] ?? '')) !== '', 'passive'=>true]; }
}
