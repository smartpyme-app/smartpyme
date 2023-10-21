<?php

namespace App\Http\Controllers\Api\Ventas\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Documento;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class DocumentosController extends Controller
{
    

    public function index($id) {
        
        $documento = Documento::where('cliente_id', $id)->get();
        return Response()->json($documento, 200);
    }

    public function read($id) {
        
        $documento = Documento::where('id', $id)->firstOrFail();
        return Response()->json($documento, 200);
    }

    public function filter(Request $request) {

        $documento = Documento::when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->tipo, function($query) use ($request){
                            return $query->where('tipo', $request->tipo);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($documento, 200);

    }

    public function search($txt) {

        $documento = Documento::where('nombre', 'like' ,'%' . $txt . '%')->paginate(15);
        return Response()->json($documento, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'sometimes|max:255',
            'file'          => 'required_without:url|mimes:jpeg,png,jpg,ppt,pptx,doc,docx,pdf,xls,xlsx|max:3000',
            'url'           => 'sometimes|max:255',
            'cliente_id'   => 'required',
        ]);

        if($request->id)
            $documento = Documento::findOrFail($request->id);
        else
            $documento = new Documento;


        $documento->fill($request->all());

        if ($request->hasFile('file')) {
            if ($request->id && $documento->url) {
                Storage::delete($documento->url);
            }
            $nombre = $request->file->store('clientes/documentos');
            $documento->url = $nombre;
            $documento->nombre = $request->file('file')->getClientOriginalName();
            $imageSize = $request->file('file')->getSize();
            $documento->tipo = $request->file('file')->getClientOriginalExtension();
            $documento->tamano = number_format($imageSize / 1024, 2) .' KB';
        }

        $documento->save();

        return Response()->json($documento, 200);

    }


    public function delete($id)
    {
       
        $documento = Documento::findOrFail($id);
        if ($documento->url) {
            Storage::delete($documento->url);
        }
        $documento->delete();

        return Response()->json($documento, 201);

    }


}
