<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Database\DB;
use App\Security\Jwt;
use App\Routing\Route;
use App\DTO\LoginDTO;
use App\Validators\AuthValidator;
use PDO;

class AuthController
{
    private const ACCESS_TOKEN_TTL = 43200; // 12 hours
    private const REFRESH_TOKEN_TTL = 2592000; // 30 days

    private AuthValidator $validator;

    public function __construct()
    {
        $this->validator = new AuthValidator();
    }

    #[Route('POST', '/auth/login')]
    public function login(Request $req)
    {
        $data = $req->body;

        // Валидация через DTO
        $dto = new LoginDTO();
        $dto->email = strtolower(trim($data['email'] ?? ''));
        $dto->password = $data['password'] ?? '';
        $errors = $this->validator->validate($dto);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT id,name,email,password_hash,telegram_id FROM users WHERE email=?');
        $stmt->execute([$dto->email]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($dto->password, $u['password_hash'])) {
            return Response::json(['error' => 'invalid credentials'], 401);
        }

        // Load roles
        $roles = $this->loadRoles($pdo, (int)$u['id']);
        [$token, $refreshToken] = $this->issueTokens($pdo, $u, $roles);

        return Response::json([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user' => $this->formatUser($u, $roles),
        ]);
    }

    #[Route('POST', '/auth/refresh')]
    public function refresh(Request $req)
    {
        $refreshToken = trim((string)($req->body['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            return Response::json(['error' => 'refresh_token_required'], 422);
        }

        $pdo = DB::conn();
        $hash = hash('sha256', $refreshToken);

        $stmt = $pdo->prepare('SELECT id, user_id, expires_at FROM refresh_tokens WHERE token_hash = ? LIMIT 1');
        $stmt->execute([$hash]);
        $stored = $stmt->fetch();

        if (!$stored) {
            return Response::json(['error' => 'invalid_refresh_token'], 401);
        }

        $now = time();
        if ((int)$stored['expires_at'] < $now) {
            $pdo->prepare('DELETE FROM refresh_tokens WHERE id = ?')->execute([(int)$stored['id']]);
            return Response::json(['error' => 'invalid_refresh_token'], 401);
        }

        $pdo->prepare('DELETE FROM refresh_tokens WHERE id = ?')->execute([(int)$stored['id']]);

        $userStmt = $pdo->prepare('SELECT id, name, email, telegram_id FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([(int)$stored['user_id']]);
        $user = $userStmt->fetch();

        if (!$user) {
            return Response::json(['error' => 'invalid_refresh_token'], 401);
        }

        $roles = $this->loadRoles($pdo, (int)$user['id']);
        [$token, $newRefreshToken] = $this->issueTokens($pdo, $user, $roles);

        return Response::json([
            'token' => $token,
            'refresh_token' => $newRefreshToken,
            'user' => $this->formatUser($user, $roles),
        ]);
    }

    private function loadRoles(PDO $pdo, int $userId): array
    {
        $rs = $pdo->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?');
        $rs->execute([$userId]);
        return array_map(static fn($r) => $r['name'], $rs->fetchAll());
    }

    private function issueTokens(PDO $pdo, array $user, array $roles): array
    {
        $token = Jwt::sign([
            'sub' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'roles' => $roles,
        ], self::ACCESS_TOKEN_TTL);

        $refreshToken = bin2hex(random_bytes(32));
        $now = time();
        $pdo->prepare('DELETE FROM refresh_tokens WHERE expires_at < ?')->execute([$now]);
        $insert = $pdo->prepare('INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
        $insert->execute([
            (int)$user['id'],
            hash('sha256', $refreshToken),
            $now + self::REFRESH_TOKEN_TTL,
            $now,
        ]);

        return [$token, $refreshToken];
    }

    private function formatUser(array $user, array $roles): array
    {
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'telegram_id' => $user['telegram_id'] ?? null,
            'roles' => $roles,
        ];
    }
}
