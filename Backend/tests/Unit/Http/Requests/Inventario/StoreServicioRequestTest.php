<?php

namespace Tests\Unit\Http\Requests\Inventario;

use App\Http\Requests\Inventario\StoreServicioRequest;
use Tests\TestCase;

/**
 * El listado activa/desactiva enviando la fila del servicio (`id_categoria`, `id_empresa`).
 * Si la regla vuelve a pedir `categoria_id` / `empresa_id`, el cambio de estado responde 422.
 */
class StoreServicioRequestTest extends TestCase
{
    public function test_exige_las_columnas_reales_del_servicio(): void
    {
        $reglas = (new StoreServicioRequest())->rules();

        $this->assertArrayHasKey('id_categoria', $reglas);
        $this->assertArrayHasKey('id_empresa', $reglas);
        $this->assertArrayNotHasKey('categoria_id', $reglas);
        $this->assertArrayNotHasKey('empresa_id', $reglas);
    }
}
