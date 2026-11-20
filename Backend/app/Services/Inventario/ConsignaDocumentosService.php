<?php

namespace App\Services\Inventario;

use App\Constants\DocumentoConstants;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;

class ConsignaDocumentosService
{
    /** Nombres creados por sucursal cuando el ledger de consigna está activo. */
    public const NOMBRES_LEDGER = [
        DocumentoConstants::DEVOLUCION_CONSIGNA,
    ];

    public function asegurarDocumentosLedger(Empresa $empresa): int
    {
        if (! $empresa->isModuloConsignacionesLedgerActivo()) {
            return 0;
        }

        $creados = 0;
        $sucursales = Sucursal::query()
            ->where('id_empresa', $empresa->id)
            ->get(['id']);

        foreach ($sucursales as $sucursal) {
            foreach (self::NOMBRES_LEDGER as $nombre) {
                $existe = Documento::query()
                    ->where('id_empresa', $empresa->id)
                    ->where('id_sucursal', $sucursal->id)
                    ->whereRaw('LOWER(nombre) = LOWER(?)', [$nombre])
                    ->exists();

                if ($existe) {
                    continue;
                }

                Documento::create([
                    'nombre' => $nombre,
                    'correlativo' => 1,
                    'activo' => 1,
                    'id_sucursal' => $sucursal->id,
                    'id_empresa' => $empresa->id,
                ]);
                $creados++;
            }
        }

        return $creados;
    }
}
