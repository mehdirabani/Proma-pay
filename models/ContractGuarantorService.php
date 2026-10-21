<?php

/** Account resolution runs inside the owning contract transaction; no DDL. */
class ContractGuarantorService extends Model
{
    public static function customerIds(array $people, int $customerId, array $legacyPeople = []): array
    {
        $ids = [];
        foreach ($people as $person) {
            $name = trim((string) ($person['full_name'] ?? ''));
            if ($name === '') {
                continue; // Empty repeatable form row.
            }
            $nationalId = trim(to_english_digits($person['national_id'] ?? ''));
            $mobile = preg_replace('/\D+/', '', to_english_digits($person['mobile'] ?? ''));
            if (strlen($mobile) === 10 && strpos($mobile, '9') === 0) {
                $mobile = '0' . $mobile;
            }
            if (!preg_match('/^\d{10}$/D', $nationalId) || !preg_match('/^09\d{9}$/D', $mobile)) {
                // Unchanged legacy document-only people must not block unrelated edits.
                $unchanged = false;
                foreach ($legacyPeople as $legacy) {
                    if ($name === trim((string) $legacy['full_name'])
                        && $nationalId === trim(to_english_digits($legacy['national_id'] ?? ''))
                        && $mobile === preg_replace('/\D+/', '', to_english_digits($legacy['mobile'] ?? ''))) {
                        $unchanged = true;
                        break;
                    }
                }
                if ($unchanged) {
                    continue;
                }
                throw new InvalidArgumentException('برای ثبت ضامن جدید، نام، کد ملی ۱۰ رقمی و موبایل معتبر را وارد کنید.');
            }
            $matches = self::fetchAll(
                'SELECT id, role, status, national_id, mobile FROM users WHERE national_id = ? OR mobile = ? ORDER BY id FOR UPDATE',
                [$nationalId, $mobile]
            );
            if (count($matches) > 1) {
                throw new InvalidArgumentException('کد ملی و موبایل ضامن به دو حساب متفاوت تعلق دارند؛ اطلاعات را بررسی کنید.');
            }
            if ($matches) {
                $match = $matches[0];
                if ($match['role'] !== 'customer' || $match['status'] !== 'active'
                    || (string) $match['national_id'] !== $nationalId || (string) $match['mobile'] !== $mobile) {
                    throw new InvalidArgumentException('اطلاعات ضامن با حساب موجود یکسان نیست یا حساب فعال مشتری ندارد. مشتری موجود را انتخاب یا اطلاعات را اصلاح کنید.');
                }
                $id = (int) $match['id'];
            } else {
                $id = User::create([
                    'role' => 'customer', 'status' => 'active', 'full_name' => $name,
                    'national_id' => $nationalId, 'mobile' => $mobile,
                    'father_name' => $person['father_name'] ?? '', 'address' => $person['address'] ?? '',
                ]);
            }
            if ($id === $customerId) {
                throw new InvalidArgumentException('مشتری قرارداد نمی‌تواند ضامن همان قرارداد باشد.');
            }
            $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }
}
