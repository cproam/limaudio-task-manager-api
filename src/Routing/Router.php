<?php

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;

class Router
{
    /** @var array<string,array<int,array{pattern:string,handler:callable}>> */
    private array $routes = [];

    /** @var callable|null */
    private $beforeEach = null;

    public function beforeEach(callable $handler): void
    {
        $this->beforeEach = $handler;
    }

    public function scanControllers(array $controllers): void
    {
        foreach ($controllers as $controllerClass) {
            $reflection = new \ReflectionClass($controllerClass);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class);
                if (empty($attributes)) continue;

                foreach ($attributes as $attr) {
                    $routeAttr = $attr->newInstance();
                    $methodName = $method->getName();
                    $handler = function (Request $req, array $params = []) use ($controllerClass, $methodName) {
                        $controller = new $controllerClass();
                        return $controller->$methodName($req, $params);
                    };

                    // Check for RequireRole
                    $roleAttributes = $method->getAttributes(RequireRole::class);
                    if (!empty($roleAttributes)) {
                        $roleAttr = $roleAttributes[0]->newInstance();
                        $requiredRole = $roleAttr->role;
                        $originalHandler = $handler;
                        $handler = function (Request $req, array $params = []) use ($originalHandler, $requiredRole) {
                            $claims = $GLOBALS['auth_user'] ?? null;
                            $roles = is_array($claims['roles'] ?? null) ? $claims['roles'] : [];
                            if (!in_array($requiredRole, $roles, true)) {
                                Response::json(['error' => "{$requiredRole} role required"], 403);
                                exit;
                            }
                            return $originalHandler($req, $params);
                        };
                    }

                    $this->add($routeAttr->method, $routeAttr->path, $handler);
                }
            }
        }
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $pattern = $this->compilePath($path);
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }
    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }
    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }
    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(Request $request): void
    {
        $method = strtoupper($request->method);
        $path = '/' . trim($request->path, '/');
        $path = $path === '/' ? '/' : $path; // normalize

        // Run beforeEach (e.g., CORS, auth)
        if ($this->beforeEach) {
            ($this->beforeEach)($request);
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!is_int($k)) $params[$k] = $v;
                }
                $handler = $route['handler'];
                $result = $handler($request, $params);
                if ($result !== null) {
                    Response::json($result);
                }
                return;
            }
        }

        Response::json(['error' => 'Not Found'], 404);
    }

    private function compilePath(string $path): string
    {
        // Normalize leading slash
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        // Remove trailing slash except for root
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        // Convert /tasks/{id} to regex with named capture
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '$#';
    }
}
