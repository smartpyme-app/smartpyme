<?php

namespace Tests\Unit\MH;

use App\Models\MH\MHEventoRetorno;
use PHPUnit\Framework\TestCase;

class EventoRetornoTest extends TestCase
{
    private function devolucion(array $dteOriginal, float $iva, float $ivaRete = 0, float $renta = 0): object
    {
        $empresa = (object) ['nit' => '0614-010101-101-1', 'nombre' => 'Empresa Demo', 'fe_ambiente' => '00'];
        $detalles = [
            (object) ['unidad' => 'Unidad', 'producto' => (object) ['tipo' => 'Producto'], 'precio' => 10, 'cantidad' => 2, 'descuento' => 0, 'codigo' => 'P1', 'nombre_producto' => 'Producto 1'],
            (object) ['unidad' => 'Unidad', 'producto' => (object) ['tipo' => 'Servicio'], 'precio' => 5, 'cantidad' => 1, 'descuento' => 0, 'codigo' => null, 'nombre_producto' => 'Servicio 1'],
        ];

        return new class($empresa, $dteOriginal, $detalles, $iva, $ivaRete, $renta) {
            public $tipo_dte;
            public $numero_control;
            public $codigo_generacion;
            public $fecha = '2026-12-05';
            public $created_at = '2026-12-05 10:00:00';
            public $venta;

            public function __construct(private $empresaObj, array $dte, public $detalles, public $iva, public $iva_retenido, public $renta_retenida)
            {
                $this->venta = (object) ['dte' => $dte];
            }

            public function empresa()
            {
                return new class($this->empresaObj) {
                    public function __construct(private $e) {}
                    public function first() { return $this->e; }
                };
            }

            public function save() {}
        };
    }

    private function generar(object $devolucion): array
    {
        $mh = new class extends MHEventoRetorno {
            protected function codigoUnidad($unidad) { return 59; }
        };

        return $mh->generarDTE($devolucion);
    }

    /** Verifica required y additionalProperties:false del esquema, recursivo (sin if/then). */
    private function assertCumpleEsquema(array $schema, $data, string $ruta = '$'): void
    {
        if (isset($schema['properties']) && is_array($data) && !array_is_list($data)) {
            foreach ($schema['required'] ?? [] as $req) {
                $this->assertArrayHasKey($req, $data, "Falta $ruta.$req");
            }
            if (($schema['additionalProperties'] ?? true) === false) {
                foreach (array_keys($data) as $k) {
                    $this->assertArrayHasKey($k, $schema['properties'], "Campo no permitido $ruta.$k");
                }
            }
            foreach ($data as $k => $v) {
                if (isset($schema['properties'][$k])) {
                    $this->assertCumpleEsquema($schema['properties'][$k], $v, "$ruta.$k");
                }
            }
        }
        if (isset($schema['items']) && is_array($data) && array_is_list($data)) {
            foreach ($data as $i => $item) {
                $this->assertCumpleEsquema($schema['items'], $item, "{$ruta}[$i]");
            }
        }
    }

    private function esquema(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../../Fixtures/MH/fe-eret-v1.json'), true);
    }

