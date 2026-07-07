<?php

namespace App\Support\FacturacionElectronica;

/**
 * Resuelve venta.dte / devolucion.dte para FE Costa Rica:
 * formato nuevo (string XML firmado, como SV guarda el JSON del DTE) y legado (wrapper JSON).
 */
final class CostaRicaFeDteDocumento
{
    public static function esXmlComprobante(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }
        $trim = ltrim($value);

        return str_starts_with($trim, '<?xml') || str_starts_with($trim, '<');
    }

    public static function tieneComprobanteCr(mixed $dte): bool
    {
        if (self::esXmlComprobante($dte)) {
            return true;
        }

        if (! is_array($dte)) {
            return false;
        }

        $doc = $dte['documento'] ?? null;
        if (self::esXmlComprobante($doc) || is_array($doc)) {
            return true;
        }

        $cr = is_array($dte['cr'] ?? null) ? $dte['cr'] : [];
        if (self::esXmlComprobante($cr['xml_comprobante_firmado'] ?? null)) {
            return true;
        }

        return is_array($cr['payload_interno'] ?? null);
    }

    /**
     * Indica emisión registrada (mismo criterio práctico que FE SV con sello_mh en listados).
     * En CR, al aceptar Hacienda se persisten codigo_generacion, sello_mh (clave DGT) y dte (XML).
     */
    public static function tieneEmisionRegistrada(?string $codigoGeneracion, ?string $selloMh, mixed $dte = null): bool
    {
        if (self::tieneComprobanteCr($dte)) {
            return true;
        }

        if (trim((string) ($codigoGeneracion ?? '')) !== '') {
            return true;
        }

        return trim((string) ($selloMh ?? '')) !== '';
    }

    /** Clave numérica para ticket/QR: codigo_generacion o, como SV con sello_mh, el sello persistido. */
    public static function claveEmision(?string $codigoGeneracion, ?string $selloMh): string
    {
        $clave = trim((string) ($codigoGeneracion ?? ''));
        if ($clave !== '') {
            return $clave;
        }

        return trim((string) ($selloMh ?? ''));
    }

    /**
     * Array para PDF / descarga JSON: parsea XML; acepta JSON legado.
     *
     * @param  array<string, mixed>|string|null  $dte
     * @param  array<string, mixed>|null  $bloqueNd  Sub-bloque legado dte.cr.nota_debito
     * @return array<string, mixed>
     */
    public static function documentoParaPdf(mixed $dte, ?array $bloqueNd = null): array
    {
        $raw = self::documentoCrudo($dte, $bloqueNd);

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            return CostaRicaXmlComprobantePdfMapper::fromXml($raw);
        }

        $legacy = self::payloadInternoLegado($dte, $bloqueNd);
        if ($legacy !== null) {
            return $legacy;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|string|null  $dte
     * @param  array<string, mixed>|null  $bloqueNd
     * @return array<string, mixed>
     */
    public static function payloadInternoJson(mixed $dte, ?array $bloqueNd = null): array
    {
        return self::documentoParaPdf($dte, $bloqueNd);
    }

    /**
     * XML firmado del comprobante emitido (no la respuesta de Hacienda).
     *
     * @param  array<string, mixed>|string|null  $dte
     * @param  array<string, mixed>|null  $bloqueNd
     */
    public static function xmlComprobanteEmitido(mixed $dte, ?array $bloqueNd = null): ?string
    {
        $raw = self::documentoCrudo($dte, $bloqueNd);
        if (self::esXmlComprobante($raw)) {
            return $raw;
        }

        if (is_array($dte)) {
            $cr = self::bloqueCr($dte, $bloqueNd);
            $xml = $cr['xml_comprobante_firmado'] ?? null;
            if (self::esXmlComprobante($xml)) {
                return $xml;
            }
        }

        return null;
    }

    /**
     * XML de respuesta/acuse de Hacienda (solo en registros legado con wrapper cr).
     *
     * @param  array<string, mixed>|string|null  $dte
     * @param  array<string, mixed>|null  $bloqueNd
     */
    public static function respuestaHaciendaXml(mixed $dte, ?array $bloqueNd = null): ?string
    {
        if (! is_array($dte) && $bloqueNd === null) {
            return null;
        }

        $cr = self::bloqueCr(is_array($dte) ? $dte : [], $bloqueNd);

        if (isset($cr['respuesta_hacienda_xml']) && is_string($cr['respuesta_hacienda_xml']) && trim($cr['respuesta_hacienda_xml']) !== '') {
            return $cr['respuesta_hacienda_xml'];
        }

        $est = is_array($cr['estado_consulta'] ?? null) ? $cr['estado_consulta'] : [];
        $xml = $est['response_xml'] ?? null;

        return is_string($xml) && trim($xml) !== '' ? $xml : null;
    }

    /**
     * @param  array<string, mixed>|string|null  $dte
     * @param  array<string, mixed>|null  $bloqueNd
     * @return array<string, mixed>|string|null
     */
    private static function documentoCrudo(mixed $dte, ?array $bloqueNd)
    {
        if ($bloqueNd !== null) {
            return $bloqueNd['documento'] ?? null;
        }

        if (self::esXmlComprobante($dte)) {
            return $dte;
        }

        if (is_array($dte)) {
            $doc = $dte['documento'] ?? null;
            if ($doc !== null) {
                return $doc;
            }

            $cr = is_array($dte['cr'] ?? null) ? $dte['cr'] : [];
            $xml = $cr['xml_comprobante_firmado'] ?? null;
            if (self::esXmlComprobante($xml)) {
                return $xml;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dte
     * @return array<string, mixed>
     */
    private static function bloqueCr(array $dte, ?array $bloqueNd): array
    {
        if ($bloqueNd !== null) {
            return $bloqueNd;
        }

        return is_array($dte['cr'] ?? null) ? $dte['cr'] : [];
    }

    /**
     * @param  array<string, mixed>|string|null  $dte
     * @param  array<string, mixed>|null  $bloqueNd
     * @return array<string, mixed>|null
     */
    private static function payloadInternoLegado(mixed $dte, ?array $bloqueNd): ?array
    {
        if (! is_array($dte)) {
            return null;
        }

        $cr = self::bloqueCr($dte, $bloqueNd);
        $payload = $cr['payload_interno'] ?? null;

        return is_array($payload) ? $payload : null;
    }
}
