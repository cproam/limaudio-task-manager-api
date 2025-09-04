<?php
// Bootstrap autoloading (Composer or fallback PSR-4)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
	require_once $composerAutoload;
} else {
	spl_autoload_register(function ($class) {
		$prefix = 'App\\';
		$baseDir = __DIR__ . '/../src/';
		if (str_starts_with($class, $prefix)) {
			$relative = substr($class, strlen($prefix));
			$file = $baseDir . str_replace('\\', '/', $relative) . '.php';
			if (file_exists($file)) require $file;
		}
	});
}

use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use App\Controllers\TaskController;
use App\Controllers\UserController;
use App\Controllers\AuthController;
use App\Controllers\UploadController;
use App\Controllers\TaskFeatureController;
use App\Database\DB;
use App\Support\Env;
use App\Security\Auth;

// Basic CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

// Handle preflight quickly
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
	http_response_code(204);
	exit;
}

$projectRoot = dirname(__DIR__);
Env::load($projectRoot . '/.env');

$request = new Request();
$router = new Router();
$tasks = new TaskController();
$users = new UserController();
$auth = new AuthController();
$upload = new UploadController();
$taskFeature = new TaskFeatureController();

// Ensure DB is migrated
DB::migrate();

// Optional hook before each route (e.g., auth)
$router->beforeEach(function (Request $r) {
	// Require Bearer JWT for all routes except /auth/login and OPTIONS preflight
	$method = strtoupper($r->method);
	$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
	if ($method === 'OPTIONS') {
		return; // allow CORS preflight
	}
	if ($path !== '/auth/login') {
		if (!Auth::requireBearer($r)) {
			exit; // response already sent
		}
	}
});

// Routes
$router->get('/', function () {
	return ['status' => 'ok', 'service' => 'limaudio-task-manager-api'];
});

$router->get('/tasks', fn(Request $r) => $tasks->list($r));
$router->get('/tasks/{id}', fn(Request $r, array $p) => $tasks->get($r, $p));
$router->post('/tasks', fn(Request $r) => $tasks->create($r));
$router->put('/tasks/{id}', fn(Request $r, array $p) => $tasks->update($r, $p));
$router->patch('/tasks/{id}', fn(Request $r, array $p) => $tasks->update($r, $p));
$router->delete('/tasks/{id}', fn(Request $r, array $p) => $tasks->delete($r, $p));

// New Task feature routes
$router->post('/upload', fn(Request $r) => $upload->upload($r));
$router->post('/task', fn(Request $r) => $taskFeature->create($r));
$router->get('/task', fn(Request $r) => $taskFeature->list($r));
$router->get('/task/{id}', fn(Request $r, array $p) => $taskFeature->get($r, $p));
$router->post('/task/{id}/comments', fn(Request $r, array $p) => $taskFeature->addComment($r, $p));
$router->post('/task/{id}/files', fn(Request $r, array $p) => $taskFeature->attachFile($r, $p));

// User routes
$router->post('/users', fn(Request $r) => $users->create($r));
$router->get('/users', fn(Request $r) => $users->list($r));
$router->get('/users/{id}', fn(Request $r, array $p) => $users->get($r, $p));

// Auth
$router->post('/auth/login', fn(Request $r) => $auth->login($r));

$router->dispatch($request);

