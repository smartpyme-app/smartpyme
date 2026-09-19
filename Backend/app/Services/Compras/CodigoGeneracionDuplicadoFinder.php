<?php

namespace App\Services\Compras;

use App\Exceptions\Compras\DocumentoImportException;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;

/**
 * Evita repetir un DTE (codigoGeneracion / clave CR) en compras o gastos de la misma empresa.
 */
final class CodigoGeneracionDuplicadoFinder
{
    public function encontrar(
        int $idEmpresa,
        ?string $codigo,
        ?int $excluirCompraId = null,
        ?int $excluirGastoId = null,
    ): ?CodigoGeneracionDuplicado {
        $codigo = CodigoGeneracionDuplicado::normalizar($codigo);
        if ($codigo === '' || $idEmpresa <= 0) {
            return null;
        }

        $compra = Compra::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereRaw('UPPER(TRIM(codigo_generacion)) = ?', [$codigo])
            ->when($excluirCompraId, fn ($q) => $q->where('id', '!=', $excluirCompraId))
            ->orderBy('id')
            ->first(['id', 'referencia']);

        if ($compra) {
            return new CodigoGeneracionDuplicado(
                CodigoGeneracionDuplicado::TIPO_COMPRA,
                (int) $compra->id,
                $compra->referencia !== null ? (string) $compra->referencia : null,
            );
        }

        $gasto = Gasto::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->whereRaw('UPPER(TRIM(codigo_generacion)) = ?', [$codigo])
            ->when($excluirGastoId, fn ($q) => $q->where('id', '!=', $excluirGastoId))
            ->orderBy('id')
            ->first(['id', 'referencia']);

        if ($gasto) {
            return new CodigoGeneracionDuplicado(
                CodigoGeneracionDuplicado::TIPO_GASTO,
                (int) $gasto->id,
                $gasto->referencia !== null ? (string) $gasto->referencia : null,
            );
        }

        return null;
    }

    public function assertDisponible(
        int $idEmpresa,
        ?string $codigo,
        ?int $excluirCompraId = null,
        ?int $excluirGastoId = null,
    ): void {
        $dup = $this->encontrar($idEmpresa, $codigo, $excluirCompraId, $excluirGastoId);
        if ($dup) {
            throw new DocumentoImportException($dup->mensaje());
        }
    }
}
