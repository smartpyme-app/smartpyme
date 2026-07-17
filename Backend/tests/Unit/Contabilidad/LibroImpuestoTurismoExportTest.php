<?php

namespace Tests\Unit\Contabilidad;

use App\Exports\Contabilidad\ElSalvador\LibroImpuestoTurismoExport;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\TestCase;

class LibroImpuestoTurismoExportTest extends TestCase
{
    public function test_mapea_solo_impuestos_cinco_por_ciento_que_no_son_iva(): void
    {
        $venta = (object) [
            'fecha' => '2026-07-15',
            'numero_control' => 'DTE-001',
            'correlativo' => '15',
            'nombre_cliente' => 'Cliente turismo',
            'sub_total' => 100,
            'impuestos' => collect([
                (object) [
                    'id_impuesto' => 8,
                    'monto' => 5,
                    'impuesto' => (object) ['porcentaje' => 5, 'codigo_mh' => '59'],
                ],
                (object) [
                    'id_impuesto' => 9,
                    'monto' => 4,
                    'impuesto' => (object) ['porcentaje' => 5, 'codigo_mh' => '20'],
                ],
                (object) [
                    'id_impuesto' => 10,
                    'monto' => 2,
                    'impuesto' => (object) ['porcentaje' => 2, 'codigo_mh' => 'C8'],
                ],
                (object) [
                    'id_impuesto' => 11,
                    'monto' => 0,
                    'impuesto' => (object) ['porcentaje' => 5, 'codigo_mh' => '59'],
                ],
                (object) [
                    'id_impuesto' => 12,
                    'monto' => -3,
                    'impuesto' => (object) ['porcentaje' => 5, 'codigo_mh' => '59'],
                ],
            ]),
        ];

        $export = new LibroImpuestoTurismoExport();

        $this->assertSame([
            '2026-07-15',
            'DTE-001',
            'Cliente turismo',
            100.0,
            5.0,
        ], $export->map($venta));
    }

    public function test_respeta_el_filtro_de_impuesto_en_el_mapeo(): void
    {
        $venta = (object) [
            'fecha' => '2026-07-15',
            'numero_control' => null,
            'correlativo' => '15',
            'nombre_cliente' => 'Cliente turismo',
            'sub_total' => 200,
            'impuestos' => collect([
                (object) [
                    'id_impuesto' => 8,
                    'monto' => 5,
                    'impuesto' => (object) ['porcentaje' => 5, 'codigo_mh' => '59'],
                ],
                (object) [
                    'id_impuesto' => 11,
                    'monto' => 7,
                    'impuesto' => (object) ['porcentaje' => 5, 'codigo_mh' => null],
                ],
            ]),
        ];
        $request = new \Illuminate\Http\Request(['id_impuesto' => 11]);
        $export = new LibroImpuestoTurismoExport();
        $export->filter($request);

        $this->assertSame(7.0, $export->map($venta)[4]);
    }

    public function test_excluye_ventas_sin_sello_cuando_la_empresa_tiene_facturacion_electronica(): void
    {
        $export = new LibroImpuestoTurismoExport();

        $this->assertTrue(
            method_exists($export, 'filtrarVentasPorFacturacionElectronica'),
            'El export debe filtrar ventas según facturación electrónica.'
        );

        $auth = Mockery::mock();
        $usuario = Mockery::mock();
        $empresa = Mockery::mock();
        $auth->shouldReceive('user')->andReturn($usuario);
        $usuario->shouldReceive('empresa')->andReturn($empresa);
        $empresa->shouldReceive('first')->andReturn((object) ['facturacion_electronica' => true]);
        $log = Mockery::mock();
        $log->shouldReceive('warning')->once();

        $app = new Container();
        $app->instance('auth', $auth);
        $app->instance('log', $log);
        Auth::setFacadeApplication($app);

        try {
            $metodo = new \ReflectionMethod($export, 'filtrarVentasPorFacturacionElectronica');
            $metodo->setAccessible(true);

            $ventas = collect([
                (object) ['id' => 1, 'sello_mh' => null],
                (object) ['id' => 2, 'sello_mh' => 'SELLO-MH'],
            ]);

            $filtradas = $metodo->invoke($export, $ventas);

            $this->assertSame([2], $filtradas->pluck('id')->values()->all());
        } finally {
            Auth::clearResolvedInstances();
            Auth::setFacadeApplication(null);
            Mockery::close();
        }
    }
}
