<?php

namespace App\Http\Controllers\Api\DteManagement;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEmailAccountJob;
use App\Models\DteManagement\UserEmailAccount;
use App\Models\User;
use App\Services\Imap\ImapConnectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailAccountController extends Controller
{
    public function __construct(
        protected ImapConnectionService $imapConnectionService
    ) {
    }

    /**
     * List email accounts for the current tenant.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $accounts = UserEmailAccount::with([
                'sucursal:id,nombre',
                'bodega:id,nombre',
                'notificationUser:id,name,email',
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return $this->formatAccount($account);
            });

        return response()->json($accounts);
    }

    /**
     * Test IMAP connection before saving.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testImap(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'encryption' => 'required|in:ssl,tls,starttls,none',
            'user' => 'required|string',
            'password' => 'required|string',
        ]);

        $config = $request->only(['host', 'port', 'encryption', 'user', 'password']);

        $ok = $this->imapConnectionService->testConnection($config);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Conexión exitosa' : 'No se pudo conectar al servidor IMAP',
        ]);
    }

    /**
     * Create IMAP account.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeImap(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'encryption' => 'required|in:ssl,tls,starttls,none',
            'user' => 'required|string',
            'password' => 'required|string',
            'id_sucursal' => 'nullable|integer|exists:sucursales,id',
            'id_bodega' => 'nullable|integer|exists:sucursal_bodegas,id',
            'actualizar_inventario' => 'boolean',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $config = $request->only([
            'host', 'port', 'encryption', 'user', 'password',
            'id_sucursal', 'id_bodega', 'actualizar_inventario',
        ]);

        try {
            $account = $this->imapConnectionService->saveAccount(
                $user->id,
                $user->id_empresa,
                $config
            );

            return response()->json([
                'success' => true,
                'message' => 'Cuenta IMAP conectada correctamente',
                'account' => $this->formatAccount($account),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Trigger sync for an email account.
     * Dispatches ProcessEmailAccountJob for the last 30 days by default.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(int $id, Request $request): JsonResponse
    {
        $account = UserEmailAccount::find($id);

        if (!$account) {
            return response()->json(['error' => 'Cuenta no encontrada'], 404);
        }

        if ($account->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if (!$account->is_active) {
            return response()->json(['error' => 'Cuenta inactiva'], 422);
        }

        if ($account->last_sync_at) {
            $nextAllowedAt = $account->last_sync_at->copy()->addMinutes(10);
            if ($nextAllowedAt->isFuture()) {
                return response()->json([
                    'error' => 'Debe esperar antes de sincronizar nuevamente',
                    'message' => 'Puede volver a sincronizar después de 10 minutos desde la última ejecución.',
                    'retry_after_seconds' => max(1, (int) now()->diffInSeconds($nextAllowedAt)),
                    'next_sync_at' => $nextAllowedAt->toIso8601String(),
                ], 429);
            }
        }

        $request->validate([
            'dias' => 'sometimes|integer|min:1|max:365',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $dateFrom = Carbon::parse($request->input('fecha_inicio'))->startOfDay();
            $dateTo = Carbon::parse($request->input('fecha_fin'))->endOfDay();
            if ($dateTo->gt(now())) {
                $dateTo = Carbon::now();
            }
        } else {
            $daysBack = (int) $request->input('dias', 30);
            $dateTo = Carbon::now();
            $dateFrom = Carbon::now()->subDays($daysBack);
        }

        if ($dateFrom->gt($dateTo)) {
            return response()->json(['error' => 'La fecha inicial no puede ser posterior a la final'], 422);
        }

        // Ejecutar en la misma petición: evita depender de queue:work cuando QUEUE_CONNECTION no es sync.
        set_time_limit(300);
        ProcessEmailAccountJob::dispatchSync($account, $dateFrom, $dateTo);

        $account->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Sincronización completada',
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'last_sync_at' => $account->last_sync_at?->toIso8601String(),
        ]);
    }

    /**
     * Delete (disconnect) an email account.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $account = UserEmailAccount::find($id);

        if (!$account) {
            return response()->json(['error' => 'Cuenta no encontrada'], 404);
        }

        if ($account->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $account->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta desconectada',
        ]);
    }

    /**
     * Guardar usuario que recibirá notificaciones de DTEs por revisar.
     */
    public function updateNotificaciones(Request $request, int $id): JsonResponse
    {
        $account = UserEmailAccount::find($id);

        if (!$account) {
            return response()->json(['error' => 'Cuenta no encontrada'], 404);
        }

        if ($account->id_empresa !== auth()->user()->id_empresa) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'notification_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $notificationUserId = $request->input('notification_user_id');

        if ($notificationUserId) {
            $belongsToEmpresa = User::where('id', $notificationUserId)
                ->where('id_empresa', auth()->user()->id_empresa)
                ->exists();

            if (!$belongsToEmpresa) {
                return response()->json(['error' => 'El usuario no pertenece a la empresa'], 422);
            }
        }

        $account->update(['notification_user_id' => $notificationUserId]);

        $account->load('notificationUser:id,name,email');

        return response()->json([
            'success' => true,
            'message' => 'Configuración de notificaciones guardada',
            'account' => $this->formatAccount($account),
        ]);
    }

    /**
     * Format account for API response (hide sensitive fields).
     *
     * @param UserEmailAccount $account
     * @return array
     */
    protected function formatAccount(UserEmailAccount $account): array
    {
        return [
            'id' => $account->id,
            'email' => $account->email,
            'provider' => $account->provider,
            'is_active' => $account->is_active,
            'last_sync_at' => $account->last_sync_at?->toIso8601String(),
            'id_sucursal' => $account->id_sucursal,
            'id_bodega' => $account->id_bodega,
            'actualizar_inventario' => $account->actualizar_inventario,
            'notification_user_id' => $account->notification_user_id,
            'notification_user' => $account->notificationUser
                ? [
                    'id' => $account->notificationUser->id,
                    'name' => $account->notificationUser->name,
                    'email' => $account->notificationUser->email,
                ]
                : null,
            'sucursal' => $account->sucursal ? ['id' => $account->sucursal->id, 'nombre' => $account->sucursal->nombre] : null,
            'bodega' => $account->bodega ? ['id' => $account->bodega->id, 'nombre' => $account->bodega->nombre] : null,
        ];
    }
}
