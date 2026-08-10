<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Resources\Restaurante\MesaMapaDto;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\ZonaRestaurante;
use App\Services\Restaurante\MesaMapaCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MesaController extends Controller
{
    public function __construct(
        private MesaMapaCacheService $mapaCache,
    ) {}

    private function sincronizarZonaTexto(array &$data, int $idEmpresa): void
    {
        if (! empty($data['zona_id'])) {
            $zona = ZonaRestaurante::where('id_empresa', $idEmpresa)->find($data['zona_id']);
            $data['zona'] = $zona?->nombre;
        } elseif (array_key_exists('zona_id', $data) && empty($data['zona_id'])) {
            $data['zona'] = null;
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $idEmpresa = (int) $user->id_empresa;
        $idSucursal = $request->filled('id_sucursal') ? (int) $request->id_sucursal : null;
        $activoFilter = $request->activo;

        $payload = $this->mapaCache->remember(
            $idEmpresa,
            $idSucursal,
            $activoFilter,
            function () use ($idEmpresa, $idSucursal, $activoFilter) {
                $query = Mesa::where('id_empresa', $idEmpresa)
                    ->select([
                        'id',
                        'id_empresa',
                        'id_sucursal',
                        'numero',
                        'capacidad',
                        'zona_id',
                        'zona',
                        'estado',
                        'activo',
                        'orden',
                    ])
                    ->when($idSucursal !== null, fn ($q) => $q->where('id_sucursal', $idSucursal))
                    ->when($activoFilter !== null && $activoFilter !== '', fn ($q) => $q->where('activo', filter_var($activoFilter, FILTER_VALIDATE_BOOLEAN)));

                $mesas = $query->with([
                    'sesionActiva:id,mesa_id,estado,opened_at,num_comensales',
                    'reservasActivas:id,mesa_id,fecha_reserva,hora_reserva,cliente_nombre,estado',
                    'zonaRestaurante:id,nombre,orden,activo',
                ])
                    ->orderBy('orden')
                    ->orderBy('numero')
                    ->get();

                return $mesas->map(function (Mesa $mesa) {
                    $sesion = $mesa->sesionActiva;
                    $reserva = $mesa->reservasActivas->first();

                    if ($sesion) {
                        // Con pre-cuenta informativa la mesa permanece ocupada hasta cierre por facturación.
                        $mesa->estado = 'ocupada';
                        $mesa->tiempo_abierta = $sesion->opened_at?->diffForHumans(null, true);
                    } elseif ($reserva) {
                        $mesa->estado = 'reservada';
                    } else {
                        $mesa->estado = 'libre';
                    }

                    return MesaMapaDto::fromModel($mesa);
                })->values()->all();
            }
        );

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'numero' => 'required|string|max:20',
            'capacidad' => 'nullable|integer|min:1|max:99',
            'zona_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurante_zonas', 'id')->where('id_empresa', $user->id_empresa),
            ],
            'zona' => 'nullable|string|max:50',
            'id_sucursal' => [
                'nullable',
                'integer',
                Rule::exists('sucursales', 'id')->where('id_empresa', $user->id_empresa),
            ],
            'orden' => 'nullable|integer|min:0',
        ]);

        $validated['id_empresa'] = $user->id_empresa;
        $validated['capacidad'] = $validated['capacidad'] ?? 4;
        $validated['orden'] = $validated['orden'] ?? 0;
        $this->sincronizarZonaTexto($validated, $user->id_empresa);

        $mesa = Mesa::create($validated);
        $mesa->load('zonaRestaurante');
        $this->mapaCache->invalidateEmpresa((int) $user->id_empresa);

        return response()->json($mesa, 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $mesa = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($id);

        return response()->json($mesa);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $mesa = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'numero' => 'sometimes|string|max:20',
            'capacidad' => 'nullable|integer|min:1|max:99',
            'zona_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurante_zonas', 'id')->where('id_empresa', $user->id_empresa),
            ],
            'zona' => 'nullable|string|max:50',
            'activo' => 'sometimes|boolean',
            'orden' => 'nullable|integer|min:0',
        ]);

        $this->sincronizarZonaTexto($validated, $user->id_empresa);
        $mesa->update($validated);
        $mesa->load('zonaRestaurante');
        $this->mapaCache->invalidateEmpresa((int) $user->id_empresa);

        return response()->json($mesa);
    }
}
