<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Abono;
use App\Models\Ventas\Venta;
use Barryvdh\DomPDF\Facade as PDF;
use JWTAuth;

class AbonosController extends Controller
{
    

    public function index() {
       
        $abono = Abono::orderBy('id','desc')->paginate(10);
        return Response()->json($abono, 200);

    }


    public function read($id) {

        $abono = Abono::findOrFail($id);
        return Response()->json($abono, 200);

    }

    public function store(Request $request)
    {

        $venta = Venta::find($request->id_venta);

        $request->validate([
            'fecha'       => 'required|date',
            'concepto'    => 'required|max:255',
            'nombre_de'    => 'required|max:255',
            'estado'      => 'required|max:255',
            'forma_pago' => 'required|max:255',
            'total'       => 'required|numeric',
            'id_venta'    => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_sucursal'    => 'required|numeric',
        ]);

        if($request->id)
            $abono = Abono::findOrFail($request->id);
        else
            $abono = new Abono;

        
        $abono->fill($request->all());
        $abono->save();

        if ($venta && $venta->saldo <= 0) {
            $venta->estado = 'Pagada';
            $venta->save();
        }

        if ($venta && $venta->saldo > 0) {
            $venta->estado = 'Pendiente';
            $venta->save();
        }

        return Response()->json($abono, 200);

    }

    public function delete($id){
        $abono = Abono::findOrFail($id);
        $abono->delete();
        
        return Response()->json($abono, 201);

    }

    public function print($id){

        $recibo = Abono::where('id', $id)->first();
        $venta = Venta::where('id', $recibo->id_venta)->first();

        if(JWTAuth::parseToken()->authenticate()->id_empresa == 38){
            $pdf = PDF::loadView('reportes.recibos.velo-recibo', compact('venta', 'recibo'));
            $pdf->setPaper('US Letter', 'portrait');
        }else{
            $pdf = PDF::loadView('reportes.recibos.recibo', compact('venta', 'recibo'));
            $pdf->setPaper('US Letter', 'portrait');  
        }     

        return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');
    }


}
