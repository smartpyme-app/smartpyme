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
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Transaccion;
use App\Models\User;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Intervention\Image\ImageManagerStatic as Image;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Models\EmpresaConfiguracionPlanilla;
use App\Services\Planilla\PlanillaTemplatesService;
use App\Http\Requests\Admin\Empresas\StoreEmpresaRequest;
use App\Http\Requests\Admin\Empresas\UpdatePagoRecurrenteRequest;
use App\Http\Requests\Admin\Empresas\UpdateCustomConfigRequest;
use App\Http\Requests\Admin\Empresas\StoreImagenesRequest;

class EmpresasController extends Controller
{
    private $suscripcionService;

    public function __construct(SuscripcionService $suscripcionService)
    {
        $this->suscripcionService = $suscripcionService;
    }

    public function index(Request $request)
    {

        $empresas = Empresa::when($request->activo !== null, function ($q) use ($request) {
            $q->where('activo', !!$request->activo);
        })
            ->when($request->isColumnEnabled('columna_proyecto'), function ($query) {
                return $query->with('proyecto');
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })

            ->when($request->buscador, function ($query) use ($request) {
                return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orwhere('correo', 'like', "%" . $request->buscador . "%");
            })
            ->when($request->pago_inicio, function ($query) use ($request) {
                return $query->where('fecha_ultimo_pago', '>=', $request->pago_inicio);
            })
            ->when($request->pago_fin, function ($query) use ($request) {
                return $query->where('fecha_ultimo_pago', '<=', $request->pago_fin);
            })
            ->when($request->suscripcion_inicio, function ($query) use ($request) {
                return $query->where('created_at', '>=', $request->suscripcion_inicio);
            })
            ->when($request->suscripcion_fin, function ($query) use ($request) {
                return $query->where('created_at', '<=', $request->suscripcion_fin);
            })
            ->when($request->forma_pago, function ($query) use ($request) {
                return $query->where('forma_pago', $request->forma_pago);
            })
            ->when($request->plan, function ($query) use ($request) {
                return $query->where('plan', $request->plan);
            })
            ->orderBy($request->orden, $request->direccion)
            ->paginate($request->paginate);

        return Response()->json($empresas, 200);
    }

