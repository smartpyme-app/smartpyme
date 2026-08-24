<?php

namespace Tests\Unit;

use App\Models\Indicador;
use Tests\TestCase;

class IndicadorBodegaFillableTest extends TestCase
{
    public function test_fillable_incluye_id_bodega(): void
    {
        $defaults = (new \ReflectionClass(Indicador::class))->getDefaultProperties();
        $this->assertContains('id_bodega', $defaults['fillable']);
    }

    public function test_indicador_define_relacion_bodega(): void
    {
        $indicador = (new \ReflectionClass(Indicador::class))->newInstanceWithoutConstructor();
        $relation = $indicador->bodega();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
        $this->assertInstanceOf(\App\Models\Inventario\Bodega::class, $relation->getRelated());
        $this->assertSame('id_bodega', $relation->getForeignKeyName());
    }
}
