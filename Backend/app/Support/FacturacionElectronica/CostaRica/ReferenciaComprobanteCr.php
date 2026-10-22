<?php

namespace App\Support\FacturacionElectronica\CostaRica;

use InvalidArgumentException;

final class ReferenciaComprobanteCr
{
    public const MOTIVO_DEVOLUCION = 'devolucion';

    public const MOTIVO_ANULA = 'anula';

    public const MOTIVO_CORRIGE_MONTO = 'corrige_monto';

    public const MOTIVO_EXONERACION_POSTERIOR = 'exoneracion_posterior';

    public const CODIGO_RECHAZADO = '16';

    public const CODIGO_PAGO_REP = '17';

    public const TIPO_RECHAZADO = '10';

    public const TIPO_EXPORTACION = '19';

    public const TIPO_RECIBO_PAGO = '20';

    public static function codigoNota(string $motivo, \DateTimeInterface $fechaOriginal, \DateTimeInterface $fechaNota): string
    {
        return match ($motivo) {
            self::MOTIVO_DEVOLUCION => '06',
            self::MOTIVO_EXONERACION_POSTERIOR => '12',
            self::MOTIVO_ANULA => self::esPeriodoAnterior($fechaOriginal, $fechaNota) ? '13' : '01',
            self::MOTIVO_CORRIGE_MONTO => self::esPeriodoAnterior($fechaOriginal, $fechaNota) ? '14' : '02',
            default => throw new InvalidArgumentException("Motivo de nota desconocido: {$motivo}"),
        };
    }

    public static function codigoSustitucion(string $codigoNotaQueAnulo): string
    {
        return match ($codigoNotaQueAnulo) {
            '01' => '07',
            '13' => '15',
            default => throw new InvalidArgumentException("Código de nota no sustituible: {$codigoNotaQueAnulo}"),
        };
    }

    /**
     * Referencia de un comprobante que Hacienda rechazó (tipo 10, código 16).
     *
     * @return array{document_type: string, referenced_code: string, document_number: string}
     */
    public static function referenciaDeRechazado(string $claveRechazada): array
    {
        $clave = trim($claveRechazada);
        if ($clave === '') {
            throw new InvalidArgumentException('La clave del comprobante rechazado es obligatoria.');
        }

        return [
            'document_type' => self::TIPO_RECHAZADO,
            'referenced_code' => self::CODIGO_RECHAZADO,
            'document_number' => $clave,
        ];
    }

    public static function esPeriodoAnterior(\DateTimeInterface $fechaOriginal, \DateTimeInterface $fechaNota): bool
    {
        return self::periodoEnCostaRica($fechaOriginal) < self::periodoEnCostaRica($fechaNota);
    }

    /**
     * @return list<string>
     */
    public static function tiposPermitidosEnReciboPago(): array
    {
        return ['01', '04', '07', '08', '10', '20'];
    }

    private static function periodoEnCostaRica(\DateTimeInterface $fecha): string
    {
        return \DateTimeImmutable::createFromInterface($fecha)
            ->setTimezone(new \DateTimeZone('America/Costa_Rica'))
            ->format('Y-m');
    }
}
