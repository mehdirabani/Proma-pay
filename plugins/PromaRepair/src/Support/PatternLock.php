<?php

namespace Proma\Plugins\Repair\Support;

final class PatternLock
{
    public static function normalize($value): string
    {
        $nodes = is_array($value) ? $value : preg_split('/[-,\s]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($nodes as $node) {
            $node = (int) \to_english_digits((string) $node);
            if ($node < 1 || $node > 9 || in_array($node, $result, true)) {
                throw new \InvalidArgumentException('پترن دستگاه معتبر نیست؛ هر نقطه را فقط یک‌بار انتخاب کنید.');
            }
            $result[] = $node;
        }
        if (count($result) < 4) {
            throw new \InvalidArgumentException('پترن باید دست‌کم از چهار نقطه تشکیل شود.');
        }
        return implode('-', $result);
    }

    public static function nodes(string $value): array
    {
        return array_map('intval', explode('-', self::normalize($value)));
    }
}
