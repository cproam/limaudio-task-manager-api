<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\RoleRepository;
use App\Database\DB;
use App\Routing\Route;

class RolesController
{
    private RoleRepository $roles;

    public function __construct()
    {
        DB::migrate(); // ensure tables exist
        $this->roles = new RoleRepository();
    }

    #[Route('GET', '/roles')]
    public function list(Request $req)
    {
        $limit = isset($req->query['limit']) ? max(1, (int)$req->query['limit']) : 50;
        $offset = isset($req->query['offset']) ? max(0, (int)$req->query['offset']) : 0;
        $roles = $this->roles->list($limit, $offset);
        return Response::json(['items' => $roles, 'limit' => $limit, 'offset' => $offset]);
    }
}
