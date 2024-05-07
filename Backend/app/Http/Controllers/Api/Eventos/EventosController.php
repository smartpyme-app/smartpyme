<?php

namespace App\Http\Controllers\Api\Eventos;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\Eventos\Evento;
use App\Models\Eventos\Detalle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class EventosController extends Controller
{

    public function index(Request $request) {
       
        $eventos = Evento::with('cliente', 'productos')->when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('inicio', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fin', '<=', $request->fin);
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
                            return $query->whereBetween('inicio', [$request->inicio, $request->fin]);
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

        $eventosList = collect();
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

            $data = new stdClass();

            $data->id = $evento->id;
            $data->title = $evento->descripcion . ' - ' . $evento->nombre_usuario;
            $data->start = $evento->inicio;
            $data->end = $evento->fin;
            $data->color = $color;
            $data->textColor = $textColor;
            if($evento->frecuencia){
                $data->rrule = [
                    'freq' => $evento->frecuencia ? $evento->frecuencia : 'No',
                    'dtstart' => $evento->inicio,
                    'until' => $evento->frecuencia_fin,
                ];
            }
            $data->url = '';
            // $data->allDay = false;
            // $data->editable = true;
            $data->data = $evento;

            $eventosList->push($data);
        }

        return Response()->json($eventosList, 200);
    }

    public function read($id) {

        $evento = Evento::with('productos')->where('id', $id)->firstOrFail();
        return Response()->json($evento, 200);

    }

    public function store(Request $request){

        $request->validate([
            'descripcion' => 'required|string',
            'id_cliente'  => 'required|numeric',
            // 'id_servicio' => 'required|numeric',
            'frecuencia_fin' => 'required_with:frecuencia',
            'inicio' => 'required|date',
        ],[
            'id_cliente.required' => 'El campo cliente es obligatorio.',
            'id_servicio.required' => 'El campo servicio es obligatorio.',
            'frecuencia_fin.required_with' => 'El campo terminar de repetir es obligatorio.'
        ]);
        
        DB::beginTransaction();
         
        try {

            if($request->id)
                $evento = Evento::findOrFail($request->id);
            else
                $evento = new Evento;


            if($request->id && ($request['tipo'] != $evento->tipo) && ($request['tipo'] == 'Confirmado')){
                $evento->estadoVerificarFrecuencia('Confirmado');
            }
            elseif($request->id && ($request['tipo'] != $evento->tipo) && ($request['tipo'] == 'Sin confirmar')){
                $evento->estadoVerificarFrecuencia('Sin confirmar');
            }
            elseif($request->id && ($request['tipo'] != $evento->tipo) && ($request['tipo'] == 'Cancelado')){
                $evento->estadoVerificarFrecuencia('Cancelado');
            }else{
                $evento->fill($request->all());
                $evento->save();
            }
            
        // Guardar detalles
            foreach ($request->productos as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;

                $det['id_evento'] = $evento->id;
                $detalle->fill($det);
                $detalle->save();
            }
            
            $evento->notificar();

        DB::commit();
        return Response()->json($evento, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }


    public function delete($id){

        $evento = Evento::findOrfail($id);
        $evento->delete();
        
        return Response()->json($evento, 201);
    }

}
