<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use App\Database\DB;
use App\Routing\Route;
use App\Routing\RequireRole;
use App\Validators\UserValidator;

class UserController
{
    private UserRepository $users;
    private UserValidator $validator;

    public function __construct()
    {
        DB::migrate(); // ensure tables exist
        $this->users = new UserRepository();
        $this->validator = new UserValidator();
    }

    #[Route('POST', '/users')]
    #[RequireRole('admin')]
    public function create(Request $req)
    {
        $data = $req->body;

        // Валидация через слой
        $errors = $this->validator->validateCreate($data);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $name = trim($data['name']);
        $email = strtolower(trim($data['email']));
        $password = $data['password'];
        $roles = $data['roles'] ?? [];
        $telegramId = isset($data['telegram_id']) ? (string)$data['telegram_id'] : null;
        if (!is_array($roles)) $roles = [];

        $user = $this->users->create($name, $email, $password, $roles, $telegramId);
        return Response::json($user, 201);
    }

    #[Route('GET', '/users')]
    public function list(Request $req)
    {
        $limit = isset($req->query['limit']) ? max(1, (int)$req->query['limit']) : 50;
        $offset = isset($req->query['offset']) ? max(0, (int)$req->query['offset']) : 0;
        $users = $this->users->list($limit, $offset);
        return Response::json(['items' => $users, 'limit' => $limit, 'offset' => $offset]);
    }

    #[Route('GET', '/users/{id}')]
    public function get(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $user = $this->users->findById($id);
        if (!$user) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($user);
    }

    #[Route('PATCH', '/users/{id}')]
    #[Route('PUT', '/users/{id}')]
    #[RequireRole('admin')]
    public function update(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $payload = $req->body;

        // Валидация через слой
        $errors = $this->validator->validateUpdate($payload, $id);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $fields = [];
        if (array_key_exists('name', $payload)) $fields['name'] = (string)$payload['name'];
        if (array_key_exists('email', $payload)) $fields['email'] = strtolower((string)$payload['email']);
        if (array_key_exists('telegram_id', $payload)) $fields['telegram_id'] = $payload['telegram_id'] === null ? null : (string)$payload['telegram_id'];
        if (array_key_exists('password', $payload)) $fields['password'] = (string)$payload['password'];

        $roles = null;
        if (array_key_exists('roles', $payload)) {
            $roles = is_array($payload['roles']) ? array_values(array_intersect($payload['roles'], ['admin', 'sales_manager'])) : [];
        }

        $permissions = null;
        if (array_key_exists('permissions', $payload)) {
            $permissions =  is_array($payload['permissions']) ? array_values($payload['permissions']) : [];
        }

        $updated = $this->users->update($id, $fields, $roles, $permissions);
        if (!$updated) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($updated);
    }
}
