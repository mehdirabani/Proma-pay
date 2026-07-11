<?php

class EmailService
{
    public static function send($to, $subject, $html, $text = '', array $overrideSettings = null)
    {
        $settings = $overrideSettings ? array_replace(Settings::allKeyed(), $overrideSettings) : Settings::allKeyed();
        if (($settings['email_enabled'] ?? '0') !== '1') {
            return ['ok' => false, 'message' => 'ارسال ایمیل در تنظیمات غیرفعال است.'];
        }

        $to = trim((string) $to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'ایمیل گیرنده معتبر نیست.'];
        }

        $fromAddress = trim((string) ($settings['email_from_address'] ?? ''));
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'ایمیل فرستنده معتبر نیست.'];
        }

        $fromName = trim((string) ($settings['email_from_name'] ?? '')) ?: ($settings['system_name'] ?? app_config('app_name', 'پروما'));
        $transport = strtolower(trim((string) ($settings['email_transport'] ?? 'mail')));
        if ($transport === 'smtp' && trim((string) ($settings['smtp_host'] ?? '')) !== '') {
            return self::sendSmtp($settings, $to, $subject, $html, $text, $fromAddress, $fromName);
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::formatMailbox($fromAddress, $fromName),
        ];
        $replyTo = trim((string) ($settings['email_reply_to'] ?? ''));
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $ok = @mail($to, self::encodeHeader($subject), $html, implode("\r\n", $headers));
        return ['ok' => (bool) $ok, 'message' => $ok ? 'ایمیل ارسال شد.' : 'ارسال ایمیل از طریق mail() ناموفق بود.'];
    }

    public static function sendTest($to, array $overrideSettings = null)
    {
        $settings = $overrideSettings ? array_replace(Settings::allKeyed(), $overrideSettings) : Settings::allKeyed();
        $title = 'تست ارسال ایمیل';
        $html = self::genericTemplate(
            $title,
            'این پیام برای بررسی اتصال ایمیل سامانه ارسال شده است. اگر آن را دریافت کرده‌اید، تنظیمات ایمیل درست کار می‌کند.',
            'ورود به سامانه',
            self::absoluteRouteUrl('dashboard'),
            $settings
        );
        return self::send($to, $title, $html, 'تست ارسال ایمیل سامانه', $settings);
    }

    public static function sendOrderConfirmation(array $order = null)
    {
        if (!$order || empty($order['email'])) {
            return ['ok' => false, 'message' => 'برای سفارش ایمیلی ثبت نشده است.'];
        }
        $settings = Settings::allKeyed();
        $subject = trim((string) ($settings['email_order_subject'] ?? ''));
        if ($subject === '') {
            $subject = 'سفارش شما با موفقیت ثبت شد';
        }
        $subject = strtr($subject, [
            '{{order_number}}' => $order['order_number'] ?? '',
            '{{customer_name}}' => $order['full_name'] ?? '',
            '{{system_name}}' => $settings['system_name'] ?? app_config('app_name', 'پروما'),
        ]);
        return self::send($order['email'], $subject, self::orderSuccessTemplate($order, $settings), self::orderText($order), $settings);
    }

    public static function genericTemplate($title, $body, $buttonText = null, $buttonUrl = null, array $settings = null)
    {
        $settings = $settings ?: Settings::allKeyed();
        $systemName = $settings['system_name'] ?? app_config('app_name', 'پروما');
        $logoUrl = self::logoUrl($settings);
        $note = trim((string) ($settings['email_header_note'] ?? ''));
        $footerAddress = trim((string) ($settings['email_footer_address'] ?? ''));
        $safeButton = $buttonText && $buttonUrl
            ? '<p style="margin:26px 0 10px;text-align:center"><a href="' . e($buttonUrl) . '" style="display:inline-block;background:#7366ff;color:#fff;text-decoration:none;border-radius:6px;padding:12px 24px;font-weight:700">' . e($buttonText) . '</a></p>'
            : '';

        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>' . e($title) . '</title></head>'
            . '<body style="margin:0;background:#f4f6fb;font-family:Tahoma,Arial,sans-serif;color:#1f2430;line-height:1.9">'
            . '<div style="max-width:720px;margin:0 auto;padding:58px 18px;text-align:center">'
            . ($note !== '' ? '<div style="color:#8b90a3;font-size:13px;margin-bottom:24px">' . e($note) . '</div>' : '')
            . '<div style="margin-bottom:34px">' . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="' . e($systemName) . '" style="max-width:88px;max-height:70px;display:block;margin:0 auto 8px">' : '')
            . '<strong style="font-size:22px;letter-spacing:.5px">' . e($systemName) . '</strong></div>'
            . '<div style="background:#fff;border-radius:8px;padding:34px 42px;box-shadow:0 18px 55px rgba(31,36,48,.07);text-align:right">'
            . '<h1 style="font-size:20px;margin:0 0 18px;color:#111827">' . e($title) . '</h1>'
            . '<p style="margin:0;color:#303646;font-size:14px">' . nl2br(e($body)) . '</p>'
            . $safeButton
            . '<p style="margin:22px 0 0;color:#475569;font-size:13px">با احترام،<br>' . e($systemName) . '</p>'
            . '</div>'
            . '<div style="margin-top:28px;color:#98a1b3;font-size:12px">' . ($footerAddress !== '' ? e($footerAddress) . '<br>' : '') . 'این ایمیل به صورت خودکار ارسال شده است.</div>'
            . '</div></body></html>';
    }

    public static function orderSuccessTemplate(array $order, array $settings = null)
    {
        $settings = $settings ?: Settings::allKeyed();
        $systemName = $settings['system_name'] ?? app_config('app_name', 'پروما');
        $hero = self::absoluteAssetUrl('html/RTL/assets/images/email-template/order-success.png');
        $successIcon = self::absoluteAssetUrl('html/RTL/assets/images/email-template/success.png');
        $orderUrl = self::absoluteRouteUrl('ecommerce/payment/' . (int) ($order['id'] ?? 0));
        $rows = '';
        foreach (($order['items'] ?? []) as $item) {
            $imageUrl = !empty($item['image_path']) ? self::absoluteAssetUrl($item['image_path']) : '';
            $imageHtml = $imageUrl !== ''
                ? '<img src="' . e($imageUrl) . '" alt="" style="width:58px;height:58px;object-fit:cover;border-radius:8px;background:#f4f6fb;margin-left:10px;vertical-align:middle">'
                : '';
            $rows .= '<tr>'
                . '<td style="padding:14px;border:1px solid #e6e8ef;text-align:right;font-weight:700">' . $imageHtml . '<span style="vertical-align:middle">' . e($item['product_title'] ?? '') . '</span></td>'
                . '<td style="padding:14px;border:1px solid #e6e8ef;text-align:center">' . to_persian_digits($item['quantity'] ?? 0) . '</td>'
                . '<td style="padding:14px;border:1px solid #e6e8ef;text-align:left;direction:rtl">' . money_toman($item['total_price'] ?? 0) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="3" style="padding:18px;border:1px solid #e6e8ef;text-align:center;color:#8b90a3">جزئیات کالایی برای سفارش ثبت نشده است.</td></tr>';
        }

        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>ثبت سفارش</title></head>'
            . '<body style="margin:0;background:#f4f6fb;font-family:Tahoma,Arial,sans-serif;color:#1f2430;line-height:1.8">'
            . '<div style="max-width:760px;margin:0 auto;padding:34px 18px">'
            . '<div style="background:#fff;padding:42px 36px;box-shadow:0 16px 48px rgba(31,36,48,.12);text-align:center">'
            . '<img src="' . e($hero) . '" alt="" style="max-width:210px;width:56%;height:auto;margin:0 auto 24px;display:block">'
            . '<img src="' . e($successIcon) . '" alt="" style="width:64px;height:64px;margin:0 auto 18px;display:block">'
            . '<h1 style="font-size:24px;margin:0 0 12px;color:#2b2f3a">از شما متشکریم</h1>'
            . '<p style="margin:0 0 10px;color:#111827;font-size:15px">سفارش شما با موفقیت ثبت شد و در حال بررسی است.</p>'
            . '<p style="margin:0 0 30px;color:#111827;font-size:14px">شماره سفارش: <strong dir="ltr">' . e($order['order_number'] ?? '') . '</strong></p>'
            . '<div style="border-top:1px solid #d9dde7;margin:0 auto 28px;max-width:520px"></div>'
            . '<div style="max-width:520px;margin:0 auto 34px;text-align:center">'
            . '<h2 style="font-size:18px;margin:0 0 20px;color:#2b2f3a">Order Confirmed</h2>'
            . '<div style="display:flex;align-items:center;justify-content:space-between;color:#f5a400;font-size:12px">'
            . '<span style="display:block;text-align:center;width:25%"><b style="display:block;border:3px solid #f5a400;border-radius:50%;width:30px;height:30px;line-height:24px;margin:0 auto 6px">1</b>Order<br>Confirmed</span>'
            . '<span style="height:2px;background:#9ca3af;flex:1;margin-top:-28px"></span>'
            . '<span style="display:block;text-align:center;width:25%"><b style="display:block;border:3px solid #f5a400;border-radius:50%;width:30px;height:30px;line-height:24px;margin:0 auto 6px">2</b>Order<br>Shipped</span>'
            . '<span style="height:2px;background:#9ca3af;flex:1;margin-top:-28px"></span>'
            . '<span style="display:block;text-align:center;width:25%"><b style="display:block;border:3px solid #f5a400;border-radius:50%;width:30px;height:30px;line-height:24px;margin:0 auto 6px">3</b>Out for<br>Delivery</span>'
            . '<span style="height:2px;background:#9ca3af;flex:1;margin-top:-28px"></span>'
            . '<span style="display:block;text-align:center;width:25%"><b style="display:block;border:3px solid #f5a400;border-radius:50%;width:30px;height:30px;line-height:24px;margin:0 auto 6px">4</b>Order<br>Delivered</span>'
            . '</div></div>'
            . '<h2 style="font-size:20px;margin:0 0 18px;text-align:right;color:#2b2f3a">جزئیات سفارش شما</h2>'
            . '<table style="border-collapse:collapse;width:100%;font-size:13px;margin-bottom:22px"><thead><tr>'
            . '<th style="padding:13px;border:1px solid #e6e8ef;text-align:right;background:#fafbff">محصول</th>'
            . '<th style="padding:13px;border:1px solid #e6e8ef;text-align:center;background:#fafbff">تعداد</th>'
            . '<th style="padding:13px;border:1px solid #e6e8ef;text-align:left;background:#fafbff">PRICE</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<div style="display:flex;justify-content:space-between;gap:12px;background:#fafbff;border:1px solid #e6e8ef;padding:14px 16px;font-weight:700">'
            . '<span>مجموع سفارش</span><span>' . money_toman($order['total_amount'] ?? 0) . '</span></div>'
            . '<p style="margin:28px 0 0;text-align:center"><a href="' . e($orderUrl) . '" style="display:inline-block;background:#7366ff;color:#fff;text-decoration:none;border-radius:7px;padding:12px 26px;font-weight:700">مشاهده سفارش</a></p>'
            . '</div>'
            . '<div style="text-align:center;margin-top:18px;color:#98a1b3;font-size:12px">' . e($systemName) . '</div>'
            . '</div></body></html>';
    }

    protected static function sendSmtp(array $settings, $to, $subject, $html, $text, $fromAddress, $fromName)
    {
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = max(1, min(65535, (int) to_english_digits($settings['smtp_port'] ?? 587)));
        $encryption = strtolower(trim((string) ($settings['smtp_encryption'] ?? 'tls')));
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = @fsockopen($remote, $port, $errno, $errstr, 12);
        if (!$socket) {
            return ['ok' => false, 'message' => 'اتصال SMTP برقرار نشد: ' . $errstr];
        }
        stream_set_timeout($socket, 15);
        $reader = function () use ($socket) {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };
        $send = function ($command) use ($socket, $reader) {
            fwrite($socket, $command . "\r\n");
            return $reader();
        };
        $expect = function ($response, array $codes) {
            return in_array((int) substr($response, 0, 3), $codes, true);
        };

        $greeting = $reader();
        if (!$expect($greeting, [220])) {
            fclose($socket);
            return ['ok' => false, 'message' => 'پاسخ اولیه SMTP معتبر نیست.'];
        }
        $helloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!$expect($send('EHLO ' . $helloHost), [250])) {
            fclose($socket);
            return ['ok' => false, 'message' => 'دستور EHLO توسط SMTP پذیرفته نشد.'];
        }
        if ($encryption === 'tls') {
            if (!$expect($send('STARTTLS'), [220]) || !@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['ok' => false, 'message' => 'فعال‌سازی TLS برای SMTP ناموفق بود.'];
            }
            if (!$expect($send('EHLO ' . $helloHost), [250])) {
                fclose($socket);
                return ['ok' => false, 'message' => 'دستور EHLO پس از TLS پذیرفته نشد.'];
            }
        }
        $username = trim((string) ($settings['smtp_username'] ?? ''));
        $password = (string) ($settings['smtp_password'] ?? '');
        if ($username !== '') {
            if (!$expect($send('AUTH LOGIN'), [334])
                || !$expect($send(base64_encode($username)), [334])
                || !$expect($send(base64_encode($password)), [235])) {
                fclose($socket);
                return ['ok' => false, 'message' => 'احراز هویت SMTP ناموفق بود.'];
            }
        }
        if (!$expect($send('MAIL FROM:<' . $fromAddress . '>'), [250])
            || !$expect($send('RCPT TO:<' . $to . '>'), [250, 251])
            || !$expect($send('DATA'), [354])) {
            fclose($socket);
            return ['ok' => false, 'message' => 'SMTP پیام را برای ارسال نپذیرفت.'];
        }

        $headers = [
            'From: ' . self::formatMailbox($fromAddress, $fromName),
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];
        $replyTo = trim((string) ($settings['email_reply_to'] ?? ''));
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($html) . "\r\n.";
        if (!$expect($send($payload), [250])) {
            fclose($socket);
            return ['ok' => false, 'message' => 'ارسال محتوای ایمیل از طریق SMTP ناموفق بود.'];
        }
        $send('QUIT');
        fclose($socket);
        return ['ok' => true, 'message' => 'ایمیل ارسال شد.'];
    }

    protected static function formatMailbox($email, $name)
    {
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    protected static function encodeHeader($value)
    {
        $value = (string) $value;
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    protected static function dotStuff($body)
    {
        return preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", (string) $body));
    }

    protected static function orderText(array $order)
    {
        return 'سفارش ' . ($order['order_number'] ?? '') . ' با مبلغ ' . money_toman($order['total_amount'] ?? 0) . ' ثبت شد.';
    }

    protected static function logoUrl(array $settings)
    {
        $path = trim((string) ($settings['logo_icon_path'] ?? ''));
        if ($path === '') {
            $path = trim((string) ($settings['logo_path'] ?? ''));
        }
        return $path !== '' ? self::absoluteAssetUrl($path) : '';
    }

    protected static function absoluteRouteUrl($route)
    {
        $url = url($route);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $origin = app_origin();
        return $origin ? rtrim($origin, '/') . $url : $url;
    }

    protected static function absoluteAssetUrl($path)
    {
        $url = asset_url($path);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $origin = app_origin();
        return $origin ? rtrim($origin, '/') . $url : $url;
    }
}
