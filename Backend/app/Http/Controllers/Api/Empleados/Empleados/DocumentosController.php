<?php

namespace App\Http\Controllers\Api\Empleados\Empleados;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Empleados\Empleados\Documento;

class DocumentosController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'sometimes|max:255',
            'file'          => 'required_without:url|mimes:jpeg,png,jpg,ppt,pptx,doc,docx,pdf,xls,xlsx|max:3000',
            'url'           => 'sometimes|max:255',
            'empleado_id'   => 'required',
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
            $nombre = $request->file->store('documentos');
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
        if ($documento->url)
            Storage::delete($documento->url);
        $documento->delete();

        return Response()->json($documento, 201);

    }


}
