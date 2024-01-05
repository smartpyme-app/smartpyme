<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Documento;

class DocumentosController extends Controller
{
    

    public function index() {
       
        $documentos = Documento::orderBy('id_sucursal', 'asc')->get();
        
        return Response()->json($documentos, 200);

    }

    public function list() {
       
        $documentos = Documento::where('activo', true)->get(); 
        
        return Response()->json($documentos, 200);

    }


    public function read($id) {

        $documento = Documento::findOrFail($id);
        return Response()->json($documento, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'        => 'required|max:255',
            'correlativo'   => 'sometimes|max:255',
            'rangos'        => 'sometimes|max:255',
            'numero_autorizacion'         => 'sometimes|max:255',
            'resolucion'         => 'sometimes|max:255',
            'nota'         => 'sometimes|max:500',
            'id_empresa'    => 'required|numeric',
            'id_sucursal'    => 'required|numeric'
        ]);

        if($request->id)
            $documento = Documento::findOrFail($request->id);
        else
            $documento = new Documento;


        // Solo colocar un documento predeterminado por sucursal
        if($request->id){
            
            $documentos = Documento::where('id_sucursal', $documento->id_sucursal)
                                    ->where('predeterminado', true)
                                    ->where('id', '!=' ,$documento->id)
                                    ->get();

            foreach ($documentos as $doc) {
                $doc->predeterminado = false;
                $doc->save();
            }
        }

        
        $documento->fill($request->all());
        $documento->save();

        return Response()->json($documento, 200);

    }

    public function delete($id){
        $documento = Documento::findOrFail($id);
        $documento->delete();
        
        return Response()->json($documento, 201);

    }


}
