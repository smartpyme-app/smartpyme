<?php

namespace App\Support\FacturacionElectronica\CostaRica;

use InvalidArgumentException;

final class ReferenciaComprobanteCr
{
    public const MOTIVO_DEVOLUCION = 'devolucion';

    public const MOTIVO_ANULA = 'anula';

    public const MOTIVO_ANULA_ERROR_MATERIAL = 'anula_error_material';

    public const MOTIVO_CORRIGE_MONTO = 'corrige_monto';

    public const MOTIVO_CORRIGE_MONTO_ERROR_MATERIAL = 'corrige_monto_error_material';

    public const MOTIVO_EXONERACION_POSTERIOR = 'exoneracion_posterior';

    public const MOTIVOS_NOTA_CREDITO = [
        self::MOTIVO_DEVOLUCION,
        self::MOTIVO_ANULA,
        self::MOTIVO_ANULA_ERROR_MATERIAL,
        self::MOTIVO_CORRIGE_MONTO,
        self::MOTIVO_CORRIGE_MONTO_ERROR_MATERIAL,
        self::MOTIVO_EXONERACION_POSTERIOR,
    ];

    public const CODIGO_RECHAZADO = '16';

    public const CODIGO_PAGO_REP = '17';

    public const TIPO_RECHAZADO = '10';

    public const TIPO_EXPORTACION = '19';

    public const TIPO_RECIBO_PAGO = '20';

    /**
     * Nota 9, anexos 4.4: 13 y 14 se eligen por error material, no por la fecha de la factura.
     */
    public static function codigoNota(string $motivo): string
    {
        return match ($motivo) {
            self::MOTIVO_ANULA => '01',
            self::MOTIVO_CORRIGE_MONTO => '02',
            self::MOTIVO_DEVOLUCION => '06',
            self::MOTIVO_EXONERACION_POSTERIOR => '12',
            self::MOTIVO_ANULA_ERROR_MATERIAL => '13',
            self::MOTIVO_CORRIGE_MONTO_ERROR_MATERIAL => '14',
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

    /**
     * Fecha de emisión embebida en la clave: 3 caracteres de país y luego ddMMyy.
     * Medianoche en America/Costa_Rica. Null si la clave no trae una fecha válida.
     */
    public static function fechaEmisionDesdeClave(string $clave): ?string
    {
        $clave = trim($clave);
        if (strlen($clave) < 9) {
            return null;
        }

        $ddMMyy = substr($clave, 3, 6);
        if (preg_match('/^\d{6}$/', $ddMMyy) !== 1) {
            return null;
        }

        $tz = new \DateTimeZone('America/Costa_Rica');
        $fecha = \DateTimeImmutable::createFromFormat('!dmy', $ddMMyy, $tz);
        $errores = \DateTimeImmutable::getLastErrors();
        if (! $fecha instanceof \DateTimeImmutable) {
            return null;
        }
        if (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0)) {
            return null;
        }
        if ($fecha->format('dmy') !== $ddMMyy) {
            return null;
        }

        return $fecha->format('Y-m-d\TH:i:sP');
    }

    /**
     * @return list<string>
     */
    public static function tiposPermitidosEnReciboPago(): array
    {
        return ['01', '04', '07', '08', '10', '20'];
    }
}
