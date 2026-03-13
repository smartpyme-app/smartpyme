<?php

namespace App\Services\Auth;

use App\Models\Admin\Acceso;
use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Plan;
use App\Models\Promocional;
use App\Models\Suscripcion;
use App\Models\Transaccion;
use App\Services\Suscripcion\SuscripcionService;
use App\Services\Admin\EmpresaService;
use App\Services\Promocional\PromocionalService;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AuthService
{
    protected $suscripcionService;
    protected $empresaService;
    protected $promocionalService;

    public function __construct(
        SuscripcionService $suscripcionService,
        EmpresaService $empresaService,
        PromocionalService $promocionalService
    ) {
        $this->suscripcionService = $suscripcionService;
        $this->empresaService = $empresaService;
        $this->promocionalService = $promocionalService;
    }

    /**
     * Autentica un usuario y genera token JWT
     *
     * @param array $credentials
     * @return array
     * @throws \Exception
     */
    public function login(array $credentials): array
    {
        $token = JWTAuth::attempt($credentials);
        
        if (!$token) {
            throw new \Exception('Datos incorrectos, asegúrate de que tu usuario y contraseña estén escritos correctamente.', 401);
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Error al obtener usuario autenticado', 401);
        }
        
        if (!$user->enable) {
            throw new \Exception('Lo sentimos, este usuario esta inactivo', 401);
        }

        if (!$user->empresa()->pluck('activo')->first()) {
            throw new \Exception('Lo sentimos, la cuenta no esta activa', 401);
        }

        // Actualizar último login
        $user->ultimo_login = Carbon::now();
        $user->save();

        // Registrar acceso
        $this->registrarAcceso($user);

        // Cargar datos adicionales del usuario
        $user = $this->cargarDatosUsuario($user);

        // Obtener permisos
        $permissions = $this->obtenerPermisosUsuario($user);

        return [
            'token' => $token,
            'user' => $user,
            'permissions' => $permissions
        ];
    }

    /**
     * Registra un nuevo usuario y empresa
     *
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function register(array $data): User
    {
        DB::beginTransaction();
        try {
            // Crear o actualizar empresa
            if (isset($data['id'])) {
                $empresa = Empresa::findOrFail($data['empresa']['id']);
                $esActualizacion = true;
            } else {
                $empresa = $this->crearEmpresaDesdeRegistro($data);
                $esActualizacion = false;
            }

            // Crear estructura inicial si es nueva empresa
            if (!$esActualizacion) {
                $this->empresaService->crearEstructuraInicial($empresa);
            }

            // Crear o actualizar usuario
            $usuario = $this->crearOActualizarUsuario($data, $empresa, $esActualizacion);

            // Crear suscripción
            $suscripcion = $this->crearSuscripcionDesdeRegistro($empresa, $usuario, $data);

            // Cargar datos adicionales
            $usuario->empresa = $usuario->empresa()->first();
            $usuario->plan = $empresa->plan;
            $usuario->plan_id = $suscripcion['plan_id'];
            $usuario->url_n1co = $this->obtenerUrlN1co($empresa->plan);

            DB::commit();
            return $usuario;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Crea una empresa desde datos de registro
     *
     * @param array $data
     * @return Empresa
     */
    private function crearEmpresaDesdeRegistro(array $data): Empresa
    {
        $empresa = new Empresa();
        $empresa->activo = true;
        $empresa->nombre = $data['empresa']['nombre'];
        $empresa->nombre_propietario = $data['name'];
        $empresa->telefono = $data['telefono'];
        $empresa->iva = $data['empresa']['iva'];
        $empresa->plan = $this->obtenerPlan($data['empresa']['plan'])->nombre;
        $empresa->correo = $data['email'];
        $empresa->user_limit = $data['empresa']['user_limit'];
        $empresa->sucursal_limit = $data['empresa']['sucursal_limit'];
        $empresa->tipo_plan = $data['empresa']['tipo_plan'] ?? 'Mensual';
        $empresa->industria = $data['empresa']['industria'] ?? null;
        $empresa->pais = $data['empresa']['pais'];
        $empresa->moneda = $data['empresa']['moneda'] ?? 'USD';

        // Configurar máscaras según país
        $mascaras = $this->obtenerMascarasPorPais($data['empresa']['pais']);
        $empresa->validador_dui = $mascaras['dui'];
        $empresa->validador_nit = $mascaras['nit'];
        $empresa->validador_nrc = $mascaras['nrc'];
        $empresa->validador_telefono = $mascaras['telefono'];

        // Calcular precios y aplicar códigos promocionales
        $frecuenciaPago = $data['empresa']['frecuencia_pago'] ?? $data['empresa']['tipo_plan'] ?? 'Mensual';
        $plan = $this->obtenerPlan($data['empresa']['plan']);
        $precioMensual = $this->promocionalService->calcularTotalOriginal($plan, 'Mensual');
        
        $codigoPromocional = $data['empresa']['codigo_promocional'] ?? null;
        
        // Calcular montos con descuentos
        $montoMensual = $this->promocionalService->calcularMontoMensual($precioMensual, $codigoPromocional);
        $montoAnual = $this->promocionalService->calcularMontoAnual($precioMensual, $codigoPromocional);
        
        // Aplicar código promocional al total según frecuencia
        $totalOriginal = $this->calcularTotalSegunFrecuencia($precioMensual, $frecuenciaPago);
        $resultadoPromocional = $this->promocionalService->aplicarCodigoPromocional($empresa, $codigoPromocional, $totalOriginal, $frecuenciaPago);
        
        $empresa->total = $resultadoPromocional['total'];
        $empresa->tipo_plan = $frecuenciaPago;
        $empresa->frecuencia_pago = $frecuenciaPago;
        $empresa->monto_mensual = $montoMensual;
        $empresa->monto_anual = $montoAnual;
        
        // Guardar código promocional y campaña
        if ($codigoPromocional) {
            $empresa->codigo_promocional = $codigoPromocional;
            $promocional = $this->promocionalService->obtenerConfiguracionCodigoPromocional($codigoPromocional, null);
            if ($promocional && $promocional->campania) {
                $empresa->campania = $promocional->campania;
            }
        }
        
        $empresa->save();

        // Crear cliente para la empresa
        $cliente = \App\Models\Ventas\Clientes\Cliente::create([
            'nombre' => $empresa->nombre,
            'id_empresa' => 2
        ]);
        $empresa->id_cliente = $cliente->id;
        $empresa->save();

        return $empresa;
    }

    /**
     * Crea o actualiza un usuario desde registro
     *
     * @param array $data
     * @param Empresa $empresa
     * @param bool $esActualizacion
     * @return User
     */
    private function crearOActualizarUsuario(array $data, Empresa $empresa, bool $esActualizacion): User
    {
        if ($esActualizacion) {
            $usuario = User::findOrFail($data['id']);
        } else {
            $usuario = new User();
            $sucursal = $empresa->sucursales()->first();
            $bodega = $empresa->bodegas()->first();
            $usuario->id_sucursal = $sucursal->id;
            $usuario->id_bodega = $bodega->id;
            $usuario->id_empresa = $empresa->id;
        }

        $usuario->name = $data['name'];
        $usuario->email = $data['email'];
        $usuario->telefono = $data['telefono'];
        $usuario->password = bcrypt($data['password']);
        $usuario->tipo = config('constants.TIPO_USUARIO_ADMINISTRADOR');
        $usuario->enable = true;
        $usuario->save();

        $usuario->assignRole(config('constants.ROL_ADMIN'));

        return $usuario;
    }

    /**
     * Crea suscripción desde registro
     *
     * @param Empresa $empresa
     * @param User $usuario
     * @param array $data
     * @return array
     */
    private function crearSuscripcionDesdeRegistro(Empresa $empresa, User $usuario, array $data): array
    {
        $plan = $this->obtenerPlan($data['empresa']['plan']);
        $codigoPromocional = $data['empresa']['codigo_promocional'] ?? null;
        
        $finPeriodoPrueba = $this->promocionalService->calcularFinPeriodoPrueba(
            $codigoPromocional,
            $plan,
            $empresa->tipo_plan
        );
        
        $fechaProximoPago = $this->promocionalService->calcularFechaProximoPago($empresa->tipo_plan);
        
        return $this->suscripcionService->createSuscripcion([
            'empresa_id' => $empresa->id,
            'plan_id' => $plan->id,
            'usuario_id' => $usuario->id,
            'tipo_plan' => $empresa->tipo_plan,
            'estado' => config('constants.ESTADO_SUSCRIPCION_EN_PRUEBA', 'En prueba'),
            'monto' => $empresa->total,
            'id_pago' => null,
            'id_orden' => null,
            'estado_ultimo_pago' => null,
            'fecha_ultimo_pago' => null,
            'fecha_proximo_pago' => $fechaProximoPago,
            'fin_periodo_prueba' => $finPeriodoPrueba,
            'fecha_cancelacion' => null,
            'motivo_cancelacion' => null,
            'requiere_factura' => false,
            'nit' => null,
            'nombre_factura' => $usuario->name,
            'direccion_factura' => $empresa->direccion ?? null,
            'intentos_cobro' => 0,
            'ultimo_intento_cobro' => null,
            'historial_pagos' => null
        ]);
    }

    /**
     * Carga datos adicionales del usuario para login
     *
     * @param User $user
     * @return User
     */
    public function cargarDatosUsuario(User $user): User
    {
        $user->empresa = $user->empresa()->with(['licencia', 'currency'])->first();
        
        $suscripcion = $user->empresa->suscripcion()
            ->whereNotIn('estado', [
                config('constants.ESTADO_SUSCRIPCION_INACTIVO'),
                config('constants.ESTADO_SUSCRIPCION_SUSPENDIDO')
            ])
            ->latest()
            ->first();

        $user->dias_faltantes = $suscripcion ? $suscripcion->diasFaltantes() : null;
        $user->dias_faltantes_prueba = $suscripcion ? $suscripcion->diasFaltantesPrueba() : null;
        $user->tiene_suscripcion = !is_null($suscripcion);
        $user->ordenes_pagos = $suscripcion && $suscripcion->ordenesPago()->exists() ? true : false;
        $user->tiene_metodo_pago_activo = $user->metodoPago()->where('esta_activo', true)->exists();

        $user->plan = $suscripcion && $suscripcion->plan_id 
            ? $this->obtenerPlan($suscripcion->plan_id)->nombre 
            : $this->obtenerPlan($user->empresa->plan, true, $user->empresa->plan)->nombre;
        
        $user->estado_suscripcion = $suscripcion && $suscripcion->estado 
            ? $suscripcion->estado 
            : 'No tiene suscripción';
        
        $user->plan_id = $suscripcion && $suscripcion->plan_id 
            ? $suscripcion->plan_id 
            : $this->obtenerPlan($user->empresa->plan, true, $user->empresa->plan)->id;
        
        $user->monto_plan = $suscripcion && $suscripcion->monto 
            ? $suscripcion->monto 
            : $this->obtenerPlan($user->empresa->plan, true, $user->empresa->plan)->precio;

        return $user;
    }

    /**
     * Obtiene permisos de un usuario
     *
     * @param User $user
     * @return array
     */
    public function obtenerPermisosUsuario(User $user): array
    {
        $rolePermissions = $user->getPermissionsViaRoles()->pluck('name');
        $directPermissions = $user->getDirectPermissions()->pluck('name');
        $revokedPermissions = DB::table('permission_revocations')
            ->where('user_id', $user->id)
            ->pluck('permission_name');

        $effectivePermissions = collect($rolePermissions)
            ->merge($directPermissions)
            ->diff($revokedPermissions);

        return [
            'rolePermissions' => $rolePermissions,
            'directPermissions' => $directPermissions,
            'revokedPermissions' => $revokedPermissions,
            'effectivePermissions' => $effectivePermissions
        ];
    }

    /**
     * Registra un acceso de usuario
     *
     * @param User $user
     * @return void
     */
    private function registrarAcceso(User $user): void
    {
        if (!$user->id) {
            Log::warning('Intento de registrar acceso sin ID de usuario', ['user' => $user]);
            return;
        }

        try {
            // Usar DB::table directamente ya que el modelo tiene conflicto de nombres
            DB::table('accesos')->insert([
                'id_usuario' => $user->id,
                'fecha' => $user->ultimo_login ?? Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al registrar acceso: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            // No lanzar excepción para no interrumpir el login
        }
    }

    /**
     * Obtiene máscaras de validación según país
     *
     * @param string $pais
     * @return array
     */
    private function obtenerMascarasPorPais(string $pais): array
    {
        $mascaras = [
            'El Salvador' => [
                'dui' => '00000000-0',
                'nit' => '0000000-000-000-0',
                'nrc' => '0000-000000-000-00',
                'telefono' => '0000-0000'
            ],
            'Panama' => [
                'dui' => '0-000-0000',
                'nit' => '000-0000-000000',
                'nrc' => '000-0000-000000',
                'telefono' => '0000-0000'
            ],
            'Guatemala' => [
                'dui' => '0000-0000-0000',
                'nit' => '00000000-0',
                'nrc' => '00000000-0',
                'telefono' => '000-0000'
            ],
            'Belice' => [
                'dui' => '00000000-0',
                'nit' => '0000000-000-000-0',
                'nrc' => '0000-000000-000-000',
                'telefono' => '0000-0000'
            ],
            'Honduras' => [
                'dui' => '0000-0000-00000',
                'nit' => '0000-0000-00000',
                'nrc' => '0000-0000-00000',
                'telefono' => '0000-0000'
            ],
            'Nicaragua' => [
                'dui' => '0000-0000-00000',
                'nit' => '000-000000-000-0',
                'nrc' => '000-000000-00000',
                'telefono' => '0000-0000'
            ],
            'Costa Rica' => [
                'dui' => '0-0000-0000',
                'nit' => '0-0000-0000',
                'nrc' => '0-0000-0000',
                'telefono' => '0000-0000'
            ]
        ];

        return $mascaras[$pais] ?? $mascaras['El Salvador'];
    }

    /**
     * Calcula total según frecuencia de pago
     *
     * @param float $precioMensual
     * @param string $frecuenciaPago
     * @return float
     */
    private function calcularTotalSegunFrecuencia(float $precioMensual, string $frecuenciaPago): float
    {
        if ($frecuenciaPago === 'Trimestral') {
            return $precioMensual * 3;
        } elseif ($frecuenciaPago === 'Anual') {
            return ($precioMensual * 12) * 0.8; // 20% de descuento anual
        }
        
        return $precioMensual;
    }

    /**
     * Obtiene URL de N1co según plan
     *
     * @param string $plan
     * @return string|null
     */
    private function obtenerUrlN1co(string $plan): ?string
    {
        $urls = [
            config('constants.PLAN_EMPRENDEDOR') => config('constants.URL_N1CO_EMPRENDEDOR'),
            config('constants.PLAN_ESTANDAR') => config('constants.URL_N1CO_ESTANDAR'),
            config('constants.PLAN_AVANZADO') => config('constants.URL_N1CO_AVANZADO'),
            config('constants.PLAN_PRO') => config('constants.URL_N1CO_PRO')
        ];

        return $urls[$plan] ?? null;
    }

    /**
     * Obtiene un plan por ID o nombre
     *
     * @param mixed $planId
     * @param bool $porNombre
     * @param string|null $nombre
     * @return Plan
     */
    public function obtenerPlan($planId, bool $porNombre = false, ?string $nombre = null): Plan
    {
        if ($porNombre && $nombre) {
            $plan = Plan::where('nombre', $nombre)->first();
        } else {
            $plan = Plan::find($planId);
        }

        if (!$plan) {
            throw new \Exception("Plan no encontrado");
        }

        return $plan;
    }
}
