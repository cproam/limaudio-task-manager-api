<?php

namespace App\Database;

use PDO;
use PDOException;

class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $root = dirname(__DIR__, 2); // project root
        $storageDir = $root . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storageDir)) @mkdir($storageDir, 0777, true);
        $dbPath = $storageDir . DIRECTORY_SEPARATOR . 'database.sqlite';
        $dsn = 'sqlite:' . $dbPath;
        try {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'DB connection failed', 'details' => $e->getMessage()]);
            exit;
        }
        self::$pdo = $pdo;
        return self::$pdo;
    }

    public static function migrate(): void
    {
        $pdo = self::conn();
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS user_roles (
            user_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        )');

        // Seed roles
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO roles(name) VALUES (?), (?)');
        $stmt->execute(['admin', 'sales_manager']);
    }
}
