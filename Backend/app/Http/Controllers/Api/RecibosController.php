<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Models\Ventas\Venta;
use App\Models\Recibo;
use App\Http\Requests\Recibos\StoreReciboRequest;
use App\Services\Comisiones\ComisionService;
use Illuminate\Support\Facades\Log;

class RecibosController extends Controller
{

    public function store(StoreReciboRequest $request){

        $recibo = new Recibo();
        $recibo->estado = 'Confirmado';
        $recibo->fill($request->all());
        $recibo->save();

        $venta = Venta::find($recibo->id_venta);
        $venta->forma_pago = $request->forma_pago;
        if ($venta && $venta->saldo <= 0) {
            $venta->estado = 'Pagada';
            $venta->save();

            try {
                $venta->loadMissing('detalles.producto');
                app(ComisionService::class)->registrarVentaPagada($venta);
            } catch (\Throwable $e) {
                Log::error('comisiones: fallo al registrar venta', [
                    'venta' => $venta->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Response()->json($recibo, 200);
    }

    public function print($id){

        $recibo = Recibo::where('id', $id)->firstOrFail();

        $venta = Venta::where('id', $recibo->id_venta)->first();

        $pdf = app('dompdf.wrapper')->loadView('reportes.recibo', compact('venta', 'recibo'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');
    }

}
