<?php

namespace Tests\Unit\Support\FacturacionElectronica\CostaRica;

use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReferenciaComprobanteCrTest extends TestCase
{
    public function test_cada_motivo_tiene_su_codigo_de_referencia(): void
    {
        $esperados = [
            ReferenciaComprobanteCr::MOTIVO_ANULA => '01',
            ReferenciaComprobanteCr::MOTIVO_CORRIGE_MONTO => '02',
            ReferenciaComprobanteCr::MOTIVO_DEVOLUCION => '06',
            ReferenciaComprobanteCr::MOTIVO_EXONERACION_POSTERIOR => '12',
            ReferenciaComprobanteCr::MOTIVO_ANULA_ERROR_MATERIAL => '13',
            ReferenciaComprobanteCr::MOTIVO_CORRIGE_MONTO_ERROR_MATERIAL => '14',
        ];

        foreach ($esperados as $motivo => $codigo) {
            self::assertSame($codigo, ReferenciaComprobanteCr::codigoNota($motivo), $motivo);
        }
        self::assertEqualsCanonicalizing(array_keys($esperados), ReferenciaComprobanteCr::MOTIVOS_NOTA_CREDITO);
    }

    public function test_motivo_desconocido_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReferenciaComprobanteCr::codigoNota('otro');
    }

    public function test_despues_de_anular_con_13_el_comprobante_nuevo_usa_15(): void
    {
        self::assertSame('15', ReferenciaComprobanteCr::codigoSustitucion('13'));
        self::assertSame('07', ReferenciaComprobanteCr::codigoSustitucion('01'));
    }

    public function test_rechazado_usa_tipo_10_y_codigo_16(): void
    {
        $ref = ReferenciaComprobanteCr::referenciaDeRechazado('506310126...');
        self::assertSame('10', $ref['document_type']);
        self::assertSame('16', $ref['referenced_code']);
        self::assertSame('506310126...', $ref['document_number']);
    }

    public function test_fecha_de_emision_sale_de_la_clave(): void
    {
        $clave = '506150826'.str_repeat('0', 41);

        self::assertSame('2026-08-15T00:00:00-06:00', ReferenciaComprobanteCr::fechaEmisionDesdeClave($clave));
        self::assertNull(ReferenciaComprobanteCr::fechaEmisionDesdeClave('506'));
        self::assertNull(ReferenciaComprobanteCr::fechaEmisionDesdeClave('506320126'.str_repeat('0', 41)));
    }

    public function test_referencia_de_rechazado_rechaza_clave_vacia(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReferenciaComprobanteCr::referenciaDeRechazado('   ');
    }

    public function test_recibo_de_pago_no_acepta_notas(): void
    {
        self::assertSame(['01', '04', '07', '08', '10', '20'], ReferenciaComprobanteCr::tiposPermitidosEnReciboPago());
        self::assertNotContains('02', ReferenciaComprobanteCr::tiposPermitidosEnReciboPago());
        self::assertNotContains('03', ReferenciaComprobanteCr::tiposPermitidosEnReciboPago());
    }
}
