<?php

namespace App\Http\Controllers\Api\Inventario;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Inventario\Imagen;
use Intervention\Image\ImageManagerStatic as Image;

class ImagenesController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            // 'file'          => 'required_without:img|image|mimes:jpeg,png,jpg|max:3000|dimensions:ratio=1/1',
            'file'          => 'required_without:img|image|mimes:jpeg,png,jpg|max:2000',
            'img'           => 'sometimes|max:255',
            'id_producto'   => 'required',
        ]);

        if($request->id)
            $imagen = Imagen::findOrFail($request->id);
        else
            $imagen = new Imagen;

        $imagen->fill($request->all());

        if ($request->hasFile('file')) {
            if ($imagen->id && $imagen->img && $imagen->img != 'productos/default.jpg') {
                $oldPath = 'img' . $imagen->img;
                Storage::disk('s3-public')->delete($oldPath);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(750,750)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $s3Path = "img/productos/{$hash}.jpg";
            Storage::disk('s3-public')->put($s3Path, $resize->__toString());
            $imagen->img = "productos/{$hash}.jpg";
        }

        $imagen->save();

        return Response()->json($imagen, 200);

    }

    public function delete($id)
    {
        $imagen = Imagen::findOrFail($id);
        if ($imagen->img) {
            $s3Path = 'img/' . $imagen->img;
            Storage::disk('s3-public')->delete($s3Path);
        }
        $imagen->delete();

        return Response()->json($imagen, 201);

    }


}
