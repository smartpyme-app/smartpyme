<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Compras\Gastos\Abono;
use App\Models\Compras\Gastos\Gasto;
use JWTAuth;

use App\Exports\AbonosGastosExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Compras\Gastos\StoreAbonoGastoRequest;

class AbonosController extends Controller
{
    

    public function index(Request $request) {
       
        $abonos = Abono::with('gasto')->when($request->buscador, function($query) use ($request){
                        return $query->orwhere('id_gasto', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('concepto', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('nombre_de', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->whereHas('gasto', function($q) use ($request){
                                return $q->where('id_proveedor', $request->id_proveedor);
                            });
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->orderBy($request->orden ?? 'id', $request->direccion ?? 'desc')
                        ->orderBy('id', 'desc')
                        ->paginate($request->paginate ?? 15);

        return Response()->json($abonos, 200);
           
    }


    public function read($id) {

        $abono = Abono::findOrFail($id);
        return Response()->json($abono, 200);

    }

    public function store(StoreAbonoGastoRequest $request)
    {

        $gasto = Gasto::find($request->id_gasto);

        if($request->id)
            $abono = Abono::findOrFail($request->id);
        else
            $abono = new Abono;

        // Obtener id_empresa del gasto si no viene en el request
        if (!$request->has('id_empresa') && $gasto) {
            $request->merge(['id_empresa' => $gasto->id_empresa]);
        }
        
        $abono->fill($request->all());
        $abono->save();

        // Actualizar estado del gasto según el saldo
        if ($gasto) {
            if ($gasto->saldo <= 0) {
                $gasto->estado = 'Confirmado';
                $gasto->save();
            } else {
                $gasto->estado = 'Pendiente';
                $gasto->save();
            }
        }

        return Response()->json($abono, 200);

    }

    public function delete($id){
        $abono = Abono::findOrFail($id);
        
        // Obtener el gasto antes de eliminar el abono para actualizar su estado
        $gasto = $abono->gasto;
        
        $abono->delete();
        
        // Actualizar estado del gasto después de eliminar el abono
        if ($gasto) {
            if ($gasto->saldo <= 0) {
                $gasto->estado = 'Confirmado';
            } else {
                $gasto->estado = 'Pendiente';
            }
            $gasto->save();
        }
        
        return Response()->json($abono, 201);

    }

    public function export(Request $request){
        $abonos = new AbonosGastosExport();
        $abonos->filter($request);

        return Excel::download($abonos, 'abonos-gastos.xlsx');
    }


}

