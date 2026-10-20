<?php

namespace Proma\Plugins\Repair\Services;

class AttachmentService
{
    public function store(array $upload, int $repairId): array
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new \InvalidArgumentException('بارگذاری تصویر انجام نشد.');
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 6291456) {
            throw new \InvalidArgumentException('حجم هر تصویر باید حداکثر ۶ مگابایت باشد.');
        }
        $extension = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $image = @getimagesize($upload['tmp_name']);
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if (!$image || !isset($allowed[$extension]) || ($image['mime'] ?? '') !== $allowed[$extension]) {
            throw new \InvalidArgumentException('فقط تصویر معتبر JPEG، PNG یا WebP پذیرفته می‌شود.');
        }
        if ((int) $image[0] > 6000 || (int) $image[1] > 6000 || (int) $image[0] * (int) $image[1] > 24000000) {
            throw new \InvalidArgumentException('ابعاد تصویر بیش از محدوده مجاز است.');
        }
        $root = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'promarepair';
        $directory = $root . DIRECTORY_SEPARATOR . $repairId;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('فضای خصوصی ذخیره تصاویر آماده نیست.');
        }
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new \RuntimeException('ذخیره تصویر تعمیر انجام نشد.');
        }
        @chmod($destination, 0600);
        return [
            'path' => 'storage/private/promarepair/' . $repairId . '/' . $name,
            'mime' => $allowed[$extension],
            'size' => $size,
        ];
    }
}
