<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Reserva;
use App\Models\Restaurante\SesionMesa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReservaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $query = Reserva::where('id_empresa', $user->id_empresa)
            ->with(['mesa', 'usuario'])
            ->when($request->fecha, fn ($q) => $q->where('fecha_reserva', $request->fecha))
            ->when($request->estado, fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('fecha_reserva')
            ->orderBy('hora_reserva');

        $reservas = $query->get();
        return response()->json($reservas);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'mesa_id' => 'required|exists:restaurante_mesas,id',
            'fecha_reserva' => 'required|date',
            'hora_reserva' => 'required|date_format:H:i',
            'cliente_nombre' => 'nullable|string|max:150',
            'cliente_telefono' => 'nullable|string|max:30',
            'observaciones' => 'nullable|string|max:500',
            'cliente_id' => 'nullable|exists:clientes,id',
        ]);

        $mesa = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($validated['mesa_id']);

        $sesionActiva = SesionMesa::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->exists();

        if ($sesionActiva) {
            return response()->json(['error' => 'La mesa está ocupada'], 422);
        }

        $reservaExistente = Reserva::where('mesa_id', $mesa->id)
            ->where('fecha_reserva', $validated['fecha_reserva'])
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->exists();

        if ($reservaExistente) {
            return response()->json(['error' => 'Ya existe una reserva para esta mesa en esa fecha'], 422);
        }

        $reserva = Reserva::create([
            'mesa_id' => $mesa->id,
            'id_empresa' => $user->id_empresa,
            'fecha_reserva' => $validated['fecha_reserva'],
            'hora_reserva' => $validated['hora_reserva'],
            'cliente_nombre' => $validated['cliente_nombre'] ?? null,
            'cliente_telefono' => $validated['cliente_telefono'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'estado' => 'pendiente',
            'usuario_id' => $user->id,
            'cliente_id' => $validated['cliente_id'] ?? null,
        ]);

        $mesa->update(['estado' => 'reservada']);

        return response()->json($reserva->load(['mesa', 'usuario']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $reserva = Reserva::where('id_empresa', $user->id_empresa)
            ->with(['mesa', 'usuario', 'cliente'])
            ->findOrFail($id);

        return response()->json($reserva);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $reserva = Reserva::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'fecha_reserva' => 'sometimes|date',
            'hora_reserva' => 'sometimes|date_format:H:i',
            'cliente_nombre' => 'nullable|string|max:150',
            'cliente_telefono' => 'nullable|string|max:30',
            'observaciones' => 'nullable|string|max:500',
            'estado' => 'sometimes|in:pendiente,confirmada,cumplida,cancelada,no_show',
        ]);

        $reserva->update($validated);
        return response()->json($reserva->fresh(['mesa', 'usuario']));
    }

    public function cancelar(int $id): JsonResponse
    {
        $user = auth()->user();
        $reserva = Reserva::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $reserva->update(['estado' => 'cancelada']);
        $reserva->mesa->update(['estado' => 'libre']);

        return response()->json($reserva);
    }

    public function convertirEnSesion(int $id): JsonResponse
    {
        $user = auth()->user();
        $reserva = Reserva::where('id_empresa', $user->id_empresa)
            ->with('mesa')
            ->findOrFail($id);

        if (!in_array($reserva->estado, ['pendiente', 'confirmada'])) {
            return response()->json(['error' => 'La reserva no puede convertirse en sesión'], 422);
        }

        $sesionActiva = SesionMesa::where('mesa_id', $reserva->mesa_id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->first();

        if ($sesionActiva) {
            return response()->json([
                'message' => 'Mesa ya tiene sesión activa',
                'sesion' => $sesionActiva->load(['mesa', 'mesero']),
            ]);
        }

        $sesion = SesionMesa::create([
            'mesa_id' => $reserva->mesa_id,
            'usuario_id' => $user->id,
            'id_empresa' => $user->id_empresa,
            'id_sucursal' => $user->id_sucursal,
            'num_comensales' => 1,
            'observaciones' => "Reserva: {$reserva->cliente_nombre}",
            'estado' => 'abierta',
            'opened_at' => now(),
        ]);

        $reserva->update(['estado' => 'cumplida']);
        $reserva->mesa->update(['estado' => 'ocupada']);

        return response()->json($sesion->load(['mesa', 'mesero']), 201);
    }
}
