<?php

namespace Tests\Feature\Ventas\Clientes;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EstadoCuentaPdfTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_html_muestra_mora_entera_logo_y_factura_14_en_mas_de_120(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 18:05:00'));

        $html = view('reportes.clientes.estado-cuenta', [
            'cliente' => $this->clienteConFactura14(),
        ])->render();

        $this->assertStringContainsString('+365 días', $html);
        $this->assertStringContainsString('Más de 120', $html);
        $this->assertStringContainsString('Factura #14', $html);
        $this->assertMatchesRegularExpression('/>208</', $html);
        $this->assertStringNotContainsString('208.37', $html);

        $this->assertMatchesRegularExpression(
            '/Factura #14<\/td>.*?<td>208<\/td>\s*<td class="text-right">\$0\.00<\/td>\s*<td class="text-right">\$0\.00<\/td>\s*<td class="text-right">\$0\.00<\/td>\s*<td class="text-right">\$0\.00<\/td>\s*<td class="text-right">\$0\.00<\/td>\s*<td class="text-right">\$678\.00<\/td>\s*<td class="text-right">\$0\.00<\/td>/s',
            $html
        );
    }

    private function clienteConFactura14(): object
    {
        $venta = (object) [
            'fecha' => '2026-01-12',
            'fecha_pago' => '2026-02-11',
            'saldo' => 678.0,
            'total' => 678.0,
            'nombre_documento' => 'Factura',
            'correlativo' => 14,
            'abonos' => new Collection(),
        ];

        return (object) [
            'tipo' => 'Empresa',
            'nombre_empresa' => 'PRINTCRAFT CENTRAL AMERICA',
            'nombre_completo' => '',
            'ncr' => '20387533',
            'dui' => null,
            'empresa' => (object) [
                'logo' => null,
                'currency' => (object) ['currency_symbol' => '$'],
            ],
            'ventas' => new Collection([$venta]),
        ];
    }
}
