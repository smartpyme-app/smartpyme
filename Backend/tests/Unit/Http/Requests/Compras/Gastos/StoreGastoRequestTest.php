<?php

namespace Tests\Unit\Http\Requests\Compras\Gastos;

use App\Http\Requests\Compras\Gastos\StoreGastoRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * `egresos.id_categoria` es nullable y la mayoría de gastos no la usa: si vuelve a ser
 * `required`, cancelar o pagar un gasto desde el listado responde 422.
 */
class StoreGastoRequestTest extends TestCase
{
    public function test_id_categoria_no_es_obligatoria(): void
    {
        $reglas = (new StoreGastoRequest())->rules()['id_categoria'];

        $this->assertStringNotContainsString('required', $reglas);
    }

    public function test_id_categoria_nula_pasa_la_validacion(): void
    {
        $validator = Validator::make(
            ['id_categoria' => null],
            ['id_categoria' => (new StoreGastoRequest())->rules()['id_categoria']]
        );

        $this->assertFalse($validator->errors()->has('id_categoria'));
    }
}
