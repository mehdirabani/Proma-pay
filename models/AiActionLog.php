<?php

class AiActionLog extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        self::execute(
            "CREATE TABLE IF NOT EXISTS ai_action_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                instruction TEXT NOT NULL,
                response_json LONGTEXT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'previewed',
                applied_summary TEXT NULL,
                created_at DATETIME NOT NULL,
                confirmed_at DATETIME NULL,
                KEY idx_ai_action_user (user_id),
                KEY idx_ai_action_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaReady = true;
    }

    public static function createPreview($userId, $instruction, array $response)
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO ai_action_logs (user_id, instruction, response_json, status, created_at) VALUES (?, ?, ?, ?, NOW())',
            [(int) $userId, trim((string) $instruction), json_encode($response, JSON_UNESCAPED_UNICODE), 'previewed']
        );
        return (int) self::lastInsertId();
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM ai_action_logs WHERE id = ?', [(int) $id]);
    }

    public static function latest($limit = 10)
    {
        self::ensureSchema();
        return self::fetchAll('SELECT l.*, u.full_name FROM ai_action_logs l JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT ' . max(1, (int) $limit));
    }

    public static function markApplied($id, $status, $summary)
    {
        self::ensureSchema();
        self::execute(
            'UPDATE ai_action_logs SET status = ?, applied_summary = ?, confirmed_at = NOW() WHERE id = ?',
            [$status, trim((string) $summary), (int) $id]
        );
    }
}
