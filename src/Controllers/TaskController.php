<?php

namespace App\Controllers;

use App\Http\Request;

class TaskController
{
    public function list(Request $req): array
    {
        return [
            ['id' => 1, 'title' => 'Sample task', 'completed' => false],
        ];
    }

    public function get(Request $req, array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        return ['id' => $id, 'title' => 'Sample #' . $id, 'completed' => false];
    }

    public function create(Request $req): array
    {
        $data = $req->body;
        return ['id' => 2, 'title' => $data['title'] ?? 'Untitled', 'completed' => (bool)($data['completed'] ?? false)];
    }

    public function update(Request $req, array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        $data = $req->body;
        return ['id' => $id, 'title' => $data['title'] ?? 'Untitled', 'completed' => (bool)($data['completed'] ?? false)];
    }

    public function delete(Request $req, array $params): array
    {
        $id = (int)($params['id'] ?? 0);
        return ['deleted' => $id];
    }
}
