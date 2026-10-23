<?php

namespace Tests\Unit\Support\FacturacionElectronica\CostaRica;

use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReferenciaComprobanteCrTest extends TestCase
{
    public function test_devolucion_es_06_aunque_la_factura_sea_de_otro_mes(): void
    {
        $codigo = ReferenciaComprobanteCr::codigoNota(
            ReferenciaComprobanteCr::MOTIVO_DEVOLUCION,
            new \DateTimeImmutable('2026-08-15 10:00:00', new \DateTimeZone('America/Costa_Rica')),
            new \DateTimeImmutable('2026-09-02 10:00:00', new \DateTimeZone('America/Costa_Rica')),
        );

        self::assertSame('06', $codigo);
    }

    public function test_anula_y_corrige_del_mismo_mes_siguen_en_01_y_02(): void
    {
        $agosto = new \DateTimeImmutable('2026-08-10 10:00:00', new \DateTimeZone('America/Costa_Rica'));
        $mismo = new \DateTimeImmutable('2026-08-28 10:00:00', new \DateTimeZone('America/Costa_Rica'));

        self::assertSame('01', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_ANULA, $agosto, $mismo));
        self::assertSame('02', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_CORRIGE_MONTO, $agosto, $mismo));
        self::assertSame('07', ReferenciaComprobanteCr::codigoSustitucion('01'));
    }

    public function test_error_material_de_otro_mes_usa_13_14_y_15(): void
    {
        $agosto = new \DateTimeImmutable('2026-08-31 22:00:00', new \DateTimeZone('America/Costa_Rica'));
        $setiembre = new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('America/Costa_Rica'));

        self::assertTrue(ReferenciaComprobanteCr::esPeriodoAnterior($agosto, $setiembre));
        self::assertSame('13', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_ANULA, $agosto, $setiembre));
        self::assertSame('14', ReferenciaComprobanteCr::codigoNota(ReferenciaComprobanteCr::MOTIVO_CORRIGE_MONTO, $agosto, $setiembre));
        self::assertSame('15', ReferenciaComprobanteCr::codigoSustitucion('13'));
    }

    public function test_exoneracion_posterior_es_12_sin_mirar_el_mes(): void
    {
        $codigo = ReferenciaComprobanteCr::codigoNota(
            ReferenciaComprobanteCr::MOTIVO_EXONERACION_POSTERIOR,
            new \DateTimeImmutable('2026-01-01', new \DateTimeZone('America/Costa_Rica')),
            new \DateTimeImmutable('2026-11-02', new \DateTimeZone('America/Costa_Rica')),
        );

        self::assertSame('12', $codigo);
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
