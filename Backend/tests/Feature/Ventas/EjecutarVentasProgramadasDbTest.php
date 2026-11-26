<?php

namespace Tests\Feature\Ventas;

use App\Models\Ventas\VentaProgramada;
use App\Services\Ventas\EjecutarVentasProgramadasService;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\GenerarVentasRecurrentesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/** BD sqlite en memoria (la guardia de pruebas la exige): solo la tabla que usa el cron. */
class EjecutarVentasProgramadasDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('ventas_programadas', function ($t) {
            $t->id();
            foreach (['id_empresa', 'id_cliente', 'id_documento', 'ejecuciones_count'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->string('nombre')->default('t');
            $t->string('frecuencia');
            $t->integer('dia_ejecucion');
            $t->time('hora_ejecucion');
            $t->dateTime('proxima_ejecucion');
            $t->boolean('activo')->default(true);
            $t->boolean('emitir_automatico')->default(false);
            $t->boolean('enviar_email')->default(false);
            $t->integer('max_ejecuciones')->nullable();
            $t->timestamps();
        });
        Schema::create('detalles_venta_programada', function ($t) {
            $t->id();
            $t->integer('id_venta_programada');
        });
    }

    private function crear(array $extra = []): VentaProgramada
    {
        return VentaProgramada::create($extra + [
            'id_empresa' => 1, 'id_cliente' => 1, 'id_documento' => 1,
            'frecuencia' => 'mensual', 'dia_ejecucion' => 1, 'hora_ejecucion' => '08:00:00',
            'proxima_ejecucion' => '2026-01-01 08:00:00',
        ]);
    }

    public function test_max_ejecuciones_alcanzado_desactiva_sin_crear_venta(): void
    {
        $p = $this->crear(['max_ejecuciones' => 2, 'ejecuciones_count' => 2]);
        $facturacion = Mockery::mock(FacturacionService::class);
        $facturacion->shouldNotReceive('procesar');
        $service = new EjecutarVentasProgramadasService($facturacion, Mockery::mock(GenerarVentasRecurrentesService::class));

        $this->assertSame(1, $service->ejecutar(Carbon::parse('2026-02-01 09:00:00')));

        $this->assertFalse($p->fresh()->activo);
    }

    public function test_reclamar_avanza_proxima_y_solo_gana_un_proceso(): void
    {
        $p = $this->crear();
        $service = new EjecutarVentasProgramadasService(Mockery::mock(FacturacionService::class), Mockery::mock(GenerarVentasRecurrentesService::class));
        $slot = $p->proxima_ejecucion->copy();
        $now = Carbon::parse('2026-01-01 09:00:00');

        $this->assertTrue($service->reclamar($p, $slot, $now));
        $this->assertSame('2026-02-01 08:00:00', $p->fresh()->proxima_ejecucion->format('Y-m-d H:i:s'));
        $this->assertFalse($service->reclamar($p, $slot, $now), 'segundo proceso no debe reclamar el mismo slot');
    }
}
