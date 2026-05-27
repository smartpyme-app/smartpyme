<?php

namespace App\Services\Compras\DocumentoImport;

use App\DataTransferObjects\Compras\DocumentoImportDto;
use App\Exceptions\Compras\DocumentoImportException;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Selecciona el parser adecuado según país de la empresa y formato del archivo.
 */
final class DocumentoImportResolver
{
    public function __construct(
        private readonly CostaRicaXmlDocumentoParser $costaRicaXmlParser,
        private readonly ElSalvadorJsonDocumentoParser $elSalvadorJsonParser,
    ) {}

    public function parse(string $content, string $codPais): DocumentoImportDto
    {
        $ordered = $this->orderedParsers($codPais);

        foreach ($ordered as $parser) {
            if ($parser->supports($content)) {
                return $parser->parse($content);
            }
        }

        $formatoEsperado = $codPais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA
            ? 'XML de comprobante electrónico (DGT)'
            : 'JSON DTE (Ministerio de Hacienda)';

        throw new DocumentoImportException(
            "No se pudo interpretar el documento. Para esta empresa se espera {$formatoEsperado}."
        );
    }

    /**
     * @return array<int, DocumentoImportParserInterface>
     */
    private function orderedParsers(string $codPais): array
    {
        if ($codPais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return [
                $this->costaRicaXmlParser,
                $this->elSalvadorJsonParser,
            ];
        }

        return [
            $this->elSalvadorJsonParser,
            $this->costaRicaXmlParser,
        ];
    }
}
