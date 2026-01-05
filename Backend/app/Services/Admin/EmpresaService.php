<?php

namespace App\Services\Admin;

use App\Models\Admin\Empresa;
use App\Models\Admin\Canal;
use App\Models\Admin\Documento;
use App\Models\Admin\FormaDePago;
use App\Models\Admin\Impuesto;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use App\Models\Plan;
use App\Models\User;
use App\Models\EmpresaConfiguracionPlanilla;
use App\Services\Suscripcion\SuscripcionService;
use App\Services\Planilla\PlanillaTemplatesService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;
use Carbon\Carbon;

class EmpresaService
{
    protected $suscripcionService;

    public function __construct(SuscripcionService $suscripcionService)
    {
        $this->suscripcionService = $suscripcionService;
    }

    /**
     * Construye query base para listar empresas con filtros
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function construirQueryConFiltros($request)
    {
        $query = Empresa::query();

        if ($request->activo !== null) {
            $query->where('activo', !!$request->activo);
        }

        if ($request->isColumnEnabled('columna_proyecto')) {
            $query->with('proyecto');
        }

        if ($request->id_proyecto) {
            $query->where('id_proyecto', $request->id_proyecto);
        }

        if ($request->buscador) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orWhere('correo', 'like', "%" . $request->buscador . "%");
            });
        }

        $filtrosFecha = [
            'pago_inicio' => 'fecha_ultimo_pago',
            'pago_fin' => 'fecha_ultimo_pago',
            'suscripcion_inicio' => 'created_at',
            'suscripcion_fin' => 'created_at'
        ];

        foreach ($filtrosFecha as $param => $campo) {
            if ($request->$param) {
                if (strpos($param, '_inicio') !== false) {
                    $query->where($campo, '>=', $request->$param);
                } else {
                    $query->where($campo, '<=', $request->$param);
                }
            }
        }

        if ($request->forma_pago) {
            $query->where('forma_pago', $request->forma_pago);
        }

        if ($request->plan) {
            $query->where('plan', $request->plan);
        }

        $orden = $request->orden ?? 'id';
        $direccion = $request->direccion ?? 'desc';
        $query->orderBy($orden, $direccion);

        return $query;
    }

    /**
     * Lista empresas con paginación
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarEmpresas($request)
    {
        $query = $this->construirQueryConFiltros($request);
        return $query->paginate($request->paginate ?? 10);
    }

    /**
     * Obtiene lista simple de empresas activas
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listarEmpresasActivas()
    {
        return Empresa::orderBy('nombre')
            ->where('activo', true)
            ->get();
    }

    /**
     * Crea una nueva empresa con su estructura inicial
     *
     * @param array $data
     * @param bool $crearSuscripcion
     * @return Empresa
     */
    public function crearEmpresa(array $data, bool $crearSuscripcion = true): Empresa
    {
        $empresa = new Empresa;
        $empresa->fill($data);
        $empresa->save();

        if ($crearSuscripcion) {
            $this->crearSuscripcionEmpresa($empresa, $data);
        }

        $this->crearEstructuraInicial($empresa);

        return $empresa;
    }

    /**
     * Actualiza una empresa existente
     *
     * @param int $id
     * @param array $data
     * @param \Illuminate\Http\UploadedFile|null $logoFile
     * @return Empresa
     */
    public function actualizarEmpresa(int $id, array $data, $logoFile = null): Empresa
    {
        $empresa = Empresa::findOrFail($id);
        $estadoAnterior = $empresa->activo;

        $this->manejarEstadoUsuarios($empresa, $estadoAnterior, $data['activo'] ?? $estadoAnterior);

        $woocommerceValues = $this->preservarConfiguracionWoocommerce($empresa);

        $this->manejarConfiguracionPersonalizada($data, $empresa);

        $empresa->fill($data);

        if ($logoFile) {
            $empresa->logo = $this->procesarLogo($logoFile, $empresa);
        }

        $this->restaurarConfiguracionWoocommerce($empresa, $woocommerceValues);

        $empresa->save();

        return $empresa;
    }

    /**
     * Crea la estructura inicial de una empresa
     *
     * @param Empresa $empresa
     * @return void
     */
    public function crearEstructuraInicial(Empresa $empresa): void
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

