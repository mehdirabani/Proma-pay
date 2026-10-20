<?php

namespace Proma\Plugins\Repair\Repositories;

class RepairSettingsRepository
{
    public const DEFAULTS = [
        'repair_number_prefix' => 'PR-REP',
        'default_warranty_days' => '0',
        'default_estimated_hours' => '24',
        'password_retention_days' => '0',
        'show_customer_financials' => '1',
        'show_customer_timeline' => '1',
        'show_customer_warranty' => '1',
        'show_customer_delivery_estimate' => '1',
        'receipt_business_name' => '',
        'receipt_contact' => '',
        'receipt_footer' => '',
        'receipt_paper_size' => 'A5',
        'receipt_copy_mode' => 'single',
        'receipt_terms' => '',
        'receipt_union_text' => '',
        'receipt_union_version' => '0',
        'receipt_union_updated_at' => '',
        'receipt_union_updated_by' => '0',
        'pickup_instructions' => '',
        'store_working_hours' => '',
        'store_address' => '',
    ];

    public function all(): array
    {
        $settings = self::DEFAULTS;
        $settings['receipt_template_html'] = \Proma\Plugins\Repair\Services\RepairReceiptTemplate::defaultTemplate();
        foreach (\Model::fetchAll('SELECT setting_key, setting_value FROM proma_repair_settings') as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        }
        if (\Proma\Plugins\Repair\Services\RepairReceiptTemplate::isLegacyDefault((string) $settings['receipt_template_html'])) {
            $settings['receipt_template_html'] = \Proma\Plugins\Repair\Services\RepairReceiptTemplate::defaultTemplate();
        }
        return $settings;
    }

    public function save(array $input, ?int $userId): array
    {
        $current = $this->all();
        $values = $current;
        $values['repair_number_prefix'] = $this->plain($input['repair_number_prefix'] ?? $current['repair_number_prefix'], 24);
        if ($values['repair_number_prefix'] === '' || !preg_match('/^[A-Za-z0-9-]+$/', $values['repair_number_prefix'])) {
            throw new \InvalidArgumentException('پیشوند شماره تعمیر فقط می‌تواند شامل حروف لاتین، عدد و خط تیره باشد.');
        }
        $values['default_warranty_days'] = (string) max(0, min(3650, (int) \to_english_digits($input['default_warranty_days'] ?? 0)));
        $values['default_estimated_hours'] = (string) max(1, min(8760, (int) \to_english_digits($input['default_estimated_hours'] ?? 24)));
        $values['password_retention_days'] = (string) max(-1, min(3650, (int) \to_english_digits($input['password_retention_days'] ?? 0)));
        foreach (['show_customer_financials', 'show_customer_timeline', 'show_customer_warranty', 'show_customer_delivery_estimate'] as $key) {
            $values[$key] = !empty($input[$key]) ? '1' : '0';
        }
        $values['receipt_paper_size'] = in_array(($input['receipt_paper_size'] ?? $current['receipt_paper_size']), ['A5', 'A4'], true)
            ? (string) ($input['receipt_paper_size'] ?? $current['receipt_paper_size']) : 'A5';
        $values['receipt_copy_mode'] = in_array(($input['receipt_copy_mode'] ?? $current['receipt_copy_mode']), ['single', 'duplicate'], true)
            ? (string) ($input['receipt_copy_mode'] ?? $current['receipt_copy_mode']) : 'single';
        foreach (['receipt_business_name' => 150, 'receipt_contact' => 255, 'receipt_footer' => 500, 'pickup_instructions' => 1200, 'store_working_hours' => 255, 'store_address' => 500] as $key => $max) {
            $values[$key] = $this->plain($input[$key] ?? '', $max);
        }
        $values['receipt_template_html'] = $this->safeTemplate($input['receipt_template_html'] ?? $current['receipt_template_html']);
        $values['receipt_terms'] = $this->richText($input['receipt_terms'] ?? '');
        $newUnionText = $this->richText($input['receipt_union_text'] ?? '');
        if ($newUnionText !== $current['receipt_union_text']) {
            $values['receipt_union_text'] = $newUnionText;
            $values['receipt_union_version'] = (string) ((int) $current['receipt_union_version'] + 1);
            $values['receipt_union_updated_at'] = date('Y-m-d H:i:s');
            $values['receipt_union_updated_by'] = (string) ((int) ($userId ?? 0));
        }
        \Model::begin();
        try {
            foreach ($values as $key => $value) {
                \Model::execute(
                    'INSERT INTO proma_repair_settings (setting_key, setting_value, updated_by, updated_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()',
                    [$key, (string) $value, $userId]
                );
            }
            if ($values['receipt_union_text'] !== $current['receipt_union_text']) {
                \Model::execute(
                    'INSERT INTO proma_repair_setting_versions (setting_key, version_number, setting_value, updated_by, created_at) VALUES (?, ?, ?, ?, NOW())',
                    ['receipt_union_text', (int) $values['receipt_union_version'], (string) $values['receipt_union_text'], $userId]
                );
            }
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        return $this->all();
    }

    public function seedDefaults(?int $userId): void
    {
        $defaults = self::DEFAULTS;
        $defaults['receipt_template_html'] = \Proma\Plugins\Repair\Services\RepairReceiptTemplate::defaultTemplate();
        foreach ($defaults as $key => $value) {
            \Model::execute(
                'INSERT IGNORE INTO proma_repair_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW())',
                [$key, (string) $value, $userId]
            );
        }
    }

    private function plain($value, int $max): string
    {
        $value = trim(strip_tags((string) $value));
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    private function richText($value): string
    {
        $value = strip_tags((string) $value, '<p><br><strong><b><em><i><u><ul><ol><li>');
        $value = preg_replace('/<([a-z0-9]+)\b[^>]*>/iu', '<$1>', $value);
        return mb_substr((string) $value, 0, 12000, 'UTF-8');
    }

    private function safeTemplate($value): string
    {
        $value = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', (string) $value);
        $value = strip_tags((string) $value, '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><div><span><header><footer><section><article><dl><dt><dd><table><thead><tbody><tr><th><td><blockquote><a>');
        $value = preg_replace_callback('/<([a-z][a-z0-9]*)(\s[^>]*)?>/i', static function (array $match): string {
            $tag = strtolower((string) $match[1]);
            $attributes = (string) ($match[2] ?? '');
            if (!preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attributes, $classMatch)) {
                return '<' . $tag . '>';
            }
            $class = preg_replace('/[^a-z0-9 _-]/i', '', (string) $classMatch[2]);
            return $class === '' ? '<' . $tag . '>' : '<' . $tag . ' class="' . $class . '">';
        }, (string) $value);
        return mb_substr((string) $value, 0, 30000, 'UTF-8');
    }
}
