<?php

namespace App\Http\Controllers\Api\Transporte\Mantenimientos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\SubCategoria;
use App\Models\Transporte\Mantenimientos\Repuesto;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Inventario;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as DetalleVenta;

use App\Imports\Productos;
use Maatwebsite\Excel\Facades\Excel;

class RepuestosController extends Controller
{
    

    public function index() {
       
        $repuestos = Repuesto::where('tipo', 'Repuesto')->with('inventarios', 'sucursales')
                                // ->whereNull('codigo')
                                ->orderBy('id','desc')->paginate(10);

        return Response()->json($repuestos, 200);

    }

    public function list() {
       
        $repuestos = Repuesto::where('tipo', 'Repuesto')->orderby('nombre')->get();

        return Response()->json($repuestos, 200);

    }


    public function porCodigo($codigo) {
       
        // $repuesto = Repuesto::where('tipo', 'Repuesto')->where('codigo', $codigo )->with('inventarios')->first();
        $repuesto = Repuesto::where('tipo', 'Repuesto')->where('codigo', $codigo )->with('inventarios')->get();

        return Response()->json($repuesto, 200);

    }

    public function read($id) {

        $repuesto = Repuesto::where('tipo', 'Repuesto')->where('id', $id)
                                ->with('inventarios', 'composiciones', 'promociones', 'imagenes', 'sucursales')
                                ->first();
        return Response()->json($repuesto, 200);

    }

