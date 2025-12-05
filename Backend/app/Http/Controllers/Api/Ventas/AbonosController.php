<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Abono;
use App\Models\Ventas\Venta;
use App\Models\Inventario\Paquete;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\DB;
use App\Exports\AbonosVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use JWTAuth;
use App\Http\Requests\Ventas\Abonos\StoreAbonoRequest;
use App\Http\Requests\Ventas\Abonos\UpdateAbonoRequest;

class AbonosController extends Controller
{

    protected $transaccionesService;
    protected $chequesService;

    public function __construct(TransaccionesService $transaccionesService, ChequesService $chequesService)
    {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
    }

    public function index(Request $request) {

        $abonos = Abono::with('venta')->when($request->buscador, function($query) use ($request){
                        return $query->orwhere('id_venta', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('concepto', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('nombre_de', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->id_documento, function ($query) use ($request) {
                            // Buscar el documento por ID (respetando el scope de empresa)
                            $documento = \App\Models\Admin\Documento::find($request->id_documento);

                            if ($documento) {
                                // Filtrar por todos los abonos de ventas que tengan documentos con el mismo nombre (case insensitive)
                                return $query->whereHas('venta.documento', function ($q) use ($documento) {
                                    $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                                });
                            } else {
                                // Si no se encuentra el documento, filtrar por ID directo del documento de la venta
                                return $query->whereHas('venta', function ($q) use ($request) {
                                    $q->where('id_documento', $request->id_documento);
                                });
                            }
                        })
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->paginate($request->paginate);

        return Response()->json($abonos, 200);

    }


    public function read($id) {

        $abono = Abono::findOrFail($id);
        return Response()->json($abono, 200);

    }

    public function store(StoreAbonoRequest $request)
    {
        $venta = Venta::find($request->id_venta);

        DB::beginTransaction();

        try {

            if($request->id)
                $abono = Abono::findOrFail($request->id);
            else
                $abono = new Abono;

            // Obtener el documento y asignar correlativo
            $documento = \App\Models\Admin\Documento::where('nombre', 'Abono de Venta')
                            ->where('id_sucursal', $request->id_sucursal)
                            ->lockForUpdate()
                            ->first();

            $abono->fill($request->all());
            if($documento){
                $abono->id_documento = $documento->id;
                $abono->correlativo = $documento->correlativo;
                $documento->increment('correlativo');
            }
            $abono->save();

            if ($venta && $venta->saldo <= 0) {
                $venta->estado = 'Pagada';
                $venta->save();

                // Actualziar si es paquete
                    $paquetes = Paquete::where('id_venta', $venta->id)->get();
                    foreach ($paquetes as $paquete) {
                        $paquete->fecha = $abono->fecha;
                        $paquete->estado = 'Facturado';
                        $paquete->save();
                    }
            }

            if ($venta && $venta->saldo > 0) {
                $venta->estado = 'Pendiente';
                $venta->save();

                // Actualziar si es paquete
                    $paquetes = Paquete::where('id_venta', $venta->id)->get();
                    foreach ($paquetes as $paquete) {
                        $paquete->fecha = $abono->fecha;
                        $paquete->estado = 'Pendiente';
                        $paquete->save();
                    }
            }

            // Crear transaccion bancaria
                if(!$request->id && $abono->forma_pago != 'Efectivo' && $abono->forma_pago != 'Cheque'){
                    $this->transaccionesService->crear($abono, 'Abono', 'Abono de venta: ' . $venta->nombre_documento . ' #' . $venta->correlativo, 'Abono de Venta');
                }

            // Crear cheque
                if(!$request->id && $abono->forma_pago == 'Cheque'){
                    $this->chequesService->crear($abono, $venta->nombre_cliente, 'Abono de venta: ' . $venta->nombre_documento . ' #' . $venta->correlativo, 'Abono de Venta');
                }


        DB::commit();
        return Response()->json($abono, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function update(UpdateAbonoRequest $request)
    {
        try {
            DB::beginTransaction();


            $abono = Abono::findOrFail($request->id);
            $venta = Venta::find($request->id_venta);

            // Actualizar el abono
            $abono->fill($request->all());
            $abono->save();

            // Actualizar estado de la venta según el saldo
            if ($venta) {
                if ($venta->saldo <= 0) {
                    $venta->estado = 'Pagada';
                    $venta->save();

                    // Actualizar paquetes relacionados
                    $paquetes = Paquete::where('id_venta', $venta->id)->get();
                    foreach ($paquetes as $paquete) {
                        $paquete->fecha = $abono->fecha;
                        $paquete->estado = 'Facturado';
                        $paquete->save();
                    }
                } else {
                    $venta->estado = 'Pendiente';
                    $venta->save();

                    // Actualizar paquetes relacionados
                    $paquetes = Paquete::where('id_venta', $venta->id)->get();
                    foreach ($paquetes as $paquete) {
                        $paquete->fecha = $abono->fecha;
                        $paquete->estado = 'Pendiente';
                        $paquete->save();
                    }
                }
            }

            DB::commit();
            return response()->json($abono, 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function delete($id){
        $abono = Abono::findOrFail($id);
        $abono->delete();

        return Response()->json($abono, 201);

    }

    public function print($id){

        $recibo = Abono::with('documento')->where('id', $id)->first();
        $venta = Venta::with('empresa.currency')->where('id', $recibo->id_venta)->first();

        $pdf = PDF::loadView('reportes.recibos.recibo', compact('venta', 'recibo'));
        $pdf->setPaper('US Letter', 'portrait');

        $nombreArchivo = ($recibo->nombre_documento ?? 'recibo') . '-' . ($recibo->correlativo ?? $recibo->id) . '.pdf';
        return $pdf->stream($nombreArchivo);

    }

    public function export(Request $request){
        $abonos = new AbonosVentasExport();
        $abonos->filter($request);

        return Excel::download($abonos, 'abonos.xlsx');
    }



}
