<?php
namespace Proma\Plugins\SignConnect\Services;

final class BaleSafirPayloadBuilder
{
    public function build(array $message, string $requestId, string $botId): array
    {
        if (!preg_match('/^\d+$/', $botId)) {
            throw new \InvalidArgumentException('شناسه بازوی بله باید عددی باشد.');
        }
        $type = (string) ($message['type'] ?? 'text');
        $data = [];

        if ($type === 'otp') {
            $otp = (string) ($message['otp'] ?? $message['variables']['code'] ?? '');
            if (!preg_match('/^\d{4,8}$/', $otp)) {
                throw new \InvalidArgumentException('OTP نامعتبر است.');
            }
            $data = ['otp_message' => ['otp' => $otp]];
        } elseif (in_array($type, ['template', 'secure_template'], true)) {
            $id = trim((string) ($message['template_id'] ?? ''));
            if ($id === '') throw new \InvalidArgumentException('شناسه قالب بله الزامی است.');
            $fields = $message['text_fields'] ?? [];
            if (!is_array($fields)) throw new \InvalidArgumentException('نگاشت قالب معتبر نیست.');
            $data = ['template_message' => ['template_id' => $id, 'text_fields' => $fields]];
            if ($type === 'secure_template') $data['is_secure'] = true;
        } elseif (in_array($type, ['text', 'secure'], true)) {
            $text = trim((string) ($message['text'] ?? ''));
            $fileId = trim((string) ($message['file_id'] ?? ''));
            if ($text === '' && $fileId === '') {
                throw new \InvalidArgumentException('متن پیام بله خالی است.');
            }
            if (mb_strlen($text, 'UTF-8') > 4000) {
                throw new \InvalidArgumentException('متن پیام بله بیش از حد مجاز است.');
            }
            $content = [];
            if ($text !== '') $content['text'] = $text;
            if ($fileId !== '') $content['file_id'] = $fileId;
            if (!empty($message['buttons'])) {
                $content['reply_markup'] = ['inline_keyboard' => $this->buttons($message['buttons'])];
            }
            $data = ['message' => $content];
            if ($type === 'secure') $data['is_secure'] = true;
        } else {
            throw new \InvalidArgumentException('نوع پیام بله پشتیبانی نمی‌شود.');
        }

        return [
            'request_id' => $requestId,
            'bot_id' => (int) $botId,
            'phone_number' => IranianPhoneNumberNormalizer::normalize((string) ($message['to'] ?? '')),
            'message_data' => $data,
        ];
    }

    private function buttons(array $buttons): array
    {
        $safe = [];
        foreach (array_slice($buttons, 0, 4) as $button) {
            if (!is_array($button)) continue;
            $text = trim((string) ($button['text'] ?? ''));
            if ($text === '') throw new \InvalidArgumentException('عنوان دکمه بله الزامی است.');
            $item = ['text' => mb_substr($text, 0, 50, 'UTF-8')];

            $url = trim((string) ($button['url'] ?? ''));
            if ($url !== '') {
                if (!preg_match('#^https://#i', $url)) {
                    throw new \InvalidArgumentException('نشانی دکمه باید HTTPS باشد.');
                }
                $item['url'] = $url;
            } elseif (isset($button['web_app']) && is_array($button['web_app'])) {
                $webAppUrl = trim((string) ($button['web_app']['url'] ?? ''));
                if (!preg_match('#^https://#i', $webAppUrl)) {
                    throw new \InvalidArgumentException('نشانی مینی‌اپ باید HTTPS باشد.');
                }
                $item['web_app'] = ['url' => $webAppUrl];
            } elseif (trim((string) ($button['copy_text'] ?? '')) !== '') {
                $item['copy_text'] = mb_substr(trim((string) $button['copy_text']), 0, 256, 'UTF-8');
            } else {
                throw new \InvalidArgumentException('هر دکمه بله باید نشانی، مینی‌اپ یا متن قابل کپی داشته باشد.');
            }
            $safe[] = $item;
        }
        if (!$safe) throw new \InvalidArgumentException('حداقل یک دکمه معتبر برای بله لازم است.');
        return [$safe];
    }
}
