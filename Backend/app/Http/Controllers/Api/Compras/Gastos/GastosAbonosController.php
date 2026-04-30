<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use App\Models\Compras\Gastos\Abono;
use App\Models\Compras\Gastos\Gasto;
use App\Services\Bancos\ChequesService;
use App\Services\Bancos\TransaccionesService;
use Illuminate\Http\Request;

class GastosAbonosController extends Controller
{
    protected $transaccionesService;

    protected $chequesService;

    public function __construct(TransaccionesService $transaccionesService, ChequesService $chequesService)
    {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
    }

    public function store(Request $request)
    {
        $gasto = Gasto::find($request->id_gasto);
        if (! $gasto) {
            return response()->json(['error' => 'Gasto no encontrado'], 404);
        }

        $request->validate([
            'fecha' => 'required|date',
            'concepto' => 'required|max:255',
            'nombre_de' => 'required|max:255',
            'estado' => 'required|max:255',
            'forma_pago' => 'required|max:255',
            'total' => 'required|numeric',
            'id_gasto' => 'required|numeric',
            'id_usuario' => 'required|numeric',
            'id_sucursal' => 'required|numeric',
        ]);

        if ($request->id) {
            $abono = Abono::findOrFail($request->id);
        } else {
            $abono = new Abono;
        }

        $abono->fill($request->all());
        $abono->save();

        $gasto->refresh();
        if ($gasto->saldo <= 0) {
            $gasto->estado = 'Confirmado';
        } else {
            $gasto->estado = 'Pendiente';
        }
        $gasto->save();

        if (! $request->id && $abono->forma_pago != 'Efectivo' && $abono->forma_pago != 'Cheque') {
            $this->transaccionesService->crear(
                $abono,
                'Abono',
                'Abono de gasto: '.$gasto->tipo_documento.' #'.($gasto->referencia ? $gasto->referencia : ''),
                'Abono de Gasto'
            );
        }

        if (! $request->id && $abono->forma_pago == 'Cheque') {
            $this->chequesService->crear(
                $abono,
                $gasto->nombre_proveedor,
                'Abono de gasto: '.$gasto->tipo_documento.' #'.($gasto->referencia ? $gasto->referencia : ''),
                'Abono de Gasto'
            );
        }

        return response()->json($abono, 200);
    }
}
