<?php

namespace Tests\Unit\Http\Requests\Compras\Proveedores;

use App\Http\Requests\Compras\Proveedores\StoreProveedorRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreProveedorRequestTest extends TestCase
{
    private function validarBanco(array $data)
    {
        $reglas = (new StoreProveedorRequest())->rules();

        return Validator::make($data, [
            'banco' => $reglas['banco'],
            'tipo_cuenta' => $reglas['tipo_cuenta'],
            'numero_cuenta' => $reglas['numero_cuenta'],
            'titular_cuenta' => $reglas['titular_cuenta'],
            'forma_pago' => $reglas['forma_pago'],
        ]);
    }

    public function test_bloque_bancario_vacio_es_valido(): void
    {
        $validator = $this->validarBanco([
            'banco' => null,
            'tipo_cuenta' => null,
            'numero_cuenta' => null,
            'titular_cuenta' => null,
            'forma_pago' => null,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_solo_numero_cuenta_exige_el_resto_de_cuenta(): void
    {
        $validator = $this->validarBanco([
            'numero_cuenta' => '123456',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('banco'));
        $this->assertTrue($validator->errors()->has('tipo_cuenta'));
        $this->assertTrue($validator->errors()->has('titular_cuenta'));
        $this->assertFalse($validator->errors()->has('forma_pago'));
    }

    public function test_cuatro_campos_de_cuenta_sin_forma_pago_es_valido(): void
    {
        $validator = $this->validarBanco([
            'banco' => 'Banco Agrícola',
            'tipo_cuenta' => 'Ahorro',
            'numero_cuenta' => '123456',
            'titular_cuenta' => 'ACME SA',
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_tipo_cuenta_invalido_falla(): void
    {
        $validator = $this->validarBanco([
            'banco' => 'Banco Agrícola',
            'tipo_cuenta' => 'Nómina',
            'numero_cuenta' => '123456',
            'titular_cuenta' => 'ACME SA',
        ]);

        $this->assertTrue($validator->errors()->has('tipo_cuenta'));
    }
}
