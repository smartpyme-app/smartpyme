<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionReglaService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComisionReglaServiceTest extends TestCase
{
    public function test_rechaza_tipo_calculo_invalido(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->crear(1, [
            'nombre' => 'Regla',
            'tipo_calculo' => 'mixta',
            'config' => [],
        ]);
    }

    public function test_rechaza_por_volumen_sin_tramos(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->crear(1, [
            'nombre' => 'Volumen',
            'tipo_calculo' => ComisionRegla::TIPO_POR_VOLUMEN,
            'config' => [],
        ]);
    }

    public function test_rechaza_por_margen_sin_porcentaje(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->crear(1, [
            'nombre' => 'Margen',
            'tipo_calculo' => ComisionRegla::TIPO_POR_MARGEN,
            'config' => [],
        ]);
    }

    public function test_rechaza_individual_sin_un_vendedor(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->crear(1, [
            'nombre' => 'Individual',
            'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
            'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
            'id_vendedores' => [],
            'config' => [],
        ]);
    }

    public function test_rechaza_equipo_sin_vendedores(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->crear(1, [
            'nombre' => 'Equipo',
            'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
            'alcance' => ComisionRegla::ALCANCE_EQUIPO,
            'id_vendedores' => [],
            'config' => [],
        ]);
    }

    public function test_rechaza_por_margen_porcentaje_fuera_de_rango(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->prepararPayload([
            'nombre' => 'Margen',
            'tipo_calculo' => ComisionRegla::TIPO_POR_MARGEN,
            'config' => ['porcentaje' => 150],
        ]);
    }

    public function test_rechaza_por_volumen_tramo_malformado(): void
    {
        $this->expectException(ValidationException::class);

        (new ComisionReglaService())->prepararPayload([
            'nombre' => 'Volumen',
            'tipo_calculo' => ComisionRegla::TIPO_POR_VOLUMEN,
            'config' => [
                'tramos' => [
                    ['umbral' => 1000],
                ],
            ],
        ]);
    }

    public function test_prepara_payload_por_volumen_con_salario_base(): void
    {
        $payload = (new ComisionReglaService())->prepararPayload([
            'nombre' => 'Volumen mixto',
            'tipo_calculo' => ComisionRegla::TIPO_POR_VOLUMEN,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'momento_devengo' => ComisionRegla::MOMENTO_AL_PAGAR,
            'salario_base' => 400,
            'config' => [
                'tramos' => [
                    ['umbral' => 0, 'porcentaje' => 1],
                    ['umbral' => 1000, 'porcentaje' => 2],
                ],
            ],
        ]);

        $this->assertSame(ComisionRegla::TIPO_POR_VOLUMEN, $payload['tipo_calculo']);
        $this->assertSame(400.0, $payload['config']['salario_base']);
        $this->assertCount(2, $payload['config']['tramos']);
        $this->assertNull($payload['id_vendedores']);
    }
}