    public function test_retorno_de_factura_cumple_esquema_e_incluye_iva(): void
    {
        $original = [
            'identificacion' => ['tipoDte' => '01', 'codigoGeneracion' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', 'fecEmi' => '2026-12-01', 'numeroControl' => 'DTE-01-M001P001-000000000000001'],
            'emisor' => [],
            'receptor' => ['tipoDocumento' => '13', 'numDocumento' => '012345678', 'nombre' => 'Cliente', 'telefono' => null, 'correo' => null],
        ];

        $dte = json_decode(json_encode($this->generar($this->devolucion($original, 3.25, 0.25))), true);

        $this->assertCumpleEsquema($this->esquema(), $dte);
        $this->assertSame('18', $dte['identificacion']['tipoEvento']);
        $this->assertSame('M001', $dte['emisor']['codEstableMH']);
        $this->assertSame('P001', $dte['emisor']['codPuntoVentaMH']);
        // Precios con IVA incluido: 2 × 11.30 + 1 × 5.65.
        $this->assertSame(28.25, $dte['resumen']['totalGravada']);
        $this->assertSame(3.25, $dte['resumen']['totalIva']);
        $this->assertSame(0.25, round(array_sum(array_column($dte['cuerpoDocumento'], 'ivaRete')), 2));
        $this->assertSame(28.0, (float) $dte['resumen']['totalPagar']);
    }

    public function test_retorno_lleva_tributos_no_iva_proporcionales_al_original(): void
    {
        $original = [
            'identificacion' => ['tipoDte' => '01', 'codigoGeneracion' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', 'fecEmi' => '2026-12-01', 'numeroControl' => 'DTE-01-M001P001-000000000000001'],
            'emisor' => [],
            'receptor' => ['tipoDocumento' => null, 'numDocumento' => null, 'nombre' => 'Clientes Varios', 'telefono' => null, 'correo' => null],
            'cuerpoDocumento' => [['descripcion' => 'Producto 1', 'tributos' => ['59']], ['descripcion' => 'Servicio 1', 'tributos' => null]],
            'resumen' => ['subTotalVentas' => 56.50, 'tributos' => [['codigo' => '59', 'descripcion' => 'Turismo 5%', 'valor' => 2.50]]],
        ];

        $dte = json_decode(json_encode($this->generar($this->devolucion($original, 3.25))), true);

        $this->assertCumpleEsquema($this->esquema(), $dte);
        $this->assertSame(['59'], $dte['cuerpoDocumento'][0]['tributos']);
        $this->assertNull($dte['cuerpoDocumento'][1]['tributos']);
        // Retorno de 28.25 sobre 56.50 = mitad del tributo original.
        $this->assertSame(1.25, (float) $dte['resumen']['tributos'][0]['valor']);
        $this->assertSame(29.5, (float) $dte['resumen']['montoTotalOperacion']);
        $this->assertSame(29.5, (float) $dte['resumen']['totalPagar']);
    }

    public function test_retorno_de_compra_sujeto_excluido_usa_compra_y_renta_proporcional(): void
    {
        $original = [
            'identificacion' => ['tipoDte' => '14', 'codigoGeneracion' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', 'fecEmi' => '2026-12-01', 'numeroControl' => 'DTE-14-M001P001-000000000000001'],
            'emisor' => [],
            'receptor' => ['tipoDocumento' => '13', 'numDocumento' => '012345678', 'nombre' => 'Proveedor SE', 'telefono' => null, 'correo' => null],
            'resumen' => ['totalCompra' => 100.0, 'reteRenta' => 10.0],
        ];
        $empresa = (object) ['nit' => '06140101011011', 'nombre' => 'Empresa Demo', 'fe_ambiente' => '00'];
        $devolucion = new class($empresa, $original) {
            public $tipo_dte;
            public $codigo_generacion;
            public $fecha = '2026-12-05';
            public $created_at = '2026-12-05 10:00:00';
            public $compra;
            public $detalles;

            public function __construct(private $empresaObj, array $dte)
            {
                $this->compra = (object) ['dte' => $dte, 'iva' => 0];
                $this->detalles = [(object) ['costo' => 25, 'cantidad' => 2, 'descuento' => 0, 'nombre_producto' => 'Insumo', 'producto' => (object) ['tipo' => 'Producto']]];
            }

            public function empresa()
            {
                return new class($this->empresaObj) {
                    public function __construct(private $e) {}
                    public function first() { return $this->e; }
                };
            }

            public function save() {}
        };

        $dte = json_decode(json_encode((new \App\Models\MH\MHEventoRetornoCompra)->generarDTE($devolucion)), true);

        $this->assertCumpleEsquema($this->esquema(), $dte);
        $this->assertSame('14', $dte['documentoRelacionado'][0]['tipoDocumento']);
        $this->assertSame(50.0, (float) $dte['cuerpoDocumento'][0]['compra']);
        $this->assertSame(50.0, (float) $dte['resumen']['totalCompraExcluidos']);
        // Mitad de la compra: mitad de la renta retenida original.
        $this->assertSame(5.0, (float) $dte['resumen']['reteRenta']);
        $this->assertSame(45.0, (float) $dte['resumen']['totalPagar']);
        $this->assertSame('Proveedor SE', $dte['documento']['nombre']);
    }

    public function test_retorno_de_exportacion_no_lleva_iva_y_copia_datos_de_aduana(): void
    {
        $original = [
            'identificacion' => ['tipoDte' => '11', 'codigoGeneracion' => 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', 'fecEmi' => '2026-12-01', 'numeroControl' => 'DTE-11-M001P001-000000000000001'],
            'emisor' => ['tipoItemExpor' => 1, 'recintoFiscal' => '02', 'tipoRegimen' => 'EX-1', 'regimen' => '1000.000'],
            'receptor' => ['tipoDocumento' => '03', 'numDocumento' => 'X123', 'nombre' => 'Cliente Ext', 'codPais' => '9320', 'nombrePais' => 'Guatemala', 'telefono' => null, 'correo' => null],
        ];

        $dte = json_decode(json_encode($this->generar($this->devolucion($original, 0))), true);

        $this->assertCumpleEsquema($this->esquema(), $dte);
        $this->assertSame(25.0, (float) $dte['resumen']['totalGravada']);
        $this->assertSame(0.0, (float) $dte['resumen']['totalIva']);
        $this->assertSame('EX-1', $dte['emisor']['tipoRegimen']);
        $this->assertSame('9320', $dte['documento']['codPais']);
    }
}
