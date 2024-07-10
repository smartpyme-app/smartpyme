<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Clientes\Cliente;

use App\Imports\Paquetes;
use App\Exports\PaquetesExport;
use Maatwebsite\Excel\Facades\Excel;
use Auth;

class PaquetesController extends Controller
{
    

    public function index(Request $request) {

        $paquetes = Paquete::with('cliente', 'proveedor')
                                ->when($request->id_sucursal, function($q) use ($request){
                                    $q->whereHas('inventarios', function($q) use ($request){
                                        return $q->where('id_sucursal', $request->id_sucursal);
                                    });
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->whereHas('cliente', function($q) use ($request){
                                                    $q->where('nombre', 'like' ,"%" . $request->buscador . "%");
                                                 })
                                                 ->orwhere('num_guia', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('embalaje', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('nota', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('wr', 'like' ,"%" . $request->buscador . "%")
                                                 ->orwhere('num_seguimiento', 'like' ,"%" . $request->buscador . "%");
                                })
                                ->when($request->wr, function($q) use ($request){
                                    $q->where('wr', $request->wr);
                                })
                                ->when($request->cuenta_a_terceros !== null, function($q) use ($request){
                                    $q->where('cuenta_a_terceros', '>', 0);
                                })
                                ->when($request->id_cliente, function($q) use ($request){
                                    return $q->where("id_cliente", $request->id_cliente);
                                })
                                ->when($request->id_asesor, function($q) use ($request){
                                    return $q->where("id_asesor", $request->id_asesor);
                                })
                                ->when($request->id_usuario, function($q) use ($request){
                                    return $q->where("id_usuario", $request->id_usuario);
                                })
                                ->when($request->estado, function($q) use ($request){
                                    $q->where('estado', $request->estado);
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->orderBy($request->orden ? $request->orden : 'nombre', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate);

        return Response()->json($paquetes, 200);

    }

    public function list() {
       
        $paquetes = Paquete::orderby('nombre')
                                ->with('inventarios')
                                ->where('enable', true)
                                ->get();

        return Response()->json($paquetes, 200);

    }


    public function porCodigo($codigo) {
       
        $paquete = Paquete::
                            where('codigo', $codigo )
                            ->wherehas('sucursales', function($q){
                                $q->where('sucursal_id', \JWTAuth::parseToken()->authenticate()->sucursal_id)
                                    ->where('activo', true);
                            })
                            ->with('inventarios', 'precios')->get();

        return Response()->json($paquete, 200);

    }

    public function read($id) {

        $paquete = Paquete::where('id', $id)
                                ->with('cliente', 'proveedor')
                                ->firstOrFail();

        return Response()->json($paquete, 200);

    }


    public function store(Request $request)
    {
 
        $request->validate([
            'fecha' => 'required|max:255',
            'wr' => 'required|max:255',
            // 'transportista' => 'required|max:255',
            // 'consignatario' => 'required|max:255',
            // 'transportador' => 'required|max:255',
            'estado' => 'required|max:255',
            // 'num_seguimiento' => 'required|max:255',
            'num_guia' => 'required|max:255',
            'piezas' => 'required',
            'peso'  => 'required',
            'precio'  => 'required',
            // 'volumen'  => 'required',
            'id_cliente'  => 'required',
            'id_usuario'  => 'required',
            'id_sucursal' => 'required',
            'id_empresa' => 'required',
        ],[
            // 'nombre.required' => 'Agregue un nombre.',
            // 'costo.required' => 'Agregue el costo.'
        ]);

        if($request->id)
            $paquete = Paquete::findOrFail($request->id);
        else
            $paquete = new Paquete;
        

        $paquete->fill($request->all());
        $paquete->save();

        return Response()->json($paquete, 200);

    }

    public function delete($id)
    {
        $paquete = Paquete::findOrFail($id);
        $paquete->enable = false;

        $paquete->save();

        return Response()->json($paquete, 201);

    }

    public function precios($id)
    {
        $paquete = Paquete::findOrFail($id);
        
        
        $ventas = DetalleVenta::where('paquete_id', $paquete->id)->get();

        $ventas_precios =  collect();
        $ventas_fechas =  collect();

        foreach ($ventas->unique('precio') as $venta) {
            $ventas_precios->push($venta->precio);
            $ventas_fechas->push($venta->created_at->format('d/m/Y'));
        }
        $paquete->ventas_precios = $ventas_precios;
        $paquete->ventas_fechas = $ventas_fechas;
        $paquete->ventas = count($ventas);

        return Response()->json($paquete, 201);

    }


    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ],[
            'file.required' => 'El documento es obligatorio.'
        ]);

        $import = new Paquetes();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){
        $paquetes = new PaquetesExport();
        $paquetes->filter($request);

        return Excel::download($paquetes, 'paquetes.xlsx');
    }

    public function clientesPaquetesPendientes() {

        $clientes = Cliente::orderBy('nombre','asc')
                            ->whereHas('paquetes', function($query) {
                                $query->where('estado', 'En bodega');
                            })
                            ->where('enable', true)
                            ->get();
        
        return Response()->json($clientes, 200);

    }

}
