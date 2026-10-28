<?php

namespace App\Http\Controllers\Api\Licencias;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\User as Usuario;
use App\Models\Licencias\Licencia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Auth;
use App\Http\Requests\Licencias\StoreLicenciaRequest;
use App\Support\AdminUsuariosAlcance;

class LicenciasController extends Controller
{

    public function index(Request $request) {
       
        $licencias = Licencia::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('nombre', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('created_at', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('created_at', '<=', $request->fin);
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($licencias, 200);
    }

    public function read($id) {

        $licencia = Licencia::with('empresas.empresa')->where('id', $id)->firstOrFail();

        return Response()->json($licencia, 200);

    }

    public function store(StoreLicenciaRequest $request){

        if($request->id)
            $licencia = Licencia::findOrFail($request->id);
        else
            $licencia = new Licencia;
        
        $licencia->fill($request->all());
        $licencia->save();
        
        return Response()->json($licencia, 200);
    }


    public function delete($id){

        $licencia = Licencia::findOrfail($id);
        $licencia->delete();
        
        return Response()->json($licencia, 201);
    }

    public function usuarios(Request $request) {
        $actor = Auth::user();

        $query = Usuario::with('empresa', 'roles')
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            });

        AdminUsuariosAlcance::aplicarAlQuery($query, $actor, $request);
        AdminUsuariosAlcance::aplicarBuscador($query, $request->buscador);

        $usuarios = $query
            ->orderBy($request->orden, $request->direccion)
            ->paginate($request->paginate);

        return Response()->json($usuarios, 200);

    }

}