    public function list()
    {
        try {
            // Intentar obtener empresas activas
            $empresas = Empresa::select('id', 'nombre')
                ->where(function($query) {
                    $query->where('activo', true)
                          ->orWhere('activo', 1);
                })
                ->orderBy('nombre')
                ->get();

            return Response()->json($empresas, 200);
        } catch (QueryException $e) {
            // Si hay un error de SQL, puede ser que la columna no exista
            Log::error('Error SQL en EmpresasController@list: ' . $e->getMessage());
            Log::error('Código SQL: ' . $e->getCode());

            // Intentar sin el filtro de activo
            try {
                $empresas = Empresa::select('id', 'nombre')
                    ->orderBy('nombre')
                    ->get();

                return Response()->json($empresas, 200);
            } catch (\Exception $e2) {
                Log::error('Error al cargar empresas sin filtro: ' . $e2->getMessage());
                return Response()->json([
                    'error' => 'Error al cargar las empresas',
                    'message' => $e2->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en EmpresasController@list: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return Response()->json([
                'error' => 'Error al cargar las empresas',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function read($id)
    {

        $empresa = Empresa::findOrFail($id);
        return Response()->json($empresa, 200);
    }

    public function store(StoreEmpresaRequest $request)
    {

        if ($request->id) {
            $empresa = $this->updateEmpresa($request);
        } else {
            $empresa = $this->createEmpresa($request);
        }

        return Response()->json($empresa, 200);
    }

    private function updateEmpresa(Request $request)
    {
        $empresa = Empresa::findOrFail($request->id);

        $this->handleUserAccountStatus($empresa, $request);

        $woocommerceValues = $this->preserveWoocommerceSettings($empresa);

        $this->handleCustomEmpresa($request, $empresa); // Maneja la personalización de la empresa

        // Si se envía el país pero no el cod_pais, establecerlo automáticamente
        if ($request->has('pais') && !$request->has('cod_pais')) {
            $request->merge(['cod_pais' => $this->mapearCodigoPais($request->pais)]);
        }

        $empresa->fill($request->all());

        if ($request->hasFile('file')) {
            $empresa->logo = $this->handleLogoUpload($request, $empresa);
        }

        $this->restoreWoocommerceSettings($empresa, $woocommerceValues);

        $empresa->save();

        return $empresa;
    }

    private function createEmpresa(Request $request)
    {
        // Si se envía el país pero no el cod_pais, establecerlo automáticamente
        if ($request->has('pais') && !$request->has('cod_pais')) {
            $request->merge(['cod_pais' => $this->mapearCodigoPais($request->pais)]);
        }

        $empresa = new Empresa;
        $empresa->fill($request->all());

        if ($request->hasFile('file')) {
            $empresa->logo = $this->handleLogoUpload($request, $empresa);
        }

        $empresa->save();

        if (!isset($request->isRegister) || $request->isRegister !== false) {
            $this->createCompanySubscription($empresa, $request);
        }

        $this->createInitialCompanyStructure($empresa);

        return $empresa;
    }

    private function handleUserAccountStatus(Empresa $empresa, Request $request)
    {
        if (($empresa->activo == '1') && ($request['activo'] == '0')) {
            foreach ($empresa->usuarios()->get() as $usuario) {
                $usuario->enable = false;
                $usuario->save();
            }
        }

        if (($empresa->activo == '0') && ($request['activo'] == '1')) {
            foreach ($empresa->usuarios()->where('tipo', 'Administrador')->get() as $usuario) {
                $usuario->enable = true;
                $usuario->save();
            }
        }
    }

    private function preserveWoocommerceSettings(Empresa $empresa)
    {
        $woocommerceFields = [
            'woocommerce_api_key',
            'woocommerce_store_url',
            'woocommerce_consumer_key',
            'woocommerce_consumer_secret',
            'woocommerce_status'
        ];

        $woocommerceValues = [];
        foreach ($woocommerceFields as $field) {
            $woocommerceValues[$field] = $empresa->$field;
        }

        return $woocommerceValues;
    }

    private function restoreWoocommerceSettings(Empresa $empresa, array $woocommerceValues)
    {
        foreach ($woocommerceValues as $field => $value) {
            $empresa->$field = $value;
        }
    }

    private function handleLogoUpload(Request $request, Empresa $empresa)
    {
        if ($request->id && $empresa->logo && $empresa->logo != 'empresas/default.jpg') {
            Storage::delete($empresa->logo);
        }

        $path = $request->file('file');
        $resize = Image::make($path)->resize(350, 350)->encode('jpg', 75);
        $hash = md5($resize->__toString());
        $path = "empresas/{$hash}.jpg";
        $resize->save(public_path('img/' . $path), 50);

        return "/" . $path;
    }

    private function createCompanySubscription(Empresa $empresa, Request $request)
    {
        $plan = $this->getPlan($empresa->plan, true, $empresa->plan);

        $this->createSuscripcion([
            'empresa_id' => $empresa->id,
            'plan_id' => $plan->id,
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
    }

    private function createInitialCompanyStructure(Empresa $empresa)
    {
        $sucursal = Sucursal::create([
            'nombre' => $empresa->nombre,
            'id_empresa' => $empresa->id
        ]);

        $bodega = Bodega::create([
            'nombre' => $empresa->nombre,
            'id_sucursal' => $sucursal->id,
            'id_empresa' => $empresa->id
        ]);

        Canal::create([
            'nombre' => $empresa->nombre,
            'enable' => true,
            'id_empresa' => $empresa->id
        ]);

        Impuesto::create([
            'nombre' => 'IVA',
            'porcentaje' => $empresa->iva,
            'id_empresa' => $empresa->id
        ]);

        $this->createPaymentMethods($empresa);
        $this->createDocuments($sucursal, $empresa);
        $this->createPlanillaConfiguration($empresa);
    }

    private function createPlanillaConfiguration($empresa)
    {
        try {
            $codPais = $this->mapearCodigoPais($empresa->pais ?? 'El Salvador');

            EmpresaConfiguracionPlanilla::create([
                'empresa_id' => $empresa->id,
                'cod_pais' => $codPais,
                'configuracion' => PlanillaTemplatesService::getConfiguracionPorPais($codPais),
                'activo' => true,
                'fecha_vigencia_desde' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error creando configuración: {$e->getMessage()}");
        }
    }

    private function mapearCodigoPais($nombrePais)
    {
        $mapeo = [
            'El Salvador' => 'SV',
            'Guatemala' => 'GT',
            'Honduras' => 'HN',
            'Nicaragua' => 'NI',
            'Costa Rica' => 'CR',
            'Panama' => 'PA',
            'Panamá' => 'PA',
            'Belice' => 'BZ'
        ];

        return $mapeo[$nombrePais] ?? 'SV';
    }

    private function createPaymentMethods(Empresa $empresa)
    {
        FormaDePago::create([
            'nombre' => config('constants.TIPO_PAGO_EFECTIVO'),
            'id_empresa' => $empresa->id
        ]);

        FormaDePago::create([
            'nombre' => config('constants.TIPO_PAGO_TRANSFERENCIA'),
            'id_empresa' => $empresa->id
        ]);

        FormaDePago::create([
            'nombre' => config('constants.TIPO_PAGO_TARJETA'),
            'id_empresa' => $empresa->id
        ]);
    }

    private function createDocuments(Sucursal $sucursal, Empresa $empresa)
    {
        $documentTypes = [
            'TIPO_DOCUMENTO_TICKET',
            'TIPO_DOCUMENTO_FACTURA',
            'TIPO_DOCUMENTO_CREDITO_FISCAL',
            'TIPO_DOCUMENTO_COTIZACION',
            'TIPO_DOCUMENTO_ORDEN_COMPRA'
        ];

        foreach ($documentTypes as $type) {
            Documento::create([
                'nombre' => config('constants.' . $type),
                'correlativo' => 1,
                'activo' => 1,
                'id_sucursal' => $sucursal->id,
                'id_empresa' => $empresa->id
            ]);
        }
    }

    private function createSuscripcion(array $data): array
    {
        $plan = Plan::find($data['plan_id']);

        if ($plan && $plan->permite_periodo_prueba) {
            $diasPrueba = $plan->dias_periodo_prueba;

            $data = array_merge($data, [
                'estado' => config('constants.ESTADO_SUSCRIPCION_EN_PRUEBA'),
                'estado_ultimo_pago' => null,
                'fecha_ultimo_pago' => null,
                'fecha_proximo_pago' => now()->addDays($diasPrueba),
                'fin_periodo_prueba' => now()->addDays($diasPrueba),
                'monto' => 0,
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
                'id',
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
                    'id',
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


        if ($empresa->pago_recurrente) {
            $suscripcion->pago_recurrente_empresa = $empresa->pago_recurrente;
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
                ->whereIn('estado', ['completado', 'fallido', 'rechazado'])
                ->latest()
                ->get()
                ->toArray();

            $pagos = array_merge($pagos, $pagosPorUsuario);
        }

        usort($pagos, function ($a, $b) {
            return strtotime($b['fecha_transaccion']) - strtotime($a['fecha_transaccion']);
        });

        // Obtener métodos de pago asociados a la empresa
        $metodoPago = null;
        $usuarioAutenticado = JWTAuth::parseToken()->authenticate();
        $metodoPago = $usuarioAutenticado->metodoPago()
            ->where('es_predeterminado', true)
            ->where('esta_activo', true)
            ->first(['id', 'marca_tarjeta', 'ultimos_cuatro']);

        $dataResponse = [
            'suscripcion' => $suscripcion,
            'pagos' => $pagos,
            'plan' => $planData,
            'metodoPago' => $metodoPago
        ];

        return Response()->json($dataResponse, 201);
    }

    // public function printRecibo($id){

    //     $recibo = Transaccion::where('id', $id)->firstOrFail();
    //     // return $recibo;
    //     $pdf = PDF::loadView('reportes.recibo-suscripcion', compact('recibo'));
    //     $pdf->setPaper('US Letter', 'portrait');


    //     return $pdf->stream('recibo-' . $recibo->concepto . '.pdf');
    // }

    public function printReciboSuscripcion($id, Request $request)
    {
        try {
            Log::info('Imprimiendo recibo de suscripción con ID: ' . $id);

            // Obtener la suscripción
            $suscripcion = Suscripcion::findOrFail($id);

            // Obtener datos del pago desde los parámetros
            $fechaTransaccion = $request->get('fecha');
            $monto = $request->get('monto');
            $estado = $request->get('estado');
            $plan = $request->get('plan');

            // Buscar empresa asociada
            $empresa = Empresa::findOrFail($suscripcion->empresa_id);

            // Preparar datos para la vista
            $recibo = new \stdClass();
            $recibo->id = 'R-' . time();
            $recibo->concepto = "Pago de suscripción {$plan}";
            $recibo->descripcion = "Plan {$plan} - {$suscripcion->tipo_plan}";
            $recibo->total = $monto;
            $recibo->estado = $estado;
            $recibo->created_at = $fechaTransaccion ?
                \Carbon\Carbon::parse($fechaTransaccion) :
                now();

            // Asignar la empresa como una propiedad normal (no como función)
            $recibo->empresa = $empresa;

            // Generar el PDF
            $pdf = app('dompdf.wrapper')->loadView('reportes.recibo-suscripcion', compact('recibo'));
            $pdf->setPaper('US Letter', 'portrait');

            return $pdf->stream("recibo-plan-{$plan}.pdf");
        } catch (\Exception $e) {
            Log::error('Error generando recibo: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error generando el recibo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function eliminarDatos(Request $request)
    {
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

    private function getPlan($plan_id, $withName = false, $name = null)
    {
        $plan = null;
        if ($withName) {
            $plan = Plan::where('nombre', $name)->first();
        } else {
            $plan = Plan::find($plan_id);
        }

        return $plan;
    }

    public function updatePagoRecurrente(UpdatePagoRecurrenteRequest $request)
    {
        $empresa = Empresa::findOrFail($request->id_empresa);
        $empresa->pago_recurrente = $request->pago_recurrente;
        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Pago recurrente actualizado exitosamente',
            'data' => $empresa
        ]);
    }

    public function getAlertSuscription()
    {
        try {
            $id_empresa = auth()->user()->id_empresa;

            $empresa = Empresa::findOrFail($id_empresa);
            return response()->json([
                'alerta_suscripcion' => $empresa->alerta_suscripcion
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'alerta_suscripcion' => false
            ], 500);
        }
    }

    public function isVisibleAlertSuscription(Request $request)
    {
        try {
            $id_empresa = auth()->user()->id_empresa;

            $empresa = Empresa::findOrFail($id_empresa);
            $empresa->alerta_suscripcion = false;
            $empresa->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Alerta desactivada correctamente',
                'alerta_suscripcion' => false
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al desactivar la alerta: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleCustomEmpresa(Request $request, Empresa $empresa)
    {
        // Si viene custom_empresa en el request
        if ($request->has('custom_empresa')) {
            $customConfig = $request->input('custom_empresa');

            // Si es string JSON, decodificar
            if (is_string($customConfig)) {
                $customConfig = json_decode($customConfig, true);
            }

            // Validar estructura básica
            if (is_array($customConfig)) {
                // Asegurar que existan las secciones básicas
                $defaultStructure = [
                    'columnas' => [],
                    'modulos' => [],
                    'configuraciones' => [],
                    'campos_personalizados' => []
                ];

                // Mergear con estructura por defecto
                $customConfig = array_merge($defaultStructure, $customConfig);

                // Validar y limpiar datos de columnas
                if (isset($customConfig['columnas']) && is_array($customConfig['columnas'])) {
                    $customConfig['columnas'] = $this->validateColumnConfig($customConfig['columnas']);
                }

                // Validar y limpiar datos de configuraciones
                if (isset($customConfig['configuraciones']) && is_array($customConfig['configuraciones'])) {
                    $customConfig['configuraciones'] = $this->validateConfiguracionConfig($customConfig['configuraciones']);
                }

                $empresa->custom_empresa = $customConfig;
            }
        } else {
            // Si no viene custom_empresa pero la empresa no tiene configuración, inicializarla
            if (empty($empresa->custom_empresa)) {
                $empresa->initializeCustomConfig();
            }
        }
    }

    private function validateColumnConfig(array $columnas): array
    {
        $validatedColumns = [];
        $allowedColumns = [
            'columna_proyecto',
            // Agregar más columnas válidas aquí
        ];

        foreach ($columnas as $column => $enabled) {
            // Solo permitir columnas válidas
            if (in_array($column, $allowedColumns)) {
                // Asegurar que el valor sea boolean
                $validatedColumns[$column] = (bool) $enabled;
            }
        }

        return $validatedColumns;
    }

    /**
     * Validación opcional para configuraciones
     */
    private function validateConfiguracionConfig(array $configuraciones): array
    {
        $validatedConfig = [];
        $allowedConfigs = [
            'ticket_en_pdf',
            'componente_quimico_activo',
            'dte_mostrar_descripcion_producto',
        ];

        foreach ($configuraciones as $config => $value) {
            // Solo permitir configuraciones válidas
            if (in_array($config, $allowedConfigs)) {
                // Para configuraciones booleanas
                if (in_array($config, ['ticket_en_pdf', 'componente_quimico_activo', 'dte_mostrar_descripcion_producto'])) {
                    $validatedConfig[$config] = (bool) $value;
                } else {
                    $validatedConfig[$config] = $value;
                }
            }
        }

        return $validatedConfig;
    }

    public function updateCustomConfig(UpdateCustomConfigRequest $request)
    {
        $empresa = Auth::user()->empresa;

        if ($request->input('section') === 'configuraciones' && in_array($request->input('key'), ['ticket_en_pdf', 'componente_quimico_activo', 'sku_correlativo_automatico', 'dte_mostrar_descripcion_producto'])) {
            $request->validate([
                'value' => 'boolean'
            ]);
        }

        $empresa->updateCustomConfig(
            $request->input('section'),
            $request->input('key'),
            $request->input('value')
        );

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada correctamente',
            'config' => $empresa->fresh()->custom_empresa
        ]);
    }

    public function getCustomConfig()
    {
        $empresa = Auth::user()->empresa;

        if (empty($empresa->custom_empresa)) {
            $empresa->initializeCustomConfig();
        }

        return response()->json([
            'success' => true,
            'config' => $empresa->custom_empresa,
            'available_columns' => $empresa->getAvailableColumns()
        ]);
    }

    public function resetCustomConfig()
    {
        $empresa = Auth::user()->empresa;
        $empresa->custom_empresa = null;
        $empresa->save();

        $defaultConfig = $empresa->initializeCustomConfig();

        return response()->json([
            'success' => true,
            'message' => 'Configuración restablecida correctamente',
            'config' => $defaultConfig
        ]);
    }


    public function storeImagenes(StoreImagenesRequest $request)
    {
        $empresa = Empresa::findOrFail($request->id);

        $type = $request->type;


        if ($type == 'sello') {
            if ($empresa->sello && $empresa->sello != 'empresas/default.jpg') {
                Storage::delete($empresa->sello);
            }
        }

        if ($type == 'firma') {
            if ($empresa->firma && $empresa->firma != 'empresas/default.jpg') {
                Storage::delete($empresa->firma);
            }
        }

        if ($type == 'logo') {
            if ($empresa->logo && $empresa->logo != 'empresas/default.jpg') {
                Storage::delete($empresa->logo);
            }
        }

        $path = $request->file('file');
        $resize = Image::make($path)->resize(350, 350)->encode('jpg', 75);
        $hash = md5($resize->__toString());
        $path = "empresas/{$hash}.jpg";
        $resize->save(public_path('img/' . $path), 50);

        if ($type == 'sello') {
            $empresa->sello = "/" . $path;
        }

        if ($type == 'firma') {
            $empresa->firma = "/" . $path;
        }

        if ($type == 'logo') {
            $empresa->logo = "/" . $path;
        }
        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Imagen guardada correctamente',
            'path' => "/" . $path
        ]);
    }
}
