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
            telegram_id TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        // Ensure telegram_id exists on existing databases
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN telegram_id TEXT');
        } catch (PDOException $e) {
            // Ignore if column already exists
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT
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

        // Permissions
        $pdo->exec('CREATE TABLE IF NOT EXISTS permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            user_id INTEGER,
            role_id INTEGER,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        )');

        // Directions
        $pdo->exec('CREATE TABLE IF NOT EXISTS directions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )');
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO directions(name) VALUES (?), (?), (?)');
        $stmt->execute(['Строительство', 'Дистрибуция', 'Партнерская программа']);

        // Tasks
        $pdo->exec('CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            direction_id INTEGER,
            due_at TEXT,
            assigned_user_id INTEGER,
            status TEXT NOT NULL DEFAULT "Новая",
            created_by INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            notified_30 INTEGER NOT NULL DEFAULT 0,
            notified_10 INTEGER NOT NULL DEFAULT 0,
            notified_0 INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(direction_id) REFERENCES directions(id) ON DELETE SET NULL,
            FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        )');

        // Task links
        $pdo->exec('CREATE TABLE IF NOT EXISTS task_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            url TEXT NOT NULL,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )');

        // Task files
        $pdo->exec('CREATE TABLE IF NOT EXISTS task_files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            file_name TEXT NOT NULL,
            file_url TEXT NOT NULL,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )');

        // Comments
        $pdo->exec('CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            user_id INTEGER,
            text TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        )');
    }
}
