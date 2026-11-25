<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\NifMapeoSeeder;
use PHPUnit\Framework\TestCase;

class NifMapeoSeederTest extends TestCase
{
    public function test_todo_tipo_de_gasto_del_formulario_tiene_categoria_nif(): void
    {
        $html = __DIR__ . '/../../../../../Frontend/src/app/views/compras/gastos/gastos.component.html';
        if (!is_file($html)) {
            $this->markTestSkipped('Sin Frontend en este checkout.');
        }
        preg_match('/<select name="tipo".*?<\/select>/s', file_get_contents($html), $bloque);
        preg_match_all('/<option value="([^"]+)">/', $bloque[0] ?? '', $m);
        $tipos = $m[1];
        $this->assertContains('Planilla', $tipos);
        $this->assertSame([], array_values(array_diff($tipos, array_keys(NifMapeoSeeder::TIPOS))));
    }

    public function test_el_mapeo_solo_apunta_a_categorias_del_catalogo(): void
    {
        foreach (NifMapeoSeeder::TIPOS as $tipo => [$codigo]) {
            $this->assertArrayHasKey($codigo, NifMapeoSeeder::CATALOGO, $tipo);
        }
    }
}
