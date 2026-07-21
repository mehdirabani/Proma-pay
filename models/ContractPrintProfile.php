<?php

class ContractPrintProfile
{
    const OFFICIAL_COMPACT = 'official_compact';

    public static function presets()
    {
        return [
            self::OFFICIAL_COMPACT => [
                'label' => 'قرارداد فشرده یک‌صفحه‌ای',
                'margin_top' => 4, 'margin_right' => 7, 'margin_bottom' => 7, 'margin_left' => 7,
                'body_font_size' => 8, 'important_font_size' => 9, 'heading_font_size' => 9,
                'body_line_height' => 1.18, 'important_line_height' => 1.18, 'heading_line_height' => 1.16,
                'paragraph_spacing' => .5, 'section_top_spacing' => 1, 'section_bottom_spacing' => .5,
                'list_spacing' => 1, 'list_indent' => 10,
                'header_top_spacing' => 0, 'header_bottom_spacing' => 1, 'header_divider_spacing' => 1,
                'logo_width' => 32, 'logo_height' => 12,
                'table_font_size' => 7.2, 'table_heading_font_size' => 7.7, 'table_line_height' => 1.12,
                'table_cell_vertical_padding' => .5, 'table_cell_horizontal_padding' => 2, 'table_margin' => 1,
                'signature_top_spacing' => 5, 'signature_box_height' => 15,
            ],
            'standard' => [
                'label' => 'استاندارد',
                'margin_top' => 8, 'margin_right' => 12, 'margin_bottom' => 12, 'margin_left' => 12,
                'body_font_size' => 9, 'important_font_size' => 10, 'heading_font_size' => 10,
                'body_line_height' => 1.4, 'important_line_height' => 1.35, 'heading_line_height' => 1.3,
                'paragraph_spacing' => 3, 'section_top_spacing' => 4, 'section_bottom_spacing' => 2,
                'list_spacing' => 2, 'list_indent' => 14,
                'header_top_spacing' => 0, 'header_bottom_spacing' => 4, 'header_divider_spacing' => 3,
                'logo_width' => 36, 'logo_height' => 14,
                'table_font_size' => 8, 'table_heading_font_size' => 8.5, 'table_line_height' => 1.25,
                'table_cell_vertical_padding' => 2, 'table_cell_horizontal_padding' => 3, 'table_margin' => 4,
                'signature_top_spacing' => 10, 'signature_box_height' => 20,
            ],
            'comfortable' => [
                'label' => 'خوانا',
                'margin_top' => 10, 'margin_right' => 14, 'margin_bottom' => 14, 'margin_left' => 14,
                'body_font_size' => 10, 'important_font_size' => 11, 'heading_font_size' => 11,
                'body_line_height' => 1.55, 'important_line_height' => 1.45, 'heading_line_height' => 1.35,
                'paragraph_spacing' => 5, 'section_top_spacing' => 6, 'section_bottom_spacing' => 3,
                'list_spacing' => 3, 'list_indent' => 16,
                'header_top_spacing' => 0, 'header_bottom_spacing' => 5, 'header_divider_spacing' => 4,
                'logo_width' => 40, 'logo_height' => 16,
                'table_font_size' => 9, 'table_heading_font_size' => 9.5, 'table_line_height' => 1.3,
                'table_cell_vertical_padding' => 3, 'table_cell_horizontal_padding' => 4, 'table_margin' => 5,
                'signature_top_spacing' => 12, 'signature_box_height' => 24,
            ],
        ];
    }

    public static function defaults()
    {
        $preset = self::presets()[self::OFFICIAL_COMPACT];
        unset($preset['label']);
        return array_merge($preset, [
            'preset' => self::OFFICIAL_COMPACT, 'page_size' => 'A4', 'orientation' => 'portrait',
            'show_footer' => '0', 'show_customer_header' => '1', 'show_contract_title' => '1',
            'color_mode' => 'color',
        ]);
    }

    public static function load(array $settings = null)
    {
        $settings = $settings === null ? Settings::allKeyed() : $settings;
        if (($settings['contract_print_preset'] ?? '') === 'compact') {
            return self::fromPreset(self::OFFICIAL_COMPACT);
        }
        $values = self::defaults();
        foreach ($values as $key => $default) {
            $settingKey = 'contract_print_' . $key;
            if (array_key_exists($settingKey, $settings)) {
                $values[$key] = $settings[$settingKey];
            }
        }
        return self::validate($values);
    }

