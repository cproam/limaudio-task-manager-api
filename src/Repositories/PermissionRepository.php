<?php

namespace App\Repositories;

use App\Database\DB;
use PDO;

class PermissionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::conn();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, role_id FROM permissions ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, role_id FROM permissions WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM permissions WHERE name=? AND id<>? LIMIT 1');
            $stmt->execute([$name, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM permissions WHERE name=? LIMIT 1');
            $stmt->execute([$name]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function create(string $name, int $userId, int $roleId): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO permissions(name, user_id, role_id) VALUES(?, ?, ?)');
        $stmt->execute([$name, $userId, $roleId]);
        $id = (int)$this->pdo->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, string $name, int $userId, int $roleId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE permissions SET name=?, user_id=?, role_id=? WHERE id=?');
        $stmt->execute([$name, $userId, $roleId, $id]);
        if ($stmt->rowCount() === 0) {
            $existing = $this->findById($id);
            if (!$existing) return null;
        }
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM permissions WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
