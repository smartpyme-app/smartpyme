<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaDgtUbicacionCatalogService;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaHaciendaPublicApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogos y datos públicos de Hacienda CR (proxy + caché).
 *
 * @see https://api.hacienda.go.cr/docs/
 */
class CostaRicaFeCatalogController extends Controller
{
    public function cabys(Request $request, CostaRicaHaciendaPublicApiService $api): JsonResponse
    {
        $top = (int) $request->input('top', 15);
        $codigoRaw = $request->input('codigo');
        $qRaw = $request->input('q');

        $codigo = $codigoRaw !== null && $codigoRaw !== '' ? preg_replace('/\D/', '', (string) $codigoRaw) : '';
        $q = $qRaw !== null ? trim((string) $qRaw) : '';

        if (($codigo === '' && $q === '') || ($codigo !== '' && $q !== '')) {
            return response()->json([
                'error' => 'Debe enviar solo el parámetro codigo (13 dígitos CABYS) o solo q (texto de búsqueda, mínimo 3 caracteres).',
            ], 422);
        }

        if ($codigo !== '' && strlen($codigo) !== 13) {
            return response()->json([
                'error' => 'codigo debe ser un CABYS de 13 dígitos.',
            ], 422);
        }

        if ($q !== '' && strlen($q) < 3) {
            return response()->json([
                'error' => 'q debe tener al menos 3 caracteres.',
            ], 422);
        }

        if ($q !== '' && strlen($q) > 200) {
            return response()->json([
                'error' => 'q no debe superar 200 caracteres.',
            ], 422);
        }

        $result = $api->cabys($codigo !== '' ? $codigo : null, $q !== '' ? $q : null, $top);

        return response()->json($result['data'], $result['status']);
    }

    public function contribuyente(Request $request, CostaRicaHaciendaPublicApiService $api): JsonResponse
    {
        $request->validate([
            'identificacion' => 'required|string|min:9|max:14',
        ]);

        $id = preg_replace('/\D/', '', (string) $request->input('identificacion'));
        if (strlen($id) < 9 || strlen($id) > 12) {
            return response()->json([
                'error' => 'identificacion debe tener entre 9 y 12 dígitos (sin guiones).',
            ], 422);
        }

        $result = $api->contribuyente($id);

        return response()->json($result['data'], $result['status']);
    }

    public function exoneracion(Request $request, CostaRicaHaciendaPublicApiService $api): JsonResponse
    {
        $request->validate([
            'autorizacion' => 'required|string|max:32',
        ]);

        $raw = trim((string) $request->input('autorizacion'));
        if (! preg_match('/^AL-\d{8}-\d{2}$/i', $raw)) {
            return response()->json([
                'error' => 'autorizacion debe tener el formato AL-XXXXXXXX-XX (ej. AL-00460853-20).',
            ], 422);
        }

        $result = $api->exoneracion($raw);

        return response()->json($result['data'], $result['status']);
    }

    public function tipoCambioDolar(CostaRicaHaciendaPublicApiService $api): JsonResponse
    {
        $result = $api->tipoCambioDolar();

        return response()->json($result['data'], $result['status']);
    }

    /**
     * Provincias (1–7), mismo formato que GET /departamentos (MH) para reutilizar selects.
     *
     * @see CostaRicaDgtUbicacionCatalogService
     */
    public function departamentos(CostaRicaDgtUbicacionCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->departamentos(), 200);
    }

    /**
     * Cantones (3 dígitos), mismo formato que GET /municipios.
     */
    public function municipios(CostaRicaDgtUbicacionCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->municipios(), 200);
    }

    /**
     * Distritos INEC (5 dígitos), mismo formato que GET /distritos.
     */
    public function distritos(CostaRicaDgtUbicacionCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->distritos(), 200);
    }
}
