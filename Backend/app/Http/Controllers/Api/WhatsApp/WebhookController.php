<?php

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppSession;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\JsonResponse;

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
        Log::info('Manejando webhook de WhatsApp', ['payload' => $request->all()]);

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





    
    public function disconnectSession(int $sessionId): JsonResponse
    {
        try {
            $session = WhatsAppSession::findOrFail($sessionId);

      
            $session->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
                'disconnected_by' => auth()->id()
            ]);


            $this->whatsAppService->disconnectSession($session->whatsapp_number);

            Log::info('Sesión WhatsApp desconectada', [
                'session_id' => $sessionId,
                'whatsapp_number' => $session->whatsapp_number,
                'disconnected_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sesión desconectada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error desconectando sesión WhatsApp', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desconectar sesión'
            ], 500);
        }
    }



    public function getSessionMessages(int $sessionId, Request $request): JsonResponse
    {
        try {
            $session = WhatsAppSession::findOrFail($sessionId);
            $perPage = $request->get('per_page', 20);

            $messages = WhatsAppMessage::where('whatsapp_number', $session->whatsapp_number)
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $messages->getCollection()->transform(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'message_type' => $message->message_type,
                    'status' => $message->status,
                    'created_at' => $message->created_at,
                    'metadata' => $message->metadata,
                    'is_ai_generated' => $message->metadata['ai_generated'] ?? false,
                    'sent_by' => $message->metadata['sent_by_user'] ?? null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages,
                'session' => [
                    'id' => $session->id,
                    'whatsapp_number' => $session->whatsapp_number,
                    'status' => $session->status,
                    'empresa' => $session->empresa->nombre
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo mensajes de sesión', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo mensajes'
            ], 500);
        }
    }


    public function getExecutiveSummary(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);

            $summary = [
                'period' => $days,
                'overview' => [
                    'total_sessions' => WhatsAppSession::count(),
                    'active_sessions_today' => WhatsAppSession::where('last_message_at', '>=', now()->startOfDay())->count(),
                    'messages_today' => WhatsAppMessage::whereDate('created_at', today())->count(),
                    'companies_using' => WhatsAppSession::distinct('id_empresa')->count()
                ],
                'trends' => [
                    'messages_growth' => $this->calculateGrowthRate('messages', $days),
                    'sessions_growth' => $this->calculateGrowthRate('sessions', $days),
                    'engagement_rate' => $this->calculateEngagementRate($days)
                ],
                'top_metrics' => [
                    'busiest_hour' => $this->getBusiestHour($days),
                    'most_active_company' => $this->getMostActiveCompany($days),
                    'avg_response_time' => $this->getAverageResponseTime($days)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
                'generated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Error generando resumen ejecutivo WhatsApp', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generando resumen'
            ], 500);
        }
    }

    // Métodos auxiliares privados para el resumen ejecutivo

    private function calculateGrowthRate(string $type, int $days): float
    {
        $currentPeriod = $type === 'messages'
            ? WhatsAppMessage::where('created_at', '>=', now()->subDays($days))->count()
            : WhatsAppSession::where('created_at', '>=', now()->subDays($days))->count();

        $previousPeriod = $type === 'messages'
            ? WhatsAppMessage::whereBetween('created_at', [now()->subDays($days * 2), now()->subDays($days)])->count()
            : WhatsAppSession::whereBetween('created_at', [now()->subDays($days * 2), now()->subDays($days)])->count();

        if ($previousPeriod === 0) return $currentPeriod > 0 ? 100 : 0;

        return round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 2);
    }

    private function calculateEngagementRate(int $days): float
    {
        $totalSessions = WhatsAppSession::where('created_at', '>=', now()->subDays($days))->count();
        $activeSessions = WhatsAppSession::where('last_message_at', '>=', now()->subDays($days))->count();

        if ($totalSessions === 0) return 0;

        return round(($activeSessions / $totalSessions) * 100, 2);
    }

    private function getBusiestHour(int $days): int
    {
        $messages = WhatsAppMessage::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return $messages->hour ?? 12;
    }

    private function getMostActiveCompany(int $days): ?array
    {
        $company = WhatsAppSession::join('empresas', 'whatsapp_sessions.id_empresa', '=', 'empresas.id')
            ->where('whatsapp_sessions.created_at', '>=', now()->subDays($days))
            ->selectRaw('empresas.nombre, empresas.id, COUNT(whatsapp_sessions.id) as session_count')
            ->groupBy('empresas.id', 'empresas.nombre')
            ->orderByDesc('session_count')
            ->first();

        return $company ? [
            'id' => $company->id,
            'nombre' => $company->nombre,
            'session_count' => $company->session_count
        ] : null;
    }

    private function getAverageResponseTime(int $days): float
    {
        // Esta es una implementación simplificada
        // Deberías calcular el tiempo real entre mensajes entrantes y salientes
        return 2.5; // minutos (placeholder)
    }
}
