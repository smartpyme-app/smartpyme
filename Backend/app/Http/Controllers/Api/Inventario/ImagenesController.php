<?php

namespace App\Http\Controllers\Api\Inventario;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Inventario\Imagen;
use Intervention\Image\ImageManagerStatic as Image;
use App\Http\Requests\Inventario\Imagenes\StoreImagenRequest;

class ImagenesController extends Controller
{

    public function store(StoreImagenRequest $request)
    {

        if($request->id)
            $imagen = Imagen::findOrFail($request->id);
        else
            $imagen = new Imagen;

        $imagen->fill($request->all());

        if ($request->hasFile('file')) {
            if ($imagen->id && $imagen->img && $imagen->img != 'productos/default.jpg') {
                Storage::delete($imagen->img);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(750,750)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $path = "productos/{$hash}.jpg";
            $resize->save(public_path('img/'.$path), 50);
            $imagen->img = "/" . $path;
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
