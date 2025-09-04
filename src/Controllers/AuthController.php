<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Database\DB;
use App\Security\Jwt;

class AuthController
{
    public function login(Request $req)
    {
        $data = $req->body;
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        if ($email === '' || $password === '') {
            return Response::json(['error' => 'email and password are required'], 422);
        }
        $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT id,name,email,password_hash,telegram_id FROM users WHERE email=?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            return Response::json(['error' => 'invalid credentials'], 401);
        }
        // Load roles
        $rs = $pdo->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?');
        $rs->execute([(int)$u['id']]);
        $roles = array_map(fn($r) => $r['name'], $rs->fetchAll());
        $token = Jwt::sign(['sub' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['name'], 'roles' => $roles], 3600 * 12);
    return Response::json(['token' => $token, 'user' => ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'telegram_id' => $u['telegram_id'], 'roles' => $roles]]);
    }
}
