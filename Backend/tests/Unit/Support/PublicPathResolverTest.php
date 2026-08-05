<?php

namespace Tests\Unit\Support;

use App\Support\PublicPathResolver;
use PHPUnit\Framework\TestCase;

final class PublicPathResolverTest extends TestCase
{
    private const REPO = '/home/smartpyme/repositories/unificado/Backend';

    private const DOCROOT = '/home/smartpyme/public_html/apiunificado';

    public function test_la_variable_de_entorno_tiene_prioridad(): void
    {
        $resuelto = PublicPathResolver::resolve(
            ['APP_PUBLIC_PATH' => '/var/www/otro/'],
            self::REPO,
            static fn (string $ruta): bool => true
        );

        $this->assertSame('/var/www/otro', $resuelto);
    }

    public function test_usa_el_docroot_mapeado_para_el_repo_de_produccion(): void
    {
        $resuelto = PublicPathResolver::resolve(
            [],
            self::REPO.'/',
            static fn (string $ruta): bool => $ruta === self::DOCROOT
        );

        $this->assertSame(self::DOCROOT, $resuelto);
    }

    public function test_otro_despliegue_en_el_mismo_servidor_no_toma_el_docroot(): void
    {
        $resuelto = PublicPathResolver::resolve(
            [],
            '/home/smartpyme/repositories/main/Backend',
            static fn (string $ruta): bool => true
        );

        $this->assertNull($resuelto);
    }

    public function test_sin_docroot_en_disco_conserva_el_public_del_repo(): void
    {
        $resuelto = PublicPathResolver::resolve(
            ['APP_PUBLIC_PATH' => '   '],
            self::REPO,
            static fn (string $ruta): bool => false
        );

        $this->assertNull($resuelto);
    }
}
