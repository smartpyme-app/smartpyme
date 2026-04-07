<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Services\Paquetes\PaqueteExternalImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PackagesController extends Controller
{
    private const MAX_PACKAGES = 200;

    public function import(Request $request, PaqueteExternalImportService $importService)
    {
        $top = Validator::make($request->all(), [
            'packages' => 'required|array|min:1|max:'.self::MAX_PACKAGES,
        ]);

        if ($top->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Solicitud inválida',
                'details' => $top->errors(),
            ], 400);
        }

        $empresa = $request->attributes->get('empresa');
        $empresaId = (int) $empresa->id;

        $idUsuario = $importService->resolveSystemUserIdForEmpresa($empresaId);
        if (!$idUsuario) {
            return response()->json([
                'success' => false,
                'error' => 'La empresa no tiene un usuario activo para registrar paquetes. Cree al menos un usuario administrador.',
            ], 422);
        }

        $packages = $request->input('packages');
        $created = 0;
        $skipped = 0;
        $errors = [];
        $items = [];

        foreach ($packages as $index => $raw) {
            if (!is_array($raw)) {
                $errors[] = [
                    'index' => $index,
                    'wr' => null,
                    'message' => 'El elemento debe ser un objeto.',
                ];
                continue;
            }

            $idSucursalInput = null;
            if (array_key_exists('id_sucursal', $raw) && $raw['id_sucursal'] !== null && $raw['id_sucursal'] !== '') {
                $idSucursalInput = (int) $raw['id_sucursal'];
                if ($idSucursalInput <= 0) {
                    $idSucursalInput = null;
                }
            }

            $nombreSucursal = array_key_exists('sucursal', $raw) && $raw['sucursal'] !== null
                ? (string) $raw['sucursal']
                : null;

            $resSuc = $importService->resolveSucursalId($empresaId, $idSucursalInput ?: null, $nombreSucursal);
            if (!$resSuc['ok']) {
                $errors[] = [
                    'index' => $index,
                    'wr' => $raw['wr'] ?? null,
                    'message' => $resSuc['error'],
                ];
                continue;
            }

            $row = $this->extractPackageRow($raw);
            $result = $importService->importRow($empresaId, $idUsuario, $resSuc['id'], $row);

            if ($result['status'] === 'error') {
                $errors[] = [
                    'index' => $index,
                    'wr' => $raw['wr'] ?? null,
                    'message' => $result['message'],
                ];
                continue;
            }

            if ($result['status'] === 'skipped') {
                $skipped++;
                $items[] = [
                    'index' => $index,
                    'wr' => $raw['wr'] ?? null,
                    'status' => 'skipped',
                    'id' => $result['paquete']?->id,
                ];
                continue;
            }

            $created++;
            $items[] = [
                'index' => $index,
                'wr' => $raw['wr'] ?? null,
                'status' => 'created',
                'id' => $result['paquete']?->id,
            ];
        }

        Log::info('API externa: importación de paquetes', [
            'empresa_id' => $empresaId,
            'created' => $created,
            'skipped' => $skipped,
            'errors_count' => count($errors),
        ]);

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'items' => $items,
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractPackageRow(array $raw): array
    {
        $keys = [
            'cliente', 'codigo_asesor', 'wr', 'guia', 'piezas', 'embalaje', 'peso', 'precio',
            'cuenta_a_terceros', 'otros', 'total', 'transportista', 'consignatario', 'transportador',
            'seguimiento', 'volumen', 'nota',
        ];

        $row = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw)) {
                $row[$k] = $raw[$k];
            }
        }

        return $row;
    }
}
