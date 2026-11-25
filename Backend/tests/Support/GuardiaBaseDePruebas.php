<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use RuntimeException;

/**
 * Las pruebas NUNCA pueden tocar una base con datos de clientes.
 *
 * Varias pruebas usan RefreshDatabase (migrate:fresh borra todas las tablas), crean y
 * eliminan tablas con nombres reales (empresas, productos, inventario…) o crean y borran
 * registros. Si la configuración está cacheada (php artisan optimize / config:cache),
 * Laravel ignora el sqlite :memory: de phpunit.xml y usa la base del .env: así se borraron
 * bases locales, y lo mismo pasaría en producción.
 *
 * Esta guardia:
 *  1. Hace que las pruebas ignoren la configuración cacheada (antes de arrancar Laravel).
 *  2. Aborta si APP_ENV no es "testing" o si la conexión por defecto no es de pruebas.
 *  3. Antes de CADA consulta, en CUALQUIER conexión (también las que se abran después),
 *     verifica que la base sea de pruebas; si no, lanza una excepción sin ejecutarla.
 *
 * Base de pruebas = sqlite en memoria, o un nombre que termine en _test, _testing o _pruebas
 * (por ejemplo smartpyme_testing en el CI).
 */
final class GuardiaBaseDePruebas
{
    /** Ruta de caché de configuración que nunca existe: las pruebas siempre leen config/ y el entorno. */
    public const CACHE_CONFIG_PRUEBAS = 'bootstrap/cache/config.pruebas-nunca-existe.php';

    public static function antesDeArrancar(): void
    {
        foreach (['APP_CONFIG_CACHE' => self::CACHE_CONFIG_PRUEBAS] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }

    public static function proteger(Application $app): void
    {
        if ($app->configurationIsCached()) {
            self::abortar('La configuración está cacheada y las pruebas la usarían. Corre "php artisan config:clear".');
        }

        if (! $app->environment('testing')) {
            self::abortar('APP_ENV es "'.$app->environment().'"; las pruebas solo corren con APP_ENV=testing.');
        }

        $db = $app['db'];
        $default = $db->getDefaultConnection();
        $cfg = $app['config']->get("database.connections.{$default}", []);
        if (! self::esBaseDePruebas($cfg['driver'] ?? '', (string) ($cfg['database'] ?? ''))) {
            self::abortar("La conexión por defecto \"{$default}\" apunta a \"".($cfg['database'] ?? '?').'", que no es una base de pruebas.');
        }

        // Conexiones ya abiertas durante el arranque y todas las que se abran después.
        foreach ($db->getConnections() as $conexion) {
            self::vigilar($conexion);
        }
        $app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $e) {
            self::vigilar($e->connection);
        });
    }

    public static function vigilar(Connection $conexion): void
    {
        $conexion->beforeExecuting(function ($query, $bindings, Connection $c) {
            if (! self::esBaseDePruebas($c->getDriverName(), (string) $c->getDatabaseName())) {
                self::abortar('Consulta bloqueada en la conexión "'.$c->getName().'" (base "'.$c->getDatabaseName().'"): no es una base de pruebas. SQL: '.mb_substr((string) $query, 0, 120));
            }
        });
    }

    public static function esBaseDePruebas(string $driver, string $base): bool
    {
        if ($driver === 'sqlite') {
            return $base === ':memory:' || preg_match('/(^|[\/_.-])(test|testing|pruebas)[^\/]*\.sqlite$/i', $base) === 1;
        }

        return preg_match('/_(test|testing|pruebas)$/i', $base) === 1;
    }

    private static function abortar(string $motivo): never
    {
        $mensaje = "\n[GUARDIA DE PRUEBAS] {$motivo}\nLas pruebas nunca pueden tocar una base con datos reales.\n";
        fwrite(STDERR, $mensaje);

        throw new RuntimeException($mensaje);
    }
}
