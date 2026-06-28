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
        self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll($sql, array $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function execute($sql, array $params = [])
    {
        return self::query($sql, $params)->rowCount();
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
