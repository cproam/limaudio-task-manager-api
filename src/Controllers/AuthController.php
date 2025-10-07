<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Database\DB;
use App\Security\Jwt;
use App\Routing\Route;
use App\DTO\LoginDTO;
use App\Validators\AuthValidator;

class AuthController
{
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
        $rs = $pdo->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');
        $rs->execute([(int)$u['id']]);
        $roles = array_map(fn($r) => $r['name'], $rs->fetchAll());
        $token = Jwt::sign(['sub' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['name'], 'roles' => $roles], 60);
        return Response::json(['token' => $token, 'user' => ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'telegram_id' => $u['telegram_id'], 'roles' => $roles]]);
    }
}
