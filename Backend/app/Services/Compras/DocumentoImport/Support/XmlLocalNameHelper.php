<?php

namespace App\Services\Compras\DocumentoImport\Support;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Utilidades XPath por nombre local (ignora namespaces DGT).
 */
final class XmlLocalNameHelper
{
    public static function loadXml(string $content): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $loaded = $doc->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new \InvalidArgumentException('El XML no es válido o está mal formado.');
        }

        return $doc;
    }

    public static function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);

        return $xp;
    }

    public static function rootLocalName(DOMDocument $doc): string
    {
        return $doc->documentElement?->localName ?? $doc->documentElement?->nodeName ?? '';
    }

    public static function firstText(DOMXPath $xp, string $localName, ?DOMNode $context = null): ?string
    {
        $nodes = $xp->query(".//*[local-name()='{$localName}']", $context);
        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        $value = trim((string) $nodes->item(0)?->textContent);

        return $value === '' ? null : $value;
    }

    public static function allNodes(DOMXPath $xp, string $localName, ?DOMNode $context = null): array
    {
        $nodes = $xp->query(".//*[local-name()='{$localName}']", $context);
        if (! $nodes) {
            return [];
        }
        $out = [];
        for ($i = 0; $i < $nodes->length; $i++) {
            $out[] = $nodes->item($i);
        }

        return $out;
    }

    public static function floatValue(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', trim($value));
    }

    /**
     * Fecha ISO CR → Y-m-d para formularios.
     */
    public static function fechaSolo(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $iso, $m)) {
            return $m[1];
        }

        return null;
    }
}
