<?php

class ImportBatch extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        try {
            self::execute('ALTER TABLE import_batches ADD COLUMN error_summary TEXT NULL');
        } catch (Throwable $e) {
        }
        try {
            self::ensureRowIndexColumn();
        } catch (Throwable $e) {
        }
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

    protected static function ensureRowIndexColumn()
    {
        if (!self::columnExists('import_rows', 'row_index')) {
            if (self::columnExists('import_rows', 'row_number')) {
                self::execute('ALTER TABLE import_rows CHANGE COLUMN row_number row_index INT NOT NULL');
            } else {
                self::execute('ALTER TABLE import_rows ADD COLUMN row_index INT NOT NULL AFTER batch_id');
            }
        }
    }

    protected static function columnExists($table, $column)
    {
        $row = self::fetch(
            'SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }
}
