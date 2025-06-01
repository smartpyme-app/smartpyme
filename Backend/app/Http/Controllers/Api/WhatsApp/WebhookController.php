<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppSession;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function verify(Request $request)
    {
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        $verifyToken = config('services.whatsapp.verify_token', 'smartpyme_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verificado correctamente');
            return response($challenge, 200);
        }

        Log::warning('Falló la verificación del webhook WhatsApp', [
            'mode' => $mode,
            'token' => $token
        ]);

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        try {
            if (config('app.env') === 'production') {
                Log::info('WhatsApp webhook recibido', ['payload' => $request->all()]);
            } else {
                Log::info('📱 WhatsApp webhook recibido [DEV]', [
                    'from' => $request->input('entry.0.changes.0.value.messages.0.from'),
                    'message' => $request->input('entry.0.changes.0.value.messages.0.text.body'),
                    'timestamp' => $request->input('entry.0.changes.0.value.messages.0.timestamp')
                ]);
            }

            if (!$this->isValidWebhook($request)) {
                Log::warning('Webhook inválido recibido');
                return response()->json(['status' => 'invalid_webhook'], 400);
            }
            $result = $this->whatsAppService->processIncomingMessage($request->all());

            if ($result['success']) {
                Log::info('✅ Mensaje procesado exitosamente', [
                    'success' => $result['success'],
                    'has_response' => isset($result['response'])
                ]);
            } else {
                Log::error('❌ Error procesando mensaje', [
                    'error' => $result['error'] ?? 'Error desconocido'
                ]);
            }

            return response()->json(['status' => 'received'], 200);
        } catch (Exception $e) {
            Log::error('Error en webhook WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function isValidWebhook(Request $request): bool
    {
        $data = $request->all();

        return isset($data['entry'])
            && is_array($data['entry'])
            && count($data['entry']) > 0
            && isset($data['entry'][0]['changes'])
            && is_array($data['entry'][0]['changes'])
            && count($data['entry'][0]['changes']) > 0;
    }

    public function getStats(Request $request)
    {
        try {
            $days = $request->get('days', 30);
            $empresaId = $request->get('empresa_id');

            $stats = [
                'sessions' => [
                    'total' => WhatsAppSession::count(),
                    'active' => WhatsAppSession::where('last_message_at', '>=', now()->subHours(24))->count(),
                    'connected' => WhatsAppSession::where('status', 'connected')->count(),
                    'pending' => WhatsAppSession::whereIn('status', ['pending_code', 'pending_user'])->count(),
                ],
                'messages' => [
                    'total' => WhatsAppMessage::where('created_at', '>=', now()->subDays($days))->count(),
                    'incoming' => WhatsAppMessage::where('message_type', 'incoming')
                        ->where('created_at', '>=', now()->subDays($days))->count(),
                    'outgoing' => WhatsAppMessage::where('message_type', 'outgoing')
                        ->where('created_at', '>=', now()->subDays($days))->count(),
                    'ai_generated' => WhatsAppMessage::whereJsonContains('metadata->ai_generated', true)
                        ->where('created_at', '>=', now()->subDays($days))->count(),
                ],
                'companies' => [
                    'total_with_sessions' => WhatsAppSession::distinct('id_empresa')->count(),
                    'active_today' => WhatsAppSession::where('last_message_at', '>=', now()->startOfDay())
                        ->distinct('id_empresa')->count(),
                ]
            ];

            if ($empresaId) {
                $stats['empresa_specific'] = $this->getEmpresaStats($empresaId, $days);
            }

            $stats['daily_activity'] = WhatsAppMessage::selectRaw('DATE(created_at) as date, COUNT(*) as messages')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();


            $stats['top_companies'] = WhatsAppSession::join('empresas', 'whatsapp_sessions.id_empresa', '=', 'empresas.id')
                ->selectRaw('empresas.nombre, empresas.id, COUNT(whatsapp_sessions.id) as session_count')
                ->where('whatsapp_sessions.created_at', '>=', now()->subDays($days))
                ->groupBy('empresas.id', 'empresas.nombre')
                ->orderByDesc('session_count')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period_days' => $days,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo estadísticas',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }


    public function getSessions(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $empresaId = $request->get('empresa_id');
            $search = $request->get('search');

            $query = WhatsAppSession::with(['empresa', 'usuario'])
                ->orderByDesc('last_message_at');

            if ($status) {
                $query->where('status', $status);
            }

            if ($empresaId) {
                $query->where('id_empresa', $empresaId);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('whatsapp_number', 'like', "%{$search}%")
                        ->orWhereHas('empresa', function ($eq) use ($search) {
                            $eq->where('nombre', 'like', "%{$search}%");
                        })
                        ->orWhereHas('usuario', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            $sessions = $query->paginate($perPage);

            $sessions->getCollection()->transform(function ($session) {
                return [
                    'id' => $session->id,
                    'whatsapp_number' => $session->whatsapp_number,
                    'status' => $session->status,
                    'empresa' => $session->empresa ? [
                        'id' => $session->empresa->id,
                        'nombre' => $session->empresa->nombre,
                        'codigo' => $session->empresa->codigo
                    ] : null,
                    'usuario' => $session->usuario ? [
                        'id' => $session->usuario->id,
                        'name' => $session->usuario->name,
                        'email' => $session->usuario->email,
                        'tipo' => $session->usuario->tipo
                    ] : null,
                    'created_at' => $session->created_at,
                    'last_message_at' => $session->last_message_at,
                    'code_attempts' => $session->code_attempts,
                    'user_attempts' => $session->user_attempts,
                    'is_active' => $session->last_message_at >= now()->subHours(24),
                    'message_count' => WhatsAppMessage::where('whatsapp_number', $session->whatsapp_number)->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $sessions,
                'filters_applied' => array_filter([
                    'status' => $status,
                    'empresa_id' => $empresaId,
                    'search' => $search
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo sesiones WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo sesiones',
                'message' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    private function getEmpresaStats(int $empresaId, int $days): array
    {
        return [
            'sessions' => [
                'total' => WhatsAppSession::where('id_empresa', $empresaId)->count(),
                'active' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->where('last_message_at', '>=', now()->subHours(24))->count(),
                'connected' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->where('status', 'connected')->count(),
            ],
            'messages' => [
                'total' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->where('created_at', '>=', now()->subDays($days))->count(),
                'incoming' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->where('message_type', 'incoming')
                    ->where('created_at', '>=', now()->subDays($days))->count(),
                'outgoing' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->where('message_type', 'outgoing')
                    ->where('created_at', '>=', now()->subDays($days))->count(),
                'ai_generated' => WhatsAppMessage::where('id_empresa', $empresaId)
                    ->whereJsonContains('metadata->ai_generated', true)
                    ->where('created_at', '>=', now()->subDays($days))->count(),
            ],
            'users' => [
                'unique_users' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->whereNotNull('id_usuario')
                    ->distinct('id_usuario')->count(),
                'unique_numbers' => WhatsAppSession::where('id_empresa', $empresaId)
                    ->distinct('whatsapp_number')->count(),
            ]
        ];
    }
}
