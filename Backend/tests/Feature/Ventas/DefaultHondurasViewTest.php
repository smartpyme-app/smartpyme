<?php

namespace Tests\Feature\Ventas;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class DefaultHondurasViewTest extends TestCase
{
    public function test_renderiza_documento_hondureno_con_datos_fiscales_y_footer_escapado(): void
    {
        [$venta, $empresa, $cliente, $documento, $dolares, $centavos] = $this->datosDeVista([
            'nombre' => 'Factura sin RTN',
            'numero_emision' => '01',
            'nota' => "Primera línea\n<script>alert('x')</script>",
            'resolucion' => 'CAI-123',
            'rangos' => '001-001-01-00000001 A 001-001-01-00003000',
            'fecha' => '2027-05-23',
        ]);

        $html = view(
            'reportes.facturacion.formatos_pais.default-honduras',
            compact('venta', 'empresa', 'cliente', 'documento', 'dolares', 'centavos')
        )->render();

        $this->assertStringContainsString('FACTURA SIN RTN', $html);
        $this->assertStringContainsString('001-001-01-00000439', $html);
        $this->assertStringContainsString('Producto de prueba', $html);
        $this->assertStringContainsString('Importe Gravado 15%', $html);
        $this->assertStringContainsString('100.00', $html);
        $this->assertStringContainsString('Importe Gravado 18%', $html);
        $this->assertStringContainsString('200.00', $html);
        $this->assertStringContainsString('ISV 15%', $html);
        $this->assertStringContainsString('15.00', $html);
        $this->assertStringContainsString('ISV 18%', $html);
        $this->assertStringContainsString('36.00', $html);
        $this->assertStringContainsString('CUATROCIENTOS VEINTISÉIS CON 00/100 LEMPIRAS', $html);
        $this->assertStringContainsString('CAI-123', $html);
        $this->assertStringContainsString('RANGO AUTORIZADO', $html);
        $this->assertStringContainsString('23/05/2027', $html);
        $this->assertStringContainsString('Primera línea<br />', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
    }

    public function test_omite_rotulos_del_footer_cuando_no_hay_valores_configurados(): void
    {
        [$venta, $empresa, $cliente, $documento, $dolares, $centavos] = $this->datosDeVista([
            'nombre' => 'Nota de débito',
            'numero_emision' => '02',
            'nota' => '',
            'resolucion' => '',
            'rangos' => '',
            'fecha' => null,
        ]);

        $html = view(
            'reportes.facturacion.formatos_pais.default-honduras',
            compact('venta', 'empresa', 'cliente', 'documento', 'dolares', 'centavos')
        )->render();

        $this->assertStringContainsString('NOTA DE DÉBITO', $html);
        $this->assertStringNotContainsString('CAI:', $html);
        $this->assertStringNotContainsString('RANGO AUTORIZADO:', $html);
        $this->assertStringNotContainsString('FECHA LÍMITE DE EMISIÓN:', $html);
    }

    private function datosDeVista(array $documentoDatos): array
    {
        $empresa = new Empresa([
            'nombre' => 'Empresa Honduras',
            'nit' => '0801-1999-123456',
            'direccion' => 'Tegucigalpa, Honduras',
            'telefono' => '2222-3333',
            'correo' => 'facturacion@example.test',
            'iva' => 15,
        ]);
        $cliente = new Cliente([
            'tipo' => 'Empresa',
            'nombre_empresa' => 'Cliente de Prueba',
            'nit' => '0801-2000-654321',
            'empresa_direccion' => 'San Pedro Sula',
            'empresa_telefono' => '9999-0000',
            'ncr' => 'CONST-EXON-9',
        ]);
        $documento = new Documento($documentoDatos);
        $venta = new Venta([
            'fecha' => '2026-08-05',
            'correlativo' => 439,
            'condicion' => 'Contado',
            'forma_pago' => 'Efectivo',
            'num_orden_exento' => 'OC-EXENTA-7',
            'sub_total' => 375,
            'descuento' => 5,
            'iva' => 51,
            'total' => 426,
        ]);
        $venta->setAttribute('registro_sag', 'SAG-88');
        $venta->setRelation('detalles', new Collection([
            new Detalle([
                'descripcion' => 'Producto de prueba',
                'cantidad' => 1,
                'precio' => 115,
                'descuento' => 5,
                'gravada' => 100,
                'total' => 115,
                'iva' => 15,
                'porcentaje_impuesto' => 15,
                'tipo_gravado' => 'gravada',
            ]),
            new Detalle([
                'descripcion' => 'Servicio especial',
                'cantidad' => 1,
                'precio' => 236,
                'gravada' => 200,
                'total' => 236,
                'iva' => 36,
                'porcentaje_impuesto' => 18,
                'tipo_gravado' => 'gravada',
            ]),
            new Detalle([
                'descripcion' => 'Artículo exento',
                'cantidad' => 1,
                'precio' => 50,
                'exenta' => 50,
                'total' => 50,
                'porcentaje_impuesto' => 0,
                'tipo_gravado' => 'exenta',
            ]),
            new Detalle([
                'descripcion' => 'Artículo exonerado',
                'cantidad' => 1,
                'precio' => 25,
                'gravada' => 25,
                'total' => 25,
                'porcentaje_impuesto' => 15,
                'tipo_gravado' => 'exonerada',
            ]),
        ]));

        return [$venta, $empresa, $cliente, $documento, 'CUATROCIENTOS VEINTISÉIS', '00'];
    }
}
