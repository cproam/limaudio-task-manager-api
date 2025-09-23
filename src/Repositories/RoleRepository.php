<?php

namespace App\Repositories;

use App\Database\DB;
use PDO;

class RoleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::conn();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM roles ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM roles WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM roles WHERE name=? AND id<>? LIMIT 1');
            $stmt->execute([$name, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM roles WHERE name=? LIMIT 1');
            $stmt->execute([$name]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function create(string $name): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO roles(name) VALUES(?)');
        $stmt->execute([$name]);
        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, string $name): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE roles SET name=? WHERE id=?');
        $stmt->execute([$name, $id]);
        if ($stmt->rowCount() === 0) {
            // Could be same value or not found; check existence explicitly
            $existing = $this->findById($id);
            if (!$existing) return null;
        }
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM roles WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
