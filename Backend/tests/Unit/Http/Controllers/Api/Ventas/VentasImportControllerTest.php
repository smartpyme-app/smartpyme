<?php

namespace Tests\Unit\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Api\Ventas\VentasImportController;
use PHPUnit\Framework\TestCase;

class VentasImportControllerTest extends TestCase
{
    public function test_payload_un_error(): void
    {
        $payload = VentasImportController::payloadErrores([
            ['fila' => 12, 'columna' => 'nit', 'mensaje' => 'obligatorio porque tipo_documento_venta es Crédito fiscal'],
        ]);

        $this->assertSame(0, $payload['procesadas']);
        $this->assertSame(12, $payload['errores'][0]['fila']);
        $this->assertSame('nit', $payload['errores'][0]['columna']);
        $this->assertSame('No se importó ninguna venta. Hay 1 error.', $payload['message']);
    }

    public function test_payload_varios_errores(): void
    {
        $payload = VentasImportController::payloadErrores([
            ['fila' => 5, 'columna' => 'correlativo', 'mensaje' => 'es obligatorio (ventas históricas)'],
            ['fila' => 6, 'columna' => 'forma_pago', 'mensaje' => 'es obligatorio.'],
            ['fila' => 7, 'columna' => 'nombre', 'mensaje' => 'es obligatorio.'],
        ]);

        $this->assertCount(3, $payload['errores']);
        $this->assertSame('No se importó ninguna venta. Hay 3 errores.', $payload['message']);
        $this->assertSame(0, $payload['procesadas']);
    }
}
