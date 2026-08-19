<?php

namespace Tests\Feature\Ventas\Cotizaciones;

use App\Models\CotizacionVentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class CotizacionSmartpymePdfTest extends TestCase
{
    public function test_html_incluye_cliente_fiscales_cantidades_y_totales(): void
    {
        $html = view(
            'reportes.facturacion.formatos_empresas.cotizacion-smartpyme',
            $this->viewData($this->detalles(1, 100))
        )->render();

        $this->assertStringContainsString('PRUEBA CLIENTE DAVID', $html);
        $this->assertStringContainsString('NCR:', $html);
        $this->assertStringContainsString('123-456', $html);
        $this->assertStringContainsString('DUI:', $html);
        $this->assertStringContainsString('7777-8888', $html);
        $this->assertStringContainsString('Colonia Escalón', $html);
        $this->assertStringContainsString('Cotización #244', $html);
        $this->assertStringContainsString('Válido hasta', $html);
        $this->assertStringContainsString('Plan Avanzado', $html);
        $this->assertStringContainsString('Hasta 2 sucursales', $html);
        $this->assertStringContainsString('1,000.00', $html);
        $this->assertStringContainsString('+ IVA', $html);
        $this->assertStringContainsString('¿Por qué elegir SmartPyme?', $html);
        $this->assertStringContainsString('contact@smartpyme.sv', $html);
    }

    public function test_pdf_corto_y_extenso_respetan_saltos_de_pagina(): void
    {
        $corto = Pdf::loadHTML(
            view('reportes.facturacion.formatos_empresas.cotizacion-smartpyme', $this->viewData($this->detalles(1)))->render()
        )->setPaper('letter', 'portrait');
        $corto->render();
        $paginasCorto = $corto->getDomPDF()->getCanvas()->get_page_number();

        $largo = Pdf::loadHTML(
            view('reportes.facturacion.formatos_empresas.cotizacion-smartpyme', $this->viewData($this->detalles(25)))->render()
        )->setPaper('letter', 'portrait');
        $largo->render();
        $paginasLargo = $largo->getDomPDF()->getCanvas()->get_page_number();

        $this->assertGreaterThanOrEqual(2, $paginasCorto);
        $this->assertGreaterThanOrEqual(3, $paginasLargo);
        $this->assertGreaterThanOrEqual($paginasCorto, $paginasLargo);
        $this->assertGreaterThan(5000, strlen($corto->output()));
    }

    private function viewData(Collection $detalles): array
    {
        $empresa = (object) [
            'pais' => 'El Salvador',
            'direccion' => 'Edificio Colabora loca 1-2',
            'municipio' => 'San Salvador',
            'departamento' => 'San Salvador',
            'telefono' => '7732-5932',
            'mostrar_sello_firma_cotizacion' => false,
            'firma' => null,
            'sello' => null,
            'currency' => (object) ['currency_symbol' => '$'],
        ];

        $cliente = (object) [
            'ncr' => '123-456',
            'dui' => '00123456-7',
            'telefono' => '7777-8888',
            'direccion' => 'Colonia Escalón',
            'municipio' => 'San Salvador',
            'departamento' => 'San Salvador',
        ];

        $venta = (object) [
            'correlativo' => '244',
            'nombre_cliente' => 'PRUEBA CLIENTE DAVID',
            'fecha' => '2026-08-18',
            'fecha_expiracion' => '2026-09-18',
            'sub_total' => 1000 * $detalles->count(),
            'iva' => 130 * $detalles->count(),
            'total' => 1130 * $detalles->count(),
            'observaciones' => '',
            'detalles' => $detalles,
            'empresa' => $empresa,
            'cliente' => $cliente,
        ];

        return [
            'venta' => $venta,
            'cotizacion_mostrar_descripcion' => true,
        ];
    }

    private function detalles(int $n, float $descuento = 0): Collection
    {
        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $items[] = new CotizacionVentaDetalle([
                'descripcion' => 'Plan Avanzado: Inventario, Servicios, Ventas, Compras, Gastos, Finanzas, Citas, Cierre de Caja e Inteligencia de Negocios. Hasta 2 sucursales 5 usuarios incluidos',
                'cantidad' => 1,
                'precio' => 1000,
                'descuento' => $descuento,
                'total' => 1000 - $descuento,
            ]);
        }

        return collect($items);
    }
}
