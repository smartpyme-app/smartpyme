<?php

namespace App\Services\Compras\DocumentoImport;

use App\DataTransferObjects\Compras\DocumentoImportResult;
use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Orquesta la importación de documentos electrónicos recibidos (compras/gastos).
 */
final class DocumentoImportService
{
    public function __construct(
        private readonly DocumentoImportResolver $resolver,
    ) {}

    public function importar(string $contenido, ?Empresa $empresa = null): DocumentoImportResult
    {
        $contenido = trim($contenido);
        if ($contenido === '') {
            throw new \InvalidArgumentException('El contenido del documento está vacío.');
        }

        $empresa = $empresa ?? Auth::user()?->empresa;
        $codPais = FacturacionElectronicaCountryResolver::codPais($empresa);

        $dto = $this->resolver->parse($contenido, $codPais);

        $nombre = $dto->tipoDocumentoNombre
            ?? DocumentoTipoDocumentoMapper::nombre(
                (string) ($dto->identificacion['tipoDocumento'] ?? '01'),
                $codPais
            );

        return new DocumentoImportResult(
            dto: $dto,
            dte: $dto->toMhCompatArray(),
            tipoDocumentoNombre: $nombre,
        );
    }
}
