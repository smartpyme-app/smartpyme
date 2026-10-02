<?php

namespace Tests\Unit\Support\Ventas;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Support\Admin\DocumentosDefaultPorPais;
use App\Support\Ventas\Ticket80mm;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class Ticket80mmTest extends TestCase
{
    public function test_switch_apagado_no_elige_la_plantilla(): void
    {
        $this->assertFalse(Ticket80mm::aplica(
            $this->empresa(false),
            new Documento(['nombre' => 'Factura'])
        ));
    }

    public function test_switch_encendido_elige_factura_y_ticket(): void
    {
        $empresa = $this->empresa(true);
        $this->assertTrue(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Factura'])));
        $this->assertTrue(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Ticket'])));
        $this->assertTrue(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Factura con RTN'])));
        $this->assertTrue(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Factura sin RTN'])));
    }

    public function test_recibo_cotizacion_y_dte_no_la_eligen(): void
    {
        $empresa = $this->empresa(true);
        $this->assertFalse(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Recibo'])));
        $this->assertFalse(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Cotización'])));
        $this->assertFalse(Ticket80mm::aplica($empresa, new Documento(['nombre' => DocumentosDefaultPorPais::CR_FACTURA])));
        $this->assertFalse(Ticket80mm::aplica($empresa, new Documento(['nombre' => DocumentosDefaultPorPais::CR_TIQUETE])));
    }

    public function test_empresas_con_plantilla_propia_no_la_eligen(): void
    {
        foreach (Ticket80mm::EMPRESAS_PROPIAS as $id) {
            $empresa = $this->empresa(true);
            $empresa->id = $id;
            $this->assertFalse(Ticket80mm::aplica($empresa, new Documento(['nombre' => 'Factura'])));
        }
    }

    public function test_html_honduras_incluye_cai_y_correlativo(): void
    {
        $html = $this->html('Honduras', [
            'nombre' => 'Factura sin RTN',
            'numero_emision' => '01',
            'resolucion' => 'CAI-123',
            'rangos' => '001-001-01-00000001 A 001-001-01-00003000',
            'fecha' => '2027-05-23',
            'nota' => 'Obs de prueba',
        ], 'HNL');

        $this->assertStringContainsString('CAI', $html);
        $this->assertStringContainsString('CAI-123', $html);
        $this->assertStringContainsString('001-001-01-00000439', $html);
        $this->assertStringContainsString('ISV 15%', $html);
        $this->assertStringContainsString('LEMPIRAS', $html);
        $this->assertStringContainsString('La factura es beneficio de todos, exíjala', $html);
        $this->assertStringContainsString('Obs de prueba', $html);
        $this->assertStringContainsString('Documento generado por SmartPyme', $html);
    }

    public function test_html_honduras_sin_cai_no_imprime_el_rotulo(): void
    {
        $html = $this->html('Honduras', [
            'nombre' => 'Factura',
            'numero_emision' => '',
            'resolucion' => '',
            'rangos' => '',
            'fecha' => null,
        ], 'HNL');

        $this->assertStringNotContainsString('CAI', $html);
        $this->assertStringContainsString('439', $html);
        $this->assertStringNotContainsString('001-001-01-', $html);
    }

    public function test_html_muestra_descuento_de_linea_aunque_cabecera_este_en_cero(): void
    {
        $html = $this->html('Honduras', ['nombre' => 'Ticket'], 'HNL', 0, 7.5);

        $this->assertStringContainsString('Descuentos y rebajas', $html);
        $this->assertStringContainsString('7.50', $html);
    }

    public function test_html_otro_pais_no_incluye_cai(): void
    {
        $html = $this->html('El Salvador', [
            'nombre' => 'Factura',
            'resolucion' => 'NO-DEBE-SALIR',
        ], 'USD');

        $this->assertStringNotContainsString('CAI', $html);
        $this->assertStringNotContainsString('NO-DEBE-SALIR', $html);
        $this->assertStringContainsString('Número:</span> 439', $html);
        $this->assertStringContainsString('IVA', $html);
        $this->assertStringContainsString('USD', $html);
    }

    private function empresa(bool $switch): Empresa
    {
        $empresa = new Empresa([
            'nombre' => 'Empresa prueba',
            'pais' => 'El Salvador',
            'moneda' => 'USD',
        ]);
        $empresa->id = 10;
        $empresa->custom_empresa = [
            'configuraciones' => [
                Ticket80mm::CLAVE => $switch,
            ],
        ];

        return $empresa;
    }

    private function html(
        string $pais,
        array $documentoDatos,
        string $moneda,
        float $descuentoVenta = 5,
        ?float $descuentoLinea = null
    ): string {
        $empresa = new Empresa([
            'nombre' => 'Empresa prueba',
            'pais' => $pais,
            'moneda' => $moneda,
            'nit' => '0614-010190-101-0',
            'direccion' => 'Calle 1',
            'iva' => 15,
        ]);
        $documento = new Documento($documentoDatos);
        $venta = new Venta([
            'fecha' => '2026-08-05',
            'correlativo' => 439,
            'forma_pago' => 'Efectivo',
            'sub_total' => 300,
            'exenta' => 50,
            'descuento' => $descuentoVenta,
            'iva' => 51,
            'total' => 426,
        ]);
        $venta->setRelation('detalles', new Collection([
            new Detalle([
                'descripcion' => 'Producto de prueba',
                'cantidad' => 1,
                'precio' => 115,
                'total' => 115,
                'gravada' => 100,
                'iva' => 15,
                'porcentaje_impuesto' => 15,
                'descuento' => $descuentoLinea ?? $descuentoVenta,
                'tipo_gravado' => 'gravada',
            ]),
        ]));
        $cliente = null;
        $letras = 'CUATROCIENTOS VEINTISEIS';
        $centavos = '00';

        return view(Ticket80mm::VISTA, compact(
            'venta', 'empresa', 'documento', 'cliente', 'letras', 'centavos'
        ))->render();
    }
}
