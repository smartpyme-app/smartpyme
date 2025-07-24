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

    public function store(Request $request){

        $request->validate([
            'num_licencias' => 'required|numeric',
            'id_empresa'  => 'required|numeric',
        ],[
            'id_empresa.required' => 'El campo empresa es obligatorio.',
        ]);

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
        $licencia = Auth::user()->empresa()->first()->licencia()->first();
        $empresas = $licencia->empresas()->pluck('id_empresa')->toArray();;

        $usuarios = Usuario::with('empresa','roles')
                                ->whereIn('id_empresa', $empresas)
                                ->when($request->estado !== null, function($q) use ($request){
                                    $q->where('enable', !!$request->estado);
                                })
                                ->when($request->id_empresa, function($q) use ($request){
                                    $q->where('id_empresa', $request->id_empresa);
                                })
                                ->when($request->id_sucursal, function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal);
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('name', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('email', 'like' ,"%" . $request->buscador . "%");
                                })
                                // ->orderBy('enable', 'desc')
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($usuarios, 200);

    }

}
