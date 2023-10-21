<?php

namespace App\Http\Controllers\Api\Inventario;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Inventario\Imagen;

class ImagenesController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'file'          => 'required_without:img|image|mimes:jpeg,png,jpg|max:3000|dimensions:ratio=1/1',
            'img'           => 'sometimes|max:255',
            'producto_id'   => 'required',
        ]);

        if($request->id)
            $imagen = Imagen::findOrFail($request->id);
        else
            $imagen = new Imagen;

        $imagen->fill($request->all());

        if ($request->hasFile('file')) {
            if ($request->id && $imagen->img) {
                Storage::delete($imagen->img);
            }
           $nombre = $request->file->store('productos');
           $imagen->img = $nombre;
        }

        $imagen->save();

        return Response()->json($imagen, 200);

    }

    public function delete($id)
    {
        $imagen = Imagen::findOrFail($id);
        if ($imagen->img)
            Storage::delete($imagen->img);
        $imagen->delete();

        return Response()->json($imagen, 201);

    }


}
