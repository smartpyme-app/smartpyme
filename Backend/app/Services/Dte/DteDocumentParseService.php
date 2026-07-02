<?php

namespace App\Services\Dte;

use App\DataTransferObjects\Compras\DocumentoImportDto;
use App\Models\Admin\Empresa;
use App\Services\Compras\DocumentoImport\DocumentoImportResolver;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Parsea DTE recibido (JSON MH o XML DGT) a estructura normalizada del módulo de correo.
 */
class DteDocumentParseService
{
    public function __construct(
        private readonly DteParserService $jsonParser,
        private readonly DocumentoImportResolver $importResolver,
    ) {}

    /**
     * @return array{
     *     dte_uuid: string,
     *     dte_type: string,
     *     dte_number: string,
     *     emission_date: string,
     *     total_amount: float,
     *     issuer_nit: string,
     *     issuer_name: string,
     *     receiver_nit: ?string,
     *     receiver_name: ?string,
     *     items: array,
     *     pais: string,
     *     formato_origen: string,
     *     raw: array
     * }
     */
    public function parse(string $content, Empresa $empresa): array
    {
        $trim = ltrim($content);
        $codPais = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);

        if ($trim !== '' && ($trim[0] === '<' || str_starts_with($trim, '<?xml'))) {
            $dto = $this->importResolver->parse($content, $codPais);

            return $this->fromImportDto($dto);
        }

        $parsed = $this->jsonParser->parseFromJson($content);

        return array_merge($parsed, [
            'pais' => FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR,
            'formato_origen' => 'json',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromImportDto(DocumentoImportDto $dto): array
    {
        $mh = $dto->toMhCompatArray();
        $identificacion = $dto->identificacion;
        $cuerpoDocumento = $mh['cuerpoDocumento'] ?? [];
        $resumen = $mh['resumen'] ?? [];
        $receptor = $mh['receptor'] ?? [];

        $items = [];
        foreach ($cuerpoDocumento as $item) {
            $items[] = [
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (float) ($item['cantidad'] ?? 0),
                'precioUni' => (float) ($item['precioUni'] ?? 0),
                'ventaTotal' => (float) ($item['ventaGravada'] ?? 0)
                    + (float) ($item['ventaExenta'] ?? 0)
                    + (float) ($item['ventaNoSuj'] ?? 0),
            ];
        }

        $dteType = str_pad((string) ($identificacion['tipoDocumento'] ?? '01'), 2, '0', STR_PAD_LEFT);

        return [
            'dte_uuid' => (string) ($identificacion['codigoGeneracion'] ?? $identificacion['clave'] ?? ''),
            'dte_type' => $dteType,
            'dte_number' => (string) ($identificacion['numeroControl'] ?? $identificacion['consecutivo'] ?? ''),
            'emission_date' => (string) ($identificacion['fechaEmision'] ?? date('Y-m-d')),
            'total_amount' => (float) ($resumen['totalPagar'] ?? $resumen['montoTotalOperacion'] ?? 0),
            'issuer_nit' => (string) ($dto->emisor['nit'] ?? $dto->emisor['identificacion'] ?? ''),
            'issuer_name' => (string) ($dto->emisor['nombre'] ?? ''),
            'receiver_nit' => $receptor['nit'] ?? null,
            'receiver_name' => $receptor['nombre'] ?? null,
            'items' => $items,
            'pais' => $dto->pais,
            'formato_origen' => $dto->formatoOrigen,
            'raw' => $mh,
        ];
    }
}
