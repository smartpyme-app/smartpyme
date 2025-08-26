<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Transaccion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Bancos\TransaccionesExport;

class TransaccionesController extends Controller
{


    public function index(Request $request) {

        $transacciones = Transaccion::with('cuenta', 'usuario')->when($request->buscador, function($query) use ($request){
                                    return $query->where('concepto', 'like' ,'%' . $request->buscador . '%')
                                    ->orWhereHas('cuenta', function($query) use ($request){
                                        return $query->where('numero', 'like' ,'%' . $request->buscador . '%')
                                        ->orWhere('nombre_banco', 'like' ,'%' . $request->buscador . '%');
                                    })
                                    ->orWhereHas('usuario', function($query) use ($request){
                                        return $query->where('name', 'like' ,'%' . $request->buscador . '%');
                                    });
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->tipo, function($query) use ($request){
                                    return $query->where('tipo', $request->tipo);
                                })
                                ->when($request->tipo_operacion, function($query) use ($request){
                                    return $query->where('tipo_operacion', $request->tipo_operacion);
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->orderBy('id', 'desc')
                                ->paginate($request->paginate);

        return Response()->json($transacciones, 200);

    }

    public function list() {

        $transacciones = Transaccion::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($transacciones, 200);

    }

    public function read($id) {

        $transaccion = Transaccion::where('id', $id)->firstOrFail();
        return Response()->json($transaccion, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'id_cuenta'     => 'required|numeric',
            'concepto'      => 'required|max:255',
            'tipo_operacion' => 'required|max:255',
            'tipo'          => 'required|max:255',
            'estado'          => 'required|max:255',
            'total'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {

            if($request->id)
                $transaccion = Transaccion::findOrFail($request->id);
            else
                $transaccion = new Transaccion;

            // Aprobar transaccion
                if(($transaccion->estado == 'Pendiente') && ($request['estado'] == 'Aprobada')){

                    //Actualizar saldo de cuanta
                        $cuenta = $transaccion->cuenta()->first();

                        // Normalizar valor decimal
                        $total = $this->normalizeDecimal($transaccion->total);

                        if ($transaccion->tipo == 'Cargo') {
                            $cuenta->saldo = $cuenta->saldo - $total;
                        }

                        if ($transaccion->tipo == 'Abono') {
                            $cuenta->saldo = $cuenta->saldo + $total;
                        }

                        $cuenta->save();
                }

            $transaccion->fill($request->all());

            if ($request->hasFile('file')) {
                if ($request->id && $transaccion->url_referencia) {
                    Storage::delete($transaccion->url_referencia);
                }
                $nombre = $request->file->store('documentos_transacciones');
                $transaccion->url_referencia = $nombre;
            }

            $transaccion->save();

        DB::commit();
        return Response()->json($transaccion, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function delete($id)
    {
        $transaccion = Transaccion::findOrFail($id);
        $transaccion->delete();

        return Response()->json($transaccion, 201);

    }

    public function export(Request $request){
        $transacciones = new TransaccionesExport();
        $transacciones->filter($request);

        return Excel::download($transacciones, 'transacciones.xlsx');
    }

    /**
     * Normalizar valores decimales: convertir comas a puntos
     * Para evitar errores de sintaxis SQL con formatos de números europeos
     */
    private function normalizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Convertir a string y reemplazar comas por puntos
        $normalized = str_replace(',', '.', (string)$value);

        // Convertir a float y luego formatear con 2 decimales usando punto
        return number_format((float)$normalized, 2, '.', '');
    }

}
