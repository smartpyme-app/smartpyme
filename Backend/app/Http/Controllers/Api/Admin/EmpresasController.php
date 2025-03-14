<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Admin\FormaDePago;
use App\Models\Admin\Impuesto;
use App\Models\Admin\Sucursal;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Inventario\Bodega;
use App\Models\Plan;
use App\Models\Transaccion;
use App\Models\User;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManagerStatic as Image;
use Carbon\Carbon;
use JWTAuth;

class EmpresasController extends Controller
{

    private $suscripcionService;

    public function __construct(SuscripcionService $suscripcionService)
    {
        $this->suscripcionService = $suscripcionService;
    }

    public function index(Request $request) {
       
        $empresas = Empresa::when($request->activo !== null, function($q) use ($request){
            $q->where('activo', !!$request->activo);
        })
        ->when($request->buscador, function($query) use ($request){
            return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                            ->orwhere('correo', 'like' ,"%" . $request->buscador . "%");
        })
        ->when($request->pago_inicio, function($query) use ($request){
            return $query->where('fecha_ultimo_pago', '>=', $request->pago_inicio);
        })
        ->when($request->pago_fin, function($query) use ($request){
            return $query->where('fecha_ultimo_pago', '<=', $request->pago_fin);
        })
        ->when($request->suscripcion_inicio, function($query) use ($request){
            return $query->where('created_at', '>=', $request->suscripcion_inicio);
        })
        ->when($request->suscripcion_fin, function($query) use ($request){
            return $query->where('created_at', '<=', $request->suscripcion_fin);
        })
        ->when($request->forma_pago, function($query) use ($request){
            return $query->where('forma_pago', $request->forma_pago);
        })
        ->when($request->plan, function($query) use ($request){
            return $query->where('plan', $request->plan);
        })
        ->orderBy($request->orden, $request->direccion)
        ->paginate($request->paginate);

        return Response()->json($empresas, 200);

    }

    public function list() {
       
        $empresas = Empresa::orderby('nombre')
                                ->where('activo', true)
                                ->get();

        return Response()->json($empresas, 200);

    }


    public function read($id) {

        $empresa = Empresa::findOrFail($id);
        return Response()->json($empresa, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'iva'       => 'required|numeric',
        ]);

        if($request->id)
            $empresa = Empresa::findOrFail($request->id);
        else
            $empresa = new Empresa;
        
        //Bloquear usuarios
        if ($request->id && ($empresa->activo == '1') && ($request['activo'] == '0')){
            foreach ($empresa->usuarios()->get() as $usuario) {
                $usuario->enable = false;
                $usuario->save();
            }
        }
        //Des bloquear usuario Administrador
        if ($request->id && ($empresa->activo == '0') && ($request['activo'] == '1')){
            foreach ($empresa->usuarios()->where('tipo', 'Administrador')->get() as $usuario) {
                $usuario->enable = true;
                $usuario->save();
            }
        }

        $empresa->fill($request->all());

