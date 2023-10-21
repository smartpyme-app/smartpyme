<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Notificacion;

class NotificacionesController extends Controller
{
    

    public function index() {
       
        $notificaciones = Notificacion::orderBy('id','desc')->paginate(10);

        return Response()->json($notificaciones, 200);

    }


    public function read($id) {

        $notificacion = Notificacion::findOrFail($id);
        return Response()->json($notificacion, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'titulo'        => 'required|max:255',
            'descripcion'   => 'required|max:500',
            'tipo'          => 'required|max:255',
            'categoria'     => 'required|max:255',
            'prioridad'    => 'required|max:255',
            'empresa_id'    => 'required|numeric',
            'sucursal_id'    => 'required|numeric'
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
