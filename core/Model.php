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
        $charset = $config['charset'] ?: 'utf8mb4';
        $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';charset=' . $charset;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        self::$pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        self::$pdo->exec("SET time_zone = '+03:30'");
        return self::$pdo;
    }

    public static function query($sql, array $params = [])
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
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
