<?php

namespace App\Http\Controllers\Api\Inventario;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use App\Services\ShopifyImageService;
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
            $imagen->hash = $hash;
        }

        $imagen->save();

        // Sincronizar automáticamente hacia Shopify si el producto está vinculado
        try {
            $producto = Producto::withoutGlobalScope('empresa')->find($imagen->id_producto);
            if ($producto && !empty($producto->shopify_product_id)) {
                app(ShopifyImageService::class)->subirImagenAShopify($imagen);
            }
        } catch (\Throwable $t) {
            \Illuminate\Support\Facades\Log::channel('shopify')->warning('ImagenesController: error en sincronización automática a Shopify', [
                'imagen_id' => $imagen->id,
                'error' => $t->getMessage(),
            ]);
        }

        return Response()->json($imagen, 200);

    }

    public function delete($id)
    {
        $imagen = Imagen::findOrFail($id);

        // Si la imagen está en Shopify, eliminarla también de Shopify
        try {
            if (!empty($imagen->shopify_image_id)) {
                app(ShopifyImageService::class)->eliminarImagenDeShopify($imagen);
            }
        } catch (\Throwable $t) {
            \Illuminate\Support\Facades\Log::channel('shopify')->warning('ImagenesController: error al eliminar imagen de Shopify', [
                'imagen_id' => $imagen->id,
                'error' => $t->getMessage(),
            ]);
        }

        if ($imagen->img)
            Storage::delete($imagen->img);
        $imagen->delete();

        return Response()->json($imagen, 201);

    }


}
