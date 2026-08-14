<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionConfigService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComisionConfigIdReglaTest extends TestCase
{
    public function test_rechaza_id_regla_ajena_o_tipo_incorrecto(): void
    {
        $svc = new ComisionConfigService(fn () => null);

        $this->expectException(ValidationException::class);
        $svc->resolverIdRegla(1, 99);
    }

    public function test_acepta_id_regla_por_categoria_de_la_empresa(): void
    {
        $regla = (object) [
            'id' => 5,
            'id_empresa' => 1,
            'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
        ];
        $svc = new ComisionConfigService(function (int $idEmpresa, int $idRegla) use ($regla) {
            $this->assertSame(1, $idEmpresa);
            $this->assertSame(5, $idRegla);

            return $regla;
        });

        $this->assertSame(5, $svc->resolverIdRegla(1, 5));
    }
}
