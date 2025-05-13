<?php

namespace App\Http\Controllers\n1co;

use App\Http\Controllers\Controller;
use App\Models\MH\EstadoPais;
use App\Models\MH\Pais;
use Illuminate\Http\Request;

class EstadoController extends Controller
{

    public function paisesSuscripcion() {
       
        $paises = Pais::select('id', 'nombre','cod')->orderBy('nombre','asc')->get();
        return Response()->json($paises, 200);

    }
    
    public function getEstadosByPais($countryCode)
    {

        $pais = Pais::where('cod', $countryCode)->first();
        $estados = EstadoPais::where('pais_id', $pais->id)
                    ->orderBy('nombre')
                    ->get();
        
        return response()->json($estados);
    }
}
