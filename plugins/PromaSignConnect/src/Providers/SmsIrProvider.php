<?php
namespace Proma\Plugins\SignConnect\Providers;
final class SmsIrProvider extends AbstractProvider
{
    public function getKey(): string { return 'smsir'; }
    public function getCapabilities(): array { return ['sms','verify','otp','delivery']; }
    public function send(array $message): array
    {
        $pattern = trim((string) ($message['pattern'] ?? ''));
        $headers = ['X-API-KEY'=>$this->required('api_key'),'Content-Type'=>'application/json'];
        $base = rtrim($this->config['base_url'] ?? 'https://api.sms.ir/v1', '/');
        $mobile = ltrim($this->normalizeIranMobile((string)$message['to']), '+98');
        if ($pattern !== '') {
            $variables = [];
            foreach ((new \Proma\Plugins\SignConnect\Services\TemplateVariableRegistry())->mapValues('smsir',$message['variables'] ?? []) as $name => $value) $variables[] = ['name'=>(string)$name,'value'=>(string)$value];
            $response = $this->http->request('POST', $base . '/send/verify', $headers, ['mobile'=>$mobile,'templateId'=>(int)$pattern,'parameters'=>$variables]);
            $id = $response['body']['data']['messageId'] ?? null;
        } else {
            $response = $this->http->request('POST', $base . '/send/bulk', $headers, [
                'lineNumber'=>$this->required('sender_line'),'messageText'=>(string)($message['text']??''),'mobiles'=>[$mobile]
            ]);
            $id = $response['body']['data'][0]['messageId'] ?? $response['body']['data']['messageId'] ?? null;
        }
        return ['ok'=>$response['status'] >= 200 && $response['status'] < 300 && $id !== null,'external_id'=>(string)$id,'status'=>$response['status'],'response'=>$response['body']];
    }
    public function healthCheck(): array { return ['ok'=>trim((string)($this->config['api_key'] ?? '')) !== '', 'passive'=>true]; }
}
