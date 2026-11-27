<?php
/**
 * Arranque de un ambiente aislado (QA o DEMO). Lo incluyen el index.php público y el artisan del ambiente.
 *
 * Define, ANTES de arrancar Laravel, rutas propias para .env, storage, caché de config y carpeta
 * pública. Sin esto, la DEMO (que usa el código de producción) leería la config cacheada de
 * producción, con la base de producción.
 *
 * Seguro final: si la base configurada no es la del ambiente, se detiene sin atender nada.
 */

$AMBIENTE = '__AMBIENTE__';            // qa | demo
$CODIGO = '__CODIGO__';                // carpeta Backend del código que usa
$DIR = '/home/smartpyme/ambientes/' . $AMBIENTE;
$BASE_ESPERADA = 'smartpyme_' . $AMBIENTE;

ini_set('pcre.jit', '0');
ini_set('pcre.backtrack_limit', '1000000');
ini_set('pcre.recursion_limit', '1000000');

$variables = [
    'APP_CONFIG_CACHE' => "$DIR/cache/config.php",
    'APP_ROUTES_CACHE' => "$DIR/cache/routes-v7.php",
    'APP_EVENTS_CACHE' => "$DIR/cache/events.php",
    'APP_SERVICES_CACHE' => "$DIR/cache/services.php",
    'APP_PACKAGES_CACHE' => "$DIR/cache/packages.php",
    'APP_PUBLIC_PATH' => '__PUBLICO__',
];
foreach ($variables as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}

require $CODIGO . '/vendor/autoload.php';
$app = require_once $CODIGO . '/bootstrap/app.php';
$app->useEnvironmentPath($DIR);
$app->useStoragePath("$DIR/storage");

$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($BASE_ESPERADA) {
    $config = $app['config'];
    $conexion = $config->get('database.default');
    $base = $config->get("database.connections.$conexion.database");
    if ($base !== $BASE_ESPERADA || $config->get('queue.default') !== 'sync') {
        if (PHP_SAPI !== 'cli') {
            http_response_code(503);
        }
        fwrite(fopen('php://stderr', 'w'), "Ambiente detenido: base '$base' (se esperaba '$BASE_ESPERADA') o cola distinta de sync.\n");
        exit(1);
    }
});

return $app;
