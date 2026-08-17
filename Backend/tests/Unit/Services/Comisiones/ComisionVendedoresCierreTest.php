<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionVendedoresCierre;
use PHPUnit\Framework\TestCase;

class ComisionVendedoresCierreTest extends TestCase
{
    private function regla(array $over): object
    {
        return (object) array_merge([
            'id' => 1,
            'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
            'config' => [],
        ], $over);
    }

    public function test_tipos_vendedor_incluyen_vendedor_ventas_y_limitado(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Vendedor', 'Ventas', 'Ventas Limitado'],
            ComisionVendedoresCierre::TIPOS
        );
    }

    public function test_solo_movimientos_si_no_hay_regla_periodo_o_base(): void
    {
        $ids = ComisionVendedoresCierre::unir(
            [10, 11],
            [$this->regla(['tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA])],
            [10, 11, 99],
        );
        $this->assertSame([10, 11], $ids);
    }

    public function test_global_base_incluye_vendedores_sin_ventas(): void
    {
        $ids = ComisionVendedoresCierre::unir(
            [10],
            [$this->regla([
                'tipo_calculo' => ComisionRegla::TIPO_POR_VOLUMEN,
                'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            ])],
            [10, 20],
        );
        $this->assertEqualsCanonicalizing([10, 20], $ids);
    }

    public function test_global_no_mete_admins_fuera_de_la_lista_de_vendedores(): void
    {
        $ids = ComisionVendedoresCierre::unir(
            [],
            [$this->regla(['config' => ['salario_base' => 200]])],
            [5, 6],
        );
        $this->assertEqualsCanonicalizing([5, 6], $ids);
        $this->assertNotContains(1, $ids);
    }

    public function test_individual_une_id_vendedores_aunque_no_vendan(): void
    {
        $ids = ComisionVendedoresCierre::unir(
            [10],
            [$this->regla([
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [8],
                'config' => ['salario_base' => 100],
            ])],
            [10, 11, 12],
        );
        $this->assertEqualsCanonicalizing([10, 8], $ids);
    }
}
