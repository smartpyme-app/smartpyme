<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Notificacion;

class NotificacionesController extends Controller
{
    

    public function index(Request $request) {
       
        $notificaciones = Notificacion::when($request->tipo, function($q) use ($request){
                                $q->where('tipo', $request->tipo);
                            })
                            ->when($request->categoria, function($q) use ($request){
                                $q->where('categoria', $request->categoria);
                            })
                            ->when($request->leido !== null, function($q) use ($request){
                                $q->where('leido', !!$request->leido);
                            })
                            ->when($request->buscador, function($query) use ($request){
                                return $query->where('titulo', 'like' ,'%' . $request->buscador . '%')
                                             ->orwhere('descripcion', 'like' ,"%" . $request->buscador . "%");
                            })
                            ->orderBy($request->orden, $request->direccion)
                            ->paginate($request->paginate);

        return Response()->json($notificaciones, 200);

    }


    public function read($id) {

        $notificacion = Notificacion::findOrFail($id);
        return Response()->json($notificacion, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'descripcion'   => 'required|max:500',
            'titulo'        => 'sometimes|max:255',
            'tipo'          => 'required|max:255',
            'categoria'     => 'sometimes|max:255',
            'prioridad'    => 'required|max:255',
            'id_empresa'    => 'required|numeric',
            // 'id_sucursal'    => 'required|numeric'
        ]);

        if($request->id)
            $notificacion = Notificacion::findOrFail($request->id);
        else
            $notificacion = new Notificacion;

        
        $notificacion->fill($request->all());
        $notificacion->save();

        return Response()->json($notificacion, 200);

    }

    public function delete($id){
        $notificacion = Notificacion::findOrFail($id);
        $notificacion->delete();
        
        return Response()->json($notificacion, 201);

    }


}
