<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GET /api/salud: lo consulta el despliegue a producción después de cambiar de versión.
 * Si no responde 200 con la versión esperada, el servidor vuelve a la versión anterior.
 * Público y sin datos: solo dice si la app arranca, si llega a la base y qué versión corre.
 */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $version = is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : null;

        try {
            DB::select('SELECT 1');
            $base = true;
        } catch (Throwable) {
            $base = false;
        }

        return response()
            ->json(['ok' => $base, 'version' => $version], $base ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
