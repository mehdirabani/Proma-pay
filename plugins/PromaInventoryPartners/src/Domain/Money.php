<?php

namespace Proma\Plugins\InventoryPartners\Domain;

final class Money
{
    public static function fromToman($value)
    {
        $normalized = self::normalizeDigits($value);
        $normalized = str_replace(['٬', ',', '،', ' ', 'تومان', 'ریال'], '', $normalized);
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/D', $normalized, $matches)) {
            throw new \InvalidArgumentException('مبلغ باید عدد صحیح و نامنفی باشد.');
        }
        $whole = self::positiveInteger($matches[1]);
        $fraction = $matches[2] ?? '';
        if ($fraction !== '' && (strlen($fraction) > 1 && preg_match('/[1-9]/', substr($fraction, 1)))) {
            throw new \InvalidArgumentException('دقت مبلغ بیشتر از یک ریال پشتیبانی نمی‌شود.');
        }
        $rialFraction = $fraction === '' ? 0 : (int) $fraction[0];
        return self::add(self::multiply($whole, 10), $rialFraction);
    }

    public static function fromPromaToman($value)
    {
        return self::fromToman($value);
    }

    public static function integer($value)
    {
        $normalized = self::normalizeDigits($value);
        $normalized = str_replace(['٬', ',', '،', 'ریال', ' '], '', $normalized);
        if (!preg_match('/^\d+$/D', $normalized)) {
            throw new \InvalidArgumentException('مبلغ داخلی معتبر نیست.');
        }
        return self::positiveInteger($normalized);
    }

    public static function quantity($value)
    {
        $normalized = self::normalizeDigits($value);
        $normalized = str_replace(['٬', ',', '،', ' '], '', $normalized);
        if (!preg_match('/^\d+$/D', $normalized)) {
            throw new \InvalidArgumentException('تعداد باید عدد صحیح و نامنفی باشد.');
        }
        return self::positiveInteger($normalized);
    }

    public static function formatToman($rial)
    {
        $rial = self::integer($rial);
        $whole = intdiv($rial, 10);
        $formatted = number_format($whole, 0, '.', ',');
        if (($rial % 10) !== 0) {
            $formatted .= '.' . (string) ($rial % 10);
        }
        return self::persianDigits($formatted) . ' تومان';
    }

    public static function formatInteger($value)
    {
        return self::persianDigits(number_format(self::integer($value), 0, '.', ','));
    }

    public static function basisPoints($value)
    {
        $normalized = self::normalizeDigits($value);
        $normalized = str_replace(['٪', '%', ' '], '', $normalized);
        if (!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $normalized, $matches)) {
            throw new \InvalidArgumentException('درصد باید بین صفر و صد و حداکثر با دو رقم اعشار باشد.');
        }
        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');
        $points = ($whole * 100) + $fraction;
        if ($points > 10000) {
            throw new \InvalidArgumentException('درصد نمی‌تواند از صد بیشتر باشد.');
        }
        return $points;
    }

    /**
     * Split an integer amount by basis-point weights. Any residual rial is
     * assigned by largest remainder, with partner ID as the stable tie-breaker.
     */
    public static function allocate($amount, array $shares)
    {
        $amount = self::integer($amount);
        if (!$shares) {
            throw new \InvalidArgumentException('برای تخصیص مبلغ، سهم مالکان لازم است.');
        }
        $total = 0;
        $rows = [];
        foreach ($shares as $share) {
            $partnerId = (int) ($share['partner_id'] ?? 0);
            $weight = (int) ($share['weight_bp'] ?? $share['share_bp'] ?? 0);
            if ($partnerId <= 0 || $weight < 0 || $weight > 10000 || isset($rows[$partnerId])) {
                throw new \InvalidArgumentException('ردیف سهم شریک معتبر نیست.');
            }
            $total += $weight;
            $base = intdiv($amount, 10000) * $weight;
            $tail = ($amount % 10000) * $weight;
            $rows[$partnerId] = [
                'amount' => $base + intdiv($tail, 10000),
                'remainder' => $tail % 10000,
                'partner_id' => $partnerId,
            ];
        }
        if ($total !== 10000) {
            throw new \InvalidArgumentException('جمع سهم‌ها باید دقیقاً صد درصد باشد.');
        }
        $assigned = 0;
        foreach ($rows as $row) {
            $assigned = self::add($assigned, $row['amount']);
        }
        $left = $amount - $assigned;
        $order = array_values($rows);
        usort($order, static function (array $leftRow, array $rightRow) {
            if ($leftRow['remainder'] !== $rightRow['remainder']) {
                return $rightRow['remainder'] <=> $leftRow['remainder'];
            }
            return $leftRow['partner_id'] <=> $rightRow['partner_id'];
        });
        for ($index = 0; $index < $left; $index++) {
            $partnerId = $order[$index]['partner_id'];
            $rows[$partnerId]['amount']++;
        }
        $result = [];
        foreach ($rows as $partnerId => $row) {
            $result[$partnerId] = $row['amount'];
        }
        return $result;
    }

    public static function allocateCapped($amount, array $rows, $weightField = 'weight_bp', $capField = 'cap_rial')
    {
        $amount = self::integer($amount);
        $indexed = [];
        $capacity = 0;
        foreach ($rows as $row) {
            $partnerId = (int) ($row['partner_id'] ?? 0);
            $weight = (int) ($row[$weightField] ?? 0);
            $cap = self::integer($row[$capField] ?? 0);
            if ($partnerId <= 0 || $weight < 0 || isset($indexed[$partnerId])) {
                throw new \InvalidArgumentException('ردیف تخصیص محدود معتبر نیست.');
            }
            $indexed[$partnerId] = ['partner_id' => $partnerId, 'weight' => $weight, 'cap' => $cap, 'amount' => 0];
            $capacity = self::add($capacity, $cap);
        }
        if ($amount > $capacity) {
            throw new \InvalidArgumentException('مبلغ تخصیص از مانده شرکا بیشتر است.');
        }
        $remaining = $amount;
        while ($remaining > 0) {
            $active = array_values(array_filter($indexed, static function (array $row) {
                return $row['cap'] > $row['amount'];
            }));
            if (!$active) {
                throw new \LogicException('تخصیص باقیمانده امکان‌پذیر نیست.');
            }
            $weightTotal = array_sum(array_column($active, 'weight'));
            if ($weightTotal <= 0) {
                $weightTotal = count($active);
                foreach ($active as &$row) { $row['weight'] = 1; }
                unset($row);
            }
            $rounds = [];
            $floorTotal = 0;
            foreach ($active as $row) {
                $q = intdiv($remaining, $weightTotal);
                $r = $remaining % $weightTotal;
                $base = self::add(self::multiply($q, $row['weight']), intdiv($r * $row['weight'], $weightTotal));
                $remainder = ($r * $row['weight']) % $weightTotal;
                $headroom = $row['cap'] - $row['amount'];
                $grant = min($base, $headroom);
                $indexed[$row['partner_id']]['amount'] = self::add($indexed[$row['partner_id']]['amount'], $grant);
                $floorTotal = self::add($floorTotal, $grant);
                $rounds[] = ['partner_id' => $row['partner_id'], 'remainder' => $remainder, 'headroom' => $headroom - $grant];
            }
            $remaining -= $floorTotal;
            if ($remaining <= 0) {
                break;
            }
            usort($rounds, static function (array $left, array $right) {
                if ($left['remainder'] !== $right['remainder']) {
                    return $right['remainder'] <=> $left['remainder'];
                }
                return $left['partner_id'] <=> $right['partner_id'];
            });
            $progress = 0;
            foreach ($rounds as $round) {
                if ($remaining <= 0) { break; }
                if ($round['headroom'] <= 0) { continue; }
                $indexed[$round['partner_id']]['amount']++;
                $remaining--;
                $progress++;
            }
            if ($floorTotal === 0 && $progress === 0) {
                throw new \LogicException('تخصیص باقیمانده امکان‌پذیر نیست.');
            }
        }
        $result = [];
        foreach ($indexed as $partnerId => $row) {
            $result[$partnerId] = $row['amount'];
        }
        return $result;
    }

    public static function multiply($amount, $quantity)
    {
        $amount = self::integer($amount);
        $quantity = (int) $quantity;
        if ($quantity < 0 || ($quantity > 0 && $amount > intdiv(PHP_INT_MAX, $quantity))) {
            throw new \OverflowException('مبلغ از محدوده محاسبات صحیح بیشتر است.');
        }
        return $amount * $quantity;
    }

    public static function add($left, $right)
    {
        $left = (int) $left;
        $right = (int) $right;
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new \OverflowException('جمع مبلغ از محدوده پشتیبانی‌شده بیشتر است.');
        }
        return $left + $right;
    }

    public static function db($value)
    {
        return (string) self::integer($value);
    }

    private static function positiveInteger($value)
    {
        $digits = ltrim((string) $value, '0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new \OverflowException('مبلغ از محدوده محاسبات صحیح بیشتر است.');
        }
        return (int) $digits;
    }

    private static function normalizeDigits($value)
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private static function persianDigits($value)
    {
        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
