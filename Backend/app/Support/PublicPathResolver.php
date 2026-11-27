<?php

namespace App\Support;

/**
 * Resuelve el directorio público real servido por el servidor web.
 *
 * En producción el DocumentRoot vive fuera del repositorio, por lo que el
 * `public/` del código no se sirve por HTTP y todo lo que se escriba ahí
 * (imágenes, plantillas) responde 404.
 */
final class PublicPathResolver
{
    /**
     * Ruta del repositorio => DocumentRoot que sirve Nginx.
     *
     * ponytail: el mapa está fijo en código porque .env todavía no está cargado
     * durante el bootstrap. Para cualquier otro servidor basta con exportar
     * APP_PUBLIC_PATH como variable de entorno real (php-fpm env[] o
     * fastcgi_param), sin tocar este archivo.
     */
    public const DOCROOTS = [
        '/home/smartpyme/repositories/unificado/Backend' => '/home/smartpyme/public_html/apiunificado',
    ];

    /**
     * Carpeta de versiones de producción => DocumentRoot. Cada despliegue vive en
     * /home/smartpyme/prod/releases/<versión>/Backend (ver ops/prod/README.md).
     */
    public const PREFIJOS = [
        '/home/smartpyme/prod/releases/' => '/home/smartpyme/public_html/apiunificado',
    ];

    /**
     * Devuelve la ruta pública a usar, o null para conservar `base_path('public')`.
     *
     * @param  array<string, mixed>  $vars
     * @param  null|callable(string): bool  $existe
     */
    public static function resolve(array $vars = [], ?string $basePath = null, ?callable $existe = null): ?string
    {
        $existe ??= static fn (string $ruta): bool => is_dir($ruta);

        $configurado = $vars['APP_PUBLIC_PATH'] ?? null;
        if (is_string($configurado) && trim($configurado) !== '') {
            return self::normalizar($configurado);
        }

        $base = self::normalizar((string) $basePath);
        $docroot = self::DOCROOTS[$base] ?? null;
        if ($docroot === null) {
            foreach (self::PREFIJOS as $prefijo => $destino) {
                if (str_starts_with($base, $prefijo) && str_ends_with($base, '/Backend')) {
                    $docroot = $destino;
                    break;
                }
            }
        }

        return $docroot !== null && $existe($docroot) ? $docroot : null;
    }

    private static function normalizar(string $ruta): string
    {
        return rtrim(trim($ruta), '/\\');
    }
}
