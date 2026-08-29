<?php

namespace Tests\Unit\Services\Restaurante;

use App\Models\User;
use App\Services\Restaurante\RestauranteAutorizacionService;
use Tests\TestCase;

class RestauranteAutorizacionServiceTest extends TestCase
{
    private RestauranteAutorizacionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new RestauranteAutorizacionService;
    }

    public function test_usuario_puede_cerrar_mesa_roles_ticket_sp2158(): void
    {
        foreach (['Administrador', 'Supervisor', 'Ventas'] as $tipo) {
            $user = new User;
            $user->exists = true;
            $user->tipo = $tipo;
            $this->assertTrue($this->svc->usuarioPuedeCerrarMesa($user), $tipo);
        }
    }

    public function test_usuario_no_puede_cerrar_mesa_otros_roles(): void
    {
        foreach (['Ventas Limitado', 'Supervisor Limitado', 'Contador', 'Mesero', ''] as $tipo) {
            $user = new User;
            $user->exists = true;
            $user->tipo = $tipo;
            $this->assertFalse($this->svc->usuarioPuedeCerrarMesa($user), $tipo ?: '(vacío)');
        }
    }

    public function test_usuario_puede_cerrar_mesa_forzada_sin_codigo_solo_admin_y_supervisor(): void
    {
        foreach (['Administrador', 'Supervisor'] as $tipo) {
            $user = new User;
            $user->exists = true;
            $user->tipo = $tipo;
            $this->assertTrue($this->svc->usuarioPuedeCerrarMesaForzadaSinCodigo($user), $tipo);
        }
        $ventas = new User;
        $ventas->exists = true;
        $ventas->tipo = 'Ventas';
        $this->assertFalse($this->svc->usuarioPuedeCerrarMesaForzadaSinCodigo($ventas));
    }
}
