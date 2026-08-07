<?php

namespace App\Services\Compras\DocumentoImport;

use App\DataTransferObjects\Compras\DocumentoImportDto;
use App\DataTransferObjects\Compras\DocumentoImportResult;
use App\Exceptions\Compras\DocumentoImportException;
use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\FacturacionElectronica\CostaRica\DocumentoMoneda;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la importación de documentos electrónicos recibidos (compras/gastos).
 */
final class DocumentoImportService
{
    public function __construct(
        private readonly DocumentoImportResolver $resolver,
        private readonly CostaRicaTipoCambioService $tipoCambioService,
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

        if ($codPais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            $this->validarYLoguearMonedaCr($dto, $empresa);
        }

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

    /**
     * Spec §12: no asumir CRC, rechazar monedas fuera de CRC/USD (Fase 1) y avisar (log) si el
     * tipo de cambio del XML difiere del BCCR del día — la persistencia siempre usa BCCR (Task 2).
     */
    private function validarYLoguearMonedaCr(DocumentoImportDto $dto, ?Empresa $empresa): void
    {
        $codigoMoneda = $dto->resumen['currency_code'] ?? null;
        if ($codigoMoneda === null || $codigoMoneda === '') {
            return;
        }

        $codigoMoneda = strtoupper(trim((string) $codigoMoneda));
        if (! in_array($codigoMoneda, [DocumentoMoneda::MONEDA_CRC, DocumentoMoneda::MONEDA_USD], true)) {
            throw new DocumentoImportException(
                "Moneda del documento ({$codigoMoneda}) no soportada. Solo CRC o USD en esta fase."
            );
        }

        if ($codigoMoneda !== DocumentoMoneda::MONEDA_USD) {
            return;
        }

        if ($empresa && ! $empresa->tieneFuncionalidadMultimoneda()) {
            throw new DocumentoImportException(
                'El documento está en USD. Habilite la funcionalidad Multimoneda para esta empresa en Super Admin.'
            );
        }

        $tipoCambioXml = (float) ($dto->resumen['exchange_rate_xml'] ?? 0);
        $fecha = $dto->identificacion['fechaEmision'] ?? null;
        if ($tipoCambioXml <= 0 || ! $fecha) {
            return;
        }

        try {
            $tipoCambioBccr = $this->tipoCambioService->rateForDate(new \DateTimeImmutable($fecha));
        } catch (\Throwable) {
            // BCCR no disponible en este momento; el bloqueo real ocurre al guardar (DocumentoMoneda::resolve).
            return;
        }

        if (abs($tipoCambioXml - $tipoCambioBccr) > 0.01) {
            Log::warning('Import CR: tipo de cambio del XML difiere del BCCR', [
                'id_empresa' => $empresa?->id,
                'fecha' => $fecha,
                'tipo_cambio_xml' => $tipoCambioXml,
                'tipo_cambio_bccr' => $tipoCambioBccr,
            ]);
        }
    }
}
