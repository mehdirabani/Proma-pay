<?php

class SpreadsheetHelper
{
    /**
     * Collect a bounded, paginated result and send it as an XLSX workbook.
     * The callbacks keep each controller's existing filters and role scope.
     */
    public static function downloadPaginatedXlsx(string $filename, string $sheetName, array $headers, callable $fetchPage, callable $mapRow, int $perPage = 100): void
    {
        $firstPage = $fetchPage(1, $perPage);
        if (!is_array($firstPage) || !isset($firstPage['items']) || !is_array($firstPage['items'])) {
            throw new RuntimeException('صفحهٔ داده برای خروجی اکسل معتبر نیست.');
        }
        if ((int) ($firstPage['total'] ?? 0) > 100000) {
            throw new RuntimeException('تعداد ردیف‌ها از سقف خروجی اکسل بیشتر است؛ فیلترها را محدودتر کنید.');
        }
        $pages = max(1, (int) ($firstPage['pages'] ?? ceil((int) ($firstPage['total'] ?? 0) / max(1, $perPage))));
        $rows = static function () use ($firstPage, $pages, $perPage, $fetchPage, $mapRow): iterable {
            $count = 0;
            for ($page = 1; $page <= $pages; $page++) {
                $result = $page === 1 ? $firstPage : $fetchPage($page, $perPage);
                if (!is_array($result) || !isset($result['items']) || !is_array($result['items'])) {
                    throw new RuntimeException('صفحهٔ داده برای خروجی اکسل معتبر نیست.');
                }
                foreach ($result['items'] as $item) {
                    if (++$count > 100000) {
                        throw new RuntimeException('تعداد ردیف‌ها از سقف خروجی اکسل بیشتر است؛ فیلترها را محدودتر کنید.');
                    }
                    yield $mapRow($item);
                }
            }
        };

        self::downloadXlsx($filename, $sheetName, $headers, $rows());
    }

    /**
     * Send a real OOXML workbook without requiring an additional PHP package.
     * Values supplied as strings remain text cells (including values beginning
     * with "="); integers and floats remain numeric cells.
     */
    public static function downloadXlsx(string $filename, string $sheetName, array $headers, iterable $rows): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('افزونه ZIP برای ساخت فایل اکسل فعال نیست.');
        }
        if (!$headers) {
            throw new InvalidArgumentException('ستون‌های فایل اکسل مشخص نشده‌اند.');
        }
        if (count($headers) > 16384) {
            throw new InvalidArgumentException('تعداد ستون‌های فایل اکسل از حد مجاز بیشتر است.');
        }

        $filename = trim(basename($filename));
        if ($filename === '') {
            $filename = 'export.xlsx';
        }
        if (strtolower(substr($filename, -5)) !== '.xlsx') {
            $filename .= '.xlsx';
        }
        $safeSheetName = preg_replace('/[\[\]:*?\/\\\\]/u', ' ', trim($sheetName));
        $safeSheetName = trim(mb_substr((string) $safeSheetName, 0, 31, 'UTF-8'), "' ");
        if ($safeSheetName === '') {
            $safeSheetName = 'گزارش';
        }

        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'proma-xlsx-' . bin2hex(random_bytes(8));
        if (!mkdir($workDir, 0700, true)) {
            throw new RuntimeException('فضای موقت فایل اکسل ساخته نشد.');
        }
        $xlsxPath = $workDir . DIRECTORY_SEPARATOR . 'export.xlsx';
        $sheetPath = $workDir . DIRECTORY_SEPARATOR . 'sheet1.xml';
        $sheet = null;

        try {
            $sheet = fopen($sheetPath, 'wb');
            if (!$sheet) {
                throw new RuntimeException('برگهٔ فایل اکسل ساخته نشد.');
            }

            $columnEnd = self::excelColumnName(count($headers));
            fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            fwrite($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><sheetData>');
            self::writeXlsxRow($sheet, 1, $headers);
            $lastRow = 1;
            foreach ($rows as $row) {
                if (++$lastRow > 100001) {
                    throw new RuntimeException('تعداد ردیف‌ها از سقف خروجی اکسل بیشتر است؛ فیلترها را محدودتر کنید.');
                }
                $values = [];
                foreach (array_keys($headers) as $index) {
                    $values[] = is_array($row) ? ($row[$index] ?? '') : '';
                }
                self::writeXlsxRow($sheet, $lastRow, $values);
            }
            fwrite($sheet, '</sheetData><autoFilter ref="A1:' . $columnEnd . $lastRow . '"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>');
            fclose($sheet);
            $sheet = null;

            $zip = new ZipArchive();
            if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('فایل اکسل ساخته نشد.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::xmlText($safeSheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            if (!$zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml') || !$zip->close()) {
                throw new RuntimeException('ساختار فایل اکسل کامل نشد.');
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="export.xlsx"; filename*=UTF-8\'\'' . rawurlencode($filename));
                header('Content-Length: ' . filesize($xlsxPath));
                header('Cache-Control: private, no-store, max-age=0');
                header('X-Content-Type-Options: nosniff');
            }
            readfile($xlsxPath);
        } finally {
            if (is_resource($sheet)) {
                fclose($sheet);
            }
            foreach ([$sheetPath, $xlsxPath] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
            if (is_dir($workDir)) {
                @rmdir($workDir);
            }
        }
        exit;
    }

    private static function writeXlsxRow($handle, int $rowNumber, array $values): void
    {
        fwrite($handle, '<row r="' . $rowNumber . '">');
        foreach (array_values($values) as $index => $value) {
            $reference = self::excelColumnName($index + 1) . $rowNumber;
            if (is_int($value) || is_float($value)) {
                $numeric = is_finite((float) $value) ? (string) $value : '0';
                fwrite($handle, '<c r="' . $reference . '"><v>' . $numeric . '</v></c>');
            } else {
                $text = self::xmlText((string) ($value ?? ''));
                fwrite($handle, '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>');
            }
        }
        fwrite($handle, '</row>');
    }

    private static function excelColumnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder) . $name;
            $number = intdiv($number - 1, 26);
        }
        return $name ?: 'A';
    }

    private static function xmlText(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

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
            $delimiter = self::detectDelimiter($line);
            $rows[] = $delimiter ? array_map('trim', str_getcsv($line, $delimiter)) : [$line];
        }
        return $rows;
    }

    protected static function readCsv($path)
    {
        $rows = [];
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('فایل بارگذاری شده قابل خواندن نیست.');
        }
        $sample = strtok($content, "\r\n") ?: '';
        $delimiter = self::detectDelimiter($sample) ?: ',';
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('فایل بارگذاری شده قابل خواندن نیست.');
        }
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
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

    protected static function detectDelimiter($line)
    {
        $line = (string) $line;
        $candidates = ["\t", ',', ';', '|', '،'];
        $best = null;
        $bestCount = 0;
        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }
        return $bestCount > 0 ? $best : null;
    }
}
