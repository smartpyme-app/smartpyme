<?php

namespace Tests\Unit\Services\Compras\DocumentoImport;

use App\Services\Compras\DocumentoImport\ElSalvadorJsonDocumentoParser;
use PHPUnit\Framework\TestCase;

class ElSalvadorJsonDocumentoParserTest extends TestCase
{
    private ElSalvadorJsonDocumentoParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ElSalvadorJsonDocumentoParser;
    }

    public function test_parse_json_dte_sv(): void
    {
        $json = json_encode([
            'identificacion' => [
                'fecEmi' => '2024-05-01',
                'tipoDte' => '03',
                'codigoGeneracion' => 'ABC-123',
                'numeroControl' => 'DTE-03-00001',
            ],
            'emisor' => [
                'nit' => '0614-010101-101-1',
                'nombre' => 'Proveedor Test',
                'telefono' => '2222-2222',
                'correo' => 'prov@test.com',
                'direccion' => ['complemento' => 'San Salvador'],
            ],
            'cuerpoDocumento' => [
                [
                    'numItem' => 1,
                    'codigo' => 'P001',
                    'descripcion' => 'Producto A',
                    'cantidad' => 2,
                    'precioUni' => 10,
                    'ventaGravada' => 20,
                ],
            ],
            'resumen' => [
                'subTotal' => 20,
                'totalPagar' => 22.6,
                'tributos' => [['codigo' => '20', 'valor' => 2.6]],
            ],
        ], JSON_THROW_ON_ERROR);

        $dto = $this->parser->parse($json);

        $this->assertSame('SV', $dto->pais);
        $this->assertSame('json', $dto->formatoOrigen);
        $this->assertSame('2024-05-01', $dto->identificacion['fechaEmision']);
        $this->assertCount(1, $dto->lineas);
        $mh = $dto->toMhCompatArray();
        $this->assertSame('ABC-123', $mh['identificacion']['codigoGeneracion']);
        $this->assertCount(1, $mh['cuerpoDocumento']);
    }
}
