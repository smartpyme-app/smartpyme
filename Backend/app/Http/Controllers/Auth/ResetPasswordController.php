<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    protected function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',              // Mínimo 8 caracteres
                'confirmed',          // Confirmación de contraseña
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', // Contiene al menos una minúscula, una mayúscula, un número y un carácter especial
            ],
        ],[
            'password.regex' => 'Debe tener al menos una minúscula, una mayúscula, un número y un carácter especial'
        ]);

        // Encuentra al usuario por la dirección de correo electrónico
        $user = User::where('email', $request->email)->first();

        // Actualiza la contraseña del usuario
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect(env('APP_URL'));
    }

}
