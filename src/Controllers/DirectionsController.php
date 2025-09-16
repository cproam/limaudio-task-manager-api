<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\DirectionRepository;
use App\Routing\Route;
use App\Routing\RequireRole;
use App\Validators\DirectionValidator;

class DirectionsController
{
    private DirectionRepository $repo;
    private DirectionValidator $validator;

    public function __construct()
    {
        $this->repo = new DirectionRepository();
        $this->validator = new DirectionValidator();
    }

    #[Route('GET', '/directions')]
    public function list(Request $req)
    {
        return Response::json(['items' => $this->repo->listAll()]);
    }

    #[Route('POST', '/directions')]
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
        $dir = $this->repo->create($name);
        return Response::json($dir, 201);
    }

    #[Route('PUT', '/directions/{id}'), Route('PATCH', '/directions/{id}')]
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
        $dir = $this->repo->update($id, $name);
        if (!$dir) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($dir);
    }

    #[Route('DELETE', '/directions/{id}')]
    #[RequireRole('admin')]
    public function delete(Request $req, array $params)
    {
        $id = (int)($params['id'] ?? 0);
        $ok = $this->repo->delete($id);
        if (!$ok) return Response::json(['error' => 'Not Found'], 404);
        return Response::json(['deleted' => $id]);
    }
}
