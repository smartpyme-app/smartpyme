<?php
/**
 * Cachea la config del ambiente __AMBIENTE__ de forma segura.
 *
 * NO usar `artisan config:cache` ni `artisan optimize` en los ambientes: esos comandos arman la
 * config con una instancia nueva de Laravel que lee el .env del repositorio (en DEMO, el de
 * producción). Aquí se arranca por arranque.php (con su seguro de base) y se escribe lo cargado.
 */
$cache = '/home/smartpyme/ambientes/__AMBIENTE__/cache/config.php';
if (file_exists($cache)) {
    unlink($cache);
}
$app = require __DIR__ . '/arranque.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();   // el seguro corre aquí
$config = $app['config']->all();
file_put_contents("$cache.tmp", '<?php return ' . var_export($config, true) . ';' . PHP_EOL);
rename("$cache.tmp", $cache);
$base = $config['database']['connections'][$config['database']['default']]['database'];
echo "config cacheada: base=$base cola={$config['queue']['default']} correo={$config['mail']['default']} env={$config['app']['env']}\n";
