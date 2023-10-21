<?php

namespace App\Http\Controllers\Api\Inventario\Categorias;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\SubCategoria;

use App\Imports\SubCategorias;
use Maatwebsite\Excel\Facades\Excel;

class SubCategoriasController extends Controller
{
    

    public function index() {
       
        $subcategorias = SubCategoria::orderby('nombre', 'asc')->get();

        return Response()->json($subcategorias, 200);

    }


    public function read($id) {

        $subcategoria = SubCategoria::findOrFail($request->id);
        return Response()->json($subcategoria, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'categoria_id'  => 'required|numeric',
        ]);

        if($request->id)
            $subcategoria = SubCategoria::findOrFail($request->id);
        else
            $subcategoria = new SubCategoria;

        $subcategoria->fill($request->all());
        $subcategoria->save();

        if ($request->tipo_comision) {
            foreach ($subcategoria->productos as $producto) {
                $producto->tipo_comision = $request->tipo_comision;
                $producto->comision = $request->comision ? $request->comision : 0;
                $producto->save();
            }
        }

        return Response()->json($subcategoria, 200);

    }

    public function delete($id)
    {
        $subcategoria = SubCategoria::findOrFail($id);
        $subcategoria->delete();

        return Response()->json($subcategoria, 201);

    }


    public function change(Request $request){

        $request->validate([
            'subcategoria_anterior'  => 'required|numeric',
            'subcategoria_nueva'  => 'required|numeric',
        ]);

        $subcategoriaAnterior = SubCategoria::findOrFail($request->subcategoria_anterior);
        $subcategoriaNueva = SubCategoria::findOrFail($request->subcategoria_nueva);

        foreach ($subcategoriaAnterior->productos as $producto) {
            $producto->subcategoria_id = $request->subcategoria_nueva;
            $producto->categoria_id = $subcategoriaNueva->categoria_id;
            $producto->save();
        }

        return Response()->json($subcategoriaAnterior, 200);


    }

    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ]);

        $import = new SubCategorias();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){

      $categorias = new CategoriasExport();
      $categorias->filter($request);

      return Excel::download($categorias, 'categorias.xlsx');
    }


}
