<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\MH\ActividadEconomica;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
use App\Models\MH\Unidad;
use App\Models\MH\Pais;

class MHController extends Controller
{
    

    public function paises() {
       
        $paises = Pais::orderBy('nombre','asc')->get();
        return Response()->json($paises, 200);

    }

    public function distritos() {
       
        $distritos = Distrito::orderBy('nombre','asc')->get();
        return Response()->json($distritos, 200);

    }

    public function municipios() {
       
        $municipios = Municipio::orderBy('nombre','asc')->get();
        return Response()->json($municipios, 200);

    }

    public function departamentos() {
       
        $departamentos = Departamento::orderBy('nombre','asc')->get();
        return Response()->json($departamentos, 200);

    }

    public function actividadesEconomicas() {
       
        $actividadesEconomicas = ActividadEconomica::orderBy('nombre','asc')->get();
        return Response()->json($actividadesEconomicas, 200);

    }

    public function unidades() {
       
        $unidades = Unidad::orderBy('nombre','asc')->get();
        return Response()->json($unidades, 200);

    }

}
