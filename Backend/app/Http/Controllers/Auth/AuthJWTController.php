<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Tymon\JWTAuth\Exceptions\JWTException;
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
use App\Models\Transaccion;
use App\Models\User;
use Carbon\Carbon;
use JWTAuth;
use Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\Notificacion;
use App\Models\Plan;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthJWTController extends Controller
{
    use SendsPasswordResetEmails;

    private $suscripcionService;

    public function __construct(SuscripcionService $suscripcionService)
    {
        $this->suscripcionService = $suscripcionService;
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

        $request->validate([
            'name'      => 'required',
            'email'     => 'required|unique:users,email,' . $request->id,
            'password'  => [
                'required_if:id,null',
                // 'confirmed',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*()_+{}\[\]:;<>,.?~\\-]/',
            ],
            'telefono'  => 'required',
            'empresa.nombre'   => 'required',
            'empresa.industria' => 'required',
            'empresa.iva'       => 'required',
            'empresa.moneda'    => 'required',
            'empresa.plan'      => 'required',
            'empresa.tipo_plan' => 'required',
            'empresa.total'     => 'required',
            'empresa.user_limit'     => 'required',
            'empresa.sucursal_limit'     => 'required',
        ]);

        DB::beginTransaction();

        try {
            if ($request->id) {
                $empresa = Empresa::findOrFail($request['empresa']['id']);
            } else {
                $empresa = new Empresa();
            }

            $empresa->activo = true;
            $empresa->nombre = $request['empresa']['nombre'];
            $empresa->nombre_propietario = $request->name;
            $empresa->telefono = $request->telefono;
            $empresa->iva   = $request['empresa']['iva'];
            $empresa->plan   = $this->getPlan($request['empresa']['plan'])->nombre;
            $empresa->correo   = $request->email;
            $empresa->user_limit   = $request['empresa']['user_limit'];
            $empresa->sucursal_limit   = $request['empresa']['sucursal_limit'];
            $empresa->tipo_plan   = $request['empresa']['tipo_plan'];
            $empresa->industria   = $request['empresa']['industria'];
            $empresa->pais   = $request['empresa']['pais'];

            $mascara_dui = "0";
            $mascara_nit = "0";
            $mascara_nrc = "0";
            $mascara_telefono = "0";

            switch ($request['empresa']['pais']) {
                case 'El Salvador':
                    $mascara_dui = "00000000-0";
                    $mascara_nit = "0000000-000-000-0";
                    $mascara_nrc = "0000-000000-000-00";
                    $mascara_telefono = "0000-0000";
                    break;
                case 'Panama':
                    $mascara_dui = "0-000-0000";
                    $mascara_nit = "000-0000-000000";
                    $mascara_nrc = "000-0000-000000";
                    $mascara_telefono = "0000-0000";
                    break;
                case 'Guatemala':
                    $mascara_dui = "0000-0000-0000";
                    $mascara_nit = "00000000-0";
                    $mascara_nrc = "00000000-0";
                    $mascara_telefono = "000-0000";
                    break;
                case 'Belice':
                    $mascara_dui = "00000000-0";
                    $mascara_nit = "0000000-000-000-0";
                    $mascara_nrc = "0000-000000-000-000";
                    $mascara_telefono = "0000-0000";
                    break;
                case 'Honduras':
                    $mascara_dui = "0000-0000-00000";
                    $mascara_nit = "0000-0000-00000";
                    $mascara_nrc = "0000-0000-00000";
                    $mascara_telefono = "0000-0000";
                    break;
                case 'Nicaragua':
                    $mascara_dui = "0000-0000-00000";
                    $mascara_nit = "000-000000-000-0";
                    $mascara_nrc = "000-000000-00000";
                    $mascara_telefono = "0000-0000";
                    break;
                case 'Costa Rica':
                    $mascara_dui = "0-0000-0000";
                    $mascara_nit = "0-0000-0000";
                    $mascara_nrc = "0-0000-0000";
                    $mascara_telefono = "0000-0000";
                    break;
                default:
                    $mascara_dui = "00000000-0";
                    $mascara_nit = "0000000-000-000-0";
                    $mascara_nrc = "0000-000000-000-00";
                    $mascara_telefono = "0000-0000";
            }

            $empresa->validador_dui = $mascara_dui;
            $empresa->validador_nit = $mascara_nit;
            $empresa->validador_nrc = $mascara_nrc;
            $empresa->validador_telefono = $mascara_telefono;
            $empresa->total   = $request['empresa']['total'];
            $empresa->moneda = $request['empresa']['moneda'];
            $empresa->save();


            if (!$request->id) {
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

            if ($request->id) {
                $usuario = User::findOrFail($request->id);
            } else {
                $usuario = new User();
                $usuario->id_sucursal  = $sucursal->id;
                $usuario->id_bodega    = $bodega->id;
                $usuario->id_empresa   = $empresa->id;
            }

            $usuario->name         = $request->name;
            $usuario->email        = $request->email;
            $usuario->telefono     = $request->telefono;
            $usuario->password     = bcrypt($request->password);
            $usuario->tipo         = config('constants.TIPO_USUARIO_ADMINISTRADOR');
            $usuario->enable       = true;
            $usuario->save();


            DB::commit();

            $suscripcion = $this->createSuscripcion([
                'empresa_id' => $empresa->id,
                'plan_id' => $this->getPlan($request['empresa']['plan'])->id,
                'usuario_id' => $usuario->id,
                'tipo_plan' => $empresa->tipo_plan,
                'estado' => config('constants.ESTADO_SUSCRIPCION_ACTIVO'),
                'monto' => $request['empresa']['total'],
                'id_pago' => null,
                'id_orden' => null,
                'estado_ultimo_pago' => null,
                'fecha_ultimo_pago' => null,
                'fecha_proximo_pago' => null,
                'fin_periodo_prueba' => now()->addDays($this->getPlan($request['empresa']['plan'])->duracion_dias),
                'fecha_cancelacion' => null,
                'motivo_cancelacion' => null,
                'requiere_factura' => false,
                'nit' => null,
                'nombre_factura' => $usuario->name,
                'direccion_factura' => $empresa->direccion,
                'intentos_cobro' => 0,
                'ultimo_intento_cobro' => null,
                'historial_pagos' => null
            ]);

            $usuario->empresa = $usuario->empresa()->first();

            // if ($empresa->plan == config('constants.PLAN_EMPRENDEDOR')){
            //     $usuario->url_n1co = config('constants.URL_N1CO_EMPRENDEDOR');
            // }
            // if ($empresa->plan == config('constants.PLAN_ESTANDAR')){
            //     $usuario->url_n1co = config('constants.URL_N1CO_ESTANDAR');
            // }
            // if ($empresa->plan == config('constants.PLAN_AVANZADO')){
            //     $usuario->url_n1co = config('constants.URL_N1CO_AVANZADO');
            // }
            // if ($empresa->plan == config('constants.PLAN_PRO')){
            //     $usuario->url_n1co = config('constants.URL_N1CO_PRO');
            // }


            // $paymentLink = $this->createPaymentLink([
            //     'name' => "SmartPyme Plan " . $empresa->plan,
            //     'description' => "Suscripción SmartPyme - Plan " . $empresa->plan,
            //     'amount' => $empresa->total,
            //     'user_id' => $usuario->id,
            //     'plan' => $empresa->plan
            // ]);

            // if ($paymentLink['success']) {
            //     $usuario->url_n1co = $paymentLink['paymentLinkUrl'];
            //     Log::info('URL de pago generada:', ['url' => $usuario->url_n1co]);
            // } else {
            // Log::error('Error al generar la URL de pago:', ['error' => $paymentLink['error']]);
            // Usar URLs por defecto como fallback
            switch ($empresa->plan) {
                case config('constants.PLAN_EMPRENDEDOR'):
                    $usuario->url_n1co = config('constants.URL_N1CO_EMPRENDEDOR');
                    break;
                case config('constants.PLAN_ESTANDAR'):
                    $usuario->url_n1co = config('constants.URL_N1CO_ESTANDAR');
                    break;
                case config('constants.PLAN_AVANZADO'):
                    $usuario->url_n1co = config('constants.URL_N1CO_AVANZADO');
                    break;
                case config('constants.PLAN_PRO'):
                    $usuario->url_n1co = config('constants.URL_N1CO_PRO');
                    break;
            }
            // }

            $usuario->plan = $empresa->plan;
            $usuario->plan_id = $suscripcion['plan_id'];

            // $usuario->url_n1co = "https://pay.h4b.dev/pl/1l4ohx7";

            return response()->json($usuario, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    protected function sendResetLinkEmail(Request $request)
    {

        $this->validateEmail($request);

        $response = $this->broker()->sendResetLink(
            $this->credentials($request)
        );

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    protected function sendResetLinkResponse($response)
    {
        return  Response()->json(['message' => '¡Te hemos enviado por correo el enlace para restablecer tu contraseña!', 'code' => 200], 200);
    }

    protected function sendResetLinkFailedResponse($response)
    {
        return response()->json(['error' => "El correo ingresado no esta en nuestros registros"], 400);
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

        $pdf = PDF::loadView('documentos.ticket-suscription', compact('transaccion'));
        $pdf->setPaper([0, 0, 365.669, 566.929133858]);

        return $pdf->download($transaccion->descripcion . '-' . $transaccion->id . '.pdf');
    }

    public function cancelarSuscripcion(Request $request)
    {
        $request->validate([
            'password'      => 'required',
            'id'            => 'required',
            'id_empresa'    => 'required',
        ]);


        $usuario = User::findOrfail($request->id);

        if (!Hash::check($request->password, $usuario->password)) {
            return response()->json(['error' => ['La contraseña no es correcta'], 'code' => 422], 422);
        }

        $usuario->enable = false;
        $usuario->save();

        $empresa = Empresa::findOrfail($request->id_empresa);
        $empresa->activo = false;
        $empresa->fecha_cancelacion = date('Y-m-d');
        $empresa->save();


        $data = [
            'titulo' => 'Cancelación de Suscripción.',
            'descripcion' => 'El usuario ' . $usuario->name . ' de la empresa ' . $empresa->nombre . ' con ID: ' . $empresa->id . ' ha cancelado su suscripción.'
        ];

        // Notificar
        Mail::send('mails.notificacion', ['data' => $data], function ($m) use ($data) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
                ->to(env('MAIL_TO_ADDRESS'))
                ->cc(config('constants.MAIL_CC_ADDRESS_1'))
                ->cc(config('constants.MAIL_CC_ADDRESS_2'))
                ->subject('Se ha registrado una nueva cuenta en SmartPyme');
        });


        return response()->json($usuario, 200);
    }

    protected function getApiToken(): string
    {
        try {
            $baseUrl = env('N1CO_SANDBOX_URL', 'https://api-sandbox.n1co.shop/api/v2');

            $response = Http::post($baseUrl . '/Token', [
                'clientId' => env('CLIENT_ID'),
                'clientSecret' => env('CLIENT_SECRET')
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['accessToken'];
            }

            Log::error('Error obteniendo token N1co', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return '';
        } catch (\Exception $e) {
            Log::error('Exception obteniendo token N1co', [
                'message' => $e->getMessage()
            ]);
            return '';
        }
    }


    public function createPaymentLink(array $data): array
    {
        try {
            $baseUrl = 'https://api-pay-sandbox.n1co.shop/api/v2';
            $apiToken = $this->getApiToken();

            $payload = [
                'orderName' => $data['name'] ?? 'Orden de SmartPyme',
                'orderDescription' => $data['description'] ?? null,
                'amount' => floatval($data['amount']),
                'successUrl' => url('/api/pago-completado'),
                'cancelUrl' => url('/api/pago-cancelado'),
                'metadata' => [
                    [
                        'name' => 'userId',
                        'value' => (string)$data['user_id']
                    ],
                    [
                        'name' => 'plan',
                        'value' => $data['plan']
                    ]
                ]
            ];

            Log::info('N1co Request Payload', $payload);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/paymentlink/checkout', $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                DB::table('ordenes_pagos')->insert([
                    'id_orden' => $responseData['orderId'],
                    'order_code' => $responseData['orderCode'],
                    'id_usuario' => $data['user_id'],
                    'plan' => $data['plan'],
                    'monto' => number_format($data['amount'], 2, '.', ''),
                    'estado' => 'pendiente',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info('Orden creada', [
                    'response' => $responseData,
                    'payload' => $payload
                ]);

                return [
                    'success' => true,
                    'paymentLinkUrl' => $responseData['paymentLinkUrl']
                ];
            }

            Log::error('N1co Payment Link Error', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_data' => $payload,
                'url' => $baseUrl . '/paymentlink/checkout'
            ]);

            return [
                'success' => false,
                'error' => 'Error al crear enlace de pago: ' . ($response->json()['title'] ?? 'Error desconocido')
            ];
        } catch (\Exception $e) {
            Log::error('N1co Payment Link Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ];
        }
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
}
