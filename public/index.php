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
use App\Controllers\AuthController;
use App\Controllers\UploadController;
use App\Controllers\TaskFeatureController;
use App\Controllers\WebhookController;
use App\Controllers\DirectionsController;
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
	if ($path !== '/auth/login' && $path !== '/webhook/telegram') {
		if (!Auth::requireBearer($r)) {
			exit; // response already sent
		}
	}
});

// Routes
$router->get('/', function () {
	return ['status' => 'ok', 'service' => 'limaudio-task-manager-api'];
});

// Scan controllers for routes and roles
$router->scanControllers([
	AuthController::class,
	UploadController::class,
	TaskFeatureController::class,
	WebhookController::class,
	DirectionsController::class,
]);

$router->dispatch($request);

