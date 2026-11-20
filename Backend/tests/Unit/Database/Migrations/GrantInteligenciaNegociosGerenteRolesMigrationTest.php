<?php

namespace Tests\Unit\Database\Migrations;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class GrantInteligenciaNegociosGerenteRolesMigrationTest extends TestCase
{
    public function test_migration_grants_only_gerente_roles(): void
    {
        $path = database_path('migrations/2026_11_20_120000_grant_inteligencia_negocios_to_gerente_roles.php');
        $this->assertFileExists($path);

        $source = File::get($path);
        $this->assertStringContainsString('inteligencia_negocios.ver', $source);
        $this->assertStringContainsString('ROL_GERENTE_VENTAS', $source);
        $this->assertStringContainsString('ROL_GERENTE_COMPRAS', $source);
        $this->assertStringContainsString('ROL_GERENTE_OPERACIONES', $source);
        $this->assertStringContainsString('hasPermissionTo', $source);
        $this->assertStringNotContainsString('Permission::truncate', $source);
        $this->assertStringNotContainsString('givePermissionTo(Permission::all())', $source);
    }
}