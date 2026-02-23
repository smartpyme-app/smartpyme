<?php

namespace App\Http\Controllers\Api\Licencias;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Licencias\Empresa;
use Auth;

class EmpresasController extends Controller
{

    public function index(Request $request) {
       
        $empresas = Empresa::when($request->buscador, function($query) use ($request){
                        return $query->whereHas('empresa', function($q) use ($request){
                                return $q->where('nombre', 'like', '%'.$request->buscador.'%');
                            });
                        })
                    ->when($request->id_licencia, function($query) use ($request){
                        return $query->where('id_licencia', $request->id_licencia);
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($empresas, 200);
    }

    public function list() {
        $licencia = Auth::user()->empresa()->first()->licencia()->first();
        $empresas = Empresa::where('id_licencia', $licencia->id)->get();

        return Response()->json($empresas, 200);
    }

    public function store(Request $request){

        $this->validate($request, [
            'id_licencia' => 'required|numeric',
            'id_empresa' => 'required|numeric',
        ]);

        if($request->id){
            $empresa = Empresa::findOrFail($request->id);
        }
        else{

            $empresa = new Empresa;
            $existe = Empresa::where('id_empresa', $request->id_empresa)->where('id_licencia', $request->id_licencia)->first();

            if($existe)
                return  Response()->json(['error' => 'Ya ha sido agregada la empresa', 'code' => 400], 400);
        }

        $empresa->fill($request->all());
        $empresa->save();

        return Response()->json($empresa->load('empresa'), 200);

    }


    public function delete($id){
        $empresa = Empresa::findOrfail($id);
        $empresa->delete();

        return Response()->json($empresa, 201);
    }
}
