<?php

namespace App\Http\Controllers\Api\Contabilidad\Partidas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use Illuminate\Support\Facades\DB;

class PartidasController extends Controller
{


    public function index(Request $request) {

        $partidas = Partida::with('detalles')->when($request->buscador, function($query) use ($request){
                                    return $query->where('concepto', 'like' ,'%' . $request->buscador . '%')
                                                ->orwhere('tipo', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->tipo, function($query) use ($request){
                                    return $query->where('tipo', $request->tipo);
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate);

        $partidas = $partidas->toArray();
        $partidas['total_pendientes'] = Partida::where('estado', 'Pendiente')->count();

        return Response()->json($partidas, 200);

    }

    public function list() {

        $partidas = Partida::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($partidas, 200);

    }

    public function read($id) {

        $partida = Partida::with('detalles')->where('id', $id)->firstOrFail();
        return Response()->json($partida, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'tipo'          => 'required|max:255',
            'concepto'      => 'required|max:255',
            'estado'        => 'required|max:255',
            'detalles'      => 'required',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {

            if($request->id)
                $partida = Partida::findOrFail($request->id);
            else
                $partida = new Partida;


            // Detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;

                $detalle['id_partida'] = $partida->id;
                $detalle->fill($det);
                $detalle->save();

                $debe = $detalle->debe ? $detalle->debe : 0;
                $haber = $detalle->haber ? $detalle->haber : 0;

                // Aplicar partida
                if(($request['estado'] == 'Aplicada') && ($partida->estado != 'Aplicada')){
                    $detalle->cuenta->increment('cargo', $debe);
                    $detalle->cuenta->increment('abono', $haber);

                    if($detalle->cuenta->naturaleza == 'Deudor'){
                        $detalle->cuenta->increment('saldo', $debe - $haber);
                    }else{
                        $detalle->cuenta->increment('saldo', $haber - $debe);
                    }

                }

                // Anular aplicacion
                if(($request['estado'] != 'Aplicada') && ($partida->estado == 'Aplicada')){
                    $detalle->cuenta->decrement('cargo', $debe);
                    $detalle->cuenta->decrement('abono', $haber);
                    if($detalle->cuenta->naturaleza == 'Deudor'){
                        $detalle->cuenta->decrement('saldo', $debe - $haber);
                    }else{
                        $detalle->cuenta->decrement('saldo', $haber - $debe);
                    }
                }
            }
            
            $partida->fill($request->all());
            $partida->save();

            DB::commit();
            return Response()->json($partida, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function delete($id)
    {
        $partida = Partida::findOrFail($id);
        $partida->delete();

        return Response()->json($partida, 201);

    }

}
