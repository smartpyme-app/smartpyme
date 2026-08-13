<?php

namespace Tests\Unit;

use App\Models\Indicador;
use PHPUnit\Framework\TestCase;

class IndicadorBodegaFillableTest extends TestCase
{
    public function test_fillable_incluye_id_bodega(): void
    {
        $defaults = (new \ReflectionClass(Indicador::class))->getDefaultProperties();
        $this->assertContains('id_bodega', $defaults['fillable']);
    }
}
