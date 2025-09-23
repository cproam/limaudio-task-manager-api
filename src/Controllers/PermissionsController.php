<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Database\DB;
use App\Routing\Route;
use App\Routing\RequireRole;
use App\Repositories\PermissionRepository;
use App\Validators\PermissionValidator;

class PermissionsController
{
    private PermissionRepository $repo;
    private PermissionValidator $validator;

    public function __construct()
    {
        DB::migrate();
        $this->repo = new PermissionRepository();
        $this->validator = new PermissionValidator();
    }

    #[Route('GET', '/permissions')]
    public function list(Request $req)
    {
        return Response::json(['items' => $this->repo->listAll()]);
    }

    #[Route('POST', '/permissions')]
    #[RequireRole('admin')]
    public function create(Request $req)
    {
        $data = $req->body;

        $errors = $this->validator->validateCreate($data);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $name = trim($data['name']);
        $userId = (int)trim($data['user_id']) ?? 0;
        $roleId = (int)trim($data['role_id']) ?? 0;
        $dir = $this->repo->create($name, $userId, $roleId);
        return Response::json($dir, 201);
    }

    #[Route('PATCH', '/permissions/{id}')]
    #[Route('PUT', '/permissions/{id}')]
    #[RequireRole('admin')]
    public function update(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $data = $req->body;

        $errors = $this->validator->validateUpdate($data, $id);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $name = trim($data['name']);
        $userId = (int)trim($data['user_id']);
        $roleId = (int)trim($data['role_id']);
        $dir = $this->repo->update($id, $name, $userId, $roleId);
        if (!$dir) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($dir);
    }

    #[Route('DELETE', '/roles/{id}')]
    #[RequireRole('admin')]
    public function delete(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $ok = $this->repo->delete($id);
        if (!$ok) return Response::json(['error' => 'Not Found'], 404);
        return Response::json(['deleted' => $id]);
    }
}
