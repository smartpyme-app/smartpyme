<?php

namespace Tests\Unit\Services\Compras\Gastos;

use App\Models\Compras\Gastos\Gasto;
use App\Services\Compras\Gastos\GastoImportService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * La tabla `egresos` no tiene columna `condicion`: si el mapeo del DTE o el modelo la
 * vuelven a aceptar, el insert falla con "Unknown column 'condicion'".
 */
class GastoImportCondicionTest extends TestCase
{
    public function test_condicion_no_es_fillable_en_gasto(): void
    {
        $this->assertNotContains('condicion', (new Gasto())->getFillable());
    }

    public function test_condicion_operacion_solo_afecta_el_estado(): void
    {
        $gasto = new Gasto();
        $metodo = new ReflectionMethod(GastoImportService::class, 'mapearResumen');
        $metodo->setAccessible(true);

        $metodo->invoke(new GastoImportService(), $gasto, ['condicionOperacion' => 2]);

        $this->assertSame('Pendiente', $gasto->estado);
        $this->assertArrayNotHasKey('condicion', $gasto->getAttributes());
    }
}
