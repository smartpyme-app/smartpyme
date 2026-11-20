<?php

namespace Tests\Unit\Fidelizacion;

use App\Console\Commands\ProcesarPuntosVentasExistentes;
use App\Models\Ventas\Venta;
use App\Observers\FidelizacionCliente\VentaObserver;
use App\Services\FidelizacionCliente\ConsumoPuntosService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AcumulacionConCanjeTest extends TestCase
{
    public function test_venta_expone_monto_base_de_acumulacion(): void
    {
        $method = new ReflectionMethod(Venta::class, 'getMontoBaseAcumulacionPuntos');
        $this->assertTrue($method->isPublic());
    }

    public function test_calcular_puntos_esperados_no_bloquea_por_canje(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Models/Ventas/Venta.php'
        );

        $this->assertStringContainsString('getMontoBaseAcumulacionPuntos', $source);
        $this->assertStringNotContainsString(
            "if (\$this->tieneCanjeDePuntosEnVenta()) {\n            return 0;",
            $source
        );
        $this->assertStringNotContainsString(
            "if (\$this->tieneCanjeDePuntosEnVenta()) {\n            return false;",
            $source
        );
    }

    public function test_servicio_y_observer_no_bloquean_acumulacion_por_canje(): void
    {
        $servicio = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/FidelizacionCliente/ConsumoPuntosService.php'
        );
        $observer = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Observers/FidelizacionCliente/VentaObserver.php'
        );
        $comando = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Console/Commands/ProcesarPuntosVentasExistentes.php'
        );

        foreach ([$servicio, $observer, $comando] as $source) {
            $this->assertStringNotContainsString(
                'Venta con canje de puntos: no se acumulan puntos',
                $source
            );
            $this->assertStringNotContainsString(
                'Venta con canje de puntos: no aplica acumulación',
                $source
            );
            $this->assertStringContainsString('getMontoBaseAcumulacionPuntos', $source);
        }
    }

    public function test_clases_clave_siguen_existiendo(): void
    {
        $this->assertTrue(class_exists(ConsumoPuntosService::class));
        $this->assertTrue(class_exists(VentaObserver::class));
        $this->assertTrue(class_exists(ProcesarPuntosVentasExistentes::class));
    }
}
