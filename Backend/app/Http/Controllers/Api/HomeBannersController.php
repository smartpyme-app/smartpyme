<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeBanner;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class HomeBannersController extends Controller
{
    public function index(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->loadMissing('empresa');
        $codPais = strtoupper((string) ($user->empresa?->cod_pais ?: 'SV'));

        $banners = HomeBanner::query()
            ->visiblesParaPais($codPais)
            ->get([
                'id',
                'nombre',
                'imagen_desktop',
                'imagen_mobile',
                'url_destino',
                'abrir_nueva_pestana',
                'segundos_visualizacion',
                'orden',
            ]);

        return response()->json($banners, 200);
    }
}