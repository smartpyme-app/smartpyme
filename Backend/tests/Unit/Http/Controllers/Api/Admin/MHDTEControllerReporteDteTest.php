<?php

namespace Tests\Unit\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\MHDTEController;
use App\Services\FacturacionElectronica\ElSalvador\ElSalvadorDteService;
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

    public function test_qr_de_pdf_anulado_usa_datos_del_dte_original(): void
    {
        $registro = $this->registroConOriginalEInvalidacion();
        $registro->dte['identificacion']['ambiente'] = '00';
        $registro->dte['identificacion']['fecEmi'] = '2026-08-01';

        MHDTEController::asignarQrConsultaPublica($registro, $registro->dte_invalidacion);

        $this->assertSame(
            'https://admin.factura.gob.sv/consultaPublica?ambiente=00&codGen=ORIGINAL-UUID&fechaEmi=2026-08-01',
            $registro->qr
        );
    }

    public function test_qr_de_pdf_anulado_usa_documento_si_no_hay_dte_original(): void
    {
        $registro = (object) [
            'dte' => null,
            'qr' => null,
        ];
        $dteAnulado = [
            'identificacion' => ['ambiente' => '01', 'codigoGeneracion' => 'INVALIDACION-UUID'],
            'documento' => [
                'codigoGeneracion' => 'ORIGINAL-UUID',
                'fecEmi' => '2026-07-15',
            ],
        ];

        MHDTEController::asignarQrConsultaPublica($registro, $dteAnulado);

        $this->assertSame(
            'https://admin.factura.gob.sv/consultaPublica?ambiente=01&codGen=ORIGINAL-UUID&fechaEmi=2026-07-15',
            $registro->qr
        );
    }

    public function test_generar_dte_pdf_asigna_qr_antes_de_renderizar_anulado(): void
    {
        $source = $this->methodSource(ElSalvadorDteService::class, 'generarDTEPDF');
        $anuladoView = strpos($source, 'DTE-Anulado');

        $this->assertNotFalse($anuladoView, 'generarDTEPDF debe renderizar DTE-Anulado');
        $this->assertNotFalse(
            strpos(substr($source, 0, $anuladoView), 'asignarQrConsultaPublica'),
            'El PDF anulado debe asignar el QR antes de renderizar DTE-Anulado'
        );
    }

    public function test_generar_dte_pdf_elige_payload_segun_query_documento(): void
    {
        $source = $this->methodSource(ElSalvadorDteService::class, 'generarDTEPDF');

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
        $source = $this->methodSource(ElSalvadorDteService::class, 'generarDTEJSON');

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
