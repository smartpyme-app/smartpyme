<?php

namespace Tests\Unit\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\MHDTEController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MHDTEControllerReporteDteTest extends TestCase
{
    private function registroConOriginalEInvalidacion(): object
    {
        return (object) [
            'dte' => [
                'identificacion' => [
                    'codigoGeneracion' => 'ORIGINAL-UUID',
                    'tipoDte' => '01',
                ],
            ],
            'dte_invalidacion' => [
                'identificacion' => [
                    'codigoGeneracion' => 'INVALIDACION-UUID',
                ],
                'documento' => [
                    'nombre' => 'Cliente',
                ],
            ],
        ];
    }

    public function test_sin_documento_anulado_devuelve_dte_original_aunque_exista_invalidacion(): void
    {
        [$dte, $esAnulado] = MHDTEController::dteParaReporte($this->registroConOriginalEInvalidacion(), null);

        $this->assertFalse($esAnulado);
        $this->assertSame('ORIGINAL-UUID', $dte['identificacion']['codigoGeneracion']);
    }

    public function test_con_documento_anulado_devuelve_dte_de_invalidacion(): void
    {
        [$dte, $esAnulado] = MHDTEController::dteParaReporte($this->registroConOriginalEInvalidacion(), 'anulado');

        $this->assertTrue($esAnulado);
        $this->assertSame('INVALIDACION-UUID', $dte['identificacion']['codigoGeneracion']);
    }

    public function test_con_documento_anulado_sin_invalidacion_indica_anulado_sin_payload(): void
    {
        $registro = (object) [
            'dte' => ['identificacion' => ['codigoGeneracion' => 'ORIGINAL-UUID']],
            'dte_invalidacion' => null,
        ];

        [$dte, $esAnulado] = MHDTEController::dteParaReporte($registro, 'anulado');

        $this->assertTrue($esAnulado);
        $this->assertNull($dte);
    }

    public function test_generar_dte_pdf_elige_payload_segun_query_documento(): void
    {
        $source = $this->methodSource(MHDTEController::class, 'generarDTEPDF');

        $this->assertNotFalse(
            strpos($source, "dteParaReporte"),
            'generarDTEPDF debe elegir el DTE con dteParaReporte'
        );
        $this->assertNotFalse(
            strpos($source, "input('documento')"),
            'generarDTEPDF debe leer el query documento'
        );
        $this->assertFalse(
            (bool) preg_match('/if\s*\(\s*\$registro->dte_invalidacion\s*\)/', $source),
            'generarDTEPDF no debe sustituir el PDF original solo porque exista invalidación'
        );
    }

    public function test_generar_dte_json_elige_payload_segun_query_documento(): void
    {
        $source = $this->methodSource(MHDTEController::class, 'generarDTEJSON');

        $this->assertNotFalse(
            strpos($source, "dteParaReporte"),
            'generarDTEJSON debe elegir el DTE con dteParaReporte'
        );
        $this->assertNotFalse(
            strpos($source, "input('documento')"),
            'generarDTEJSON debe leer el query documento'
        );
        $this->assertFalse(
            (bool) preg_match('/if\s*\(\s*\$registro->dte_invalidacion\s*\)/', $source),
            'generarDTEJSON no debe devolver la invalidación salvo que se pida explícitamente'
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $file = file($ref->getFileName());
        $start = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;

        return implode('', array_slice($file, $start, $length));
    }
}
