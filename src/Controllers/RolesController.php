<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\RoleRepository;
use App\Database\DB;
use App\Routing\RequireRole;
use App\Routing\Route;
use App\Validators\RoleValidator;

class RolesController
{
    private RoleRepository $repo;
    private RoleValidator $validator;

    public function __construct()
    {
        DB::migrate(); // ensure tables exist
        $this->repo = new RoleRepository();
        $this->validator = new RoleValidator();
    }

    #[Route('GET', '/roles')]
    public function list(Request $req)
    {
        return Response::json(['items' => $this->repo->listAll()]);
    }

    #[Route('POST', '/roles')]
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
        $description = trim($data['description']);
        $dir = $this->repo->create($name, $description);
        return Response::json($dir, 201);
    }

    #[Route('PATCH', '/roles/{id}')]
    #[Route('PUT', '/roles/{id}')]
    #[RequireRole('admin')]
    public function update(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $data = $req->body;

        // Валидация через слой
        $errors = $this->validator->validateUpdate($data, $id);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $name = trim($data['name']);
        $description = trim($data['description']);
        $dir = $this->repo->update($id, $name, $description);
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
