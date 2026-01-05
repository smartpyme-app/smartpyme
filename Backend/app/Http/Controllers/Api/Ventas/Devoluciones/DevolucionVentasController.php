<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Ventas\Venta;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Devoluciones\DetalleCompuesto;
use App\Exports\DevolucionesVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Creditos\Credito;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use JWTAuth;
use Auth;
use Illuminate\Support\Str;
use App\Http\Requests\Ventas\Devoluciones\StoreDevolucionRequest;
use App\Http\Requests\Ventas\Devoluciones\UpdateDevolucionRequest;
use App\Http\Requests\Ventas\Devoluciones\FacturacionDevolucionRequest;
use App\Services\Ventas\DevolucionVentaService;
use Illuminate\Support\Facades\Log;

class DevolucionVentasController extends Controller
{
    protected $devolucionVentaService;

    public function __construct(DevolucionVentaService $devolucionVentaService)
    {
        $this->devolucionVentaService = $devolucionVentaService;
    }

    public function index(Request $request) {

        $ventas = Devolucion::when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->estado !== null, function($q) use ($request){
                            $q->where('enable', !!$request->estado);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->whereHas('documento', function ($q) use ($request) {
                                $q->where('nombre', $request->tipo_documento);
                            });
                        })
                        ->when($request->id_documento, function ($query) use ($request) {
                            // Buscar el documento por ID (respetando el scope de empresa)
                            $documento = Documento::find($request->id_documento);

                            if ($documento) {
                                // Filtrar por todos los documentos que tengan el mismo nombre (case insensitive)
                                return $query->whereHas('documento', function ($q) use ($documento) {
                                    $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                                });
                            } else {
                                // Si no se encuentra el documento, filtrar por ID directo
                                return $query->where('id_documento', $request->id_documento);
                            }
                        })
                        ->when($request->buscador, function($query) use ($request){
                        return $query->whereHas('cliente', function($q) use ($request){
                                    $q->where('nombre', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nombre_empresa', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('ncr', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nit', 'like' ,"%" . $request->buscador . "%");
                                 })->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ventas, 200);
    }



    public function read($id) {

        $venta = Devolucion::where('id', $id)->with('detalles.composiciones', 'detalles.producto', 'venta', 'cliente')->first();
        return Response()->json($venta, 200);

    }


    public function store(StoreDevolucionRequest $request)
    {

        if($request->id)
            $venta = Devolucion::findOrFail($request->id);
        else
            $venta = new Devolucion;

        // Solo ajustar stocks si el tipo de nota de crédito afecta inventario
        if ($request->tipo !== 'descuento_ajuste') {
            // Ajustar stocks
            foreach ($venta->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();

                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $venta->id_bodega)->first();

                // Anular y regresar stock
                if(($venta->enable != '0') && ($request['enable'] == '0')){

                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad * -1);
                    }

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                                    ->where('id_bodega', $venta->id_bodega)->first();

                        if ($inventario) {
                            $inventario->stock -= $detalle->cantidad * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad) * -1);
                        }
                    }

                }
                // Cancelar anulación y descargar stock
                if(($venta->enable == '0') && ($request['enable'] != '0')){
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad);
                    }

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                                    ->where('id_bodega', $venta->id_bodega)->first();

                        if ($inventario) {
                            $inventario->stock += $detalle->cantidad * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
                        }
                    }

                }
            }
        }

        $venta->fill($request->all());
        $venta->save();

        return Response()->json($venta, 200);

    }

    public function update(UpdateDevolucionRequest $request)
    {

        DB::beginTransaction();

        try {
            $devolucion = Devolucion::findOrFail($request->id);

            // Solo actualizar los campos permitidos
            $devolucion->fecha = $request->fecha;
            $devolucion->id_documento = $request->id_documento;
            $devolucion->correlativo = $request->correlativo;
            $devolucion->id_usuario = $request->id_usuario;
            $devolucion->observaciones = $request->observaciones;

            $devolucion->save();

            DB::commit();

            return response()->json([
                'message' => 'Devolución actualizada correctamente',
                'data' => $devolucion
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Error al actualizar la devolución: ' . $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
        $venta = Devolucion::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }
        $venta->delete();

        return Response()->json($venta, 201);

    }


    public function facturacion(FacturacionDevolucionRequest $request)
    {
        try {
            // Validar que la diferencia entre notas de crédito y notas de débito no supere el total de la venta
            $this->devolucionVentaService->validarLimitesDevolucion(
                $request->id_venta,
                $request->id,
                $request->id_documento,
                $request->total
            );
        } catch (\Exception $e) {
            return Response()->json(['error' => $e->getMessage()], 400);
        }

        DB::beginTransaction();

        try {
            // Guardamos la devolucion
            if ($request->id) {
                $devolucion = Devolucion::findOrFail($request->id);
            } else {
                $devolucion = new Devolucion;
            }

            $devolucion->fill($request->all());
            $devolucion->save();

            // Procesar detalles (crear detalles, manejar composiciones, actualizar inventario, manejar paquetes)
            $this->devolucionVentaService->procesarDetalles(
                $devolucion,
                $request->detalles,
                $request->tipo,
                $request->id_bodega
            );

            // Incrementar el correlativo
            $this->devolucionVentaService->incrementarCorrelativo($devolucion);

            DB::commit();
            return Response()->json($devolucion, 200);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en facturacion (DevolucionVentasController): ' . $e->getMessage(), [
                'request' => $request->all(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('Error inesperado en facturacion (DevolucionVentasController): ' . $e->getMessage(), [
                'request' => $request->all(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function generarDoc($id){
        $venta = Devolucion::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        if(Auth::user()->id_empresa == 187 && $venta->nombre_documento == "Nota de crédito"){//187  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.NC-Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        else if(Auth::user()->id_empresa == 250 && $venta->nombre_documento == "Nota de crédito"){//250  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.NC-Full-Solutions', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('Legal', 'portrait');
        }
        else if(Auth::user()->id_empresa == 128 && $venta->nombre_documento == "Nota de crédito"){//250  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.NC-Kiero', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('Legal', 'portrait');
        }else{
            $pdf = PDF::loadView('reportes.facturacion.nota-credito', compact('venta'));
            $pdf->setPaper('US Letter', 'portrait');
        }

        return $pdf->stream('nota-credito-' . $venta->id . '.pdf');

    }

    public function export(Request $request){
        $ventas = new DevolucionesVentasExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas.xlsx');
    }


}
