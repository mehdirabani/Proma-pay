<?php

class ContractPrintProfile
{
    public static function presets()
    {
        return [
            'compact' => [
                'margin_top' => 6, 'margin_right' => 10, 'margin_bottom' => 10, 'margin_left' => 10,
                'body_font_size' => 10.5, 'body_line_height' => 1.45, 'paragraph_spacing' => 1.5,
                'section_top_spacing' => 2.5, 'section_bottom_spacing' => 1.5,
                'header_top_spacing' => 0, 'header_bottom_spacing' => 2.5, 'header_divider_spacing' => 3,
                'logo_width' => 38, 'logo_height' => 15, 'table_font_size' => 9.5,
                'table_cell_vertical_padding' => 1.2, 'table_cell_horizontal_padding' => 1.8,
                'signature_top_spacing' => 12, 'signature_box_height' => 22,
            ],
            'standard' => [
                'margin_top' => 8, 'margin_right' => 12, 'margin_bottom' => 12, 'margin_left' => 12,
                'body_font_size' => 11, 'body_line_height' => 1.55, 'paragraph_spacing' => 2.2,
                'section_top_spacing' => 3, 'section_bottom_spacing' => 1.8,
                'header_top_spacing' => 0, 'header_bottom_spacing' => 3, 'header_divider_spacing' => 3.5,
                'logo_width' => 40, 'logo_height' => 16, 'table_font_size' => 10,
                'table_cell_vertical_padding' => 1.5, 'table_cell_horizontal_padding' => 2,
                'signature_top_spacing' => 14, 'signature_box_height' => 24,
            ],
            'comfortable' => [
                'margin_top' => 10, 'margin_right' => 14, 'margin_bottom' => 14, 'margin_left' => 14,
                'body_font_size' => 11.5, 'body_line_height' => 1.7, 'paragraph_spacing' => 3,
                'section_top_spacing' => 4, 'section_bottom_spacing' => 2.2,
                'header_top_spacing' => 0, 'header_bottom_spacing' => 4, 'header_divider_spacing' => 4,
                'logo_width' => 42, 'logo_height' => 17, 'table_font_size' => 10.5,
                'table_cell_vertical_padding' => 1.8, 'table_cell_horizontal_padding' => 2.2,
                'signature_top_spacing' => 16, 'signature_box_height' => 26,
            ],
        ];
    }

    public static function defaults()
    {
        return array_merge(self::presets()['compact'], [
            'preset' => 'compact', 'page_size' => 'A4', 'orientation' => 'portrait',
            'show_footer' => '0', 'show_customer_header' => '1', 'show_contract_title' => '1',
            'color_mode' => 'color',
        ]);
    }

    public static function load(array $settings = null)
    {
        $settings = $settings === null ? Settings::allKeyed() : $settings;
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
        $preset = in_array($input['preset'] ?? '', ['compact', 'standard', 'comfortable', 'custom'], true) ? $input['preset'] : 'compact';
        $values = array_merge($defaults, ['preset' => $preset]);
        $ranges = [
            'margin_top' => [4, 25], 'margin_right' => [7, 25], 'margin_bottom' => [7, 30], 'margin_left' => [7, 25],
            'body_font_size' => [8.5, 14], 'body_line_height' => [1.2, 2], 'paragraph_spacing' => [0, 10],
            'section_top_spacing' => [0, 12], 'section_bottom_spacing' => [0, 10],
            'header_top_spacing' => [0, 10], 'header_bottom_spacing' => [0, 12], 'header_divider_spacing' => [0, 12],
            'logo_width' => [15, 60], 'logo_height' => [8, 30], 'table_font_size' => [8, 13],
            'table_cell_vertical_padding' => [.5, 5], 'table_cell_horizontal_padding' => [.5, 6],
            'signature_top_spacing' => [4, 30], 'signature_box_height' => [15, 45],
        ];
        foreach ($ranges as $key => $range) {
            $raw = to_english_digits($input[$key] ?? $defaults[$key]);
            $number = is_numeric($raw) ? (float) $raw : (float) $defaults[$key];
            $values[$key] = max($range[0], min($range[1], $number));
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
        $preset = in_array($preset, ['compact', 'standard', 'comfortable'], true) ? $preset : 'compact';
        return self::validate(array_merge(self::defaults(), self::presets()[$preset], ['preset' => $preset]));
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
        $mm = ['margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'paragraph_spacing', 'section_top_spacing', 'section_bottom_spacing', 'header_top_spacing', 'header_bottom_spacing', 'header_divider_spacing', 'logo_width', 'logo_height', 'table_cell_vertical_padding', 'table_cell_horizontal_padding', 'signature_top_spacing', 'signature_box_height'];
        $pt = ['body_font_size', 'table_font_size'];
        $items = [];
        foreach ($mm as $key) {
            $items[] = '--contract-' . str_replace('_', '-', $key) . ':' . self::number($p[$key]) . 'mm';
        }
        foreach ($pt as $key) {
            $items[] = '--contract-' . str_replace('_', '-', $key) . ':' . self::number($p[$key]) . 'pt';
        }
        $items[] = '--contract-body-line-height:' . self::number($p['body_line_height']);
        return implode(';', $items) . ';';
    }

    public static function pageRule(array $profile)
    {
        $p = self::validate($profile);
        return '@page{size:A4 portrait;margin:' . self::number($p['margin_top']) . 'mm ' . self::number($p['margin_right']) . 'mm ' . self::number($p['margin_bottom']) . 'mm ' . self::number($p['margin_left']) . 'mm;}';
    }

    protected static function number($value)
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
