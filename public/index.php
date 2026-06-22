<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Subdirectory Request Normalization
|--------------------------------------------------------------------------
|
| On Windows/Laragon, REQUEST_URI may use different casing than SCRIPT_NAME
| (e.g. /Agricarterp/public vs /agricarterp/public). Symfony's path parsing
| is case-sensitive, so rewritten routes 404 while the directory index still
| works. Normalize the URI prefix to match the script base path.
|
*/
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
    $scriptBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

    if ($scriptBase !== '/' && $scriptBase !== '.') {
        $requestUri = $_SERVER['REQUEST_URI'];
        $query = '';

        if (($queryPosition = strpos($requestUri, '?')) !== false) {
            $query = substr($requestUri, $queryPosition);
            $requestUri = substr($requestUri, 0, $queryPosition);
        }

        if (strncasecmp($requestUri, $scriptBase, strlen($scriptBase)) === 0) {
            $requestUri = $scriptBase.substr($requestUri, strlen($scriptBase));
            $_SERVER['REQUEST_URI'] = $requestUri.$query;

            if (isset($_SERVER['REDIRECT_URL']) && strncasecmp($_SERVER['REDIRECT_URL'], $scriptBase, strlen($scriptBase)) === 0) {
                $_SERVER['REDIRECT_URL'] = $scriptBase.substr($_SERVER['REDIRECT_URL'], strlen($scriptBase));
            }
        }
    }
}

$app->handleRequest(Request::capture());
