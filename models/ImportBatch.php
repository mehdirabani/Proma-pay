<?php

class ImportBatch extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        SchemaGuard::requireColumns('import_batches', ['user_id', 'filename', 'status', 'raw_path', 'parsed_json', 'error_summary', 'created_at']);
        SchemaGuard::requireColumns('import_rows', ['batch_id', 'row_index', 'raw_json', 'parsed_json', 'status', 'errors', 'created_at']);
        self::$schemaReady = true;
    }

    public static function create($userId, $filename, array $rows)
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO import_batches (user_id, filename, status, raw_path, parsed_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $userId, $filename, 'uploaded', '', json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]
        );
        $batchId = (int) self::lastInsertId();
        foreach ($rows as $index => $row) {
            self::execute(
                'INSERT INTO import_rows (batch_id, row_index, raw_json, status, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$batchId, $index + 1, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'raw']
            );
        }
        return $batchId;
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM import_batches WHERE id = ?', [(int) $id]);
    }

    public static function rows($batchId)
    {
        return self::fetchAll('SELECT * FROM import_rows WHERE batch_id = ? ORDER BY row_index', [(int) $batchId]);
    }

    public static function saveParsed($batchId, $content)
    {
        self::ensureSchema();
        self::execute('UPDATE import_batches SET parsed_json = ?, status = ? WHERE id = ?', [$content, 'previewed', (int) $batchId]);
    }

    public static function saveValidation($batchId, array $errors)
    {
        self::ensureSchema();
        self::execute(
            'UPDATE import_batches SET error_summary = ? WHERE id = ?',
            [json_encode(array_values($errors), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), (int) $batchId]
        );
    }

    public static function confirm($batchId)
    {
        self::ensureSchema();
        self::execute('UPDATE import_batches SET status = ? WHERE id = ?', ['confirmed', (int) $batchId]);
    }

    public static function delete($batchId)
    {
        self::ensureSchema();
        $batchId = (int) $batchId;
        self::execute('DELETE FROM import_rows WHERE batch_id = ?', [$batchId]);
        self::execute('DELETE FROM import_batches WHERE id = ?', [$batchId]);
    }

}
