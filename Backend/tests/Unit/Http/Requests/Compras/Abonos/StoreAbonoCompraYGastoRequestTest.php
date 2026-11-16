<?php

namespace Tests\Unit\Http\Requests\Compras\Abonos;

use App\Http\Requests\Compras\Abonos\StoreAbonoCompraRequest;
use App\Http\Requests\Compras\Gastos\StoreAbonoGastoRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Abono de compra y de gasto con cheque: la referencia puede incluir letras.
 */
class StoreAbonoCompraYGastoRequestTest extends TestCase
{
    public function test_referencia_de_cheque_acepta_letras_en_compra_y_gasto(): void
    {
        foreach ([StoreAbonoCompraRequest::class, StoreAbonoGastoRequest::class] as $clase) {
            $validator = $this->validadorReferencia($clase, 'Cheque', 'CH-1842A');

            $this->assertFalse($validator->fails(), $clase.': '.$validator->errors()->first('referencia'));
        }
    }

    public function test_referencia_de_cheque_sigue_siendo_obligatoria(): void
    {
        foreach ([StoreAbonoCompraRequest::class, StoreAbonoGastoRequest::class] as $clase) {
            $validator = $this->validadorReferencia($clase, 'Cheque', '');

            $this->assertTrue($validator->errors()->has('referencia'), $clase);
        }
    }

    private function validadorReferencia(string $clase, string $formaPago, ?string $referencia)
    {
        $request = $clase::create('/', 'POST', [
            'forma_pago' => $formaPago,
            'referencia' => $referencia,
        ]);

        return Validator::make(
            $request->all(),
            ['referencia' => $request->rules()['referencia']],
            $request->messages()
        );
    }
}
