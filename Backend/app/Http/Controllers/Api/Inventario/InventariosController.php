<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Exports\Inventario\InventarioAFechaExport;
use App\Models\Inventario\AnalisisVentasMensualQueue;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Inventario\StoreInventarioRequest;
use App\Http\Requests\Inventario\ExportInventarioRequest;
use App\Http\Requests\Inventario\EstadoColaAnalisisVentasMensualRequest;
use Illuminate\Support\Facades\Log;

class InventariosController extends Controller
{


    public function index($bodega) {

        $inventarios = Inventario::where('id_bodega', $bodega)->with('producto')->orderBy('created_at','desc')->paginate(10);

        return Response()->json($inventarios, 200);

    }

    public function productos($id) {

            $productos = Inventario::where('id_bodega', $id)->with('producto')->paginate(50);

            return Response()->json($productos, 200);
    }

    public function search($id_bodega, $txt) {

        $productos = Inventario::where('id_bodega', $id_bodega)->with('producto')
                                    ->whereHas('producto', function($query) use ($txt){
                                        return $query->where('nombre', 'like' ,'%' . $txt . '%');
                                    })->paginate(30);

        return Response()->json($productos, 200);

    }

    public function productosFiltrar(Request $request) {

            $productos = Inventario::where('id_bodega', $request->id_bodega)
                                ->with('producto')
                                ->when($request->subcategorias_id, function($query) use ($request){
                                    $query->whereHas('producto', function($query) use ($request){
                                        return $query->whereIn('subcategoria_id', $request->subcategorias_id);
                                    });
                                })
                                ->when($request->stock_bodega, function($query) use ($request){
                                    return $query->whereRaw('stock <= stock_min');
                                })->paginate(20);

            return Response()->json($productos, 200);
    }


    public function read($id) {

        $bodega = Inventario::findOrFail($id);
        return Response()->json($bodega, 200);

    }

    public function store(StoreInventarioRequest $request) {

        try {
        if($request->id){
            $inventario = Inventario::findOrFail($request->id);
        }
        else{
            $inventario = new Inventario;
        }

        $inventario->fill($request->all());
        $inventario->save();

        return Response()->json($inventario, 200);


    } catch (\Exception $e) {
        return Response()->json([
            'error' => 'Error al guardar el inventario: ' . $e->getMessage(),
            'code' => 500
        ], 500);
    }


    }

    public function delete($id)
    {
        $inventario = Inventario::findOrFail($id);
        $inventario->delete();

        return Response()->json($inventario, 201);

    }

    public function bodegaSearch($txt) {
        $productoInventario = Inventario::where('id_bodega', 1)->whereHas('producto', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('codigo', 'like' ,'%' . $txt . '%');

                    })->with('producto')->orderBy('stock', 'desc')->paginate(10);


    	return Response()->json($productoInventario, 200);

    }

    public function ventaSearch($txt) {

    	$productoVenta = Inventario::where('id_bodega', 2)->whereHas('producto', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('codigo', 'like' ,'%' . $txt . '%');

                    })->with('producto')->orderBy('stock', 'desc')->paginate(10);


        return Response()->json($productoVenta, 200);

    }

    public function export(Request $request){
       $request->validate([
           'id_empresa' => 'required|numeric',
           'fecha'      => 'required|date',
       ]);

        try {
            $inventario = new InventarioAFechaExport();
            $inventario->filter($request);

            return Excel::download($inventario, 'inventario.xlsx');
        } catch (\Throwable $e) {
            \Log::error('Error al exportar inventario: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            throw $e;
        }
    }

    public function solicitarAnalisisVentasMensual(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'id_empresa' => 'required|numeric',
            'fecha' => 'nullable|date',
            'anio' => 'nullable|integer|min:2000|max:2100',
            'agrupar_por' => 'nullable|in:producto,cliente,categoria,vendedor,proveedor',
            'mostrar_datos' => 'nullable|in:unidades,valor',
            'todos_productos' => 'nullable|boolean',
            'cliente_layout' => 'nullable|in:unica,separadas',
            'id_vendedor' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
            'id_categoria' => 'nullable|integer',
            'id_proveedor' => 'nullable|integer',
            'codigo' => 'nullable|string|max:100',
        ]);

        $idEmpresa = (int) $request->input('id_empresa');
        if (!Auth::user() || (int) Auth::user()->id_empresa !== $idEmpresa) {
            return response()->json([
                'error' => 'No autorizado.',
            ], 403);
        }

        $empresa = Empresa::find($idEmpresa);
        if (!$empresa || !$empresa->isInventarioReporteAnalisisVentasMensualHabilitado()) {
            return response()->json([
                'error' => 'Reporte no habilitado en preferencias del sistema.',
            ], 403);
        }

        try {
            $params = $request->except(['email', 'id_empresa']);
            $queueItem = AnalisisVentasMensualQueue::create([
                'email' => $request->input('email'),
                'id_empresa' => $idEmpresa,
                'id_usuario' => Auth::id(),
                'params' => $params,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud registrada. Recibirá un correo cuando el reporte esté listo.',
                'queue_id' => $queueItem->id,
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Error al encolar reporte inventario ventas mensual: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            throw $e;
        }
    }

    public function estadoColaAnalisisVentasMensual(EstadoColaAnalisisVentasMensualRequest $request)
    {
        $idEmpresa = (int) $request->input('id_empresa');
        if (!Auth::user() || (int) Auth::user()->id_empresa !== $idEmpresa) {
            return response()->json([
                'error' => 'No autorizado.',
            ], 403);
        }

        try {
            $estados = AnalisisVentasMensualQueue::where('id_empresa', $idEmpresa)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get([
                    'id',
                    'email',
                    'params',
                    'status',
                    'created_at',
                    'started_at',
                    'completed_at',
                    'error_message',
                ]);

            return response()->json([
                'success' => true,
                'estados' => $estados,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener estado cola análisis ventas mensual: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al obtener estado de cola.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
