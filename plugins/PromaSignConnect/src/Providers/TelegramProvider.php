<?php
namespace Proma\Plugins\SignConnect\Providers;
final class TelegramProvider extends AbstractProvider
{
    public function getKey(): string { return 'telegram'; }
    public function getName(): string { return 'ربات تلگرام'; }
    public function getCapabilities(): array { return ['text','button','webhook','account_linking']; }
    public function normalizeRecipient(string $recipient): string
    {
        if (!preg_match('/^-?\d+$/', $recipient)) throw new \InvalidArgumentException('invalid_telegram_chat');
        return $recipient;
    }
    public function send(array $message): array
    {
        if (empty($message['chat_id'])) throw new \RuntimeException('telegram_not_linked');
        $payload = ['chat_id'=>(string)$message['chat_id'],'text'=>(string)($message['text'] ?? ''),'parse_mode'=>'HTML'];
        if (!empty($message['button'])) $payload['reply_markup'] = ['inline_keyboard'=>[[$message['button']]]];
        $response = $this->http->request('POST', 'https://api.telegram.org/bot' . $this->required('bot_token') . '/sendMessage', ['Content-Type'=>'application/json'], $payload);
        $id = $response['body']['result']['message_id'] ?? null;
        return ['ok'=>!empty($response['body']['ok']),'external_id'=>(string)$id,'status'=>$response['status'],'response'=>$response['body']];
    }
    public function healthCheck(): array
    {
        if (trim((string)($this->config['bot_token'] ?? '')) === '') return ['ok'=>false,'passive'=>true];
        $response = $this->http->request('GET', 'https://api.telegram.org/bot' . $this->required('bot_token') . '/getMe');
        return ['ok'=>!empty($response['body']['ok']),'passive'=>false];
    }
}
