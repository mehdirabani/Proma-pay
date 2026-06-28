<?php

class SpreadsheetHelper
{
    public static function read($path, $originalName)
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return self::readCsv($path);
        }
        if ($extension === 'txt') {
            return self::readText($path);
        }
        if ($extension === 'xlsx') {
            return self::readXlsx($path);
        }
        throw new RuntimeException('فقط فایل‌های اکسل، سی‌اس‌وی یا متن پذیرفته می‌شود.');
    }

    public static function readRawText($text)
    {
        $lines = preg_split('/\R/u', trim((string) $text));
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $rows[] = [$line];
        }
        return $rows;
    }

    protected static function readCsv($path)
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('فایل بارگذاری شده قابل خواندن نیست.');
        }
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_map('trim', $data);
        }
        fclose($handle);
        return $rows;
    }

    protected static function readText($path)
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('فایل متنی قابل خواندن نیست.');
        }
        return self::readRawText($content);
    }

    protected static function readXlsx($path)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه خواندن فایل اکسل روی سرور فعال نیست.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('فایل اکسل قابل باز شدن نیست.');
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } elseif (isset($si->r)) {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared[] = $text;
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('برگه نخست فایل اکسل پیدا نشد.');
        }
        $xml = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $cells[] = trim($value);
            }
            $rows[] = $cells;
        }
        return $rows;
    }
}
