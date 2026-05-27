<?php

namespace App\DataTransferObjects\Compras;

/**
 * Resultado de parsear e interpretar un documento para importación.
 */
final class DocumentoImportResult
{
    public function __construct(
        public readonly DocumentoImportDto $dto,
        public readonly array $dte,
        public readonly string $tipoDocumentoNombre,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        return [
            'pais' => $this->dto->pais,
            'formato_origen' => $this->dto->formatoOrigen,
            'tipo_documento_nombre' => $this->tipoDocumentoNombre,
            'documento' => $this->dto->toArray(),
            'dte' => $this->dte,
        ];
    }
}