        $this->crearMetodosPago($empresa);
        $this->crearDocumentos($sucursal, $empresa);
        $this->crearConfiguracionPlanilla($empresa);
    }

    /**
     * Crea métodos de pago por defecto para una empresa
     *
     * @param Empresa $empresa
     * @return void
     */
    public function crearMetodosPago(Empresa $empresa): void
    {
        $metodosPago = [
            config('constants.TIPO_PAGO_EFECTIVO'),
            config('constants.TIPO_PAGO_TRANSFERENCIA'),
            config('constants.TIPO_PAGO_TARJETA')
        ];

        foreach ($metodosPago as $metodo) {
            FormaDePago::create([
                'nombre' => $metodo,
                'id_empresa' => $empresa->id
            ]);
        }
    }

    /**
     * Crea documentos por defecto para una sucursal
     *
     * @param Sucursal $sucursal
     * @param Empresa $empresa
     * @return void
     */
    public function crearDocumentos(Sucursal $sucursal, Empresa $empresa): void
    {
        $tiposDocumento = [
            'TIPO_DOCUMENTO_TICKET',
            'TIPO_DOCUMENTO_FACTURA',
            'TIPO_DOCUMENTO_CREDITO_FISCAL',
            'TIPO_DOCUMENTO_COTIZACION',
            'TIPO_DOCUMENTO_ORDEN_COMPRA'
        ];

        foreach ($tiposDocumento as $tipo) {
            Documento::create([
                'nombre' => config('constants.' . $tipo),
                'correlativo' => 1,
                'activo' => 1,
                'id_sucursal' => $sucursal->id,
                'id_empresa' => $empresa->id
            ]);
        }
    }

    /**
     * Crea configuración de planilla para una empresa
     *
     * @param Empresa $empresa
     * @return void
     */
    public function crearConfiguracionPlanilla(Empresa $empresa): void
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
            Log::error("Error creando configuración de planilla: {$e->getMessage()}");
        }
    }

    /**
     * Mapea nombre de país a código ISO
     *
     * @param string $nombrePais
     * @return string
     */
    public function mapearCodigoPais(string $nombrePais): string
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

    /**
     * Crea suscripción para una empresa
     *
     * @param Empresa $empresa
     * @param array $data
     * @return void
     */
    public function crearSuscripcionEmpresa(Empresa $empresa, array $data): void
    {
        $plan = $this->obtenerPlan($empresa->plan, true, $empresa->plan);

        if (!$plan) {
            Log::warning("No se encontró el plan {$empresa->plan} para la empresa {$empresa->id}");
            return;
        }

        $datosSuscripcion = [
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
        ];

        $this->crearSuscripcion($datosSuscripcion);
    }

    /**
     * Crea una suscripción con lógica de período de prueba
     *
     * @param array $data
     * @return array
     */
    public function crearSuscripcion(array $data): array
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
            $data = array_merge($data, [
                'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                'fecha_ultimo_pago' => null,
                'fecha_proximo_pago' => null,
                'fin_periodo_prueba' => null
            ]);
        }

        return $this->suscripcionService->createSuscripcion($data);
    }

    /**
     * Maneja el estado de usuarios cuando cambia el estado de la empresa
     *
     * @param Empresa $empresa
     * @param string $estadoAnterior
     * @param string $estadoNuevo
     * @return void
     */
    public function manejarEstadoUsuarios(Empresa $empresa, string $estadoAnterior, string $estadoNuevo): void
    {
        // Si se desactiva la empresa, desactivar todos los usuarios
        if ($estadoAnterior == '1' && $estadoNuevo == '0') {
            foreach ($empresa->usuarios()->get() as $usuario) {
                $usuario->enable = false;
                $usuario->save();
            }
        }

        // Si se activa la empresa, activar solo administradores
        if ($estadoAnterior == '0' && $estadoNuevo == '1') {
            foreach ($empresa->usuarios()->where('tipo', 'Administrador')->get() as $usuario) {
                $usuario->enable = true;
                $usuario->save();
            }
        }
    }

    /**
     * Preserva configuración de WooCommerce antes de actualizar
     *
     * @param Empresa $empresa
     * @return array
     */
    public function preservarConfiguracionWoocommerce(Empresa $empresa): array
    {
        $camposWoocommerce = [
            'woocommerce_api_key',
            'woocommerce_store_url',
            'woocommerce_consumer_key',
            'woocommerce_consumer_secret',
            'woocommerce_status'
        ];

        $valores = [];
        foreach ($camposWoocommerce as $campo) {
            $valores[$campo] = $empresa->$campo;
        }

        return $valores;
    }

    /**
     * Restaura configuración de WooCommerce después de actualizar
     *
     * @param Empresa $empresa
     * @param array $valores
     * @return void
     */
    public function restaurarConfiguracionWoocommerce(Empresa $empresa, array $valores): void
    {
        foreach ($valores as $campo => $valor) {
            $empresa->$campo = $valor;
        }
    }

    /**
     * Procesa y guarda el logo de una empresa
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param Empresa|null $empresa
     * @return string
     */
    public function procesarLogo($file, ?Empresa $empresa = null): string
    {
        if ($empresa && $empresa->logo && $empresa->logo != 'empresas/default.jpg') {
            Storage::delete($empresa->logo);
        }

        $resize = Image::make($file)->resize(350, 350)->encode('jpg', 75);
        $hash = md5($resize->__toString());
        $path = "empresas/{$hash}.jpg";
        $resize->save(public_path('img/' . $path), 50);

        return "/" . $path;
    }

    /**
     * Procesa y guarda una imagen (logo, sello o firma)
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $tipo
     * @param Empresa $empresa
     * @return string
     */
    public function procesarImagen($file, string $tipo, Empresa $empresa): string
    {
        $campo = $tipo; // 'logo', 'sello', 'firma'

        if ($empresa->$campo && $empresa->$campo != 'empresas/default.jpg') {
            Storage::delete($empresa->$campo);
        }

        $resize = Image::make($file)->resize(350, 350)->encode('jpg', 75);
        $hash = md5($resize->__toString());
        $path = "empresas/{$hash}.jpg";
        $resize->save(public_path('img/' . $path), 50);

        return "/" . $path;
    }

    /**
     * Maneja configuración personalizada de empresa
     *
     * @param array $data
     * @param Empresa $empresa
     * @return void
     */
    public function manejarConfiguracionPersonalizada(array &$data, Empresa $empresa): void
    {
        if (isset($data['custom_empresa'])) {
            $customConfig = $data['custom_empresa'];

            if (is_string($customConfig)) {
                $customConfig = json_decode($customConfig, true);
            }

            if (is_array($customConfig)) {
                $defaultStructure = [
                    'columnas' => [],
                    'modulos' => [],
                    'configuraciones' => [],
                    'campos_personalizados' => []
                ];

                $customConfig = array_merge($defaultStructure, $customConfig);

                if (isset($customConfig['columnas']) && is_array($customConfig['columnas'])) {
                    $customConfig['columnas'] = $this->validarConfiguracionColumnas($customConfig['columnas']);
                }

                if (isset($customConfig['configuraciones']) && is_array($customConfig['configuraciones'])) {
                    $customConfig['configuraciones'] = $this->validarConfiguracionConfig($customConfig['configuraciones']);
                }

                $data['custom_empresa'] = $customConfig;
            }
        } else {
            if (empty($empresa->custom_empresa)) {
                $empresa->initializeCustomConfig();
            }
        }
    }

    /**
     * Valida configuración de columnas
     *
     * @param array $columnas
     * @return array
     */
    public function validarConfiguracionColumnas(array $columnas): array
    {
        $validatedColumns = [];
        $allowedColumns = ['columna_proyecto'];

        foreach ($columnas as $column => $enabled) {
            if (in_array($column, $allowedColumns)) {
                $validatedColumns[$column] = (bool) $enabled;
            }
        }

        return $validatedColumns;
    }

    /**
     * Valida configuración general
     *
     * @param array $configuraciones
     * @return array
     */
    public function validarConfiguracionConfig(array $configuraciones): array
    {
        $validatedConfig = [];
        $allowedConfigs = ['ticket_en_pdf'];

        foreach ($configuraciones as $config => $value) {
            if (in_array($config, $allowedConfigs)) {
                if ($config === 'ticket_en_pdf') {
                    $validatedConfig[$config] = (bool) $value;
                } else {
                    $validatedConfig[$config] = $value;
                }
            }
        }

        return $validatedConfig;
    }

    /**
     * Elimina datos de una empresa según los módulos especificados
     *
     * @param int $empresaId
     * @param array $modulos
     * @return Empresa
     */
    public function eliminarDatosEmpresa(int $empresaId, array $modulos): Empresa
    {
        $empresa = Empresa::findOrFail($empresaId);
        $sucursales = $empresa->sucursales()->pluck('id')->toArray();
        $bodegas = $empresa->bodegas()->pluck('id')->toArray();

        if (isset($modulos['m_inventario']) && $modulos['m_inventario']) {
            DB::table('productos')->where('id_empresa', $empresa->id)->update(['deleted_at' => Carbon::now()]);
            DB::table('inventario')->whereIn('id_bodega', $bodegas)->update(['deleted_at' => Carbon::now()]);
            DB::table('ajustes')->where('id_empresa', $empresa->id)->delete();
            DB::table('traslados')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_paquetes']) && $modulos['m_paquetes']) {
            DB::table('paquetes')->where('id_empresa', $empresa->id)->update(['deleted_at' => Carbon::now()]);
        }

        if (isset($modulos['m_categorias']) && $modulos['m_categorias']) {
            DB::table('categorias')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_clientes']) && $modulos['m_clientes']) {
            DB::table('clientes')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_proveedores']) && $modulos['m_proveedores']) {
            DB::table('proveedores')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_ventas']) && $modulos['m_ventas']) {
            DB::table('ventas')->where('id_empresa', $empresa->id)->delete();
            DB::table('abonos_ventas')->where('id_empresa', $empresa->id)->delete();
            DB::table('devoluciones_venta')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_compras']) && $modulos['m_compras']) {
            DB::table('compras')->where('id_empresa', $empresa->id)->delete();
            DB::table('abonos_compras')->where('id_empresa', $empresa->id)->delete();
            DB::table('devoluciones_compra')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_gastos']) && $modulos['m_gastos']) {
            DB::table('egresos')->where('id_empresa', $empresa->id)->delete();
        }

        if (isset($modulos['m_presupuestos']) && $modulos['m_presupuestos']) {
            DB::table('presupuestos')->where('id_empresa', $empresa->id)->delete();
        }

        return $empresa;
    }

    /**
     * Obtiene información de suscripción de una empresa
     *
     * @param Empresa $empresa
     * @return array
     */
    public function obtenerInformacionSuscripcion(Empresa $empresa): array
    {
        $suscripcion = $empresa->suscripcion()
            ->where('estado', 'activo')
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
                'plan_id'
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
                    'plan_id'
                ]);
        }

        if ($empresa->pago_recurrente) {
            $suscripcion->pago_recurrente_empresa = $empresa->pago_recurrente;
        }

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

        $pagos = $this->obtenerPagosEmpresa($empresa);

        return [
            'suscripcion' => $suscripcion,
            'pagos' => $pagos,
            'plan' => $planData,
            'metodoPago' => $this->obtenerMetodoPagoPredeterminado()
        ];
    }

    /**
     * Obtiene pagos de una empresa
     *
     * @param Empresa $empresa
     * @return array
     */
    public function obtenerPagosEmpresa(Empresa $empresa): array
    {
        $pagos = [];
        $usuarios = User::where('id_empresa', $empresa->id)->get();

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

        return $pagos;
    }

    /**
     * Obtiene método de pago predeterminado del usuario autenticado
     *
     * @return \App\Models\MetodoPago|null
     */
    public function obtenerMetodoPagoPredeterminado()
    {
        $usuario = auth()->user();
        if (!$usuario) {
            return null;
        }

        return $usuario->metodoPago()
            ->where('es_predeterminado', true)
            ->where('esta_activo', true)
            ->first(['id', 'marca_tarjeta', 'ultimos_cuatro']);
    }

    /**
     * Obtiene un plan por ID o nombre
     *
     * @param mixed $planId
     * @param bool $porNombre
     * @param string|null $nombre
     * @return Plan|null
     */
    public function obtenerPlan($planId, bool $porNombre = false, ?string $nombre = null): ?Plan
    {
        if ($porNombre && $nombre) {
            return Plan::where('nombre', $nombre)->first();
        }

        return Plan::find($planId);
    }
}
