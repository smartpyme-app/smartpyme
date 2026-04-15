<?php

namespace App\Support\FacturacionElectronica;

use DOMDocument;

/**
 * Normaliza XML devuelto por Hacienda (DGT) para almacenamiento y para el visor del navegador.
 *
 * El error de Chrome "XML declaration allowed only at the start" (a menudo "line 5") aparece cuando:
 * - Hay basura/BOM antes del primer {@code <?xml };
 * - Hay **dos declaraciones XML** en el mismo string;
 * - Se confunde {@code <?xml-stylesheet} con {@code <?xml} (no lleva espacio tras "xml").
 *
 * Si el XML es parseable, {@see DOMDocument::saveXML()} re-emite un documento con una sola cabecera válida.
 */
final class XmlRespuestaHaciendaCr
{
    /** Declaración XML: debe ir {@code <?xml } (espacio), no {@code <?xml-stylesheet}. */
    private const PATTERN_DECL_INICIO = '/<\?xml\s/i';

    private const PATTERN_PRIMERA_DECL_CIERRE = '/<\?xml\s[^?]*\?>/is';

    public static function normalizar(string $xml): string
    {
        $xml = self::quitarBomYBasuraInicial($xml);

        // Anclar al inicio de la declaración XML real (no a <?xml-stylesheet)
        if (preg_match(self::PATTERN_DECL_INICIO, $xml, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            if ($start > 0) {
                $xml = substr($xml, $start);
            }
        } else {
            $p = strpos($xml, '<');
            if ($p !== false && $p > 0) {
                $xml = substr($xml, $p);
            }
        }

        $xml = self::truncarAntesSegundaDeclaracionXml($xml);
        $xml = self::quitarBomYBasuraInicial($xml);

        $roundTrip = self::reemitirConDomSiParsea($xml);
        if ($roundTrip !== null) {
            return $roundTrip;
        }

        return $xml;
    }

    private static function quitarBomYBasuraInicial(string $xml): string
    {
        while (str_starts_with($xml, "\xEF\xBB\xBF")) {
            $xml = substr($xml, 3);
        }
        if (str_starts_with($xml, "\xFF\xFE")) {
            $xml = mb_convert_encoding(substr($xml, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($xml, "\xFE\xFF")) {
            $xml = mb_convert_encoding(substr($xml, 2), 'UTF-8', 'UTF-16BE');
        }

        $xml = preg_replace('/^\x{FEFF}[\x{200B}-\x{200D}\x{2060}]*/u', '', $xml) ?? $xml;

        // Quitar todo lo que no sea '<' al inicio (evita caracteres de control invisibles)
        if ($xml !== '' && $xml[0] !== '<') {
            $xml = preg_replace('/^[^<]+/u', '', $xml) ?? $xml;
        }

        return ltrim($xml);
    }

    /**
     * Segunda {@code <?xml } (declaración) suele quedar en la "línea 5" y rompe el visor.
     */
    private static function truncarAntesSegundaDeclaracionXml(string $xml): string
    {
        if (! preg_match(self::PATTERN_PRIMERA_DECL_CIERRE, $xml, $m, PREG_OFFSET_CAPTURE)) {
            return $xml;
        }
        $declLen = strlen($m[0][0]);
        $declEnd = $m[0][1] + $declLen;
        $rest = substr($xml, $declEnd);
        if (! preg_match(self::PATTERN_DECL_INICIO, $rest, $m2, PREG_OFFSET_CAPTURE)) {
            return $xml;
        }
        $second = $m2[0][1];

        return substr($xml, 0, $declEnd + $second);
    }

    private static function reemitirConDomSiParsea(string $xml): ?string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $prev = libxml_use_internal_errors(true);
        $ok = @$dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $ok) {
            return null;
        }

        $out = $dom->saveXML();
        if ($out === false || $out === '') {
            return null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $estado
     * @return array<string, mixed>
     */
    public static function normalizarResponseXmlEnEstado(array $estado): array
    {
        if (isset($estado['response_xml']) && is_string($estado['response_xml']) && $estado['response_xml'] !== '') {
            $estado['response_xml'] = self::normalizar($estado['response_xml']);
        }

        return $estado;
    }
}
