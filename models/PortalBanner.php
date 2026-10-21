<?php

class PortalBanner extends Model
{
    public const PLACEMENT_CUSTOMER_DASHBOARD = 'customer_dashboard';

    public static function allForAdmin()
    {
        return self::fetchAll(
            'SELECT pb.*, creator.full_name AS creator_name, updater.full_name AS updater_name
             FROM portal_banners pb
             LEFT JOIN users creator ON creator.id = pb.created_by
             LEFT JOIN users updater ON updater.id = pb.updated_by
             WHERE pb.archived_at IS NULL
             ORDER BY pb.sort_order ASC, pb.id DESC'
        );
    }

    public static function activeForCustomer($limit = 10)
    {
        $limit = max(1, min(10, (int) $limit));
        try {
            return self::fetchAll(
                "SELECT * FROM portal_banners
                 WHERE placement = ? AND audience_role = 'customer' AND is_active = 1
                 AND archived_at IS NULL
                 AND (starts_at IS NULL OR starts_at <= NOW())
                 AND (ends_at IS NULL OR ends_at > NOW())
                 ORDER BY sort_order ASC, id DESC
                 LIMIT {$limit}",
                [self::PLACEMENT_CUSTOMER_DASHBOARD]
            );
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1146) {
                return [];
            }
            throw $e;
        }
    }

    public static function find($id)
    {
        return self::fetch(
            'SELECT * FROM portal_banners WHERE id = ? AND archived_at IS NULL LIMIT 1',
            [(int) $id]
        );
    }

    public static function save(array $data, $actorId, $id = null)
    {
        $before = $id ? self::find((int) $id) : null;
        if ($id && !$before) {
            throw new InvalidArgumentException('بنر انتخاب‌شده پیدا نشد.');
        }

        $payload = self::normalize($data, $before ?: []);
        if ($id) {
            self::execute(
                'UPDATE portal_banners SET
                 title = ?, body = ?, eyebrow = ?, desktop_image_path = ?, mobile_image_path = ?, image_alt = ?,
                 cta_label = ?, link_type = ?, link_target = ?, open_in_new_tab = ?, tone = ?, starts_at = ?, ends_at = ?,
                 is_active = ?, sort_order = ?, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND archived_at IS NULL',
                [
                    $payload['title'], $payload['body'], $payload['eyebrow'], $payload['desktop_image_path'],
                    $payload['mobile_image_path'], $payload['image_alt'], $payload['cta_label'], $payload['link_type'],
                    $payload['link_target'], $payload['open_in_new_tab'], $payload['tone'], $payload['starts_at'],
                    $payload['ends_at'], $payload['is_active'], $payload['sort_order'], (int) $actorId, (int) $id,
                ]
            );
            $bannerId = (int) $id;
            $action = 'updated';
        } else {
            self::execute(
                "INSERT INTO portal_banners
                 (placement, audience_role, title, body, eyebrow, desktop_image_path, mobile_image_path, image_alt,
                  cta_label, link_type, link_target, open_in_new_tab, tone, starts_at, ends_at, is_active, sort_order,
                  created_by, updated_by, created_at, updated_at)
                 VALUES (?, 'customer', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    self::PLACEMENT_CUSTOMER_DASHBOARD, $payload['title'], $payload['body'], $payload['eyebrow'],
                    $payload['desktop_image_path'], $payload['mobile_image_path'], $payload['image_alt'],
                    $payload['cta_label'], $payload['link_type'], $payload['link_target'], $payload['open_in_new_tab'],
                    $payload['tone'], $payload['starts_at'], $payload['ends_at'], $payload['is_active'],
                    $payload['sort_order'], (int) $actorId, (int) $actorId,
                ]
            );
            $bannerId = (int) self::lastInsertId();
            $action = 'created';
        }

        $after = self::find($bannerId);
        if (class_exists('AuditLog')) {
            AuditLog::record('portal_banner', $action, 'portal_banner', $bannerId, [
                'actor_user_id' => (int) $actorId,
                'severity' => 'medium',
                'old_values' => $before,
                'new_values' => $after,
                'description' => $action === 'created' ? 'ایجاد بنر پنل مشتری' : 'ویرایش بنر پنل مشتری',
            ]);
        }
        return $after;
    }

    public static function toggle($id, $actorId)
    {
        $before = self::find((int) $id);
        if (!$before) {
            throw new InvalidArgumentException('بنر انتخاب‌شده پیدا نشد.');
        }
        $next = empty($before['is_active']) ? 1 : 0;
        self::execute(
            'UPDATE portal_banners SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND archived_at IS NULL',
            [$next, (int) $actorId, (int) $id]
        );
        if (class_exists('AuditLog')) {
            AuditLog::record('portal_banner', $next ? 'activated' : 'deactivated', 'portal_banner', (int) $id, [
                'actor_user_id' => (int) $actorId,
                'severity' => 'medium',
                'old_values' => ['is_active' => (int) $before['is_active']],
                'new_values' => ['is_active' => $next],
            ]);
        }
        return $next === 1;
    }

    public static function archive($id, $actorId)
    {
        $before = self::find((int) $id);
        if (!$before) {
            throw new InvalidArgumentException('بنر انتخاب‌شده پیدا نشد.');
        }
        self::execute(
            'UPDATE portal_banners SET is_active = 0, archived_at = NOW(), archived_by = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND archived_at IS NULL',
            [(int) $actorId, (int) $actorId, (int) $id]
        );
        if (class_exists('AuditLog')) {
            AuditLog::record('portal_banner', 'archived', 'portal_banner', (int) $id, [
                'actor_user_id' => (int) $actorId,
                'severity' => 'medium',
                'old_values' => $before,
                'new_values' => ['archived_at' => date('Y-m-d H:i:s'), 'is_active' => 0],
                'description' => 'بایگانی بنر پنل مشتری بدون حذف تاریخچه',
            ]);
        }
    }

    protected static function normalize(array $data, array $before)
    {
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        if ($title === '') {
            throw new InvalidArgumentException('عنوان بنر الزامی است.');
        }
        $startsAt = self::normalizeDateTime($data['starts_at'] ?? null);
        $endsAt = self::normalizeDateTime($data['ends_at'] ?? null);
        if ($startsAt && $endsAt && strtotime($endsAt) <= strtotime($startsAt)) {
            throw new InvalidArgumentException('زمان پایان بنر باید بعد از زمان شروع باشد.');
        }

        $linkType = in_array(($data['link_type'] ?? 'none'), ['none', 'internal', 'external'], true)
            ? (string) $data['link_type'] : 'none';
        $linkTarget = trim((string) ($data['link_target'] ?? ''));
        if ($linkType === 'internal') {
            $linkTarget = trim($linkTarget, " \t\n\r\0\x0B/");
            if ($linkTarget === '' || !preg_match('#^[a-z0-9][a-z0-9/_-]*(?:\?[a-z0-9_=&%.-]+)?$#i', $linkTarget)) {
                throw new InvalidArgumentException('مسیر داخلی بنر معتبر نیست.');
            }
        } elseif ($linkType === 'external') {
            if (!filter_var($linkTarget, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $linkTarget)) {
                throw new InvalidArgumentException('پیوند خارجی باید یک نشانی معتبر HTTP یا HTTPS باشد.');
            }
        } else {
            $linkTarget = '';
        }

        return [
            'title' => mb_substr($title, 0, 190, 'UTF-8'),
            'body' => mb_substr(trim(strip_tags((string) ($data['body'] ?? ''))), 0, 500, 'UTF-8'),
            'eyebrow' => mb_substr(trim(strip_tags((string) ($data['eyebrow'] ?? ''))), 0, 80, 'UTF-8'),
            'desktop_image_path' => trim((string) ($data['desktop_image_path'] ?? ($before['desktop_image_path'] ?? ''))) ?: null,
            'mobile_image_path' => trim((string) ($data['mobile_image_path'] ?? ($before['mobile_image_path'] ?? ''))) ?: null,
            'image_alt' => mb_substr(trim(strip_tags((string) ($data['image_alt'] ?? ''))), 0, 190, 'UTF-8'),
            'cta_label' => mb_substr(trim(strip_tags((string) ($data['cta_label'] ?? ''))), 0, 80, 'UTF-8'),
            'link_type' => $linkType,
            'link_target' => mb_substr($linkTarget, 0, 500, 'UTF-8'),
            'open_in_new_tab' => $linkType === 'external' && !empty($data['open_in_new_tab']) ? 1 : 0,
            'tone' => in_array(($data['tone'] ?? 'primary'), ['primary', 'success', 'warning', 'info', 'neutral'], true)
                ? (string) $data['tone'] : 'primary',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => max(0, min(9999, (int) to_english_digits($data['sort_order'] ?? 0))),
        ];
    }

    protected static function normalizeDateTime($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('زمان‌بندی بنر معتبر نیست.');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }
}
