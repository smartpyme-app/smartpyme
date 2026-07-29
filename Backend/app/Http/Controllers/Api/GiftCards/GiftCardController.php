<?php

namespace App\Http\Controllers\Api\GiftCards;

use App\Http\Controllers\Controller;
use App\Models\GiftCards\GiftCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function byCodigo(Request $request, string $codigo): JsonResponse
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return response()->json([
                'success' => false,
                'message' => 'Código requerido',
            ], 422);
        }

        $card = GiftCard::query()->where('codigo', $codigo)->first();
        if ($card === null) {
            return response()->json([
                'success' => false,
                'message' => 'Gift card no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeCard($card),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = GiftCard::query()
            ->select([
                'id',
                'codigo',
                'saldo',
                'estado',
                'monto_inicial',
                'fecha_emision',
                'fecha_vencimiento',
            ])
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%'.$request->input('codigo').'%');
        }

        $perPage = min(max((int) $request->input('paginate', 25), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginated->items())->map(fn (GiftCard $card) => $this->serializeCard($card))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeCard(GiftCard $card): array
    {
        return [
            'id' => $card->id,
            'codigo' => $card->codigo,
            'saldo' => (float) $card->saldo,
            'estado' => $card->estado,
            'monto_inicial' => (float) $card->monto_inicial,
            'fecha_emision' => $card->fecha_emision?->toIso8601String(),
            'fecha_vencimiento' => $card->fecha_vencimiento?->toIso8601String(),
        ];
    }
}
