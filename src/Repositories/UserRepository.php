<?php

namespace App\Repositories;

use App\Database\DB;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::conn();
    }

    public function create(string $name, string $email, string $password, array $roles = [], ?string $telegramId = null): array
    {
        $now = gmdate('c');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users(name,email,password_hash,telegram_id,created_at,updated_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$name, $email, $hash, $telegramId, $now, $now]);
        $id = (int)$this->pdo->lastInsertId();
        if ($roles) $this->assignRoles($id, $roles);
        return $this->findById($id);
    }

    public function findById(int $id): ?array
    {
    $stmt = $this->pdo->prepare('SELECT id,name,email,telegram_id,created_at,updated_at FROM users WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['roles'] = $this->getRoles($id);
        return $row;
    }

    public function findByEmail(string $email): ?array
    {
    $stmt = $this->pdo->prepare('SELECT id,name,email,telegram_id,created_at,updated_at FROM users WHERE email=?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['roles'] = $this->getRoles((int)$row['id']);
        return $row;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT id,name,email,telegram_id,created_at,updated_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();
        foreach ($users as &$u) {
            $u['roles'] = $this->getRoles((int)$u['id']);
        }
        return $users;
    }

    public function setTelegramId(int $userId, string $telegramId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET telegram_id=?, updated_at=? WHERE id=?');
        $stmt->execute([$telegramId, gmdate('c'), $userId]);
    }

    public function assignRoles(int $userId, array $roles): void
    {
        if (!$roles) return;
        $roleIds = $this->resolveRoleIds($roles);
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO user_roles(user_id, role_id) VALUES(?, ?)');
        foreach ($roleIds as $rid) {
            $stmt->execute([$userId, $rid]);
        }
    }

    private function resolveRoleIds(array $roles): array
    {
        $in = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name IN ($in)");
        $stmt->execute(array_values($roles));
        return array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
    }

    private function getRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');
        $stmt->execute([$userId]);
        return array_map(fn($r) => $r['name'], $stmt->fetchAll());
    }
}