        if ($request->hasFile('file')) {
            if ($request->id && $empresa->logo && $empresa->logo != 'empresas/default.jpg') {
                Storage::delete($empresa->logo);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(350,350)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $path = "empresas/{$hash}.jpg";
            $resize->save(public_path('img/'.$path), 50);
            $empresa->logo = "/" . $path;
        }


        $empresa->save();

        $suscripcion = $this->createSuscripcion([
            'empresa_id' => $empresa->id,
            'plan_id' => $plan = $this->getPlan($empresa->plan)->id,
            'usuario_id' => $usuario->id,
            'tipo_plan' => $empresa->tipo_plan,
            'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
            'monto' => $plan->precio,
            'id_pago' => null,
            'id_orden' => null,
            'estado_ultimo_pago' => null,
            'fecha_ultimo_pago' => null,
            'fecha_proximo_pago' => null,
            'fin_periodo_prueba' => now()->addDays($plan->duracion_dias),
            'fecha_cancelacion' => null,
            'motivo_cancelacion' => null,
            'requiere_factura' => false,
            'nit' => null,
            'nombre_factura' => $empresa->nombre,
            'direccion_factura' => $empresa->direccion,
            'intentos_cobro' => 0,
            'ultimo_intento_cobro' => null,
            'historial_pagos' => null
        ]);

        //Crear sucursal
            if(!$request->id){
                // Crear cliente
                    $cliente = Cliente::create(['nombre' => $empresa->nombre, 'id_empresa' => 2]);
                    $empresa->cliente_id = $cliente->id;
                    $empresa->save();
                // Crear sucursal
                $sucursal = Sucursal::create(['nombre' => $empresa->nombre, 'id_empresa' => $empresa->id]);
                // Crear bodega
                $bodega = Bodega::create(['nombre' => $empresa->nombre, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
                // Crear canales
                Canal::create(['nombre' => $empresa->nombre, 'enable' => true, 'id_empresa' => $empresa->id]);
                // Crear impuesto
                Impuesto::create(['nombre' => 'IVA', 'porcentaje' => $empresa->iva, 'id_empresa' => $empresa->id]);
                // Formas de pago
                FormaDePago::create(['nombre' => config('constants.TIPO_PAGO_EFECTIVO'), 'id_empresa' => $empresa->id]);
                FormaDePago::create(['nombre' => config('constants.TIPO_PAGO_TRANSFERENCIA'), 'id_empresa' => $empresa->id]);
                FormaDePago::create(['nombre' => config('constants.TIPO_PAGO_TARJETA'), 'id_empresa' => $empresa->id]);
                // Crear documentos
                Documento::create(['nombre' => config('constants.TIPO_DOCUMENTO_TICKET'), 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
                Documento::create(['nombre' => config('constants.TIPO_DOCUMENTO_FACTURA'), 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
                Documento::create(['nombre' => config('constants.TIPO_DOCUMENTO_CREDITO_FISCAL'), 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
                Documento::create(['nombre' => config('constants.TIPO_DOCUMENTO_COTIZACION'), 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
                Documento::create(['nombre' => config('constants.TIPO_DOCUMENTO_ORDEN_COMPRA'), 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
            }

        return Response()->json($empresa, 200);

    }

    private function createSuscripcion(array $data): array 
    {
        $plan = Plan::find($data['plan_id']);
        
        if ($plan && $plan->permite_periodo_prueba) {
            $diasPrueba = $plan->dias_periodo_prueba;
            
            $data = array_merge($data, [
                'estado' => config('constants.ESTADO_SUSCRIPCION_EN_PRUEBA'), // Cambiar estado a 'prueba'
                'estado_ultimo_pago' => null,
                'fecha_ultimo_pago' => null, // No hay pago inicial en período de prueba
                'fecha_proximo_pago' => now()->addDays($diasPrueba), // Próximo pago al finalizar la prueba
                'fin_periodo_prueba' => now()->addDays($diasPrueba),
                'monto' => 0, // Sin costo durante el período de prueba
                'intentos_cobro' => 0,
                'ultimo_intento_cobro' => null,
                'historial_pagos' => null,
                'requiere_factura' => false,
                'nit' => null,
                'nombre_factura' => null,
                'direccion_factura' => null
            ]);
        } else {
            // Si el plan no permite período de prueba, mantener la configuración original
            $data = array_merge($data, [
                'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                'fecha_ultimo_pago' => null,
                'fecha_proximo_pago' => null,
                'fin_periodo_prueba' => null
            ]);
        }
    
        return $this->suscripcionService->createSuscripcion($data);
    }
    

    public function delete($id)
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->delete();

        return Response()->json($empresa, 201);

    }

    // public function suscripcion()
    // {
    //     $empresa = Empresa::with('pagos')->where('id', JWTAuth::parseToken()->authenticate()->id_empresa)->firstOrFail();
    //     $empresa->next_pay  = $empresa->getNextPayAttribute();
    //     $empresa->total  = $empresa->total;

    //     if ($empresa->next_pay >= date('Y-m-d')) {
    //         $empresa->estado  = 'Activo';
    //     }else{
    //         $empresa->estado  = 'Vencido';
    //     }

    //     return Response()->json($empresa, 201);

    // }

   public function suscripcion()
    {
        $empresa = Empresa::with('pagos')->where('id', JWTAuth::parseToken()->authenticate()->id_empresa)->firstOrFail();
        $suscripcion = $empresa->suscripcion()->where('estado', 'activo')
            ->latest()
            ->first([
                'estado', 
                'fecha_proximo_pago', 
                'fecha_ultimo_pago', 
                'fin_periodo_prueba', 
                'tipo_plan', 
                'created_at', 
                'monto',
                'fecha_ultimo_pago', 
                'fecha_proximo_pago', 
                'fin_periodo_prueba'
            ]);
        
        if (!$suscripcion) {
            $suscripcion = $empresa->suscripcion()
                ->latest()
                ->first([
                    'estado', 
                    'fecha_proximo_pago', 
                    'fecha_ultimo_pago', 
                    'fin_periodo_prueba', 
                    'tipo_plan', 
                    'created_at', 
                    'monto',
                    'fecha_ultimo_pago', 
                    'fecha_proximo_pago', 
                    'fin_periodo_prueba'
                ]);
        }
        
        // Obtener el plan desde la suscripción o desde la empresa
        $plan = null;
        if ($suscripcion && $suscripcion->plan_id) {
            $plan = Plan::find($suscripcion->plan_id);
        } else {
            $plan = Plan::where('nombre', $empresa->plan)->first();
        }
        
        $planData = null;
        if ($plan) {
            $planData = [
                'id' => $plan->id,
                'nombre' => $plan->nombre,
                'precio' => $plan->precio
            ];
        }
        
        // Obtener todos los pagos de la empresa
        $pagos = [];
        
        // Buscar todos los usuarios de la empresa
        $usuarios = User::where('id_empresa', $empresa->id)->get();
        
        // Recopilar los pagos de todos los usuarios de la empresa
        foreach ($usuarios as $usuario) {
            $pagosPorUsuario = $usuario->ordenesPago()
                ->select('plan', 'divisa', 'monto', 'estado', 'fecha_transaccion')
                ->get()
                ->toArray();
            
            $pagos = array_merge($pagos, $pagosPorUsuario);
        }
        
        // Obtener métodos de pago asociados a la empresa
        $metodoPago = null;
        foreach ($usuarios as $usuario) {
            $metodo = $usuario->metodoPago()
                ->where('es_predeterminado', true)
                ->where('esta_activo', true)
                ->first(['id', 'marca_tarjeta', 'ultimos_cuatro']);
            
            if ($metodo) {
                $metodoPago = $metodo;
                break;
            }
        }
        
        $dataResponse = [
            'suscripcion' => $suscripcion,
            'pagos' => $pagos,
            'plan' => $planData,
            'metodoPago' => $metodoPago
        ];
        
        return Response()->json($dataResponse, 201);
    }

    public function printRecibo($id){

        $recibo = Transaccion::where('id', $id)->firstOrFail();
        // return $recibo;
        $pdf = PDF::loadView('reportes.recibo-suscripcion', compact('recibo'));
        $pdf->setPaper('US Letter', 'portrait');  


        return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');
    }

    public function eliminarDatos(Request $request){
        $empresa = Empresa::where('id', $request->id)->firstOrFail();
        $sucursales = $empresa->sucursales()->pluck('id')->toArray();
        $bodegas = $empresa->bodegas()->pluck('id')->toArray();

        if ($request->m_inventario) {
            DB::table('productos')->where('id_empresa', $empresa->id)->update(['deleted_at' => Carbon::now()]);
            DB::table('inventario')->whereIn('id_bodega', $bodegas)->update(['deleted_at' => Carbon::now()]);
            DB::table('ajustes')->where('id_empresa', $empresa->id)->delete();
            DB::table('traslados')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_paquetes) {
            DB::table('paquetes')->where('id_empresa', $empresa->id)->update(['deleted_at' => Carbon::now()]);
        }

        if ($request->m_categorias) {
            DB::table('categorias')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_clientes) {
            DB::table('clientes')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_proveedores) {
            DB::table('proveedores')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_ventas) {
            DB::table('ventas')->where('id_empresa', $empresa->id)->delete();
            DB::table('abonos_ventas')->where('id_empresa', $empresa->id)->delete();
            DB::table('devoluciones_venta')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_compras) {
            DB::table('compras')->where('id_empresa', $empresa->id)->delete();
            DB::table('abonos_compras')->where('id_empresa', $empresa->id)->delete();
            DB::table('devoluciones_compra')->where('id_empresa', $empresa->id)->delete();
        }

        if ($request->m_gastos) {
            DB::table('egresos')->where('id_empresa', $empresa->id)->delete();
        }
        
        if ($request->m_presupuestos) {
            DB::table('presupuestos')->where('id_empresa', $empresa->id)->delete();
        }


        return Response()->json($empresa, 200);
    }

    public function getEmpresasforSelect()
    {
        $empresas = Empresa::select('id', 'nombre')
            ->orderBy('nombre', 'asc')
            ->where('activo', true)
            ->get();
        
        return response()->json($empresas);
    }

    private function getPlan($plan_id,$withName = false,$name = null)
    {
       $plan= null;
        if ($withName) {
            $plan= Plan::where('nombre',$name)->first();
        }else{
            $plan= Plan::find($plan_id);
        }

        return $plan;
    }

}
