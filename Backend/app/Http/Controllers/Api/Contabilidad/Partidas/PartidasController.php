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
       
        $partidas = Partida::when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                ->orwhere('codigo', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate);

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
            
            $partida->fill($request->all());
            $partida->save();

            // Detalles
            foreach ($request->detalles as $item) {
                if (!isset($item['id'])) {
                    $detalle = new Detalle;
                    $item['id_partida'] = $partida->id;
                    $detalle->fill($item);
                    $detalle->save();
                }
            }

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
