<?php

namespace App\Services\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LibroIVAService
{
    protected $facturacionElectronicaHelper;

    public function __construct(FacturacionElectronicaHelperService $facturacionElectronicaHelper)
    {
        $this->facturacionElectronicaHelper = $facturacionElectronicaHelper;
    }

    /**
     * Valida si existen ventas pendientes de emitirse
     *
     * @param Request $request
     * @param array|null $documentos Tipos de documentos a validar
     * @return JsonResponse|null Retorna JsonResponse con error si hay ventas pendientes, null si no hay
     */
    public function validarVentasPendientes(Request $request, ?array $documentos = null): ?JsonResponse
    {
        if (!$request->filled('inicio') || !$request->filled('fin')) {
            return null;
        }

        // Solo validar ventas pendientes si tiene facturación electrónica
        if (!$this->facturacionElectronicaHelper->tieneFacturacionElectronica()) {
            return null;
        }

        $ventasPendientes = Venta::query()
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when(!empty($documentos), function ($query) use ($documentos) {
                $query->whereHas('documento', function ($subQuery) use ($documentos) {
                    $subQuery->whereIn('nombre', $documentos);
                });
            })
            ->where(function ($query) {
                $query->whereNull('sello_mh')
                    ->orWhere('sello_mh', '');
            })
            ->exists();

        if ($ventasPendientes) {
            return response()->json([
                'message' => 'Existen ventas pendientes de emitirse para el período seleccionado, por favor complete las ventas pendientes emitiendo los documentos.',
            ], 500);
        }

        return null;
    }
}

