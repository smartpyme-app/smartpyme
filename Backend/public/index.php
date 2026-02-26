<?php

// Configuración PHP para evitar errores de PCRE JIT
ini_set('pcre.jit', '0');
ini_set('pcre.backtrack_limit', '1000000');
ini_set('pcre.recursion_limit', '1000000');

// Manejar errores de PCRE JIT específicamente
set_error_handler(function ($severity, $message, $file, $line) {
    if (strpos($message, 'preg_replace(): Allocation of JIT memory failed') !== false ||
        strpos($message, 'PCRE JIT will be disabled') !== false) {
        // Silenciar estos errores específicos y continuar
        return true;
    }
    return false; // Dejar que otros errores se manejen normalmente
});

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any prerendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = tap($kernel->handle(
    $request = Request::capture()
))->send();

$kernel->terminate($request, $response);
