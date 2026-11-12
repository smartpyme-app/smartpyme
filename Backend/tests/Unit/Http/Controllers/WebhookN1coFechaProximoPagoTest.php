<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\WebhookN1coController;
use App\Models\Suscripcion;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class WebhookN1coFechaProximoPagoTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_renovacion_con_fecha_en_texto_extiende_desde_el_vencimiento(): void
    {
        Carbon::setTestNow('2026-11-09 12:00:00');

        $vigente = new Suscripcion();
        $vigente->fecha_proximo_pago = '2099-06-15 10:00:00';

        $vencida = new Suscripcion();
        $vencida->fecha_proximo_pago = '2026-11-06 10:00:00';

        $finDeMes = new Suscripcion();
        $finDeMes->fecha_proximo_pago = '2026-01-31 08:00:00';

        $this->assertSame('2099-07-15', $this->fecha('Mensual', $vigente)->format('Y-m-d'));
        $this->assertSame('2026-12-06', $this->fecha('Mensual', $vencida)->format('Y-m-d'));
        $this->assertSame('2026-02-28', $this->fecha('Mensual', $finDeMes)->format('Y-m-d'));
        $this->assertSame('2027-11-09', $this->fecha('Anual', null)->format('Y-m-d'));
    }

    private function fecha(string $tipoPlan, ?Suscripcion $suscripcion): Carbon
    {
        $method = new ReflectionMethod(WebhookN1coController::class, 'calcularFechaProximoPagoWebhook');

        return $method->invoke(new WebhookN1coController(), $tipoPlan, $suscripcion);
    }
}
