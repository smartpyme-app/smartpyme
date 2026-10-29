<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\Configuracion;
use App\Models\EmpresaConfiguracion;
use App\Models\EmpresaConfiguracionPlanilla;
use JWTAuth;
use App\Http\Requests\Contabilidad\StoreConfiguracionRequest;

class ConfiguracionController extends Controller
{
    

    public function read($id) {
        $empresaId = (int) $id;
        $configuracion = Configuracion::where('id_empresa', $empresaId)->first();
        $empresa = Empresa::find($empresaId);

        return Response()->json([
            'configuracion' => $configuracion,
            'planillas_modulo_activo' => $this->planillasModuloActivo($empresaId),
            'cod_pais_empresa' => $empresa
                ? EmpresaConfiguracionPlanilla::resolverCodigoPaisEmpresa($empresa)
                : null,
        ], 200);
    }

    private function planillasModuloActivo(int $empresaId): bool
    {
        if (EmpresaConfiguracion::porEmpresa($empresaId)
            ->modulo(EmpresaConfiguracion::MODULO_PLANILLAS)
            ->exists()) {
            return true;
        }

        return EmpresaConfiguracionPlanilla::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->exists();
    }


    public function store(StoreConfiguracionRequest $request)
    {

        if($request->id)
            $configuracion = Configuracion::findOrFail($request->id);
        else
            $configuracion = new Configuracion;
        
        $configuracion->fill($request->all());
        $configuracion->save();

        return Response()->json($configuracion, 200);

    }

    public function delete($id)
    {
       
        $configuracion = Configuracion::findOrFail($id);
        $configuracion->delete();

        return Response()->json($configuracion, 201);

    }


}
