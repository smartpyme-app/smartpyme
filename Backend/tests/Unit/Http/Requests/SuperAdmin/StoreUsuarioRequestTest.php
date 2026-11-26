<?php

namespace Tests\Unit\Http\Requests\SuperAdmin;

use App\Http\Requests\SuperAdmin\StoreUsuarioRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreUsuarioRequestTest extends TestCase
{
    public function test_reglas_incluyen_codigo_autorizacion_numerico(): void
    {
        $request = StoreUsuarioRequest::createFrom(
            Request::create('/api/admin/usuario', 'POST', [
                'id' => 1,
                'name' => 'Test',
                'email' => 'test@example.com',
                'id_empresa' => 1,
                'id_sucursal' => 1,
                'id_bodega' => 1,
            ])
        );

        $this->assertArrayHasKey('codigo_autorizacion', $request->rules());
        $this->assertStringContainsString('numeric', $request->rules()['codigo_autorizacion']);
        $this->assertStringContainsString('digits_between:3,10', $request->rules()['codigo_autorizacion']);
    }

    public function test_codigo_autorizacion_rechaza_texto_no_numerico(): void
    {
        $request = new StoreUsuarioRequest();
        $rule = $request->rules()['codigo_autorizacion'];

        $validator = Validator::make(
            ['codigo_autorizacion' => 'Crecer2025$'],
            ['codigo_autorizacion' => $rule],
            $request->messages(),
            $request->attributes()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('codigo_autorizacion', $validator->errors()->toArray());
    }

    public function test_codigo_autorizacion_acepta_digitos(): void
    {
        $request = new StoreUsuarioRequest();
        $rule = $request->rules()['codigo_autorizacion'];

        $validator = Validator::make(
            ['codigo_autorizacion' => '1234'],
            ['codigo_autorizacion' => $rule]
        );

        $this->assertFalse($validator->fails());
    }
}
