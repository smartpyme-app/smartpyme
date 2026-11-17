<?php

namespace Tests\Unit\Http\Requests\Ventas\Abonos;

use App\Http\Requests\Ventas\Abonos\StoreAbonoRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Pagar una venta pendiente con cheque: la referencia puede incluir letras
 * (número de cheque alfanumérico). Solo dígitos sigue siendo válido.
 */
class StoreAbonoRequestTest extends TestCase
{
    public function test_referencia_de_cheque_acepta_letras(): void
    {
        $validator = $this->validadorReferencia('Cheque', 'CH-1842A');

        $this->assertFalse($validator->fails(), $validator->errors()->first('referencia'));
    }

    public function test_referencia_de_cheque_acepta_solo_digitos(): void
    {
        $validator = $this->validadorReferencia('Cheque', '1842');

        $this->assertFalse($validator->fails());
    }

    public function test_referencia_de_cheque_sigue_siendo_obligatoria(): void
    {
        $validator = $this->validadorReferencia('Cheque', '');

        $this->assertTrue($validator->errors()->has('referencia'));
    }

    private function validadorReferencia(string $formaPago, ?string $referencia)
    {
        $request = StoreAbonoRequest::create('/venta/abono', 'POST', [
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
