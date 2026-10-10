<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\MigrateDteToS3Command;
use PHPUnit\Framework\TestCase;

final class MigrateDteToS3CommandTest extends TestCase
{
    public function test_dte_json_de_el_salvador_usa_extension_y_content_type_json(): void
    {
        $this->assertSame(
            ['ext' => 'json', 'contentType' => 'application/json'],
            $this->command()->format('{"identificacion":{"codigoGeneracion":"ABC"}}')
        );
    }

    public function test_dte_xml_de_costa_rica_usa_extension_y_content_type_xml(): void
    {
        $xml = '<?xml version="1.0"?><FacturaElectronica></FacturaElectronica>';

        $this->assertSame(
            ['ext' => 'xml', 'contentType' => 'application/xml'],
            $this->command()->format($xml)
        );
    }

    public function test_xml_sin_declaracion_tambien_se_guarda_como_xml(): void
    {
        $this->assertSame(
            'xml',
            $this->command()->format('<TiqueteElectronico></TiqueteElectronico>')['ext']
        );
    }

    public function test_clave_s3_de_venta_json_termina_en_json(): void
    {
        $key = $this->command()->objectKey(
            'ventas',
            $this->row(88421, 12, '2026-02-15'),
            'dte',
            '{"identificacion":{}}'
        );

        $this->assertSame(
            'ventas/12-test/2026/02/registro-88421-documento.json',
            $key
        );
    }

    public function test_clave_s3_de_venta_xml_termina_en_xml(): void
    {
        $key = $this->command()->objectKey(
            'ventas',
            $this->row(88421, 12, '2026-02-15'),
            'dte',
            '<?xml version="1.0"?><FacturaElectronica></FacturaElectronica>'
        );

        $this->assertSame(
            'ventas/12-test/2026/02/registro-88421-documento.xml',
            $key
        );
    }

    private function command(): object
    {
        return new class extends MigrateDteToS3Command {
            public function format(string $bytes): array
            {
                return $this->dteS3ObjectFormat($bytes);
            }

            public function objectKey(string $table, $row, string $column, string $bytes): string
            {
                return $this->buildObjectKey($table, $row, $column, $this->dteS3ObjectFormat($bytes)['ext']);
            }

            protected function empresaPathSegment(int $idEmpresa): string
            {
                return $idEmpresa.'-test';
            }
        };
    }

    private function row(int $id, int $idEmpresa, string $fecha): object
    {
        return new class($id, $idEmpresa, $fecha) {
            public function __construct(
                public int $id,
                private int $idEmpresa,
                private string $fecha
            ) {
            }

            public function getAttribute(string $key): mixed
            {
                return match ($key) {
                    'id_empresa' => $this->idEmpresa,
                    'fecha' => $this->fecha,
                    default => null,
                };
            }
        };
    }
}
