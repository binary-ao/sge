<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Instalação em subpasta do XAMPP (http://localhost/sg_escolar): quando o pedido
// chega através do .htaccess da raiz (sem "/public" no endereço), ajusta o
// SCRIPT_NAME para que o Laravel calcule correctamente o URL base.
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])
    && str_ends_with($_SERVER['SCRIPT_NAME'], '/public/index.php')) {
    $base = substr($_SERVER['SCRIPT_NAME'], 0, -strlen('/public/index.php'));
    if (! str_starts_with($_SERVER['REQUEST_URI'], $base.'/public')) {
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $base.'/index.php';
    }
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
