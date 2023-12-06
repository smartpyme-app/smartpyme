<?php

namespace App\Http\Controllers\Api\Eventos;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\Eventos\Evento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventosController extends Controller
{

    public function index(Request $request) {
       
        $eventos = Evento::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->tipo, function($query) use ($request){
                            return $query->where('tipo', $request->tipo);
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($eventos, 200);
    }

    public function list(Request $request) {
       
        $eventos = Evento::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->tipo, function($query) use ($request){
                            return $query->where('tipo', $request->tipo);
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->get();

        $data = collect();
        foreach ($eventos as $evento) {
            $color = '';
            $textColor = '';
            if($evento->tipo == 'Pagado'){
                $color = '#367837';
            }
            elseif($evento->tipo == 'Sin confirmar'){
                $color = 'lightgray';
                $textColor = 'black';
            }
            elseif($evento->tipo == 'Pendiente'){
                $color = 'orange';
            }
            elseif($evento->tipo == 'Confirmado'){
                $color = '#3490dc';
            }
            elseif($evento->tipo == 'Cancelado'){
                $color = '#D9213A';
            }

            $data->push([
                'id' => $evento->id,
                'title' => $evento->descripcion . ' - ' . $evento->nombre_usuario,
                'start' => $evento->inicio,
                'end' => $evento->fin,
                'color' => $color,
                'textColor' => $textColor,
                'freq' => $evento->frecuencia,
                'dtstart' => $evento->inicio,
                'url' => '',
                'allDay' => false,
                'editable' => true,
                'data' => $evento
            ]);
        }

        return Response()->json($data, 200);
    }

    public function store(Request $request){

        $request->validate([
            'descripcion' => 'required|string',
            'id_cliente'  => 'required|numeric',
            'id_servicio' => 'required|numeric',
            'inicio' => 'required|date',
        ],[
            'id_cliente.required' => 'El campo cliente es obligatorio.',
            'id_servicio.required' => 'El campo servicio es obligatorio.'
        ]);
        
        if($request->id)
            $evento = Evento::findOrFail($request->id);
        else
            $evento = new Evento;

        $evento->fill($request->all());
        $evento->save();
        
        $evento->notificar();

        return Response()->json($evento, 200);
    }


    public function delete($id){

        $evento = Evento::findOrfail($id);
        $evento->delete();
        
        return Response()->json($evento, 201);
    }

}
