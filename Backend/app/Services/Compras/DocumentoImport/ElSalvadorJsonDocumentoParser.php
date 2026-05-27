<?php

namespace App\Services\Compras\DocumentoImport;

use App\Contracts\Compras\DocumentoImportParserInterface;
use App\DataTransferObjects\Compras\DocumentoImportDto;
use App\Exceptions\Compras\DocumentoImportException;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Parser JSON DTE Ministerio de Hacienda (El Salvador).
 */
final class ElSalvadorJsonDocumentoParser implements DocumentoImportParserInterface
{
    public function supports(string $content): bool
    {
        $trim = ltrim($content);
        if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) {
            return false;
        }

        $data = json_decode($content, true);

        return is_array($data) && isset($data['identificacion']);
    }

    public function parse(string $content): DocumentoImportDto
    {
        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new DocumentoImportException('El JSON no es válido.');
        }
        if (! isset($data['identificacion']) || ! is_array($data['identificacion'])) {
            throw new DocumentoImportException(
                'Formato incorrecto: el JSON no contiene la estructura DTE esperada (identificacion).'
            );
        }

        $id = $data['identificacion'];
        $emisorRaw = is_array($data['emisor'] ?? null) ? $data['emisor'] : [];
        $resumenRaw = is_array($data['resumen'] ?? null) ? $data['resumen'] : [];
        $cuerpo = is_array($data['cuerpoDocumento'] ?? null) ? $data['cuerpoDocumento'] : [];

        $identificacion = [
            'fechaEmision' => $id['fecEmi'] ?? null,
            'tipoDocumento' => isset($id['tipoDte']) ? (string) $id['tipoDte'] : null,
            'codigoGeneracion' => $id['codigoGeneracion'] ?? null,
            'numeroControl' => $id['numeroControl'] ?? null,
            'clave' => $id['codigoGeneracion'] ?? null,
            'consecutivo' => $id['numeroControl'] ?? null,
        ];

        $emisor = [
            'identificacion' => $emisorRaw['nit'] ?? $emisorRaw['dui'] ?? null,
            'nit' => $emisorRaw['nit'] ?? null,
            'nrc' => $emisorRaw['nrc'] ?? null,
            'dui' => $emisorRaw['dui'] ?? null,
            'nombre' => $emisorRaw['nombre'] ?? '',
            'telefono' => $emisorRaw['telefono'] ?? '',
            'correo' => $emisorRaw['correo'] ?? '',
            'direccion' => $emisorRaw['direccion']['complemento'] ?? '',
        ];

        $lineas = [];
        foreach ($cuerpo as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $gravada = (float) ($item['ventaGravada'] ?? 0);
            $exenta = (float) ($item['ventaExenta'] ?? 0);
            $noSuj = (float) ($item['ventaNoSuj'] ?? 0);
            $lineas[] = [
                'numItem' => $item['numItem'] ?? ($idx + 1),
                'codigo' => isset($item['codigo']) ? trim((string) $item['codigo']) : '',
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (float) ($item['cantidad'] ?? 0),
                'precioUnitario' => (float) ($item['precioUni'] ?? 0),
                'montoGravado' => $gravada,
                'montoExento' => $exenta,
                'montoNoSujeto' => $noSuj,
                'descuento' => (float) ($item['montoDescu'] ?? 0),
                'subtotal' => $gravada + $exenta + $noSuj,
            ];
        }

        $tributos = [];
        if (! empty($resumenRaw['tributos']) && is_array($resumenRaw['tributos'])) {
            foreach ($resumenRaw['tributos'] as $t) {
                if (! is_array($t)) {
                    continue;
                }
                $tributos[] = [
                    'codigo' => (string) ($t['codigo'] ?? ''),
                    'valor' => (float) ($t['valor'] ?? 0),
                ];
            }
        }

        $resumen = [
            'subtotal' => (float) ($resumenRaw['subTotal'] ?? $resumenRaw['subTotalVentas'] ?? 0),
            'subtotalVentas' => (float) ($resumenRaw['subTotalVentas'] ?? $resumenRaw['subTotal'] ?? 0),
            'totalGravado' => (float) ($resumenRaw['totalGravada'] ?? 0),
            'total' => (float) ($resumenRaw['montoTotalOperacion'] ?? $resumenRaw['totalPagar'] ?? 0),
            'totalPagar' => (float) ($resumenRaw['totalPagar'] ?? $resumenRaw['montoTotalOperacion'] ?? 0),
            'tributos' => $tributos,
            'ivaRetenido' => (float) ($resumenRaw['ivaRete1'] ?? 0),
            'ivaPercibido' => (float) ($resumenRaw['ivaPerci1'] ?? 0),
            'rentaRetenida' => (float) ($resumenRaw['reteRenta'] ?? 0),
            'condicionOperacion' => $resumenRaw['condicionOperacion'] ?? null,
            'pagos' => is_array($resumenRaw['pagos'] ?? null) ? $resumenRaw['pagos'] : [],
        ];

        $tipoCod = $identificacion['tipoDocumento'] ?? '01';
        $nombre = DocumentoTipoDocumentoMapper::nombre(
            (string) $tipoCod,
            FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR
        );

        $sello = $data['selloRecibido']
            ?? $data['sello']
            ?? ($data['documento']['selloRecibido'] ?? null);

        return new DocumentoImportDto(
            pais: FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR,
            formatoOrigen: 'json',
            identificacion: $identificacion,
            emisor: $emisor,
            lineas: $lineas,
            resumen: $resumen,
            documentoOriginal: $data,
            selloRecibido: is_string($sello) ? $sello : null,
            tipoDocumentoNombre: $nombre,
        );
    }
}
