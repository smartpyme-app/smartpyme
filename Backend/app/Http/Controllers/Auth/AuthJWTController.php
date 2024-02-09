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
use App\Models\Transaccion;
use App\Models\User;
use Carbon\Carbon;
use JWTAuth;
use Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\Notificacion;
use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class AuthJWTController extends Controller
{
    use SendsPasswordResetEmails;
    

    public function login(Request $request){

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

        $user->empresa = $user->empresa()->first();
        
        return response()->json(['token' => $token, 'user' => $user], 200);


    }

    public function logout(Request $request){
        $user = User::findOrFail($request->id_usuario);
        // $user->ultimo_logout = Carbon::now();
        $user->save();

        return response()->json(['user' => $user], 200);

    }

    public function register(Request $request){

        $request->validate([
            'name'      => 'required',
            'email'     => 'required|unique:users,email,'.$request->id,
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
            }else{
                $empresa = new Empresa();
            }

        $empresa->activo = false;
        $empresa->nombre = $request['empresa']['nombre'];
        $empresa->nombre_propietario = $request->name;
        $empresa->telefono = $request->telefono;
        $empresa->iva   = $request['empresa']['iva'];
        $empresa->plan   = $request['empresa']['plan'];
        $empresa->correo   = $request->email;
        $empresa->user_limit   = $request['empresa']['user_limit'];
        $empresa->sucursal_limit   = $request['empresa']['sucursal_limit'];
        $empresa->tipo_plan   = $request['empresa']['tipo_plan'];
        $empresa->industria   = $request['empresa']['industria'];
        $empresa->pais   = $request['empresa']['pais'];
        $empresa->total   = $request['empresa']['total'];
        $empresa->moneda = $request['empresa']['moneda'];
        $empresa->save();


        if (!$request->id) {
            // Crear sucursal
                $sucursal = Sucursal::create(['nombre' => $empresa->nombre, 'id_empresa' => $empresa->id]);
           // Crear canales
               Canal::create(['nombre' => $empresa->nombre, 'enable' => true, 'id_empresa' => $empresa->id]);

            // Crear impuesto
               Impuesto::create(['nombre' => 'IVA', 'porcentaje' => $empresa->iva, 'id_empresa' => $empresa->id]);
           // Formas de pago
               FormaDePago::create(['nombre' => 'Efectivo', 'id_empresa' => $empresa->id]);
               FormaDePago::create(['nombre' => 'Transferencia', 'id_empresa' => $empresa->id]);
               FormaDePago::create(['nombre' => 'Tarjeta de crédito/débito', 'id_empresa' => $empresa->id]);
           // Crear documentos
               Documento::create(['nombre' => 'Ticket', 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
               Documento::create(['nombre' => 'Factura', 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
               Documento::create(['nombre' => 'Crédito fiscal', 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
               Documento::create(['nombre' => 'Cotización', 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
               Documento::create(['nombre' => 'Orden de compra', 'correlativo' => 1, 'activo' => 1, 'id_sucursal' => $sucursal->id, 'id_empresa' => $empresa->id]);
        }

        if ($request->id) {
            $usuario = User::findOrFail($request->id);
        }else{
            $usuario = new User();
            $usuario->id_sucursal  = $sucursal->id;
            $usuario->id_empresa   = $empresa->id;
        }

        $usuario->name         = $request->name;
        $usuario->email        = $request->email;
        $usuario->telefono     = $request->telefono;
        $usuario->password     = bcrypt($request->password);
        $usuario->tipo         = 'Administrador';
        $usuario->enable       = true;
        $usuario->save();
        

        DB::commit();

        $usuario->empresa = $usuario->empresa()->first();

        if ($empresa->plan == 'Emprendedor'){
            $usuario->url_n1co = "https://pay.n1co.shop/pl/WEwwXTOpy";
        }
        if ($empresa->plan == 'Estándar'){
            $usuario->url_n1co = "https://pay.n1co.shop/pl/yX99lF1Dl";
        }
        if ($empresa->plan == 'Avanzado'){
            $usuario->url_n1co = "https://pay.n1co.shop/pl/vbj8Rh0y1";
        }

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
        return response()->json(['message' => "El correo no esta en nuestros registros"], 500);
    }


    public function pagoCompletado($id_empresa){
        $empresa = Empresa::where('id', $id_empresa)->firstOrFail();

        if ($empresa->pagos()->count() == 0) {
            Mail::send('mails.bienvenida', ['empresa' => $empresa ], function ($m) use ($empresa) {
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
        Mail::send('mails.notificacion', ['data' => $data ], function ($m) use ($data) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
            ->to(env('MAIL_TO_ADDRESS'))
            ->cc('gabrielaq@smartpyme.sv')
            ->cc('contact@smartpyme.sv')
            ->subject('Se ha registrado una nueva cuenta en SmartPyme');
        });

        return redirect()->route('payment.finish', Crypt::encrypt($empresa->id));
    }

    public function pagoFinish($id_empresa){
        $transaccion = Empresa::findOrfail(Crypt::decrypt($id_empresa));
        return view('auth.payment-finish', compact('transaccion'));
    }

    public function suscription($transaccion){

        $transaccion = Empresa::findOrfail(Crypt::decrypt($transaccion));

        $pdf = PDF::loadView('documentos.ticket-suscription', compact('transaccion'));
        $pdf->setPaper([0, 0, 365.669, 566.929133858]);
    
        return $pdf->download($transaccion->descripcion . '-' .$transaccion->id .'.pdf');
    }

    public function cancelarSuscripcion(Request $request){
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
        Mail::send('mails.notificacion', ['data' => $data ], function ($m) use ($data) {
            $m->from(env('MAIL_FROM_ADDRESS'), 'SmartPyme')
            ->to(env('MAIL_TO_ADDRESS'))
            ->cc('gabrielaq@smartpyme.sv')
            ->cc('contact@smartpyme.sv')
            ->subject('Se ha registrado una nueva cuenta en SmartPyme');
        });


        return response()->json($usuario, 200);
    }


}
