<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// use App\Http\Requests\RegisterRequest; // Request no existe, usar Request genérico
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use App\Http\Requests\Auth\SendResetLinkEmailRequest;
use App\Http\Requests\Auth\CancelarSuscripcionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use App\Models\Admin\Acceso;
use App\Models\Admin\Empresa;
use App\Models\Admin\Impuesto;
use App\Models\Admin\Sucursal;
use App\Models\Admin\Canal;
use App\Models\Admin\FormaDePago;
use App\Models\Admin\Documento;
use App\Models\Inventario\Bodega;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\Carbon;
use JWTAuth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\Notificacion;
use App\Models\EmpresaConfiguracionPlanilla;
use App\Models\Plan;
use App\Models\Promocional;
use App\Models\Suscripcion;
use App\Services\Planilla\PlanillaTemplatesService;
use App\Services\Suscripcion\SuscripcionService;
use App\Services\Auth\AuthService;
use App\Services\Payment\N1coService;
use App\Services\Suscripcion\CancelacionSuscripcionService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class AuthJWTController extends Controller
{
    private $suscripcionService;
    private $authService;
    private $n1coService;
    private $cancelacionSuscripcionService;

    public function __construct(
        SuscripcionService $suscripcionService,
        AuthService $authService,
        N1coService $n1coService,
        CancelacionSuscripcionService $cancelacionSuscripcionService
    ) {
        $this->suscripcionService = $suscripcionService;
        $this->authService = $authService;
        $this->n1coService = $n1coService;
        $this->cancelacionSuscripcionService = $cancelacionSuscripcionService;
    }

    public function login(Request $request)
    {

        $credentials = $request->only('email', 'password');
        $token = null;

        $token = JWTAuth::attempt($credentials);
        $user = auth()->user();


        if (!$token)
            return  Response()->json(['message' => 'Datos incorrectos, asegúrate de que tu usuario y contraseña estén escritos correctamente.', 'code' => 401], 401);

        if (!$user->enable)
            return  Response()->json(['message' => 'Lo sentimos, este usuario esta inactivo', 'code' => 401], 401);

        if (!$user->empresa()->pluck('activo')->first())
            return  Response()->json(['message' => 'Lo sentimos, la cuenta no esta activa', 'code' => 401], 401);

        $user->ultimo_login = Carbon::now();
        $user->save();

        $acceso = new Acceso;
        $acceso->id_usuario = $user->id;
        $acceso->fecha = $user->ultimo_login;
        $acceso->save();

        $user->empresa = $user->empresa()->with('licencia')->first();

        // Agregar información sobre el tipo de empresa (padre/hija)
        if ($user->empresa) {
            $user->empresa->es_empresa_padre = $user->empresa->esEmpresaPadre();
            $user->empresa->es_empresa_hija = $user->empresa->esEmpresaHija();
        }

        $suscripcion = $user->empresa->suscripcion()
            //Esto nos rompio las pelotas >:(
            // ->whereNotIn('estado', [
            //     config('constants.ESTADO_SUSCRIPCION_INACTIVO'),
            //     config('constants.ESTADO_SUSCRIPCION_SUSPENDIDO')
            // ])
            ->latest()
            ->first();
        $user->dias_faltantes = $suscripcion ? $suscripcion->diasFaltantes() : null;
        $user->dias_faltantes_prueba = $suscripcion ? $suscripcion->diasFaltantesPrueba() : null;
        $user->tiene_suscripcion = !is_null($suscripcion);
        $user->ordenes_pagos = $suscripcion && $suscripcion->ordenesPago()->exists() ? true : false;
        $user->tiene_metodo_pago_activo = $user->metodoPago()->where('esta_activo', true)->exists();

        $user->plan = $suscripcion && $suscripcion->plan_id ? $this->getPlan($suscripcion->plan_id)->nombre : $this->getPlan($user->empresa->plan, true, $user->empresa->plan)->nombre;
        $user->estado_suscripcion = $suscripcion && $suscripcion->estado ? $suscripcion->estado : 'No tiene suscripción';
        $user->plan_id = $suscripcion && $suscripcion->plan_id ? $suscripcion->plan_id : $this->getPlan($user->empresa->plan, true, $user->empresa->plan)->id;
        $user->monto_plan = $suscripcion && $suscripcion->monto ? $suscripcion->monto : $this->getPlan($user->empresa->plan, true, $user->empresa->plan)->precio;

        return response()->json(['token' => $token, 'user' => $user], 200);
    }

    public function logout(Request $request)
    {
        $user = User::findOrFail($request->id_usuario);
        // $user->ultimo_logout = Carbon::now();
        $user->save();

        return response()->json(['user' => $user], 200);

    }

    public function register(Request $request)
    {
        try {
            $usuario = $this->authService->register($request->all());
            return response()->json($usuario, 200);
        } catch (\Exception $e) {
            $code = (int)($e->getCode() ?: 400);
            // Asegurar que el código sea válido (entre 100 y 599)
            if ($code < 100 || $code > 599) {
                $code = 400;
            }
            return response()->json(['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            $code = (int)($e->getCode() ?: 400);
            // Asegurar que el código sea válido (entre 100 y 599)
            if ($code < 100 || $code > 599) {
                $code = 400;
            }
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    public function sendResetLinkEmail(SendResetLinkEmailRequest $request)
    {

        // Enviar link de reset
        $response = Password::sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => '¡Te hemos enviado por correo el enlace para restablecer tu contraseña!', 'code' => 200], 200)
            : response()->json(['error' => "El correo ingresado no esta en nuestros registros"], 400);
    }


    public function pagoCompletado($id_empresa)
    {
        $empresa = Empresa::where('id', $id_empresa)->firstOrFail();

        if ($empresa->pagos()->count() == 0) {
            Mail::send('mails.bienvenida', ['empresa' => $empresa], function ($m) use ($empresa) {
                $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                    ->to($empresa->correo)
                    ->subject('¡Bienvenido a SmartPyme!');
            });
        }

        $transaccion = new Transaccion();
        $transaccion->fecha = date('Y-m-d');
        $transaccion->correlativo = 3;
        $transaccion->estado = 'Pagada';
        $transaccion->metodo_pago = 'N1co';
        $transaccion->tipo_documento = 'Ticket';
        $transaccion->descripcion = 'Suscripción en SmartPyme - Plan ' . $empresa->plan;
        $transaccion->cliente = $empresa->nombre;
        $transaccion->total = $empresa->total;
        $transaccion->id_empresa = $empresa->id;
        $transaccion->save();

        $empresa->activo = true;
        $empresa->save();

        $data = [
            'titulo' => 'Se ha creado una nueva cuenta.',
            'descripcion' => $empresa->usuarios()->pluck('name')->first() . ' ha registrado su empresa "' . $empresa->nombre . '".',
        ];

        // Notificar
        Mail::send('mails.notificacion', ['data' => $data], function ($m) use ($data) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                ->to(env('MAIL_TO_ADDRESS'))
                ->cc('gabrielaq@smartpyme.sv')
                ->cc('contact@smartpyme.sv')
                ->subject('Se ha registrado una nueva cuenta en SmartPyme');
        });

        return redirect()->route('payment.finish', Crypt::encrypt($empresa->id));
    }

    public function pagoFinish($id_empresa)
    {
        $transaccion = Empresa::findOrfail(Crypt::decrypt($id_empresa));
        return view('auth.payment-finish', compact('transaccion'));
    }

    public function suscription($transaccion)
    {

        $transaccion = Empresa::findOrfail(Crypt::decrypt($transaccion));

        $pdf = app('dompdf.wrapper')->loadView('documentos.ticket-suscription', compact('transaccion'));
        $pdf->setPaper([0, 0, 365.669, 566.929133858]);

        return $pdf->download($transaccion->descripcion . '-' . $transaccion->id . '.pdf');
    }

    // public function cancelarSuscripcion(Request $request)
    // {
    //     $request->validate([
    //         'password'      => 'required',
    //         'id'            => 'required',
    //         'id_empresa'    => 'required',
    //     ]);


    //     $usuario = User::findOrfail($request->id);

    //     if (!Hash::check($request->password, $usuario->password)) {
    //         return response()->json(['error' => ['La contraseña no es correcta'], 'code' => 422], 422);
    //     }

    //     $usuario->enable = false;
    //     $usuario->save();

    //     $empresa = Empresa::findOrfail($request->id_empresa);
    //     $empresa->activo = false;
    //     $empresa->fecha_cancelacion = date('Y-m-d');
    //     $empresa->save();


    //     $data = [
    //         'titulo' => 'Cancelación de Suscripción.',
    //         'descripcion' => 'El usuario ' . $usuario->name . ' de la empresa ' . $empresa->nombre . ' con ID: ' . $empresa->id . ' ha cancelado su suscripción.'
    //     ];

    //     // Notificar
    //     Mail::send('mails.notificacion', ['data' => $data], function ($m) use ($data) {
    //         $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
    //             ->to(env('MAIL_TO_ADDRESS'))
    //             ->cc(config('constants.MAIL_CC_ADDRESS_1'))
    //             ->cc(config('constants.MAIL_CC_ADDRESS_2'))
    //             ->subject('Se ha registrado una nueva cuenta en SmartPyme');
    //     });


    //     return response()->json($usuario, 200);
    // }

    public function cancelarSuscripcion(CancelarSuscripcionRequest $request)
    {
        try {
            $resultado = $this->cancelacionSuscripcionService->cancelarSuscripcion(
                $request->id,
                $request->password,
                $request->id_empresa,
                $request->motivo_cancelacion
            );
            return response()->json($resultado, 200);
        } catch (\Exception $e) {
            $code = (int)($e->getCode() ?: 500);
            // Asegurar que el código sea válido (entre 100 y 599)
            if ($code < 100 || $code > 599) {
                $code = 500;
            }
            Log::error('Error en cancelación de suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], $code);
        }
    }

    public function createPaymentLink(array $data): array
    {
        return $this->n1coService->crearEnlacePago($data);
    }


    public function me($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado', 'code' => 404], 404);
        }

        $user->ultimo_login = Carbon::now();
        $user->save();

        $user = $this->authService->cargarDatosUsuario($user);

        return response()->json(['user' => $user], 200);
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

    /**
     * Calcula el total original basándose en el plan y tipo de plan
     *
     * @param Plan $plan
     * @param string $tipoPlan
     * @return float
     */
    private function calcularTotalOriginal($plan, $tipoPlan)
    {
        if (!$plan) {
            return 0;
        }

        // Si el plan tiene precio definido, usarlo
        if ($plan->precio) {
            return floatval($plan->precio);
        }

        // Si no tiene precio, calcular basándose en el nombre del plan y tipo
        $planNombre = strtolower($plan->nombre ?? '');
        $esMensual = strtolower($tipoPlan) === 'mensual';

        // Precios por plan (Mensual / Anual)
        $precios = [
            'emprendedor' => ['mensual' => 16.95, 'anual' => 203.4],
            'estándar' => ['mensual' => 28.25, 'anual' => 339],
            'estandar' => ['mensual' => 28.25, 'anual' => 339],
            'avanzado' => ['mensual' => 56.5, 'anual' => 678],
            'pro' => ['mensual' => 113, 'anual' => 1220]
        ];

        foreach ($precios as $nombre => $precio) {
            if (strpos($planNombre, $nombre) !== false) {
                return $esMensual ? $precio['mensual'] : $precio['anual'];
            }
        }

        // Si no se encuentra, usar el precio del plan si existe
        return $plan->precio ?? 0;
    }

    /**
     * Obtiene la configuración de un código promocional desde la base de datos
     *
     * @param string $codigo
     * @param string|null $tipoPlan Tipo de plan para validar planes permitidos
     * @return Promocional|null
     */
    private function obtenerConfiguracionCodigoPromocional($codigo, $tipoPlan = null)
    {
        if (empty($codigo)) {
            return null;
        }

        $codigoUpper = strtoupper(trim($codigo));

        // Buscar el código promocional en la base de datos
        $promocional = Promocional::where('codigo', $codigoUpper)
            ->where('activo', true)
            ->first();

        if (!$promocional) {
            return null;
        }

        // Validar fechas de expiración si están definidas
        $opciones = $promocional->opciones ?? [];
        if (isset($opciones['fecha_expiracion'])) {
            $fechaExpiracion = Carbon::parse($opciones['fecha_expiracion']);
            if (now()->gt($fechaExpiracion)) {
                return null; // Código expirado
            }
        }

        if (isset($opciones['fecha_inicio'])) {
            $fechaInicio = Carbon::parse($opciones['fecha_inicio']);
            if (now()->lt($fechaInicio)) {
                return null; // Código aún no válido
            }
        }

        // Validar planes permitidos si están definidos
        if ($tipoPlan && !empty($promocional->planes_permitidos)) {
            $planesPermitidos = array_map('strtolower', $promocional->planes_permitidos);
            $tipoPlanLower = strtolower($tipoPlan);
            if (!in_array($tipoPlanLower, $planesPermitidos)) {
                return null; // Plan no permitido para este código
            }
        }

        return $promocional;
    }

    /**
     * Aplica las reglas de códigos promocionales a la empresa
     *
     * @param Empresa $empresa
     * @param string|null $codigoPromocional
     * @param float $totalOriginal
     * @param string|null $tipoPlan Tipo de plan para validar planes permitidos
     * @return array ['total' => float, 'campania' => string|null]
     */
    private function aplicarCodigoPromocional($empresa, $codigoPromocional, $totalOriginal, $tipoPlan = null)
    {
        $total = $totalOriginal;
        $campania = null;

        if (!empty($codigoPromocional)) {
            // Guardar código promocional en la empresa
            $empresa->codigo_promocional = $codigoPromocional;

            // Obtener configuración del código promocional desde la base de datos
            $promocional = $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, $tipoPlan);

            if ($promocional) {
                // Aplicar descuento según el tipo
                if ($promocional->tipo === 'porcentaje') {
                    // Descuento por porcentaje (descuento está en formato decimal, ej: 50.00 = 50%)
                    $descuentoDecimal = $promocional->descuento / 100;
                    $total = $totalOriginal * (1 - $descuentoDecimal);
                } elseif ($promocional->tipo === 'monto_fijo') {
                    // Descuento por monto fijo
                    $total = max(0, $totalOriginal - $promocional->descuento);
                }

                // Establecer campaña si está definida
                if ($promocional->campania) {
                    $empresa->campania = $promocional->campania;
                    $campania = $promocional->campania;
                }
            }
        }

        return [
            'total' => $total,
            'campania' => $campania
        ];
    }

    /**
     * Calcula la fecha de fin del período de prueba
     * Si hay código promocional válido, retorna la fecha actual (sin período de prueba)
     * Si no hay código promocional, retorna la fecha actual más los días de duración del plan
     *
     * @param string|null $codigoPromocional
     * @param Plan $plan
     * @param string|null $tipoPlan
     * @return Carbon
     */
    private function calcularFinPeriodoPrueba($codigoPromocional, $plan, $tipoPlan = null)
    {
        $tieneCodigoPromocional = !empty($codigoPromocional) &&
                                  $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, $tipoPlan) !== null;

        return $tieneCodigoPromocional
            ? now()
            : now()->addDays($plan->duracion_dias ?? 0);
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

    /**
     * Establece la frecuencia de pago en la empresa
     */
    private function setFrecuenciaPago($empresa, $frecuenciaPago)
    {
        $empresa->frecuencia_pago = $frecuenciaPago;
        $empresa->save();
        return $frecuenciaPago;
    }

    /**
     * Establece el monto mensual en la empresa
     */
    private function setMontoMensual($empresa, $montoMensual)
    {
        $empresa->monto_mensual = $montoMensual;
        $empresa->save();
        return $montoMensual;
    }

    /**
     * Establece el monto anual en la empresa
     */
    private function setMontoAnual($empresa, $montoAnual)
    {
        $empresa->monto_anual = $montoAnual;
        $empresa->save();
        return $montoAnual;
    }

    /**
     * Calcula el monto mensual con descuentos aplicados del código promocional
     */
    private function calcularMontoMensual($precioMensual, $codigoPromocional = null)
    {
        $montoMensual = $precioMensual;

        if (empty($codigoPromocional)) {
            return $montoMensual;
        }

        $promocional = $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, 'Mensual');

        if (!$promocional) {
            return $montoMensual;
        }

        if ($promocional->tipo === 'porcentaje') {
            $descuentoDecimal = $promocional->descuento / 100;
            $montoMensual = $precioMensual * (1 - $descuentoDecimal);
        } elseif ($promocional->tipo === 'monto_fijo') {
            $montoMensual = max(0, $precioMensual - $promocional->descuento);
        }

        return $montoMensual;
    }

    /**
     * Calcula el monto anual con descuentos aplicados (20% de descuento anual + código promocional si aplica)
     */
    private function calcularMontoAnual($precioMensual, $codigoPromocional = null)
    {
        // Calcular precio anual con 20% de descuento
        $precioAnualSinDescuento = $precioMensual * 12;
        $precioAnualConDescuento20 = $precioAnualSinDescuento * 0.8; // 20% de descuento anual
        $montoAnual = $precioAnualConDescuento20;

        if (empty($codigoPromocional)) {
            return $montoAnual;
        }

        $promocional = $this->obtenerConfiguracionCodigoPromocional($codigoPromocional, 'Anual');

        if (!$promocional) {
            return $montoAnual;
        }

        if ($promocional->tipo === 'porcentaje') {
            $descuentoDecimal = $promocional->descuento / 100;
            $montoAnual = $precioAnualConDescuento20 * (1 - $descuentoDecimal);
        } elseif ($promocional->tipo === 'monto_fijo') {
            $montoAnual = max(0, $precioAnualConDescuento20 - $promocional->descuento);
        }

        return $montoAnual;
    }

    /**
     * Calcula la fecha del próximo pago según la frecuencia de pago
     */
    private function calcularFechaProximoPago($frecuenciaPago)
    {
        switch ($frecuenciaPago) {
            case config('constants.FRECUENCIA_PAGO_TRIMESTRAL'):
                return now()->addMonths(3);
            case config('constants.FRECUENCIA_PAGO_ANUAL'):
                return now()->addMonths(12);
            case config('constants.FRECUENCIA_PAGO_MENSUAL'):
            default:
                return now()->addMonth();
        }
    }
}
