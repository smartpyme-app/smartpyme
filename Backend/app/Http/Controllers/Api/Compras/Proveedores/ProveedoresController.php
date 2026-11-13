<?php

namespace App\Http\Controllers\Api\Compras\Proveedores;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Compra;

use App\Imports\ProveedoresPersonas;
use App\Imports\ProveedoresEmpresas;
use App\Exports\ProveedoresPersonasExport;
use App\Exports\ProveedoresEmpresasExport;
use App\Exports\ProveedoresPersonasPlantillaExport;
use App\Exports\ProveedoresEmpresasPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Compras\Proveedores\StoreProveedorRequest;
use App\Http\Requests\Compras\Proveedores\ImportProveedoresRequest;

class ProveedoresController extends Controller
{


    public function index(Request $request) {

        $proveedores = Proveedor::withSum('compras', 'total')
                    ->when($request->buscador, function($query) use ($request){
                        return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                    ->orwhere('nombre_empresa', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('nombre_comercial', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('nit', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('giro', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('telefono', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('ncr', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('dui', 'like',  '%'. $request->buscador .'%');
                    })
                    ->when($request->nombre, function($q) use ($request){
                        $q->where('nombre', $request->nombre);
                    })
                    ->when($request->apellido, function($q) use ($request){
                        $q->where('apellido', $request->apellido);
                    })
                    ->when($request->estado !== null, function($q) use ($request){
                        $q->where('enable', !!$request->estado);
                    })
                    ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                    ->paginate($request->paginate);

        return Response()->json($proveedores, 200);

    }

    public function list(Request $request) {

        $proveedores = Proveedor::query()
            ->where('enable', true)
            ->orderBy('nombre', 'asc');

        if ($request->boolean('minimal')) {
            $proveedores = $proveedores
                ->withoutEagerLoads()
                ->select(['id', 'tipo', 'nombre', 'apellido', 'nombre_empresa', 'nombre_comercial'])
                ->toBase()
                ->get();

            foreach ($proveedores as $proveedor) {
                $proveedor->nombre_completo = $proveedor->nombre.' '.($proveedor->apellido ?: '');
                unset($proveedor->apellido);
            }
        } else {
            $proveedores = $proveedores->get();
        }

        return Response()->json($proveedores, 200);

    }

    public function buscarEmisor(Request $request)
    {
        $nit = trim((string) $request->input('nit', ''));
        $ncr = trim((string) $request->input('ncr', ''));
        $dui = trim((string) $request->input('dui', ''));
        $nombre = trim((string) $request->input('nombre', ''));

        $base = Proveedor::query()->select([
            'id', 'tipo', 'nombre', 'apellido', 'nombre_empresa', 'nombre_comercial',
            'nit', 'ncr', 'dui', 'tipo_contribuyente',
        ]);

        $proveedor = null;
        foreach (['nit' => $nit, 'ncr' => $ncr, 'dui' => $dui] as $columna => $valor) {
            if ($valor === '') {
                continue;
            }
            $proveedor = (clone $base)->where($columna, $valor)->first();
            if ($proveedor) {
                break;
            }
        }

        if (!$proveedor && $nombre !== '') {
            $proveedor = (clone $base)->where(function ($q) use ($nombre) {
                $q->where('nombre_empresa', $nombre)->orWhere('nombre', $nombre);
            })->first();
        }

        return Response()->json($proveedor, 200);
    }

    public function buscar(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return Response()->json([], 200);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $proveedores = Proveedor::query()
            ->where('enable', true)
            ->where(function ($q) use ($like) {
                $q->where('nombre', 'like', $like)
                    ->orWhere('apellido', 'like', $like)
                    ->orWhere('nombre_empresa', 'like', $like)
                    ->orWhere('nombre_comercial', 'like', $like)
                    ->orWhere('nit', 'like', $like)
                    ->orWhere('ncr', 'like', $like);
            })
            ->select(['id', 'tipo', 'nombre', 'apellido', 'nombre_empresa', 'nombre_comercial'])
            ->orderBy('nombre')
            ->limit(20)
            ->get()
            ->map(function ($proveedor) {
                return [
                    'id' => $proveedor->id,
                    'tipo' => $proveedor->tipo,
                    'nombre' => $proveedor->nombre,
                    'nombre_empresa' => $proveedor->nombre_empresa,
                    'nombre_comercial' => $proveedor->nombre_comercial,
                    'nombre_completo' => $proveedor->nombre_completo,
                ];
            })
            ->values();

        return Response()->json($proveedores, 200);
    }


    public function read($id) {

        $proveedor = Proveedor::findOrFail($id);
        $proveedor->num_compras = $proveedor->compras()->count();

        return Response()->json($proveedor, 200);

    }


    public function store(StoreProveedorRequest $request)
    {

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

    public function importPersonas(Request $request){

        $request->validate([
            'file' => 'required|file',
        ], [
            'file.required' => 'El documento es obligatorio.',
            'file.file' => 'Debe enviar un archivo válido.',
        ]);

        $import = new ProveedoresPersonas();
        Excel::import($import, $request->file('file'));

        return Response()->json($import->getRowCount(), 200);

    }

    public function importEmpresas(Request $request){

        $request->validate([
            'file' => 'required|file',
        ], [
            'file.required' => 'El documento es obligatorio.',
            'file.file' => 'Debe enviar un archivo válido.',
        ]);

        $import = new ProveedoresEmpresas();
        Excel::import($import, $request->file('file'));

        return Response()->json($import->getRowCount(), 200);

    }

    public function exportPersonas(Request $request){

      $proveedores = new ProveedoresPersonasExport();
      $proveedores->filter($request);

      return Excel::download($proveedores, 'proveedores-personas.xlsx');
    }

    public function exportEmpresas(Request $request){

      $proveedores = new ProveedoresEmpresasExport();
      $proveedores->filter($request);

      return Excel::download($proveedores, 'proveedores-empresas.xlsx');
    }

    public function downloadPlantillaPersonas()
    {
        $export = new ProveedoresPersonasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_proveedores-personas.xlsx');
    }

    public function downloadPlantillaEmpresas()
    {
        $export = new ProveedoresEmpresasPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_proveedores-empresas.xlsx');
    }



}
