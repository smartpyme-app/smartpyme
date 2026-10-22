<?php

namespace Tests\Unit\Http\Requests\Admin\Usuarios;

use App\Http\Requests\Admin\Usuarios\StoreUsuarioRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Unique;
use Tests\TestCase;

/**
 * Activar/desactivar desde el listado envía solo id+enable; no debe exigir name/email/bodega.
 */
class StoreUsuarioRequestTest extends TestCase
{
    public function test_enable_only_payload_uses_short_validation_rules(): void
    {
        $request = StoreUsuarioRequest::createFrom(
            Request::create('/api/usuario', 'POST', ['id' => 1, 'enable' => '1'])
        );

        $ref = new \ReflectionClass($request);
        $prep = $ref->getMethod('prepareForValidation');
        $prep->setAccessible(true);
        $prep->invoke($request);

        $rules = $request->rules();

        $this->assertSame(['id', 'enable'], array_keys($rules));
        $this->assertArrayNotHasKey('name', $rules);
        $this->assertArrayNotHasKey('email', $rules);
    }

    public function test_enable_only_payload_does_not_add_tipo_during_prepare(): void
    {
        $request = StoreUsuarioRequest::createFrom(
            Request::create('/api/usuario', 'POST', ['id' => 1, 'enable' => '1'])
        );

        $ref = new \ReflectionClass($request);
        $prep = $ref->getMethod('prepareForValidation');
        $prep->setAccessible(true);
        $prep->invoke($request);

        $this->assertSame(['id', 'enable'], array_keys($request->all()));
    }

    public function test_update_payload_without_id_empresa_gets_tenant_default(): void
    {
        $request = StoreUsuarioRequest::createFrom(
            Request::create('/api/usuario', 'POST', [
                'id' => 1,
                'name' => 'Test',
                'email' => 'test@example.com',
                'id_sucursal' => 1,
                'id_bodega' => 1,
                'rol_id' => 2,
            ])
        );

        $request->setUserResolver(fn () => (object) ['id_empresa' => 99]);

        $ref = new \ReflectionClass($request);
        $prep = $ref->getMethod('prepareForValidation');
        $prep->setAccessible(true);
        $prep->invoke($request);

        $this->assertSame(99, (int) $request->input('id_empresa'));
    }

    public function test_update_no_exige_telefono_unico(): void
    {
        $request = StoreUsuarioRequest::createFrom(
            Request::create('/api/usuario', 'POST', [
                'id' => 10,
                'name' => 'Test',
                'email' => 'test@example.com',
                'telefono' => '50322222222',
                'id_empresa' => 1,
                'id_sucursal' => 1,
                'id_bodega' => 1,
                'rol_id' => 3,
            ])
        );

        $this->assertFalse($this->reglaTelefonoEsUnica($request));
    }

    public function test_create_sigue_exigiendo_telefono_unico(): void
    {
        $request = StoreUsuarioRequest::createFrom(
            Request::create('/api/usuario', 'POST', [
                'name' => 'Test',
                'email' => 'test@example.com',
                'telefono' => '50322222222',
                'id_empresa' => 1,
                'id_sucursal' => 1,
                'id_bodega' => 1,
                'rol_id' => 3,
            ])
        );

        $this->assertTrue($this->reglaTelefonoEsUnica($request));
    }

    private function reglaTelefonoEsUnica(StoreUsuarioRequest $request): bool
    {
        foreach ($request->rules()['telefono'] as $rule) {
            if ($rule instanceof Unique) {
                return true;
            }
        }

        return false;
    }
}
