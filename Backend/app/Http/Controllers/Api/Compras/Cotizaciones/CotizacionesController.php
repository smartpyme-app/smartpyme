<?php

namespace App\Http\Controllers\Api\Compras\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Compras\Compra as Cotizacion;
use App\Models\Admin\Empresa;
use App\Models\Compras\Detalle;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use JWTAuth;
use App\Exports\OrdenesDeComprasExport;
use App\Models\Compras\Compra;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Compras\Cotizaciones\StoreOrdenCompraRequest;
use App\Http\Requests\Compras\Cotizaciones\FacturacionCotizacionCompraRequest;

class CotizacionesController extends Controller
{

    public function index(Request $request)
    {

        $cotizaciones = OrdenCompra::with('detalles')
            ->when($request->buscador, function ($query) use ($request) {
                return $query
                    // ->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                    ->orwhere('estado', 'like', '%' . $request->buscador . '%')
                    ->orwhere('observaciones', 'like', '%' . $request->buscador . '%')
                    ->orwhere('forma_pago', 'like', '%' . $request->buscador . '%');
            })
            ->when($request->inicio, function ($query) use ($request) {
                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->forma_pago, function ($query) use ($request) {
                return $query->where('forma_pago', $request->forma_pago);
            })
            ->when($request->id_canal, function ($query) use ($request) {
                return $query->where('id_canal', $request->id_canal);
            })
            ->when($request->id_documento, function ($query) use ($request) {
                return $query->where('id_documento', $request->id_documento);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->metodo_pago, function ($query) use ($request) {
                return $query->where('metodo_pago', $request->metodo_pago);
            })
            ->when($request->tipo_documento, function ($query) use ($request) {
                return $query->where('tipo_documento', $request->tipo_documento);
            })
            ->orderBy($request->orden ?? 'fecha', $request->direccion ?? 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->paginate ?? 10);

        return Response()->json($cotizaciones, 200);
    }

    public function read($id)
    {

        $cotizacion = OrdenCompra::where('id', $id)->with('proveedor', 'detalles')->firstOrFail();
        return Response()->json($cotizacion, 200);
    }

    public function search($txt)
    {

        $cotizaciones = OrdenCompra::with('proveedor', function ($q) use ($txt) {
            $q->where('nombre', 'like', '%' . $txt . '%');
        })
            ->orwhere('estado', 'like', '%' . $txt . '%')
            ->paginate(10);
        return Response()->json($cotizaciones, 200);
    }

    public function filter(Request $request)
    {

        $cotizaciones = OrdenCompra::when($request->fin, function ($query) use ($request) {
            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        })
            ->when($request->sucursal_id, function ($query) use ($request) {
                return $query->where('sucursal_id', $request->sucursal_id);
            })
            // ->when($request->tipo_servicio, function ($query) use ($request) {
            //     return $query->where('tipo_servicio', $request->tipo_servicio);
            // })
            ->when($request->usuario_id, function ($query) use ($request) {
                return $query->where('usuario_id', $request->usuario_id);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->orderBy('id', 'asc')->paginate(100000);

        return Response()->json($cotizaciones, 200);
    }

    public function store(StoreOrdenCompraRequest $request)
    {

        // VERIFICAR AUTORIZACIÓN por niveles de monto
        if (!$request->id && !$request->id_authorization) {
            $total = $this->calcularTotalOrden($request);
            $authType = $this->determinarTipoAutorizacion($total);

            // Verificar si el usuario está excluido de autorización por rol
            if ($authType) {
                $authTypeModel = \App\Models\Authorization\AuthorizationType::where('name', $authType)->first();
                
                if ($authTypeModel && $authTypeModel->conditions) {
                    $excludeRoles = $authTypeModel->conditions['exclude_roles'] ?? [];
                    $user = Auth::user();
                    
                    // Cargar roles del usuario si no están cargados
                    if (!$user->relationLoaded('roles')) {
                        $user->load('roles');
                    }
                    
                    // Verificar si el usuario tiene algún rol excluido
                    $userRoles = $user->roles->pluck('name')->toArray();
                    $isExcluded = !empty(array_intersect($userRoles, $excludeRoles));
                    
                    if ($isExcluded) {
                        Log::info("Usuario excluido de autorización por rol - Usuario: " . $user->id . " - Roles: " . implode(', ', $userRoles));
                        // Usuario excluido, continuar sin requerir autorización
                    } else {
                        Log::info("Orden de compra requiere autorización - Total: $" . $total . " - Tipo: " . $authType . " - Usuario: " . $user->id . " - Roles: " . implode(', ', $userRoles));

                        return response()->json([
                            'ok' => false,
                            'requires_authorization' => true,
                            'authorization_type' => $authType,
                            'message' => "Esta orden de compra de $" . number_format($total, 2) . " requiere autorización"
                        ], 403);
                    }
                } else {
                    // Si no hay condiciones definidas, requerir autorización
                    Log::info("Orden de compra requiere autorización - Total: $" . $total . " - Tipo: " . $authType);

                    return response()->json([
                        'ok' => false,
                        'requires_authorization' => true,
                        'authorization_type' => $authType,
                        'message' => "Esta orden de compra de $" . number_format($total, 2) . " requiere autorización"
                    ], 403);
                }
            }
        }

        Log::info("Procesando orden de compra normal o autorizada");


        DB::beginTransaction();

        if ($request->id)
            $cotizacion = OrdenCompra::findOrFail($request->id);
        else {
            $cotizacion = new OrdenCompra;
            $cotizacion->estado = "Pendiente";
        }


        if ($cotizacion->estado == "Aceptada" && $request->estado == "Pendiente") {
            return response()->json([
                "error" => "No se puede cambiar el estado de una cotización aceptada a pendiente",
                "currentState" => $cotizacion->estado
            ], 400);
        }

        if ($request->estado == "Anulada") {
            $existCompras = Compra::where("num_orden_compra", $cotizacion->id)->where("estado", "!=", "Anulada")->exists();
            if ($existCompras) {
                return response()->json([
                    "error" => "No se puede anular una cotización que ya tiene compras asociadas",
                    "currentState" => $cotizacion->estado
                ], 400);
            }
        }

        $cotizacion->fill($request->merge([
            "id_empresa" => Auth::user()->id_empresa,
        ])->all());
        $cotizacion->save();

        $deleted_detalles = $cotizacion->detalles->pluck("id")->diff(collect($request->detalles)->pluck("id"));
        foreach (($request->detalles ?? []) as $_detalle) {
            if ($_detalle["id"])
                $detalle = OrdenCompraDetalle::find($_detalle["id"]);
            else {
                $detalle = new OrdenCompraDetalle();
                $detalle->id_orden_compra = $cotizacion->id;
            }

            $detalle->fill($_detalle);
            $detalle->save();
        }



        if ($deleted_detalles) {
            OrdenCompraDetalle::whereIn("id", $deleted_detalles)->delete();
        }
        DB::commit();
        return Response()->json($cotizacion, 200);
    }

    public function facturacion(FacturacionCotizacionCompraRequest $request)
    {

        // Guardamos el proveedor
        if (isset($request->proveedor['id']) || isset($request->proveedor['nombre'])) {
            if (isset($request->proveedor['id']))
                $proveedor = Cliente::findOrFail($request->proveedor['id']);
            else
                $proveedor = new Cliente;

            $proveedor->fill($request->proveedor);
            $proveedor->save();
            $request['proveedor_id'] = $proveedor->id;
        }

        // Guardamos la cotizacion
        if ($request->id)
            $cotizacion = Cotizacion::findOrFail($request->id);
        else
            $cotizacion = new Cotizacion;

        $cotizacion->fill($request->all());
        $cotizacion->save();


        // Guardamos los detalles

        foreach ($request->detalles as $det) {
            if (isset($det['id']))
                $detalle = Detalle::findOrFail($det['id']);
            else
                $detalle = new Detalle;

            $det['cotizacion_id'] = $cotizacion->id;

            $detalle->fill($det);
            $detalle->save();
        }


        return Response()->json($cotizacion, 200);
    }


    public function delete($id)
    {
        $cotizacion = Cotizacion::findOrFail($id);
        foreach ($cotizacion->detalles as $detalle) {
            $detalle->delete();
        }
        $cotizacion->delete();

        return Response()->json($cotizacion, 201);
    }

    public function generarDoc($id)
    {
        $compra = OrdenCompra::where('id', $id)
            ->with(['detalles.producto', 'proveedor', 'empresa.currency'])
            ->firstOrFail();

        // Asegurar que los detalles estén cargados para que los accessors funcionen
        if (!$compra->relationLoaded('detalles')) {
            $compra->load('detalles.producto');
        }

        $pdf = PDF::loadView('reportes.facturacion.orden-de-compra', compact('compra'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('orden-de-compra-' . $compra->id . '.pdf');
    }

    public function vendedor()
    {

        $cotizaciones = OrdenCompra::orderBy('id', 'desc')->where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)->paginate(10);

        return Response()->json($cotizaciones, 200);
    }

    public function vendedorBuscador($txt)
    {

        $cotizaciones = OrdenCompra::where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)
            ->with('proveedor', function ($q) use ($txt) {
                $q->where('nombre', 'like', '%' . $txt . '%');
            })
            ->orwhere('estado', 'like', '%' . $txt . '%')
            ->paginate(10);
        return Response()->json($cotizaciones, 200);
    }

    public function export(Request $request)
    {
        $cotizaciones = new OrdenesDeComprasExport();
        $cotizaciones->filter($request);

        return Excel::download($cotizaciones, 'cotizaciones.xlsx');
    }

    public function procesarOrdenAutorizada($ordenId)
    {
        Log::info("Procesando orden de compra autorizada: " . $ordenId);

        DB::beginTransaction();

        try {
            $orden = OrdenCompra::findOrFail($ordenId);

            // Cambiar estado a aprobada
            $orden->estado = 'Aprobada';
            $orden->save();

            DB::commit();

            Log::info("Orden de compra autorizada procesada exitosamente: " . $ordenId);

            return $orden;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando orden de compra autorizada: " . $e->getMessage());
            throw $e;
        }
    }

    protected function handlePendingAuthorization($data, $authorization)
    {
        Log::info("Creando orden de compra pendiente de autorización");

        DB::beginTransaction();

        try {
            // Crear orden en estado pendiente
            $ordenData = $data;
            $ordenData['estado'] = 'Pendiente Autorización';
            $ordenData['id_authorization'] = $authorization->id;
            $ordenData['id_sucursal'] = Auth::user()->id_sucursal;

            $orden = new OrdenCompra;
            $orden->fill($ordenData);
            $orden->save();

            // Crear detalles de la orden pendiente
            foreach ($data['detalles'] as $det) {
                $detalle = new OrdenCompraDetalle;
                $det['id_orden_compra'] = $orden->id;
                $detalle->fill($det);
                $detalle->save();
            }

            // Actualizar la autorización con el ID de la orden creada
            $authorization->update([
                'authorizeable_id' => $orden->id
            ]);

            DB::commit();

            return response()->json([
                'ok' => true,
                'data' => $orden,
                'estado' => 'Pendiente Autorización',
                'requires_authorization' => true,
                'authorization_code' => $authorization->code,
                'message' => 'Orden de compra creada pendiente de autorización'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando orden pendiente: " . $e->getMessage());

            return response()->json([
                'ok' => false,
                'requires_authorization' => true,
                'authorization_type' => $authorization->authorizationType->name,
                'message' => 'Error al crear orden pendiente: ' . $e->getMessage(),
                'authorization_code' => $authorization->code
            ], 403);
        }
    }

    private function calcularTotalOrden($request)
    {
        $total = $request->total ?? $request->sub_total ?? 0;

        // Si no hay total, calcularlo de los detalles
        if ($total == 0 && isset($request->detalles)) {
            $total = collect($request->detalles)->sum('total');
        }

        return $total;
    }

    private function determinarTipoAutorizacion($total)
    {
        if ($total >= 5000) {
            return 'orden_compra_nivel_3'; // Mayor a $5,000
        } elseif ($total >= 300) {
            return 'orden_compra_nivel_2'; // $300 - $4,999
        } elseif ($total > 0) {
            return 'orden_compra_nivel_1'; // $0 - $300
        }

        return null; // No requiere autorización
    }

    public function solicitudes(Request $request){
        $user = Auth::user();
        $licencia = $user->empresa()->first()->licencia()->first();
        if(!$licencia){
            return Response()->json(['error' => ['No tienes una licencia'], 'code' => 403], 403);
        }
        $empresaPadre = $licencia->empresa()->first(); // Empresa padre de la licencia
        $empresasLicencia = $licencia->empresas()->pluck('id_empresa')->toArray();

        $cotizaciones = Cotizacion::withoutGlobalScope('empresa')
            ->whereIn('id_empresa', $empresasLicencia)
            ->whereHas('proveedor', function($query) use ($empresaPadre) {
                return $query->withoutGlobalScope('empresa')
                    ->where(function($q) use ($empresaPadre) {
                        $q->where('nit', $empresaPadre->nit)
                            ->orWhere('ncr', $empresaPadre->ncr);
                    });
            })
            ->where('estado', 'Pendiente')
            ->with(['proveedor' => function($query){
                $query->withoutGlobalScope('empresa');
            }])
            ->when($request->buscador, function($query) use ($request){
                return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
            })
            ->when($request->inicio, function($query) use ($request){
                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
            })
            ->when($request->id_sucursal, function($query) use ($request){
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_usuario, function($query) use ($request){
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_proveedor, function($query) use ($request){
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->forma_pago, function($query) use ($request){
                return $query->where('forma_pago', $request->forma_pago);
            })
            ->when($request->id_canal, function($query) use ($request){
                return $query->where('id_canal', $request->id_canal);
            })
            ->when($request->id_documento, function($query) use ($request){
                return $query->where('id_documento', $request->id_documento);
            })
            ->when($request->estado, function($query) use ($request){
                return $query->where('estado', $request->estado);
            })
            ->when($request->metodo_pago, function($query) use ($request){
                return $query->where('metodo_pago', $request->metodo_pago);
            })
            ->when($request->tipo_documento, function($query) use ($request){
                return $query->where('tipo_documento', $request->tipo_documento);
            })
            ->where('cotizacion', 1)
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        return Response()->json($cotizaciones, 200);
    }

    public function solicitud($id) {

        $cotizacion = Cotizacion::withoutGlobalScope('empresa')->where('id', $id)->with('proveedor', 'detalles')->firstOrFail();
        return Response()->json($cotizacion, 200);

    }
}