    public function search($txt) {

        $repuestos = Repuesto::whereIn('tipo', ['Repuesto', 'Servicio'])->with('inventarios')
                                ->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orwhere('codigo', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($repuestos, 200);

    }

    public function searchAll($txt) {

        $repuestos = Repuesto::with('inventarios')
                                ->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orwhere('codigo', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($repuestos, 200);

    }

    public function filter(Request $request) {

            $repuestos = Repuesto::where('tipo', 'Repuesto')->with('inventarios', 'sucursales')
                                ->when($request->subcategoria_id, function($query) use ($request){
                                    return $query->where('subcategoria_id', $request->subcategoria_id);
                                })
                                ->when($request->stock_bodega, function($query) use ($request){
                                    return $query->where('inventario', true)->whereHas('inventarios', function($query){
                                        return $query->where('bodega_id', 1)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->when($request->stock_venta, function($query) use ($request){
                                    return $query->where('inventario', true)->whereHas('inventarios', function($query){
                                        return $query->where('bodega_id', 2)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->when($request->sin_control_inventario, function($query) use ($request){
                                    return $query->where('inventario', false);
                                })
                                ->when($request->sin_subcategoria, function($query) use ($request){
                                    return $query->whereNull('subcategoria_id');
                                })
                                ->when($request->sin_condigo, function($query) use ($request){
                                    return $query->whereNull('codigo');
                                })
                                ->orderBy('id','desc')->paginate(100000);

            return Response()->json($repuestos, 200);
    }

    public function store(Request $request)
    {
        if(empty($request->codigo)){
            $request['codigo'] = NULL;
        }
        
        $subcategoria = SubCategoria::find($request->subcategoria_id);
        $request['categoria_id'] = $subcategoria->categoria_id;

        $request->validate([
            'nombre'    => 'required|max:255',
            // 'codigo'    => 'nullable|unique:repuestos,codigo,'. $request->id,
            'precio'    => 'required|numeric',
            'costo'     => 'required|numeric',
            'medida'     => 'required',
            'subcategoria_id' => 'required',
            'empresa_id'    => 'required',
        ]);

        if($request->id)
            $repuesto = Repuesto::where('tipo', 'Repuesto')->findOrFail($request->id);
        else
            $repuesto = new Repuesto;
        

        $repuesto->fill($request->all());
        $repuesto->save();


        return Response()->json($repuesto, 200);

    }

    public function storeDesdeCompras(Request $request)
    {
        if(empty($request->codigo)){
            $request['codigo'] = NULL;
        }

        $subcategoria = SubCategoria::find($request->subcategoria_id);
        $request['categoria_id'] = $subcategoria->categoria_id;

        $request->validate([
            'nombre'    => 'required|max:255',
            // 'codigo'    => 'nullable|unique:repuestos,codigo,'. $request->id,
            'precio'    => 'required|numeric',
            'costo'     => 'required|numeric',
            'medida'     => 'required',
            'subcategoria_id' => 'required',
            'empresa_id'    => 'required',
        ]);

        if($request->id)
            $repuesto = Repuesto::where('tipo', 'Repuesto')->findOrFail($request->id);
        else
            $repuesto = new Repuesto;
        
        $repuesto->fill($request->all());
        $repuesto->save();

        $sucursales = \App\Models\Admin\Sucursal::all();

        foreach ($sucursales as $sucursal) {
            $repuesto_sucursal = new \App\Models\Inventario\Sucursal();
            $repuesto_sucursal->repuesto_id = $repuesto->id;
            $repuesto_sucursal->inventario = true;
            $repuesto_sucursal->bodega_venta_id = $sucursal->bodegas()->first()->id;
            $repuesto_sucursal->activo = true;
            $repuesto_sucursal->sucursal_id = $sucursal->id;
            $repuesto_sucursal->save();


            $inventario = new Inventario;
            $inventario->repuesto_id = $repuesto->id;
            $inventario->stock = 0;
            $inventario->stock_min = 10;
            $inventario->stock_max = 100;
            $inventario->nota = '';
            $inventario->bodega_id = $sucursal->bodegas()->first()->id;
            $inventario->sucursal_id = $repuesto_sucursal->id;
            $inventario->save();
            
        }

        $repuesto = Repuesto::where('tipo', 'Repuesto')->where('id', $repuesto->id)->with('inventarios')->first();

        return Response()->json($repuesto, 200);

    }

    public function delete($id)
    {
        $repuesto = Repuesto::where('tipo', 'Repuesto')->findOrFail($id);
        foreach ($repuesto->inventarios as $bodega) {
            $bodega->delete();
        }
        $repuesto->delete();

        return Response()->json($repuesto, 201);

    }

    public function precios($id)
    {
        $repuesto = Repuesto::where('tipo', 'Repuesto')->findOrFail($id);
        
        
        $ventas = DetalleVenta::where('repuesto_id', $repuesto->id)->get();

        $ventas_precios =  collect();
        $ventas_fechas =  collect();

        foreach ($ventas->unique('precio') as $venta) {
            $ventas_precios->push($venta->precio);
            $ventas_fechas->push($venta->created_at->format('d/m/Y'));
        }
        $repuesto->ventas_precios = $ventas_precios;
        $repuesto->ventas_fechas = $ventas_fechas;
        $repuesto->ventas = count($ventas);

        return Response()->json($repuesto, 201);

    }


    public function analisis(Request $request) {


            $repuestos = Repuesto::where('tipo', 'Repuesto')->when($request->nombre, function($query) use ($request){
                                        return $query->where('nombre', 'like' ,'%' . $request->nombre . '%');
                                    })
                                    ->when($request->categoria_id, function($query) use ($request){
                                        return $query->where('categoria_id', $request->categoria_id);
                                    })

                                    ->get();

            $movimientos = collect();

            $empresa = Empresa::find(1);

            foreach ($repuestos as $repuesto) {
                if ($empresa->valor_inventario == 'Promedio') {
                    $repuesto->costo = $repuesto->costo_promedio;
                }
                $utilidad = $repuesto->precio - $repuesto->costo;
                $margen = $repuesto->costo > 0 ? (round($utilidad / $repuesto->costo, 2) * 100) : null;
                $movimientos->push([
                    'nombre'        => $repuesto->nombre,
                    'nombre_categoria'        => $repuesto->nombre_categoria,
                    'nombre_subcategoria'        => $repuesto->nombre_subcategoria,
                    // 'proveedor'     => $repuesto->proveedor,
                    'precio'        => $repuesto->precio,
                    'costo'         => $repuesto->costo,
                    'utilidad'      => $utilidad,
                    'margen'        =>  $margen
                ]);
            }

            return Response()->json($movimientos, 200);
    }

    public function compras(Request $request, $id) {

        $compras = Compra::whereHas('detalles', function($q) use ($id) {
                                    $q->where('repuesto_id', $id);
                                })
                                ->orderBy('id','desc')->paginate(5);
        

        return Response()->json($compras, 200);

    }

    public function ajustes(Request $request, $id) {

        $ajustes = Ajuste::where('repuesto_id', $id)->orderBy('id','desc')->paginate(5);
        
        return Response()->json($ajustes, 200);

    }

    public function ventas(Request $request, $id) {

        $ventas = Venta::whereHas('detalles', function($q) use ($id) {
                                    $q->where('repuesto_id', $id);
                                })
                                ->orderBy('id','desc')->paginate(5);
        
        return Response()->json($ventas, 200);

    }

    public function barcode($id) {

        $repuesto = Repuesto::findOrFail($id);

        if (!$repuesto->codigo) {
            return  Response()->json(['error' => 'No se le ha guardado codigo al repuesto', 'code' => 402], 402);
        }
        
        return view('reportes.barcode', compact('repuesto'));

        
        $reportes = \PDF::loadView('reportes.barcode', compact('repuesto'))->setPaper('letter');
        return $reportes->stream();

    }

    public function vendedor() {
       
        $repuestos = Repuesto::where('tipo', 'Repuesto')->with('inventarios', 'sucursales')
                                // ->whereNull('codigo')
                                ->orderBy('id','desc')->paginate(12);

        return Response()->json($repuestos, 200);

    }

    public function vendedorBuscador($txt) {
       
        $repuestos = Repuesto::whereIn('tipo', ['Repuesto', 'Servicio'])->with('inventarios')
                                ->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orwhere('codigo', 'like' ,'%' . $txt . '%')
                                ->paginate(12);
        return Response()->json($repuestos, 200);

    }


    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ]);

        $import = new Productos();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){

      $repuestos = new ProductosExport();
      $repuestos->filter($request);

      return Excel::download($repuestos, 'repuestos.xlsx');
    }

}
