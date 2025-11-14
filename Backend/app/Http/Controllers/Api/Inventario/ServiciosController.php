<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto as Servicio;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Exports\ServiciosExport;
use App\Exports\ServiciosPlantillaExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Servicios;

class ServiciosController extends Controller
{
    

    public function index(Request $request) {

        $servicios = Servicio::where('tipo', 'Servicio')->with('inventarios')
                                ->when($request->id_categoria, function($query) use ($request){
                                    return $query->where('id_categoria', $request->id_categoria);
                                })
                                ->when($request->id_sucursal, function($q) use ($request){
                                    $q->whereHas('inventarios', function($q) use ($request){
                                        return $q->where('id_sucursal', $request->id_sucursal);
                                    });
                                })
                                ->when($request->id_proveedor, function($q) use ($request){
                                    $q->whereHas('proveedores', function($q) use ($request){
                                        return $q->where('id_proveedor', $request->id_proveedor);
                                    });
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('codigo', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('barcode', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('etiquetas', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('marca', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('descripcion', 'like' ,"%" . $request->buscador . "%");
                                })
                                ->orderBy('enable', 'desc')
                                ->orderBy($request->orden ? $request->orden : 'nombre', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate ? $request->paginate : 10);

        return Response()->json($servicios, 200);

    }

    public function list() {
       
        $servicios = Servicio::where('tipo', 'Servicio')
                                ->orderby('nombre')
                                ->where('enable', true)
                                ->get();

        return Response()->json($servicios, 200);

    }

    public function read($id) {

        $servicio = Servicio::where('tipo', 'Servicio')
                        ->where('id', $id)
                        ->with('inventarios', 'composiciones', 'precios.usuarios', 'imagenes', 'proveedores')
                        ->first();
        return Response()->json($servicio, 200);

    }

    public function store(Request $request)
    {
        if(empty($request->codigo)){
            $request['codigo'] = NULL;
        }

        $request->validate([
            'nombre'    => 'required|max:255',
            // 'codigo'    => 'nullable|unique:productos,codigo,'. $request->id,
            'precio'    => 'required|numeric',
            'costo'     => 'required|numeric',
            'categoria_id'    => 'required',
            'empresa_id'    => 'required',
        ]);

        if($request->id)
            $producto = Servicio::where('tipo', 'Servicio')->findOrFail($request->id);
        else
            $producto = new Servicio;
        
        $producto->fill($request->all());
        $producto->save();

        return Response()->json($producto, 200);

    }

    public function delete($id)
    {
        $producto = Servicio::where('tipo', 'Servicio')->whereDoesntHave('ventas')->find($id);

        if (!$producto)
            return Response()->json(['error' => ['No se ha encontrado o no se puede eliminar'], 'code' => 422], 422);

        $producto->delete();

        return Response()->json($producto, 201);

    }

    public function precios($id)
    {
        $producto = Servicio::where('tipo', 'Servicio')->findOrFail($id);
        
        
        $ventas = DetalleVenta::where('producto_id', $producto->id)->get();

        $ventas_precios =  collect();
        $ventas_fechas =  collect();

        foreach ($ventas->unique('precio') as $venta) {
            $ventas_precios->push($venta->precio);
            $ventas_fechas->push($venta->created_at->format('d/m/Y'));
        }
        $producto->ventas_precios = $ventas_precios;
        $producto->ventas_fechas = $ventas_fechas;
        $producto->ventas = count($ventas);

        return Response()->json($producto, 201);

    }


    public function analisis(Request $request) {


            $servicios = Servicio::where('tipo', 'Servicio')->when($request->nombre, function($query) use ($request){
                                        return $query->where('nombre', 'like' ,'%' . $request->nombre . '%');
                                    })
                                    ->when($request->categoria_id, function($query) use ($request){
                                        return $query->where('categoria_id', $request->categoria_id);
                                    })

                                    ->get();

            $movimientos = collect();

            $empresa = Empresa::find(1);

            foreach ($servicios as $producto) {
                if ($empresa->valor_inventario == 'Promedio') {
                    $producto->costo = $producto->costo_promedio;
                }
                $utilidad = $producto->precio - $producto->costo;
                $margen = $producto->costo > 0 ? (round($utilidad / $producto->costo, 2) * 100) : null;
                $movimientos->push([
                    'nombre'        => $producto->nombre,
                    'nombre_categoria'        => $producto->nombre_categoria,
                    'nombre_subcategoria'        => $producto->nombre_subcategoria,
                    // 'proveedor'     => $producto->proveedor,
                    'precio'        => $producto->precio,
                    'costo'         => $producto->costo,
                    'utilidad'      => $utilidad,
                    'margen'        =>  $margen
                ]);
            }

            return Response()->json($movimientos, 200);
    }

    public function compras(Request $request, $id) {

        $compras = Compra::whereHas('detalles', function($q) use ($id) {
                                    $q->where('producto_id', $id);
                                })
                                ->orderBy('id','desc')->paginate(5);
        

        return Response()->json($compras, 200);

    }

    public function ajustes(Request $request, $id) {

        $ajustes = Ajuste::where('producto_id', $id)->orderBy('id','desc')->paginate(5);
        
        return Response()->json($ajustes, 200);

    }

    public function ventas(Request $request, $id) {

        $ventas = Venta::whereHas('detalles', function($q) use ($id) {
                                    $q->where('producto_id', $id);
                                })
                                ->orderBy('id','desc')->paginate(5);
        
        return Response()->json($ventas, 200);

    }

    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ],[
            'file.required' => 'El documento es obligatorio.'
        ]);

        $import = new Servicios();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){
        $servicios = new ServiciosExport();
        $servicios->filter($request);

        return Excel::download($servicios, 'servicios.xlsx');
    }

    public function downloadPlantilla()
    {
        $export = new ServiciosPlantillaExport();
        // Generar plantilla vacía con solo los encabezados
        return Excel::download($export, 'plantilla_servicios.xlsx');
    }

}
