<?php

namespace Tests\Unit\Services\Funcionalidades;

use PHPUnit\Framework\TestCase;

class FuncionalidadesSeederSlugTest extends TestCase
{
    public function test_seeder_incluye_comisiones_vendedores(): void
    {
        $file = __DIR__ . '/../../../../database/seeders/FuncionalidadesSeeder.php';
        $src = file_get_contents($file);
        $this->assertStringContainsString("'slug' => 'comisiones-vendedores'", $src);
    }

    public function test_seeder_incluye_creditos_clientes(): void
    {
        $file = __DIR__ . '/../../../../database/seeders/FuncionalidadesSeeder.php';
        $src = file_get_contents($file);
        $this->assertStringContainsString("'slug' => 'creditos-clientes'", $src);
    }
}
