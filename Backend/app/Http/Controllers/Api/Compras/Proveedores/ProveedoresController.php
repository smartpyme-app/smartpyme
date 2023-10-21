<?php

namespace App\Http\Controllers\Api\Compras\Proveedores;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Compra;

use App\Imports\Proveedores;
use Maatwebsite\Excel\Facades\Excel;

class ProveedoresController extends Controller
{
    

    public function index() {
       
        $proveedores = Proveedor::orderBy('id','desc')->paginate(10);

        foreach ($proveedores as $proveedor) {
            $compras = $proveedor->compras();
            $proveedor->num_compras = $compras->count();
            $proveedor->num_compras_pendientes = $compras->where('estado', 'Pendiente')->count();
            $proveedor->pago_pendiente = $compras->where('estado', 'Pendiente')->get()->sum('total');
        }

        return Response()->json($proveedores, 200);

    }


    public function search($txt) {

        $proveedores = Proveedor::where('nombre', 'like' ,'%' . $txt . '%')
                            ->orWhere('etiquetas', 'like' ,'%' . $txt . '%')
                            ->orWhere('registro', 'like' ,'%' . $txt . '%')
                            ->paginate(10);

        return Response()->json($proveedores, 200);

    }

    public function filter(Request $request) {

        if ($request->estado != '') {
            if ($request->estado == 'con') {
                $proveedores = Proveedor::wherehas('compras', function($q){
                                        $q->where('estado', 'Pendiente');
                                    })->orderBy('id','desc')->paginate(100000);
            }else{
                $proveedores = Proveedor::whereDoesntHave('compras', function($q){
                                        $q->where('estado', 'Pendiente');
                                    })->orderBy('id','desc')->paginate(100000);
            }
        }else{
            $proveedores = Proveedor::whereBetween('created_at', [$star, $end])->orderBy('id','desc')->paginate(100000);
        }

        return Response()->json($proveedores, 200);
    }

    public function read($id) {

        $proveedor = Proveedor::findOrFail($id);
        $proveedor->num_compras = $proveedor->compras()->count();

        return Response()->json($proveedor, 200);

    }


    public function store(Request $request)
    {

        $request->validate([
            'nombre'    => 'required|max:255',
            'registro'  => 'nullable|unique:proveedores,registro,'. $request->id,
            'dui'       => 'nullable|unique:proveedores,dui,'. $request->id,
            'nit'       => 'nullable|unique:proveedores,nit,'. $request->id,
            'usuario_id'     => 'required|integer|exists:users,id',
            'empresa_id'     => 'required|integer|exists:empresas,id',
        ]);

        if($request->id)
            $proveedor = Proveedor::findOrFail($request->id);
        else
            $proveedor = new Proveedor;
        
        $proveedor->fill($request->all());
        $proveedor->save();

        return Response()->json($proveedor, 200);

    }

    public function delete($id)
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->delete();

        return Response()->json($proveedor, 201);

    }

    public function compras($id) {

        $compras = Compra::where('proveedor_id', $id)->orderBy('estado', 'asc')->paginate(10);

        return Response()->json($compras, 200);

    }

    public function comprasFilter(Request $request) {

        $compras = Compra::where('proveedor_id', $request->id)
                    ->when($request->estado, function($query) use ($request){
                        return $query->where('estado', $request->estado);
                    })
                    // ->when($request->forma_de_pago, function($query) use ($request){
                    //     return $query->where('forma_de_pago', $request->forma_de_pago);
                    // })
                    ->orderBy('id','desc')->paginate(100000);

        return Response()->json($compras, 200);

    }

    public function list() {
       
        $proveedores = Proveedor::orderBy('nombre','asc')->get();

        return Response()->json($proveedores, 200);

    }

    public function cxp() {
       
        $proveedores = Proveedor::where('id','!=', 1)
                        ->whereRaw('proveedores.id in (select proveedor_id from compras where estado = ?)', ['Pendiente'])
                        ->paginate(10);

        foreach ($proveedores as $proveedor) {
            $proveedor->num_compras_pendientes = $proveedor->comprasPendientes->count();
            $proveedor->pago_pendiente = $proveedor->comprasPendientes->sum('total');
        }

        return Response()->json($proveedores, 200);

    }

    public function cxpBuscar($txt) {
       
        $proveedores = Proveedor::where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('registro', 'like' , $txt . '%')
                        ->orWhereRaw('REPLACE(registro, "-", "") like "'.$txt.'"')
                        ->whereHas('compras', function($q){
                            $q->where('estado', 'Pendiente');
                        })
                        ->paginate(10);

        return Response()->json($proveedores, 200);

    }

    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ]);

        $import = new Proveedores();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){

      $proveedores = new ProveedoresExport();
      $proveedores->filter($request);

      return Excel::download($proveedores, 'proveedores.xlsx');
    }



}
