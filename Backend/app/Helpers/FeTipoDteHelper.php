<?php

namespace App\Helpers;

use App\Constants\FacturacionElectronica\FEConstants;
use App\Models\Admin\Documento;
use App\Models\Ventas\Clientes\Cliente;

class FeTipoDteHelper
{
    public const EMPRESA_EMISORA_ID = 2;

    /** @var array<string, string> nombre documento (lowercase) => código DTE */
    private const MAPA_NOMBRE_DOCUMENTO_A_TIPO_DTE = [
        'factura' => FEConstants::TIPO_DTE_FACTURA_CONSUMIDOR_FINAL,
        'ticket' => FEConstants::TIPO_DTE_FACTURA_CONSUMIDOR_FINAL,
        'crédito fiscal' => FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL,
        'nota de crédito' => FEConstants::TIPO_DTE_NOTA_DE_CREDITO,
        'nota de débito' => FEConstants::TIPO_DTE_NOTA_DE_DEBITO,
        'factura de exportación' => FEConstants::TIPO_DTE_FACTURAS_DE_EXPORTACION,
        'sujeto excluido' => FEConstants::TIPO_DTE_FACTURA_DE_SUJETO_EXCLUIDO,
    ];

    public static function tipoDteDesdeNombreDocumento(?string $nombre): ?string
    {
        if ($nombre === null || $nombre === '') {
            return null;
        }

        $key = mb_strtolower(trim($nombre));

        return self::MAPA_NOMBRE_DOCUMENTO_A_TIPO_DTE[$key] ?? null;
    }

    public static function documentoEmisoraPorId($idDocumento): ?Documento
    {
        if (empty($idDocumento)) {
            return null;
        }

        return Documento::withoutGlobalScopes()
            ->where('id', $idDocumento)
            ->where('id_empresa', self::EMPRESA_EMISORA_ID)
            ->first();
    }

    public static function documentoFacturaDefault(): ?Documento
    {
        return Documento::withoutGlobalScopes()
            ->where('id_empresa', self::EMPRESA_EMISORA_ID)
            ->where('nombre', 'Factura')
            ->first();
    }

    public static function tipoDteFallbackDesdeCliente(Cliente $cliente): string
    {
        if (!empty($cliente->cod_pais) && strtoupper($cliente->cod_pais) !== 'SV') {
            return FEConstants::TIPO_DTE_FACTURAS_DE_EXPORTACION;
        }

        if (!empty($cliente->ncr)) {
            return FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL;
        }

        if (!empty($cliente->nit) && $cliente->tipo_documento === '36') {
            return FEConstants::TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL;
        }

        return FEConstants::TIPO_DTE_FACTURA_CONSUMIDOR_FINAL;
    }

    public static function determinarTipoDte(Cliente $cliente, $idDocumento): string
    {
        $documento = self::documentoEmisoraPorId($idDocumento);
        if ($documento) {
            $tipo = self::tipoDteDesdeNombreDocumento($documento->nombre);
            if ($tipo !== null) {
                return $tipo;
            }
        }

        return self::tipoDteFallbackDesdeCliente($cliente);
    }

    /**
     * Resuelve documento de empresa emisora y tipo DTE.
     * Si id_documento no sirve, documento fallback = Factura; tipo fallback = heurística del cliente.
     *
     * @return array{0: ?Documento, 1: string}
     */
    public static function resolverDocumentoYTipoDte(Cliente $cliente, $idDocumento): array
    {
        $documento = self::documentoEmisoraPorId($idDocumento);
        $tipo = $documento ? self::tipoDteDesdeNombreDocumento($documento->nombre) : null;

        if (!$documento || $tipo === null) {
            if (!$documento) {
                $documento = self::documentoFacturaDefault();
            }
            $tipo = $tipo ?? self::tipoDteFallbackDesdeCliente($cliente);
        }

        return [$documento, $tipo];
    }
}
