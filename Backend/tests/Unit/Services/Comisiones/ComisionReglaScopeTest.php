<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionReglaScope;
use PHPUnit\Framework\TestCase;

class ComisionReglaScopeTest extends TestCase
{
    private function regla(array $over): object
    {
        return (object) array_merge([
            'id' => 1,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
            'reemplaza_global' => false,
            'activo' => true,
        ], $over);
    }

    public function test_suma_global_e_individual(): void
    {
        $scope = new ComisionReglaScope();
        $out = $scope->aplicables([
            $this->regla(['id' => 1]),
            $this->regla([
                'id' => 2,
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [5],
            ]),
        ], 5);
        $this->assertSame([1, 2], array_map(fn ($r) => $r->id, $out));
    }

    public function test_reemplaza_global_descarta_globales(): void
    {
        $scope = new ComisionReglaScope();
        $out = $scope->aplicables([
            $this->regla(['id' => 1]),
            $this->regla([
                'id' => 2,
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [5],
                'reemplaza_global' => true,
            ]),
        ], 5);
        $this->assertSame([2], array_map(fn ($r) => $r->id, $out));
    }

    public function test_individual_de_otro_vendedor_no_aplica(): void
    {
        $scope = new ComisionReglaScope();
        $out = $scope->aplicables([
            $this->regla([
                'id' => 2,
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [9],
            ]),
        ], 5);
        $this->assertSame([], $out);
    }
}
