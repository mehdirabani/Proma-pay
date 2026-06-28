<?php

class ImportBatch extends Model
{
    public static function create($userId, $filename, array $rows)
    {
        self::execute(
            'INSERT INTO import_batches (user_id, filename, status, raw_path, parsed_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $userId, $filename, 'uploaded', '', json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE)]
        );
        $batchId = (int) self::lastInsertId();
        foreach ($rows as $index => $row) {
            self::execute(
                'INSERT INTO import_rows (batch_id, row_number, raw_json, status, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$batchId, $index + 1, json_encode($row, JSON_UNESCAPED_UNICODE), 'raw']
            );
        }
        return $batchId;
    }

    public static function find($id)
    {
        return self::fetch('SELECT * FROM import_batches WHERE id = ?', [(int) $id]);
    }

    public static function rows($batchId)
    {
        return self::fetchAll('SELECT * FROM import_rows WHERE batch_id = ? ORDER BY row_number', [(int) $batchId]);
    }

    public static function saveParsed($batchId, $content)
    {
        self::execute('UPDATE import_batches SET parsed_json = ?, status = ? WHERE id = ?', [$content, 'previewed', (int) $batchId]);
    }

    public static function confirm($batchId)
    {
        self::execute('UPDATE import_batches SET status = ? WHERE id = ?', ['confirmed', (int) $batchId]);
    }
}
