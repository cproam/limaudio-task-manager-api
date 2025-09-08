<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\DirectionRepository;

class DirectionsController
{
    private DirectionRepository $repo;

    public function __construct()
    {
        $this->repo = new DirectionRepository();
    }

    public function list(Request $req)
    {
        return Response::json(['items' => $this->repo->listAll()]);
    }

    public function create(Request $req)
    {
        if (!$this->isAdmin()) return Response::json(['error' => 'admin role required'], 403);
        $name = trim((string)($req->body['name'] ?? ''));
        if ($name === '') return Response::json(['error' => 'name is required'], 422);
        if ($this->repo->nameExists($name)) return Response::json(['error' => 'direction already exists'], 409);
        $dir = $this->repo->create($name);
        return Response::json($dir, 201);
    }

    public function update(Request $req, array $params)
    {
        if (!$this->isAdmin()) return Response::json(['error' => 'admin role required'], 403);
        $id = (int)($params['id'] ?? 0);
        $name = trim((string)($req->body['name'] ?? ''));
        if ($name === '') return Response::json(['error' => 'name is required'], 422);
        if ($this->repo->nameExists($name, $id)) return Response::json(['error' => 'direction already exists'], 409);
        $dir = $this->repo->update($id, $name);
        if (!$dir) return Response::json(['error' => 'Not Found'], 404);
        return Response::json($dir);
    }

    public function delete(Request $req, array $params)
    {
        if (!$this->isAdmin()) return Response::json(['error' => 'admin role required'], 403);
        $id = (int)($params['id'] ?? 0);
        $ok = $this->repo->delete($id);
        if (!$ok) return Response::json(['error' => 'Not Found'], 404);
        return Response::json(['deleted' => $id]);
    }

    private function isAdmin(): bool
    {
        $claims = $GLOBALS['auth_user'] ?? null;
        $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
        return in_array('admin', $roles, true);
    }
}
