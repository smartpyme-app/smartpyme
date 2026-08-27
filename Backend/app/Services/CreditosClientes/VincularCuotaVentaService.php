<?php

namespace App\Services\CreditosClientes;

use App\Exceptions\FacturacionException;
use App\Models\CreditosClientes\CreditoCuota;
use App\Models\Ventas\Venta;

class VincularCuotaVentaService
{
    public function vincular(int $idCuota, Venta $venta): CreditoCuota
    {
        $cuota = CreditoCuota::with('contrato')->findOrFail($idCuota);
        $contrato = $cuota->contrato;
        if (!$contrato) {
            throw new FacturacionException('La cuota no pertenece a un crédito válido.', 422);
        }

        if (!TipoDocumentoCredito::puedeFacturar($cuota->id_venta)) {
            throw new FacturacionException('Esta cuota ya fue facturada.', 422);
        }

        try {
            TipoDocumentoCredito::assertCompatible($contrato->id_documento, $venta->id_documento);
        } catch (\InvalidArgumentException $e) {
            throw new FacturacionException($e->getMessage(), 422);
        }

        $cuota->id_venta = $venta->id;
        $cuota->estado = CreditoCuota::ESTADO_FACTURADA;
        $cuota->save();

        if (!TipoDocumentoCredito::documentoBloqueado($contrato->id_documento)) {
            $contrato->id_documento = $venta->id_documento;
            $contrato->save();
        }

        return $cuota;
    }
}
