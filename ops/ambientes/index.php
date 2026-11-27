<?php
// Entrada pública del ambiente __AMBIENTE__. Todo el aislamiento está en /home/smartpyme/ambientes/__AMBIENTE__/arranque.php
define('LARAVEL_START', microtime(true));
if (file_exists('/home/smartpyme/ambientes/__AMBIENTE__/storage/framework/maintenance.php')) {
    require '/home/smartpyme/ambientes/__AMBIENTE__/storage/framework/maintenance.php';
}
$app = require '/home/smartpyme/ambientes/__AMBIENTE__/arranque.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = tap($kernel->handle($request = Illuminate\Http\Request::capture()))->send();
$kernel->terminate($request, $response);
