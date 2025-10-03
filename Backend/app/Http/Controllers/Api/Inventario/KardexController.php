<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\KardexMasivoQueue;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Inventario\KardexExport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class KardexController extends Controller
{
    

    public function index(Request $request) {

        $producto = Producto::where('id', $request->id_producto)->with('inventarios')->firstOrFail();

        $kardex = Kardex::where('id_producto', $producto->id)
                        ->when($request->id_inventario, function($q) use ($request){
                            $q->where('id_inventario', $request->id_inventario);
                        })
                        ->when($request->inicio, function($q) use ($request){
                            $q->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($q) use ($request){
                            $q->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->detalle, function($q) use ($request){
                            return $q->where('detalle', 'like' ,'%' . $request->detalle . '%');
                        })
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->get();
        

        $producto->movimientos = $kardex;

        return Response()->json($producto, 200);

    }


    public function read($id) {

        $kardex = Kardex::findOrFail($id);
        return Response()->json($kardex, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required',
            'id_producto'   => 'required',
            'id_sucursal' => 'required|numeric',
            'detalle'       => 'required',
            'referencia'    => 'sometimes|max:255',
            'entrada_cantidad'      => 'required|numeric',
            'entrada_valor'         => 'required|numeric',
            'salida_cantidad'      => 'required|numeric',
            'salida_valor'         => 'required|numeric',
            'total_cantidad'      => 'required|numeric',
            'total_valor'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
        ]);

        if($request->id)
            $kardex = Kardex::findOrFail($request->id);
        else
            $kardex = new Kardex;

        // Actualizar inventario
            $producto = Producto::withoutGlobalScopes()->findOrFail($request->id_producto);
            $inventario = Inventario::where('id', $request->id_sucursal)->where('id_producto', $producto->id)->first();
            $inventario->stock += ($request->stock_final - $request->stock_inicial);
            $inventario->save();

        $kardex->fill($request->all());
        $kardex->save();        

        return Response()->json($kardex, 200);

    }

    public function delete($id)
    {
        $kardex = Kardex::findOrFail($id);
        $kardex->delete();

        return Response()->json($kardex, 201);

    }


    public function search($txt) {

        $kardexs = Kardex::whereHas('producto', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orWhere('codigo', 'like' ,'%' . $txt . '%');
                            })
                            ->orwhereHas('bodega', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%');
                            })
                            ->paginate(10);

        return Response()->json($kardexs, 200);

    }

    public function export(Request $request){
        $kardex = new KardexExport();
        $kardex->filter($request);

        return Excel::download($kardex, 'kardex.xlsx');
    }

    public function exportFiltrado(Request $request){
        $kardex = new \App\Exports\Inventario\KardexFiltradoExport();
        $kardex->filter($request);

        return Excel::download($kardex, 'kardex-filtrado.xlsx');
    }

    public function solicitarMasivo(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'id_empresa' => 'required|integer'
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
            'id_empresa.required' => 'El ID de empresa es obligatorio.'
        ]);

        try {
            // Crear registro en la cola para procesamiento en segundo plano
            $queueItem = KardexMasivoQueue::create([
                'email' => $request->email,
                'id_empresa' => $request->id_empresa,
                'status' => 'pending'
            ]);
            
            return response()->json([
                'message' => 'Solicitud de kardex masivo registrada. Recibirá un correo electrónico cuando esté listo.',
                'success' => true,
                'queue_id' => $queueItem->id
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al solicitar kardex masivo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al procesar la solicitud. Intente nuevamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function estadoCola(Request $request)
    {
        $request->validate([
            'id_empresa' => 'required|integer'
        ]);
        
        try {
            $estados = KardexMasivoQueue::where('id_empresa', $request->id_empresa)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'email', 'status', 'created_at', 'started_at', 'completed_at', 'error_message']);
            
            return response()->json([
                'success' => true,
                'estados' => $estados
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener estado de cola: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener estado de cola.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
