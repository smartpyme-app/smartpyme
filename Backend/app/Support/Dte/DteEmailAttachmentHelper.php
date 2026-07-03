<?php

namespace App\Support\Dte;

use App\Services\Compras\DocumentoImport\CostaRicaXmlDocumentoParser;
use App\Services\Compras\DocumentoImport\Support\XmlLocalNameHelper;

/**
 * Agrupa adjuntos de correo DTE (JSON SV o XML/PDF/acuse CR) por clave de comprobante.
 */
final class DteEmailAttachmentHelper
{
    public const FORMAT_JSON = 'json';

    public const FORMAT_XML = 'xml';

    public const XML_COMPROBANTE = 'comprobante';

    public const XML_ACUSE = 'acuse';

    /**
     * @param  array<int, array{filename: string, content: string}>  $attachments
     * @return array<int, array{
     *     email_message_id: string,
     *     clave: ?string,
     *     source_format: string,
     *     source_content: string,
     *     pdf_content: ?string,
     *     acuse_content: ?string
     * }>
     */
    public static function groupAttachments(string $messageId, array $attachments): array
    {
        $jsonContent = null;
        $pdfByClave = [];
        $acuseByClave = [];
        $comprobanteByClave = [];

        foreach ($attachments as $attachment) {
            $filename = $attachment['filename'] ?? '';
            $content = $attachment['content'] ?? '';
            if ($content === '') {
                continue;
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $clave = self::extractClaveFromFilename($filename);

            if ($ext === 'json') {
                $jsonContent = $content;

                continue;
            }

            if ($ext === 'pdf') {
                $key = $clave ?? '_default';
                $pdfByClave[$key] = $content;

                continue;
            }

            if ($ext !== 'xml') {
                continue;
            }

            $xmlKind = self::classifyXmlContent($content);
            if ($xmlKind === self::XML_COMPROBANTE) {
                $xmlClave = self::extractClaveFromXml($content) ?? $clave ?? md5($content);
                $comprobanteByClave[$xmlClave] = $content;

                continue;
            }

            if ($xmlKind === self::XML_ACUSE) {
                $xmlClave = self::extractClaveFromXml($content) ?? $clave ?? md5($content);
                $acuseByClave[$xmlClave] = $content;
            }
        }

        if ($jsonContent !== null) {
            return [[
                'email_message_id' => $messageId,
                'clave' => null,
                'source_format' => self::FORMAT_JSON,
                'source_content' => $jsonContent,
                'pdf_content' => $pdfByClave['_default'] ?? reset($pdfByClave) ?: null,
                'acuse_content' => null,
            ]];
        }

        $results = [];
        foreach ($comprobanteByClave as $clave => $xmlContent) {
            $results[] = [
                'email_message_id' => $messageId.'-'.$clave,
                'clave' => $clave,
                'source_format' => self::FORMAT_XML,
                'source_content' => $xmlContent,
                'pdf_content' => $pdfByClave[$clave] ?? $pdfByClave['_default'] ?? null,
                'acuse_content' => $acuseByClave[$clave] ?? null,
            ];
        }

        return $results;
    }

    public static function extractClaveFromFilename(string $filename): ?string
    {
        if (preg_match('/(\d{50})/', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function classifyXmlContent(string $content): ?string
    {
        $trim = ltrim($content);
        if ($trim === '' || ($trim[0] !== '<' && ! str_starts_with($trim, '<?xml'))) {
            return null;
        }

        try {
            $doc = XmlLocalNameHelper::loadXml($content);
            $root = XmlLocalNameHelper::rootLocalName($doc);

            if ($root === 'MensajeHacienda') {
                return self::XML_ACUSE;
            }

            $parser = new CostaRicaXmlDocumentoParser();
            if ($parser->supports($content)) {
                return self::XML_COMPROBANTE;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public static function extractAcuseEstado(string $acuseXml): ?string
    {
        try {
            $doc = XmlLocalNameHelper::loadXml($acuseXml);
            $xp = XmlLocalNameHelper::xpath($doc);

            return XmlLocalNameHelper::firstText($xp, 'EstadoMensaje');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function extractClaveFromXml(string $content): ?string
    {
        try {
            $doc = XmlLocalNameHelper::loadXml($content);
            $xp = XmlLocalNameHelper::xpath($doc);
            $clave = XmlLocalNameHelper::firstText($xp, 'Clave');

            return ($clave !== null && $clave !== '') ? $clave : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
