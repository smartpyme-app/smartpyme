<?php

namespace Tests\Unit\Services;

use App\Services\Dte\DteParserService;
use PHPUnit\Framework\TestCase;

class DteParserServiceTest extends TestCase
{
    protected DteParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DteParserService();
    }

    public function testParseFromJsonThrowsOnInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->parser->parseFromJson('{ invalid json }');
    }

    public function testParseFromJsonExtractsRequiredFields(): void
    {
        $json = json_encode([
            'identificacion' => [
                'codigoGeneracion' => 'uuid-123',
                'tipoDte' => '01',
                'numeroControl' => '0001-00-00001234',
                'fecEmi' => '2025-01-15',
            ],
            'emisor' => [
                'nit' => '0614-123456-789-0',
                'nombre' => 'Proveedor S.A.',
            ],
            'receptor' => [
                'nit' => '0614-987654-321-0',
                'nombre' => 'Mi Empresa',
            ],
            'resumen' => [
                'totalPagar' => 115.00,
            ],
            'cuerpoDocumento' => [
                [
                    'descripcion' => 'Producto A',
                    'cantidad' => 1,
                    'precioUni' => 100,
                    'ventaTotal' => 115,
                ],
            ],
        ]);

        $result = $this->parser->parseFromJson($json);

        $this->assertSame('uuid-123', $result['dte_uuid']);
        $this->assertSame('01', $result['dte_type']);
        $this->assertSame('0001-00-00001234', $result['dte_number']);
        $this->assertSame('2025-01-15', $result['emission_date']);
        $this->assertSame(115.0, $result['total_amount']);
        $this->assertSame('0614-123456-789-0', $result['issuer_nit']);
        $this->assertSame('Proveedor S.A.', $result['issuer_name']);
        $this->assertSame('0614-987654-321-0', $result['receiver_nit']);
        $this->assertSame('Mi Empresa', $result['receiver_name']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Producto A', $result['items'][0]['descripcion']);
        $this->assertSame(1.0, $result['items'][0]['cantidad']);
        $this->assertSame(100.0, $result['items'][0]['precioUni']);
        $this->assertSame(115.0, $result['items'][0]['ventaTotal']);
    }

    public function testParseFromJsonUsesMontoTotalOperacionWhenTotalPagarMissing(): void
    {
        $json = json_encode([
            'identificacion' => ['codigoGeneracion' => 'uuid', 'tipoDte' => '01', 'numeroControl' => 'X', 'fecEmi' => '2025-01-01'],
            'emisor' => ['nit' => '123', 'nombre' => 'E'],
            'receptor' => ['nit' => '456', 'nombre' => 'R'],
            'resumen' => ['montoTotalOperacion' => 250.50],
            'cuerpoDocumento' => [],
        ]);

        $result = $this->parser->parseFromJson($json);

        $this->assertSame(250.5, $result['total_amount']);
    }

    public function testParseFromJsonPadsDteType(): void
    {
        $json = json_encode([
            'identificacion' => ['codigoGeneracion' => 'uuid', 'tipoDte' => 3, 'numeroControl' => 'X', 'fecEmi' => '2025-01-01'],
            'emisor' => ['nit' => '123', 'nombre' => 'E'],
            'receptor' => [],
            'resumen' => [],
            'cuerpoDocumento' => [],
        ]);

        $result = $this->parser->parseFromJson($json);

        $this->assertSame('03', $result['dte_type']);
    }

    public function testParseFromJsonHandlesEmptyCuerpoDocumento(): void
    {
        $json = json_encode([
            'identificacion' => ['codigoGeneracion' => 'uuid', 'tipoDte' => '01', 'numeroControl' => 'X', 'fecEmi' => '2025-01-01'],
            'emisor' => ['nit' => '123', 'nombre' => 'E'],
            'receptor' => [],
            'resumen' => [],
            'cuerpoDocumento' => [],
        ]);

        $result = $this->parser->parseFromJson($json);

        $this->assertSame([], $result['items']);
    }

    public function testValidateStructureValid(): void
    {
        $dteData = [
            'dte_uuid' => 'uuid',
            'dte_type' => '01',
            'issuer_nit' => '123',
            'emission_date' => '2025-01-01',
        ];

        $result = $this->parser->validateStructure($dteData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateStructureMissingDteUuid(): void
    {
        $dteData = [
            'dte_uuid' => '',
            'dte_type' => '01',
            'issuer_nit' => '123',
            'emission_date' => '2025-01-01',
        ];

        $result = $this->parser->validateStructure($dteData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Falta codigoGeneracion (dte_uuid)', $result['errors']);
    }

    public function testValidateStructureMissingIssuerNit(): void
    {
        $dteData = [
            'dte_uuid' => 'uuid',
            'dte_type' => '01',
            'issuer_nit' => '',
            'emission_date' => '2025-01-01',
        ];

        $result = $this->parser->validateStructure($dteData);

        $this->assertFalse($result['valid']);
        $this->assertContains('Falta NIT del emisor', $result['errors']);
    }

    public function testValidateStructureMultipleErrors(): void
    {
        $dteData = [
            'dte_uuid' => '',
            'dte_type' => '',
            'issuer_nit' => '',
            'emission_date' => '',
        ];

        $result = $this->parser->validateStructure($dteData);

        $this->assertFalse($result['valid']);
        $this->assertCount(4, $result['errors']);
    }
}
