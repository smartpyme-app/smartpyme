<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Dashboard;
use JWTAuth;

class DashboardsController extends Controller
{
    

    public function index(Request $request) {
       
        $dashboards = Dashboard::when($request->id_empresa, function($q) use ($request){
                                    $q->where('id_empresa', $request->id_empresa);
                                })
                                ->when($request->tipo, function($q) use ($request){
                                    $q->where('tipo', $request->tipo);
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('titulo', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($dashboards, 200);

    }


    public function read($id) {

        $dashboard = Dashboard::findOrFail($id);
        return Response()->json($dashboard, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo'        => 'required|max:255',
            'tipo'          => 'required|max:255',
            'codigo_embed'  => 'required|max:900',
            'id_empresa'  => 'required|numeric',
        ]);

        if($request->id)
            $dashboard = Dashboard::findOrFail($request->id);
        else
            $dashboard = new Dashboard;
        
        $dashboard->fill($request->all());
        $dashboard->save();

        return Response()->json($dashboard, 200);

    }

    public function delete($id)
    {
        $dashboard = Dashboard::findOrFail($id);
        $dashboard->delete();

        return Response()->json($dashboard, 201);

    }


}
