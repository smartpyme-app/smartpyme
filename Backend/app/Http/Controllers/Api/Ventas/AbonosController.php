<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Abono;
use App\Models\Ventas\Venta;
use App\Models\Inventario\Paquete;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\DB;
use App\Exports\AbonosVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use JWTAuth;

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
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->paginate($request->paginate);

        return Response()->json($abonos, 200);
           
    }


    public function read($id) {

        $abono = Abono::findOrFail($id);
        return Response()->json($abono, 200);

    }

    public function store(Request $request)
    {

        $venta = Venta::find($request->id_venta);

        $request->validate([
            'fecha'       => 'required|date',
            'concepto'    => 'required|max:255',
            'nombre_de'    => 'required|max:255',
            'estado'      => 'required|max:255',
            'forma_pago' => 'required|max:255',
            // 'detalle_banco' => 'required_unless:forma_pago,"Efectivo"',
            'total'       => 'required|numeric',
            'id_venta'    => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_sucursal'    => 'required|numeric',
        ]);

        DB::beginTransaction();
         
        try {

            if($request->id)
                $abono = Abono::findOrFail($request->id);
            else
                $abono = new Abono;

            
            $abono->fill($request->all());
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

    public function delete($id){
        $abono = Abono::findOrFail($id);
        $abono->delete();
        
        return Response()->json($abono, 201);

    }

    public function print($id){

        $recibo = Abono::where('id', $id)->first();
        $venta = Venta::with('empresa.currency')->where('id', $recibo->id_venta)->first();

        $pdf = PDF::loadView('reportes.recibos.recibo', compact('venta', 'recibo'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');   

    }

    public function export(Request $request){
        $abonos = new AbonosVentasExport();
        $abonos->filter($request);

        return Excel::download($abonos, 'abonos.xlsx');
    }



}
