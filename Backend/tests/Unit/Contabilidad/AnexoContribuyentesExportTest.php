<?php

namespace Tests\Unit\Contabilidad;

use App\Exports\Contabilidad\ElSalvador\AnexoContribuyentesExport;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\TestCase;

class AnexoContribuyentesExportTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::clearResolvedInstances();
        Auth::setFacadeApplication(null);
        Mockery::close();
        parent::tearDown();
    }

    public function test_map_usa_resumen_del_json_dte_aunque_la_bd_tenga_otro_reparto(): void
    {
        $this->autenticarConFacturacionElectronica();

        $venta = $this->ventaConDte([
            'sub_total' => 106.75,
            'iva' => 13,
            'exenta' => 0,
            'no_sujeta' => 0,
            'gravada' => 106.75,
            'total' => 100,
            'dte' => [
                'identificacion' => [
                    'fecEmi' => '2026-08-15',
                    'tipoDte' => '03',
                    'codigoGeneracion' => 'AAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
                    'numeroControl' => 'DTE-03-00000000-000000000000001',
                ],
                'receptor' => [
                    'nit' => '0614-010123-456-7',
                    'nrc' => '123456-7',
                    'nombre' => 'Cliente SA de CV',
                ],
                'resumen' => [
                    'totalNoSuj' => 1.25,
                    'totalExenta' => 5.5,
                    'totalGravada' => 100.0,
                    'totalNoGravado' => 0,
                    'montoTotalOperacion' => 119.75,
                    'totalPagar' => 119.75,
                    'tributos' => [
                        ['codigo' => '20', 'valor' => 13.0],
                    ],
                ],
                'sello' => 'SELLO-MH',
            ],
        ]);

        $fila = (new AnexoContribuyentesExport())->map($venta);

        $this->assertSame('15/08/2026', $fila[0]);
        $this->assertSame('03', $fila[2]);
        $this->assertSame('06140101234567', $fila[7]);
        $this->assertSame('Cliente SA de CV', $fila[8]);
        $this->assertSame('5.50', $fila[9]);
        $this->assertSame('1.25', $fila[10]);
        $this->assertSame('100.00', $fila[11]);
        $this->assertSame('13.00', $fila[12]);
        $this->assertSame('119.75', $fila[15]);
    }

    public function test_unir_documentos_no_duplica_ventas(): void
    {
        $export = new AnexoContribuyentesExport();
        $metodo = new \ReflectionMethod($export, 'unirDocumentos');
        $metodo->setAccessible(true);

        $ventas = collect([
            (object) ['fecha' => '2026-08-01', 'correlativo' => '1'],
            (object) ['fecha' => '2026-08-02', 'correlativo' => '2'],
        ]);
        $devoluciones = collect([
            (object) ['fecha' => '2026-08-03', 'correlativo' => '3'],
        ]);

        $unidas = $metodo->invoke($export, $ventas, $devoluciones);

        $this->assertCount(3, $unidas);
        $this->assertSame(['1', '2', '3'], $unidas->pluck('correlativo')->values()->all());
    }

    private function autenticarConFacturacionElectronica(): void
    {
        $auth = Mockery::mock();
        $usuario = Mockery::mock();
        $empresa = Mockery::mock();
        $auth->shouldReceive('user')->andReturn($usuario);
        $usuario->shouldReceive('empresa')->andReturn($empresa);
        $empresa->shouldReceive('first')->andReturn((object) ['facturacion_electronica' => true]);

        $app = new Container();
        $app->instance('auth', $auth);
        Auth::setFacadeApplication($app);
    }

    private function ventaConDte(array $attrs): object
    {
        return (object) array_merge([
            'fecha' => '2026-08-20',
            'sello_mh' => 'SELLO-MH',
            'correlativo' => '10',
            'nombre_cliente' => 'Nombre local distinto',
            'cuenta_a_terceros' => 0,
            'tipo_operacion' => 'Gravada',
            'tipo_renta' => 'Actividades Comerciales',
            'documento' => (object) ['nombre' => 'Crédito fiscal'],
            'cliente' => (object) ['ncr' => '999999-9', 'nit' => '00000000000000'],
        ], $attrs);
    }
}
