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
        $row['permissions'] = $this->getPermissions($id);
        return $row;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,name,email,telegram_id,created_at,updated_at FROM users WHERE email=?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['roles'] = $this->getRoles((int)$row['id']);
        $row['permissions'] = $this->getPermissions((int)$row['id']);
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
            $u['permissions'] = $this->getPermissions((int)$u['id']);
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

    public function replaceRoles(int $userId, array $roles): void
    {
        $this->pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$userId]);
        $this->assignRoles($userId, $roles);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email=? AND id<>? LIMIT 1');
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function update(int $id, array $fields, ?array $roles = null, ?array $permissions = null): ?array
    {
        $user = $this->findById($id);
        if (!$user) return null;

        $sets = [];
        $vals = [];
        if (isset($fields['name'])) {
            $sets[] = 'name=?';
            $vals[] = (string)$fields['name'];
        }
        if (isset($fields['email'])) {
            $sets[] = 'email=?';
            $vals[] = strtolower((string)$fields['email']);
        }
        if (isset($fields['telegram_id'])) {
            $sets[] = 'telegram_id=?';
            $vals[] = $fields['telegram_id'] !== null ? (string)$fields['telegram_id'] : null;
        }
        if (isset($fields['password']) && $fields['password'] !== '') {
            $sets[] = 'password_hash=?';
            $vals[] = password_hash((string)$fields['password'], PASSWORD_DEFAULT);
        }
        if ($sets) {
            $sets[] = 'updated_at=?';
            $vals[] = gmdate('c');
            $vals[] = $id;
            $sql = 'UPDATE users SET ' . implode(',', $sets) . ' WHERE id=?';
            $this->pdo->prepare($sql)->execute($vals);
        }

        if (is_array($roles)) {
            $this->replaceRoles($id, $roles);
        }

        if (is_array($permissions)) {
            $this->updatePermissions($id, $permissions);
        }

        return $this->findById($id);
    }

    private function resolveRoleIds(array $roles): array
    {
        $in = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM roles WHERE name IN ($in)");
        $stmt->execute(array_values($roles));
        return array_map(fn($r) => (int)$r['id'], $stmt->fetchAll());
    }

    private function resolvePermissionIds(array $permissions): array
    {
        $in = implode(',', array_fill(0, count($permissions), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM permissions WHERE name IN ($in)");
        $stmt->execute(array_values($permissions));
        return array_map(fn($p) => (int)$p['id'], $stmt->fetchAll());
    }

    private function getRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT r.name, r.description FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');
        $stmt->execute([$userId]);
        return array_map(fn($r) => [$r['name'], $r['description']], $stmt->fetchAll());
    }

    private function getPermissions(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT p.name FROM permissions p LEFT JOIN user_roles ur ON ur.role_id = p.role_id WHERE p.user_id = ? OR ur.user_id = ?');
        $stmt->execute([$userId, $userId]);
        return array_map(fn($r) => $r['name'], $stmt->fetchAll());
    }

    private function updatePermissions(int $userId, array $permissions)
    {
        if (!$permissions) return;
        $this->pdo->prepare('DELETE FROM permissions WHERE user_id=?')->execute([$userId]);
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO permissions(name, user_id) VALUES(?, ?, ?)');
        foreach ($permissions as $pname) {
            $stmt->execute([$pname, $userId]);
        }
    }
}
