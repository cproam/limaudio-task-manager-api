<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Database\DB;

class UserController
{
    private UserRepository $users;

    public function __construct()
    {
        DB::migrate(); // ensure tables exist
        $this->users = new UserRepository();
    }

    public function create(Request $req)
    {
        // Only admins can create users
        $claims = $GLOBALS['auth_user'] ?? null;
        $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
        if (!in_array('admin', $roles, true)) {
            return Response::json(['error' => 'admin role required'], 403);
        }

        $data = $req->body;
        $name = trim((string)($data['name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
    $roles = $data['roles'] ?? [];
    $telegramId = isset($data['telegram_id']) ? (string)$data['telegram_id'] : null;
        if (!is_array($roles)) $roles = [];

        if ($name === '' || $email === '' || $password === '') {
            return Response::json(['error' => 'name, email, password are required'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['error' => 'invalid email'], 422);
        }
        if ($roles) {
            // only allow known roles
            $roles = array_values(array_intersect($roles, ['admin', 'sales_manager']));
        }
        // check duplicate
        if ($this->users->findByEmail($email)) {
            return Response::json(['error' => 'email already exists'], 409);
        }
    $user = $this->users->create($name, $email, $password, $roles, $telegramId);
        return Response::json($user, 201);
    }

    public function list(Request $req)
    {
        $limit = isset($req->query['limit']) ? max(1, (int)$req->query['limit']) : 50;
        $offset = isset($req->query['offset']) ? max(0, (int)$req->query['offset']) : 0;
        $users = $this->users->list($limit, $offset);
        return Response::json(['items' => $users, 'limit' => $limit, 'offset' => $offset]);
    }

    public function get(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $user = $this->users->findById($id);
        if (!$user) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($user);
    }
}
