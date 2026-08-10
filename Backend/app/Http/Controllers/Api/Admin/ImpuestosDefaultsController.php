<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\Admin\ImpuestosDefaultPorPais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpuestosDefaultsController extends Controller
{
    public function defaults(Request $request)
    {
        $empresa = Auth::user()->empresa ?? Empresa::find(Auth::user()->id_empresa);
        $pais = $request->query('pais')
            ?: FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);

        $cfg = ImpuestosDefaultPorPais::configuracion((string) $pais);

        return response()->json([
            'pais' => strtoupper((string) $pais),
            'defaults' => $cfg,
        ], 200);
    }
}