    public static function validate(array $input)
    {
        $defaults = self::defaults();
        $preset = self::normalizePreset($input['preset'] ?? self::OFFICIAL_COMPACT);
        $values = array_merge($defaults, ['preset' => $preset]);
        $ranges = [
            'margin_top' => [4, 25], 'margin_right' => [7, 25], 'margin_bottom' => [7, 30], 'margin_left' => [7, 25],
            'body_font_size' => [5, 14], 'important_font_size' => [6, 15], 'heading_font_size' => [6, 15],
            'body_line_height' => [1.15, 2], 'important_line_height' => [1.15, 2], 'heading_line_height' => [1.15, 2],
            'paragraph_spacing' => [0, 12], 'section_top_spacing' => [0, 16], 'section_bottom_spacing' => [0, 12],
            'list_spacing' => [0, 12], 'list_indent' => [6, 30],
            'header_top_spacing' => [0, 12], 'header_bottom_spacing' => [0, 16], 'header_divider_spacing' => [0, 16],
            'logo_width' => [15, 60], 'logo_height' => [8, 30],
            'table_font_size' => [5, 13], 'table_heading_font_size' => [5.5, 14], 'table_line_height' => [1.1, 1.8],
            'table_cell_vertical_padding' => [.5, 8], 'table_cell_horizontal_padding' => [1, 10], 'table_margin' => [0, 12],
            'signature_top_spacing' => [4, 30], 'signature_box_height' => [15, 45],
        ];
        foreach ($ranges as $key => $range) {
            $raw = to_english_digits($input[$key] ?? $defaults[$key]);
            $number = is_numeric($raw) ? (float) $raw : (float) $defaults[$key];
            $values[$key] = max($range[0], min($range[1], $number));
        }
        if ($preset === self::OFFICIAL_COMPACT) {
            $values['body_font_size'] = min(8, $values['body_font_size']);
            $values['important_font_size'] = min(9, $values['important_font_size']);
            $values['heading_font_size'] = min(9, $values['heading_font_size']);
            $values['body_line_height'] = max(1.15, min(1.3, $values['body_line_height']));
            $values['important_line_height'] = max(1.15, min(1.3, $values['important_line_height']));
        }
        foreach (['show_footer', 'show_customer_header', 'show_contract_title'] as $key) {
            $values[$key] = !empty($input[$key]) && (string) $input[$key] !== '0' ? '1' : '0';
        }
        $values['page_size'] = 'A4';
        $values['orientation'] = 'portrait';
        $values['color_mode'] = ($input['color_mode'] ?? '') === 'monochrome' ? 'monochrome' : 'color';
        return $values;
    }

    public static function fromPreset($preset)
    {
        $preset = self::normalizePreset($preset);
        if ($preset === 'custom') {
            $preset = self::OFFICIAL_COMPACT;
        }
        $values = self::presets()[$preset];
        unset($values['label']);
        return self::validate(array_merge(self::defaults(), $values, ['preset' => $preset]));
    }

    public static function settingValues(array $profile)
    {
        $profile = self::validate($profile);
        $values = [];
        foreach ($profile as $key => $value) {
            $values['contract_print_' . $key] = (string) $value;
        }
        return $values;
    }

    public static function styleVariables(array $profile)
    {
        $p = self::validate($profile);
        $units = [
            'margin_top' => 'mm', 'margin_right' => 'mm', 'margin_bottom' => 'mm', 'margin_left' => 'mm',
            'body_font_size' => 'px', 'important_font_size' => 'px', 'heading_font_size' => 'px',
            'paragraph_spacing' => 'px', 'section_top_spacing' => 'px', 'section_bottom_spacing' => 'px',
            'list_spacing' => 'px', 'list_indent' => 'px',
            'header_top_spacing' => 'px', 'header_bottom_spacing' => 'px', 'header_divider_spacing' => 'px',
            'logo_width' => 'mm', 'logo_height' => 'mm',
            'table_font_size' => 'px', 'table_heading_font_size' => 'px',
            'table_cell_vertical_padding' => 'px', 'table_cell_horizontal_padding' => 'px', 'table_margin' => 'px',
            'signature_top_spacing' => 'mm', 'signature_box_height' => 'mm',
        ];
        $items = [];
        foreach ($units as $key => $unit) {
            $items[] = '--contract-' . str_replace('_', '-', $key) . ':' . self::number($p[$key]) . $unit;
        }
        foreach (['body_line_height', 'important_line_height', 'heading_line_height', 'table_line_height'] as $key) {
            $items[] = '--contract-' . str_replace('_', '-', $key) . ':' . self::number($p[$key]);
        }
        return implode(';', $items) . ';';
    }

    public static function pageRule(array $profile)
    {
        $p = self::validate($profile);
        return '@page{size:A4 portrait;margin:' . self::number($p['margin_top']) . 'mm ' . self::number($p['margin_right']) . 'mm ' . self::number($p['margin_bottom']) . 'mm ' . self::number($p['margin_left']) . 'mm;}';
    }

    protected static function normalizePreset($preset)
    {
        $preset = (string) $preset;
        if ($preset === 'compact') {
            return self::OFFICIAL_COMPACT;
        }
        return in_array($preset, array_merge(array_keys(self::presets()), ['custom']), true) ? $preset : self::OFFICIAL_COMPACT;
    }

    protected static function number($value)
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
