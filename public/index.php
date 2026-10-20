<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/config/bootstrap.php';

// The old /index.php URL is a route, not the base path of the application.
// Other .php URLs also reach this single front controller through rewriting.
$originalScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = str_replace('\\', '/', dirname($originalScriptName));
// Symfony CLI can report the requested legacy .php URL as SCRIPT_NAME even
// though it passes the request through this front controller. When public/ is
// the document root, those URL directories are routes, not an app base path.
$documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
if (PHP_SAPI === 'cli-server' || ($documentRoot !== false && $documentRoot === realpath(__DIR__))) {
    $basePath = '';
}
$basePath = $basePath === '/' || $basePath === '.' ? '' : $basePath;
$_SERVER['SCRIPT_NAME'] = $basePath.'/__symfony_front_controller.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
// HttpFoundation compares SCRIPT_NAME with SCRIPT_FILENAME when finding the
// request path. Keep all three aligned so Windows CGI does not treat a legacy
// URL ending in index.php as the front controller's own URL.
$_SERVER['SCRIPT_FILENAME'] = __DIR__.DIRECTORY_SEPARATOR.'__symfony_front_controller.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', filter_var($_SERVER['APP_DEBUG'] ?? '1', FILTER_VALIDATE_BOOL));
$request = Request::createFromGlobals();
$request->attributes->set('_app_base_path', $basePath);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
