<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/config/bootstrap.php';

// The old /index.php URL is a route, not the base path of the application.
// Other .php URLs also reach this single front controller through rewriting.
$originalScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = str_replace('\\', '/', dirname($originalScriptName));
if (PHP_SAPI === 'cli-server') {
    $basePath = '';
}
$basePath = $basePath === '/' || $basePath === '.' ? '' : $basePath;
$_SERVER['SCRIPT_NAME'] = $basePath.'/__symfony_front_controller.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', filter_var($_SERVER['APP_DEBUG'] ?? '1', FILTER_VALIDATE_BOOL));
$request = Request::createFromGlobals();
$request->attributes->set('_app_base_path', $basePath);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
