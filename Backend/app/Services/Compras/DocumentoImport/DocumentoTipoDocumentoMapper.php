<?php

namespace App\Services\Compras\DocumentoImport;

use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Mapea código de tipo DTE/comprobante al nombre de documento en SmartPyME.
 */
final class DocumentoTipoDocumentoMapper
{
  private const TIPOS_SV = [
      '01' => 'Factura',
      '03' => 'Crédito fiscal',
      '05' => 'Nota de débito',
      '06' => 'Nota de crédito',
      '07' => 'Comprobante de retención',
      '11' => 'Factura de exportación',
      '14' => 'Sujeto excluido',
  ];

  private const TIPOS_CR = [
      '01' => 'Factura Electrónica',
      '02' => 'Nota de Débito Electrónica',
      '03' => 'Nota de Crédito Electrónica',
      '04' => 'Tiquete Electrónico',
      '08' => 'Factura Electrónica de Compra',
      '09' => 'Factura Electrónica de Exportación',
  ];

  private const ROOT_CR = [
      'FacturaElectronica' => '01',
      'TiqueteElectronico' => '04',
      'NotaCreditoElectronica' => '03',
      'NotaDebitoElectronica' => '02',
      'FacturaElectronicaCompra' => '08',
      'FacturaElectronicaExportacion' => '09',
  ];

  public static function codigoDesdeRaizXml(string $rootLocalName): ?string
  {
      return self::ROOT_CR[$rootLocalName] ?? null;
  }

  public static function nombre(string $codigoTipo, string $codPais): string
  {
      $cod = str_pad(trim($codigoTipo), 2, '0', STR_PAD_LEFT);

      if ($codPais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
          return self::TIPOS_CR[$cod] ?? self::TIPOS_CR['01'];
      }

      return self::TIPOS_SV[$cod] ?? self::TIPOS_SV['01'];
  }
}
