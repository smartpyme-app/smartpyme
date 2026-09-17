<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Http\Controllers\Controller;
use App\Models\Contabilidad\Activo;
use App\Models\Contabilidad\ActivoDepreciacion;
use App\Services\Contabilidad\DepreciacionService;
use Illuminate\Http\Request;
use JWTAuth;
use RuntimeException;

class DepreciacionController extends Controller
{
    public function __construct(
        private DepreciacionService $depreciacionService
    ) {}

    public function preview(Request $request)
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $periodo = $request->input('periodo', now()->format('Y-m'));

        return response()->json(
            $this->depreciacionService->previewCorrida($usuario->id_empresa, $periodo),
            200
        );
    }

    public function ejecutar(Request $request)
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $data = $request->validate([
            'periodo' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $result = $this->depreciacionService->ejecutarCorrida(
                $usuario->id_empresa,
                $data['periodo'],
                $usuario->id
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, 200);
    }

    public function cronograma($id)
    {
        $activo = Activo::with('categoria')->findOrFail($id);

        $lineas = ActivoDepreciacion::where('id_activo', $id)
            ->orderBy('periodo')
            ->get();

        if ($lineas->isEmpty() && $activo->estado_registro === 'activo') {
            $this->depreciacionService->generarCronograma($activo);
            $lineas = ActivoDepreciacion::where('id_activo', $id)
                ->orderBy('periodo')
                ->get();
        }

        return response()->json($lineas, 200);
    }
}
