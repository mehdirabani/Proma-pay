<?php

class Model
{
    protected static $pdo;

    public static function db()
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../config/database.php';
        $environmentMap = [
            'PROMA_DB_HOST' => 'host',
            'PROMA_DB_NAME' => 'database',
            'PROMA_DB_USER' => 'username',
            'PROMA_DB_CHARSET' => 'charset',
        ];
        foreach ($environmentMap as $environmentKey => $configKey) {
            $environmentValue = getenv($environmentKey);
            if ($environmentValue !== false && trim((string) $environmentValue) !== '') {
                $config[$configKey] = trim((string) $environmentValue);
            }
        }
        $environmentPassword = getenv('PROMA_DB_PASSWORD');
        if ($environmentPassword !== false) {
            $config['password'] = (string) $environmentPassword;
        }
        $charset = $config['charset'] ?: 'utf8mb4';
        $environmentDsn = trim((string) (getenv('PROMA_DB_DSN') ?: ''));
        $dsn = $environmentDsn !== ''
            ? $environmentDsn
            : 'mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';charset=' . $charset;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        $startedAt = microtime(true);
        try {
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            self::$pdo->exec("SET time_zone = '+03:30'");
        } finally {
            if (class_exists('RequestTelemetry', false)) {
                RequestTelemetry::recordSpan('database.connect', $startedAt);
            }
        }
        return self::$pdo;
    }

    public static function query($sql, array $params = [])
    {
        $startedAt = microtime(true);
        $error = null;
        try {
            $stmt = self::db()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (Throwable $e) {
            $error = $e;
            throw $e;
        } finally {
            if (class_exists('RequestTelemetry', false)) {
                RequestTelemetry::recordQuery($sql, (microtime(true) - $startedAt) * 1000, $error);
            }
        }
    }

    public static function fetch($sql, array $params = [])
    {
        $stmt = self::query($sql, $params);
        try {
            $row = $stmt->fetch();
            return $row ?: null;
        } finally {
            $stmt->closeCursor();
        }
    }

    public static function fetchAll($sql, array $params = [])
    {
        $stmt = self::query($sql, $params);
        try {
            return $stmt->fetchAll();
        } finally {
            $stmt->closeCursor();
        }
    }

    public static function execute($sql, array $params = [])
    {
        $stmt = self::query($sql, $params);
        try {
            return $stmt->rowCount();
        } finally {
            $stmt->closeCursor();
        }
    }

    public static function lastInsertId()
    {
        return self::db()->lastInsertId();
    }

    public static function begin()
    {
        self::db()->beginTransaction();
    }

    public static function commit()
    {
        if (self::db()->inTransaction()) {
            self::db()->commit();
        }
    }

    public static function rollBack()
    {
        if (self::db()->inTransaction()) {
            self::db()->rollBack();
        }
    }
}
