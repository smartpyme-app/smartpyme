<?php

namespace Tests\Feature\Ventas;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class FacturaInversionesPadillaViewTest extends TestCase
{
    public function test_renderiza_rtn_emisor_desde_nit_arriba_de_telefono(): void
    {
        [$venta, $empresa, $cliente, $documento, $dolares, $centavos] = $this->crearDatosDePrueba([
            'nit' => '05019013561871',
            'ncr' => null,
            'telefono' => '9999-1111',
            'direccion' => 'Col. Los Castaños, SPS',
            'correo' => 'info@padilla.hn',
        ]);

        $html = view(
            'reportes.facturacion.formatos_empresas.Factura-Inversiones-Padilla',
            compact('venta', 'empresa', 'cliente', 'documento', 'dolares', 'centavos')
        )->render();

        $this->assertStringContainsString('RTN# 05019013561871', $html);
        $this->assertStringContainsString('9999-1111', $html);

        $posRtn = strpos($html, 'RTN# 05019013561871');
        $posTelefono = strpos($html, '9999-1111');
        $this->assertNotFalse($posRtn);
        $this->assertNotFalse($posTelefono);
        $this->assertTrue($posRtn < $posTelefono, 'El RTN debe aparecer arriba del teléfono en la cabecera');
    }

    public function test_renderiza_rtn_emisor_desde_ncr_cuando_nit_esta_vacio(): void
    {
        [$venta, $empresa, $cliente, $documento, $dolares, $centavos] = $this->crearDatosDePrueba([
            'nit' => '',
            'ncr' => '05019013561871',
            'telefono' => '9999-1111',
            'direccion' => 'Col. Los Castaños, SPS',
            'correo' => 'info@padilla.hn',
        ]);

        $html = view(
            'reportes.facturacion.formatos_empresas.Factura-Inversiones-Padilla',
            compact('venta', 'empresa', 'cliente', 'documento', 'dolares', 'centavos')
        )->render();

        $this->assertStringContainsString('RTN# 05019013561871', $html);
    }

    private function crearDatosDePrueba(array $empresaDatos): array
    {
        $empresa = new Empresa(array_merge([
            'nombre' => 'Inversiones Padilla',
            'iva' => 15,
        ], $empresaDatos));

        $cliente = new Cliente([
            'tipo' => 'Persona',
            'nombre' => 'Juan Perez',
            'nit' => '08011990123456',
            'telefono' => '8888-2222',
            'correo' => 'juan@example.test',
        ]);

        $documento = new Documento([
            'nombre' => 'Factura',
            'prefijo' => '000-001-01-',
            'numero_autorizacion' => '000-001-01-00000001 A 000-001-01-00005000',
            'resolucion' => 'CAI-TEST-12345',
            'fecha' => '2027-12-31',
        ]);

        $venta = new Venta([
            'fecha' => '2026-08-20',
            'correlativo' => 123,
            'nombre_documento' => 'Factura',
            'nombre_cliente' => 'Juan Perez',
            'nombre_vendedor' => 'Carlos Martinez',
            'total' => 100,
            'sub_total' => 86.96,
            'iva' => 13.04,
        ]);

        $venta->setRelation('documento', $documento);
        $venta->setRelation('empresa', $empresa);
        $venta->setRelation('sucursal', null);
        $venta->setRelation('vendedor', null);
        $venta->setRelation('cliente', $cliente);
        $venta->setRelation('detalles', new Collection([
            new Detalle([
                'descripcion' => 'Item de prueba',
                'cantidad' => 1,
                'precio' => 100,
                'gravada' => 86.96,
                'total' => 100,
                'iva' => 13.04,
                'porcentaje_impuesto' => 15,
                'tipo_gravado' => 'gravada',
            ]),
        ]));

        return [$venta, $empresa, $cliente, $documento, 'CIEN', '00'];
    }
}
