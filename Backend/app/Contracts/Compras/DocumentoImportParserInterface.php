<?php

namespace App\Contracts\Compras;

use App\DataTransferObjects\Compras\DocumentoImportDto;

interface DocumentoImportParserInterface
{
    /**
     * Indica si este parser puede interpretar el contenido.
     */
    public function supports(string $content): bool;

    /**
     * Parsea el contenido a modelo canónico.
     */
    public function parse(string $content): DocumentoImportDto;
}
