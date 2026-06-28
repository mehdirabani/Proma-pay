<?php

class Event extends Model
{
    public static function ensureSchema()
    {
        self::execute(
            "CREATE TABLE IF NOT EXISTS events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                title VARCHAR(190) NOT NULL,
                event_date DATE NOT NULL,
                description TEXT NULL,
                color VARCHAR(20) NOT NULL DEFAULT 'primary',
                created_at DATETIME NOT NULL,
                KEY idx_events_date (event_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function allForMonth($month)
    {
        self::ensureSchema();
        $start = date('Y-m-01', strtotime($month . '-01'));
        $end = date('Y-m-01', strtotime($start . ' +1 month'));
        return self::fetchAll(
            'SELECT e.*, u.full_name AS user_name
             FROM events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.event_date >= ? AND e.event_date < ?
             ORDER BY e.event_date ASC, e.id ASC',
            [$start, $end]
        );
    }

    public static function allBetween($startDate, $endDate)
    {
        self::ensureSchema();
        return self::fetchAll(
            'SELECT e.*, u.full_name AS user_name
             FROM events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.event_date >= ? AND e.event_date < ?
             ORDER BY e.event_date ASC, e.id ASC',
            [$startDate, $endDate]
        );
    }

    public static function createEvent(array $data)
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO events (user_id, title, event_date, description, color, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [
                $data['user_id'] ?: null,
                trim($data['title']),
                $data['event_date'],
                trim($data['description'] ?? ''),
                in_array($data['color'] ?? '', ['primary', 'success', 'warning', 'danger', 'info'], true) ? $data['color'] : 'primary',
            ]
        );
    }

    public static function deleteEvent($id)
    {
        self::ensureSchema();
        self::execute('DELETE FROM events WHERE id = ?', [(int) $id]);
    }
}
