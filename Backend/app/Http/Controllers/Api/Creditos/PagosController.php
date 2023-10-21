<?php

namespace App\Http\Controllers\Api\Creditos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Creditos\Pago;
use App\Models\Creditos\Credito;


class PagosController extends Controller
{

    public function index() {

        $pagos = Pago::orderBy('fecha','desc')->with('credito')->paginate(10);
        return Response()->json($pagos, 200);

    }
    
    public function read($id) {

        $pago = Pago::where('id', $id)->firstOrFail();
        return Response()->json($pago, 200);

    }

    public function search($txt) {

        $pagos = Pago::where('categoria_id', '!=', 1)->where('nombre', 'like' ,'%' . $txt . '%')->paginate(10);
        return Response()->json($pagos, 200);

    }


    public function store(Request $request)
    {

        $request->validate([
            'fecha'         => 'required|max:255',
            'credito_id'    => 'required|numeric',
            'metodo_pago'   => 'required|max:255',
            'saldo_inicial' => 'required|numeric',
            'cuota'         => 'required|numeric',
            'interes'       => 'required|numeric',
            'mora'          => 'sometimes|numeric',
            'descuento'     => 'sometimes|numeric',
            'comision'      => 'sometimes|numeric',
            'seguro'        => 'sometimes|numeric',
            'saldo_final'   => 'required|numeric',
            'usuario_id'    => 'required|numeric',

        ]);
        
        $credito = Credito::findOrFail($request->credito_id);

        if (round($request->cuota,2) > $credito->saldo) {
            return  Response()->json(['error' => 'Cuota mayor que el saldo', 'code' => 400], 400);
        }

        if($request->id)
            $pago = Pago::findOrFail($request->id);
        else
            $pago = new Pago;

        $pago->fill($request->all());
        $pago->save();

        // Actualizar venta
        $credito = Credito::findOrFail($request->credito_id);

        if (round($credito->saldo, 2) == 0) {
            $credito->venta->update(['estado' => 'Pagada']);
        }

        return $credito->venta;

        return Response()->json($pago, 200);

    }

    public function delete($id)
    {
        $pago = Pago::findOrFail($id);
        $pago->delete();

        return Response()->json($pago, 201);

    }

}
